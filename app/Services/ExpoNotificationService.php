<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;
use App\Models\People;
use App\Services\NotificationImageService;
use Illuminate\Support\Facades\Http;

class ExpoNotificationService
{
    protected $expoApiUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send notification to a specific device
     */
    public function sendToDevice(Device $device, string $title, string $body, array $data = [], ?Card $card = null)
    {
        try {
            // Check if device actually exists in database (to handle temporary card-as-device objects)
            $deviceId = $device->exists ? $device->id : null;
            
            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'expo_token' => $device->expo_token,
                'device_id' => $deviceId,
                'card_id' => $card?->id,
                'status' => 'pending',
            ]);

            // Add notification ID to data
            $data['notification_id'] = (string) $notification->id;

            $response = $this->sendExpoNotification($device->expo_token, $title, $body, $data);

            if ($response['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'data' => $data,
                ]);
                return true;
            } else {
                $notification->update([
                    'status' => 'failed',
                    'data' => $data,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send notification to multiple devices
     */
    public function sendToMultipleDevices($devices, string $title, string $body, array $data = [], ?Card $card = null)
    {
        $results = [];
        
        // Handle both Collection and array
        if (is_array($devices)) {
            $deviceArray = $devices;
        } else {
            // Keep as Collection to preserve Device objects
            $deviceArray = $devices;
        }
        
        foreach ($deviceArray as $device) {
            $results[] = $this->sendToDevice($device, $title, $body, $data, $card);
        }

        return $results;
    }

    /**
     * Send child call notification (without full card data to avoid Expo limits)
     */
    public function sendChildCall(Device $device, Card $card, $callTime = null)
    {
        $callTime = $callTime ?: now();
        $title = "Child Call";
        $body = "Child called from device at " . $callTime->format('H:i');
        
        // Check for recent duplicate notification to prevent spam
        $recentNotification = Notification::where('device_id', $device->id)
            ->where('card_id', $card->id)
            ->where('data', 'like', '%"type":"child_call"%')
            ->where('title', $title)
            ->where('created_at', '>=', now()->subMinutes(1)) // Within last 1 minute
            ->first();

        if ($recentNotification) {
            return true; // Return success to avoid error handling
        }
        
        // Load necessary relationships
        $card->load(['personType', 'group.garden.images']);
        
        // Find the active garden image
        $activeGardenImage = null;
        if ($card->active_garden_image && $card->group?->garden?->images) {
            $activeGardenImage = $card->group->garden->images->where('id', $card->active_garden_image)->first();
        }
        
        $data = [
            'type' => 'child_call',
            'notification_id' => null, // Will be set after notification is created
            'card_id' => (string) $card->id,
            'call_time' => $callTime->toISOString(),
            'device_id' => (string) $device->id,
            'card_phone' => $card->phone,
            'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
            'parent_name' => $card->parent_name,
            'garden_name' => $card->group?->garden?->name ?? 'Unknown Garden',
            'is_deleted' => $card->is_deleted,
            'deleted_at' => $card->deleted_at,
            'active_garden_image' => $activeGardenImage ? [
                'id' => (string) $activeGardenImage->id,
                'title' => $activeGardenImage->title,
                'image_path' => $activeGardenImage->image_path,
                'image_url' => $activeGardenImage->image_url,
                'index' => $activeGardenImage->index,
                'created_at' => $activeGardenImage->created_at,
            ] : null,
            'image_path' => $card->image_path,
            'image_url' => $card->image_url,
            'person_type' => $card->personType ? [
                'id' => (string) $card->personType->id,
                'name' => $card->personType->name,
                'description' => $card->personType->description,
            ] : null,
        ];

        return $this->sendToDevice($device, $title, $body, $data, $card);
    }

    /**
     * Send notification from card to all devices that have this card's group in their active_garden_groups
     */
    public function sendCardToAllDevices(Card $card, string $title, string $body, array $data = [])
    {
        if (!$card->group_id) {
            return false;
        }

        // Get all devices that have this card's group in their active_garden_groups
        $targetGroupId = $card->group_id;
        
        $devices = Device::where('status', 'active')
            ->whereNotNull('expo_token')
            ->whereJsonContains('active_garden_groups', $targetGroupId)
            ->get();

        if ($devices->isEmpty()) {
            return false;
        }

        // Load necessary relationships for card data
        $card->load(['personType', 'group.garden.images']);
        
        // Find the active garden image
        $activeGardenImage = null;
        if ($card->active_garden_image && $card->group?->garden?->images) {
            $activeGardenImage = $card->group->garden->images->where('id', $card->active_garden_image)->first();
        }

        $results = [];
        $successCount = 0;
        $failureCount = 0;
        
        foreach ($devices as $device) {
            // Create comprehensive card data
            $deviceData = array_merge($data, [
                'type' => 'card_to_device',
                'device_id' => (string) $device->id,
                'card_id' => (string) $card->id,
                'card_phone' => $card->phone,
                'card_status' => $card->status,
                'card_group_name' => $card->group?->name ?? 'Unknown Group',
                'garden_name' => $card->group?->garden?->name ?? 'Unknown Garden',
                'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                'parent_name' => $card->parent_name,
                'image_url' => $card->image_url,
                'icon' => $card->image_url, // Card image for notification icon (left side)
                'is_deleted' => $card->is_deleted,
                'deleted_at' => $card->deleted_at,
                'active_garden_image' => $activeGardenImage ? [
                    'id' => (string) $activeGardenImage->id,
                    'title' => $activeGardenImage->title,
                    'image_path' => $activeGardenImage->image_path,
                    'image_url' => $activeGardenImage->image_url,
                    'index' => $activeGardenImage->index,
                    'created_at' => $activeGardenImage->created_at,
                ] : null,
                'person_type' => $card->personType ? [
                    'id' => (string) $card->personType->id,
                    'name' => $card->personType->name,
                    'description' => $card->personType->description,
                ] : null,
            ]);

            $result = $this->sendToDevice($device, $title, $body, $deviceData, $card);
            $results[] = $result;
            
            if ($result) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return $results;
    }

    /**
     * Send notification directly to a card owner (without creating notification record)
     */
    public function sendToCardOwner(Card $card, string $title, string $body, array $data = [])
    {
        if (!$card->expo_token) {
            return false;
        }

        try {
            // Add card image as icon if not already present
            if (!isset($data['icon']) && $card->image_url) {
                $data['icon'] = $card->image_url;
            }
            
            $response = $this->sendExpoNotification($card->expo_token, $title, $body, $data);
            \Log::info('ExpoNotificationService::sendToCardOwner - Notification sent to card owner', [
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'card_child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                'card_owner_data' => $data,
                'response' => $response
            ]);

            if ($response['success']) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            \Log::error('ExpoNotificationService::sendToCardOwner - Failed to send notification to card owner', [
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'card_child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                'card_owner_data' => $data,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send actual Expo notification
     */
    protected function sendExpoNotification(string $expoToken, string $title, string $body, array $data = [])
    {
        try {
            $payload = [
                'to' => $expoToken,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'default',
            ];
            
            // Add image support - use active_garden_image if available, fallback to card image
            $imageUrl = null;
            if (isset($data['active_garden_image']['image_url']) && !empty($data['active_garden_image']['image_url'])) {
                $imageUrl = $data['active_garden_image']['image_url'];
            } elseif (isset($data['image_url']) && !empty($data['image_url'])) {
                $imageUrl = $data['image_url'];
            }

            if ($imageUrl) {
                // Get optimized image URL (converts HTTP to HTTPS, adds CDN resize params if supported)
                $optimizedImageUrl = NotificationImageService::getOptimizedImageUrl($imageUrl);

                if ($optimizedImageUrl) {
                    \Log::info('ExpoNotificationService: Sending notification with image', [
                        'original_url' => $imageUrl,
                        'optimized_url' => $optimizedImageUrl,
                        'title' => $title,
                        'expo_token' => substr($expoToken, 0, 20) . '...'
                    ]);

                    // Add to data for custom notification handling - THIS IS KEY!
                    // Mobile app will read this from data payload
                    $data['notification_image'] = $optimizedImageUrl;
                    $data['image'] = $optimizedImageUrl; // Add as 'image' too

                    // Update payload data FIRST
                    $payload['data'] = $data;

                    // CRITICAL: This triggers the Notification Service Extension on iOS
                    $payload['mutableContent'] = true;
                } else {
                    \Log::warning('ExpoNotificationService: Image optimization failed', [
                        'original_url' => $imageUrl,
                        'title' => $title
                    ]);
                }
            } else {
                \Log::warning('ExpoNotificationService: No image URL found', [
                    'title' => $title,
                    'has_active_garden_image' => isset($data['active_garden_image']),
                    'has_image_url' => isset($data['image_url']),
                    'data_keys' => array_keys($data)
                ]);
            }

            // Add dynamic icon support for Android
            if (isset($data['icon']) && !empty($data['icon'])) {
                $iconUrl = $data['icon'];
                if (strpos($iconUrl, 'http://') === 0) {
                    $iconUrl = str_replace('http://', 'https://', $iconUrl);
                }
                
                $payload['icon'] = $iconUrl;
                $payload['data']['icon'] = $iconUrl;
            }

            // Log the complete payload being sent to Expo
            \Log::info('ExpoNotificationService: Complete payload to Expo', [
                'payload' => $payload
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$payload]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if the response has the expected structure
                if (isset($responseData['data'][0]['status']) && $responseData['data'][0]['status'] === 'ok') {
                    return ['success' => true, 'response' => $responseData];
                } elseif (isset($responseData[0]['status']) && $responseData[0]['status'] === 'ok') {
                    return ['success' => true, 'response' => $responseData];
                } else {
                    return ['success' => false, 'response' => $responseData];
                }
            } else {
                return ['success' => false, 'response' => null];
            }
        } catch (\Exception $e) {
            return ['success' => false, 'response' => null];
        }
    }

    /**
     * Get notification history for a device (today's notifications only - from 00:00)
     * Returns all notifications from the start of the current day
     * Automatically cancels notifications that are pending/sent for more than 5 minutes
     * Does NOT cancel notifications that are already accepted
     */
    public function getDeviceNotifications(int $deviceId, int $limit = 50)
    {
        // First, auto-cancel notifications that are pending/sent for more than 5 minutes
        // BUT do NOT cancel if already accepted
        $fiveMinutesAgo = now()->subMinutes(5);
        
        // Log what we're about to cancel
        $notificationsToCancelIds = Notification::where('device_id', $deviceId)
            ->whereIn('status', ['pending', 'sent']) // Only pending or sent, NOT accepted
            ->where('created_at', '<', $fiveMinutesAgo)
            ->pluck('id')
            ->toArray();
        
        if (!empty($notificationsToCancelIds)) {
            \Log::info('ExpoNotificationService::getDeviceNotifications - About to auto-cancel notifications', [
                'device_id' => $deviceId,
                'notifications_to_cancel' => $notificationsToCancelIds,
                'cutoff_time' => $fiveMinutesAgo->toISOString()
            ]);
        }
        
        $canceledCount = Notification::where('device_id', $deviceId)
            ->whereIn('status', ['pending', 'sent']) // Only pending or sent, NOT accepted
            ->where('created_at', '<', $fiveMinutesAgo)
            ->update([
                'status' => 'canceled',
                'updated_at' => now()
            ]);
        
        if ($canceledCount > 0) {
            \Log::info('ExpoNotificationService::getDeviceNotifications - Auto-canceled old notifications', [
                'device_id' => $deviceId,
                'canceled_count' => $canceledCount,
                'canceled_ids' => $notificationsToCancelIds,
                'cutoff_time' => $fiveMinutesAgo->toISOString()
            ]);
        }
        
        // Log all notification statuses for debugging
        $allStatuses = Notification::where('device_id', $deviceId)
            ->where('created_at', '>=', $fiveMinutesAgo)
            ->select('id', 'status', 'created_at', 'accepted_at')
            ->get();
        
        \Log::info('ExpoNotificationService::getDeviceNotifications - Recent notification statuses', [
            'device_id' => $deviceId,
            'notifications' => $allStatuses->map(function($n) {
                return [
                    'id' => $n->id,
                    'status' => $n->status,
                    'created_at' => $n->created_at->toISOString(),
                    'accepted_at' => $n->accepted_at ? $n->accepted_at->toISOString() : null
                ];
            })->toArray()
        ]);

        // Get all notifications for the device from the start of today (00:00)
        $allNotifications = Notification::where('device_id', $deviceId)
            ->where('created_at', '>=', now()->startOfDay())
            ->with(['card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add formatted dates to each notification
        $allNotifications = $allNotifications->map(function($notification) {
            $notification->created_at_formatted = $notification->created_at->format('Y-m-d H:i:s');
            $notification->sent_at_formatted = $notification->sent_at ? $notification->sent_at->format('Y-m-d H:i:s') : null;
            $notification->created_at_iso = $notification->created_at->toISOString();
            $notification->sent_at_iso = $notification->sent_at ? $notification->sent_at->toISOString() : null;
            return $notification;
        });

        return $allNotifications;
    }
}
