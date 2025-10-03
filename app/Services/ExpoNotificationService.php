<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;
use App\Models\People;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExpoNotificationService
{
    protected $expoApiUrl = 'https://exp.host/--/api/v2/push/send';

    /**
     * Send notification to a specific device
     */
    public function sendToDevice(Device $device, string $title, string $body, array $data = [], ?Card $card = null)
    {
        Log::info('ExpoNotificationService::sendToDevice - Starting', [
            'device_id' => $device->id,
            'device_name' => $device->name,
            'expo_token' => $device->expo_token ? 'present' : 'missing',
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'card_id' => $card?->id,
            'device_exists' => $device->exists
        ]);

        try {
            // Check if device actually exists in database (to handle temporary card-as-device objects)
            $deviceId = $device->exists ? $device->id : null;
            
            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'data' => $data, // Laravel will automatically cast to JSON
                'expo_token' => $device->expo_token,
                'device_id' => $deviceId,
                'card_id' => $card?->id,
                'status' => 'pending',
            ]);

            Log::info('ExpoNotificationService::sendToDevice - Notification created', [
                'notification_id' => $notification->id,
                'device_id' => $device->id
            ]);

            // Add notification ID to data
            $data['notification_id'] = (string) $notification->id;

            $response = $this->sendExpoNotification($device->expo_token, $title, $body, $data);

            Log::info('ExpoNotificationService::sendToDevice - Expo API response', [
                'notification_id' => $notification->id,
                'device_id' => $device->id,
                'response' => $response
            ]);

            if ($response['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'data' => $data, // Update with notification ID
                ]);
                Log::info('ExpoNotificationService::sendToDevice - Notification sent successfully', [
                    'notification_id' => $notification->id,
                    'device_id' => $device->id
                ]);
                return true;
            } else {
                $notification->update([
                    'status' => 'failed',
                    'data' => $data, // Update with notification ID
                ]);
                Log::error('ExpoNotificationService::sendToDevice - Notification failed', [
                    'notification_id' => $notification->id,
                    'device_id' => $device->id,
                    'error' => $response['error'] ?? 'Unknown error'
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('ExpoNotificationService::sendToDevice - Exception occurred', [
                'device_id' => $device->id,
                'device_name' => $device->name,
                'title' => $title,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
            Log::info("Duplicate child call notification prevented for device {$device->id}, card {$card->id}");
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
            // No full card data to stay under Expo limits
        ];

        return $this->sendToDevice($device, $title, $body, $data, $card);
    }

    /**
     * Send notification from card to all devices that have this card's group in their active_garden_groups
     */
    public function sendCardToAllDevices(Card $card, string $title, string $body, array $data = [])
    {
        Log::info('ExpoNotificationService::sendCardToAllDevices - Starting', [
            'card_id' => $card->id,
            'card_phone' => $card->phone,
            'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'timestamp' => now()->toISOString(),
            'backtrace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5)
        ]);

        if (!$card->group || !$card->group->garden) {
            Log::warning('ExpoNotificationService::sendCardToAllDevices - Card has no group or garden', [
                'card_id' => $card->id,
                'has_group' => $card->group ? true : false,
                'has_garden' => $card->group && $card->group->garden ? true : false
            ]);
            return false;
        }

        // Get all devices that have this card's group in their active_garden_groups
        // CRITICAL: Only devices from the specific garden where the card belongs
        $targetGardenId = $card->group->garden->id;
        $targetGroupId = $card->group_id;
        
        $devices = Device::where('garden_id', $targetGardenId)
            ->where('status', 'active')
            ->whereNotNull('expo_token')
            ->where(function($query) use ($targetGroupId) {
                // Primary: devices that have this specific group in their active_garden_groups
                $query->whereJsonContains('active_garden_groups', $targetGroupId);
            })
            ->get();

        // Debug logging to understand the filtering
        Log::info("Device filtering debug", [
            'card_id' => $card->id,
            'card_group_id' => $targetGroupId,
            'target_garden_id' => $targetGardenId,
            'garden_name' => $card->group->garden->name,
            'devices_found' => $devices->count(),
            'device_ids' => $devices->pluck('id')->toArray(),
            'device_garden_ids' => $devices->pluck('garden_id')->unique()->toArray(),
            'device_active_groups' => $devices->pluck('active_garden_groups')->toArray(),
            'query_verification' => [
                'all_devices_in_garden' => Device::where('garden_id', $targetGardenId)->count(),
                'active_devices_in_garden' => Device::where('garden_id', $targetGardenId)->where('status', 'active')->count(),
                'devices_with_expo_token' => Device::where('garden_id', $targetGardenId)->where('status', 'active')->whereNotNull('expo_token')->count()
            ]
        ]);

        if ($devices->isEmpty()) {
            Log::info("No devices found for card {$card->id} group {$targetGroupId} in garden {$targetGardenId}");
            return false;
        }

        // CRITICAL: Verify that all devices are from the correct garden
        $incorrectGardenDevices = $devices->where('garden_id', '!=', $targetGardenId);
        if ($incorrectGardenDevices->count() > 0) {
            Log::error("CRITICAL ERROR: Found devices from wrong garden!", [
                'expected_garden_id' => $targetGardenId,
                'incorrect_devices' => $incorrectGardenDevices->pluck('id', 'garden_id')->toArray()
            ]);
            // Filter out devices from wrong gardens
            $devices = $devices->where('garden_id', $targetGardenId);
        }

        Log::info('ExpoNotificationService::sendCardToAllDevices - Sending to devices', [
            'card_id' => $card->id,
            'devices_count' => $devices->count(),
            'garden_name' => $card->group->garden->name,
            'device_ids' => $devices->pluck('id')->toArray()
        ]);

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
            Log::info('ExpoNotificationService::sendCardToAllDevices - Sending to device', [
                'card_id' => $card->id,
                'device_id' => $device->id,
                'device_name' => $device->name,
                'device_garden_id' => $device->garden_id
            ]);
            // Create comprehensive card data like in sendCardInfo
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

        // NOTE: Card owner notification is now only sent when device accepts (in acceptNotification)
        // This prevents duplicate notifications to card owner
        Log::info('ExpoNotificationService::sendCardToAllDevices - Skipping card owner notification (will be sent on accept)', [
            'card_id' => $card->id,
            'has_expo_token' => $card->expo_token ? true : false
        ]);

        Log::info('ExpoNotificationService::sendCardToAllDevices - Completed', [
            'card_id' => $card->id,
            'total_devices' => $devices->count(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Send notification directly to a card owner (without creating notification record)
     */
    public function sendToCardOwner(Card $card, string $title, string $body, array $data = [])
    {
        Log::info('ExpoNotificationService::sendToCardOwner - Starting', [
            'card_id' => $card->id,
            'card_phone' => $card->phone,
            'expo_token' => $card->expo_token ? 'present' : 'missing',
            'title' => $title,
            'body' => $body,
            'data' => $data
        ]);

        if (!$card->expo_token) {
            Log::warning('ExpoNotificationService::sendToCardOwner - No expo_token', [
                'card_id' => $card->id
            ]);
            return false;
        }

        try {
            $response = $this->sendExpoNotification($card->expo_token, $title, $body, $data);

            Log::info('ExpoNotificationService::sendToCardOwner - Expo API response', [
                'card_id' => $card->id,
                'response' => $response
            ]);

            if ($response['success']) {
                Log::info('ExpoNotificationService::sendToCardOwner - Notification sent successfully', [
                    'card_id' => $card->id
                ]);
                return true;
            } else {
                Log::error('ExpoNotificationService::sendToCardOwner - Notification failed', [
                    'card_id' => $card->id,
                    'error' => $response['error'] ?? 'Unknown error'
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('ExpoNotificationService::sendToCardOwner - Exception occurred', [
                'card_id' => $card->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Send actual Expo notification
     */
    protected function sendExpoNotification(string $expoToken, string $title, string $body, array $data = [])
    {
        Log::info('ExpoNotificationService::sendExpoNotification - Starting', [
            'expo_token' => $expoToken ? 'present' : 'missing',
            'title' => $title,
            'body' => $body,
            'data' => $data
        ]);

        try {
            $payload = [
                'to' => $expoToken,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                // Enable iOS notification service extension for image processing
                'mutableContent' => true,
                // Android notification channel and appearance
                'channelId' => 'default',
            ];

            Log::info('ExpoNotificationService::sendExpoNotification - Payload prepared', [
                'expo_token' => $expoToken ? 'present' : 'missing',
                'payload' => $payload
            ]);
            
            // Add image support - use active_garden_image if available
            $imageUrl = null;
            if (isset($data['active_garden_image']['image_url']) && !empty($data['active_garden_image']['image_url'])) {
                $imageUrl = $data['active_garden_image']['image_url'];
                
                // Convert HTTP to HTTPS if needed (iOS requirement)
                if (strpos($imageUrl, 'http://') === 0) {
                    $imageUrl = str_replace('http://', 'https://', $imageUrl);
                }
                
                // ANDROID: Big Picture style notification
                $payload['image'] = $imageUrl;
                

            }

            // Add dynamic icon support for Android
            if (isset($data['icon']) && !empty($data['icon'])) {
                $iconUrl = $data['icon'];
                if (strpos($iconUrl, 'http://') === 0) {
                    $iconUrl = str_replace('http://', 'https://', $iconUrl);
                }
                
                // Set icon for Android notifications
                $payload['icon'] = $iconUrl;
                $payload['data']['icon'] = $iconUrl;
            }

            Log::info('ExpoNotificationService::sendExpoNotification - Sending to Expo API', [
                'expo_api_url' => $this->expoApiUrl,
                'expo_token' => $expoToken ? 'present' : 'missing'
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$payload]);

            Log::info('ExpoNotificationService::sendExpoNotification - Expo API response', [
                'expo_token' => $expoToken ? 'present' : 'missing',
                'status_code' => $response->status(),
                'response_body' => $response->body(),
                'successful' => $response->successful()
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if the response has the expected structure
                if (isset($responseData['data'][0]['status']) && $responseData['data'][0]['status'] === 'ok') {
                    Log::info('ExpoNotificationService::sendExpoNotification - Success (data[0] structure)', [
                        'expo_token' => $expoToken ? 'present' : 'missing',
                        'response' => $responseData
                    ]);
                    return ['success' => true, 'response' => $responseData];
                } elseif (isset($responseData[0]['status']) && $responseData[0]['status'] === 'ok') {
                    // Fallback for different response structure
                    Log::info('ExpoNotificationService::sendExpoNotification - Success (direct structure)', [
                        'expo_token' => $expoToken ? 'present' : 'missing',
                        'response' => $responseData
                    ]);
                    return ['success' => true, 'response' => $responseData];
                } else {
                    Log::warning('ExpoNotificationService::sendExpoNotification - Failed (unexpected response structure)', [
                        'expo_token' => $expoToken ? 'present' : 'missing',
                        'response' => $responseData
                    ]);
                    return ['success' => false, 'response' => $responseData];
                }
            } else {
                Log::error('ExpoNotificationService::sendExpoNotification - HTTP request failed', [
                    'expo_token' => $expoToken ? 'present' : 'missing',
                    'status_code' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return ['success' => false, 'response' => null];
            }
        } catch (\Exception $e) {
            Log::error('ExpoNotificationService::sendExpoNotification - Exception occurred', [
                'expo_token' => $expoToken ? 'present' : 'missing',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'response' => null];
        }
    }

    /**
     * Get notification history for a device (last 24 hours, max 50 notifications)
     * Returns only unique notifications per card_id (latest notification for each card)
     */
    public function getDeviceNotifications(int $deviceId, int $limit = 50)
    {
        Log::info('ExpoNotificationService::getDeviceNotifications - Starting', [
            'device_id' => $deviceId,
            'limit' => $limit
        ]);

        // Get all notifications for the device in the last 24 hours
        $allNotifications = Notification::where('device_id', $deviceId)
            ->where('created_at', '>=', now()->subHours(24))
            ->with(['card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        Log::info('ExpoNotificationService::getDeviceNotifications - Raw notifications found', [
            'device_id' => $deviceId,
            'total_notifications' => $allNotifications->count(),
            'notifications' => $allNotifications->map(function($n) {
                return [
                    'id' => $n->id,
                    'card_id' => $n->card_id,
                    'title' => $n->title,
                    'status' => $n->status,
                    'created_at' => $n->created_at
                ];
            })->toArray()
        ]);

        // Group by card_id and get only the latest notification for each card
        $uniqueNotifications = $allNotifications
            ->groupBy('card_id')
            ->map(function ($notifications) {
                return $notifications->first(); // Get the latest (first after desc order) notification for each card
            })
            ->values() // Reset array keys
            ->take($limit); // Apply limit

        Log::info('ExpoNotificationService::getDeviceNotifications - Unique notifications after grouping', [
            'device_id' => $deviceId,
            'unique_card_count' => $uniqueNotifications->count(),
            'unique_notifications' => $uniqueNotifications->map(function($n) {
                return [
                    'id' => $n->id,
                    'card_id' => $n->card_id,
                    'title' => $n->title,
                    'status' => $n->status,
                    'created_at' => $n->created_at
                ];
            })->toArray()
        ]);

        return $uniqueNotifications;
    }
}
