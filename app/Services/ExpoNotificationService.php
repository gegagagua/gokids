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

            // CRITICAL FIX for Android: Encode notification_id in title for killed app scenario
            $encodedTitle = $title;
            if (isset($data['type']) && ($data['type'] === 'card_to_device' || $data['type'] === 'card_accepted')) {

                $encodedTitle = $title . ' - '  . $notification->id;


            }

            // Send Expo notification with encoded title and clean body
            $response = $this->sendExpoNotification($device->expo_token, $encodedTitle, $body, $data);

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
            $deviceArray = collect($devices);
        } else {
            // Keep as Collection to preserve Device objects
            $deviceArray = $devices;
        }

        // CRITICAL FIX: Deduplicate by expo_token to prevent duplicate notifications
        $uniqueDevices = $deviceArray->unique('expo_token');

        foreach ($uniqueDevices as $device) {
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
            'group_id' => (string) $card->group_id, // Add group_id for frontend filtering
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
            ->where('is_logged_in', true)
            ->whereNotNull('expo_token')
            ->whereJsonContains('active_garden_groups', $targetGroupId)
            ->get();

        // CRITICAL FIX: Deduplicate by expo_token to prevent duplicate notifications
        // Group by expo_token and keep only the first device for each unique token
        $uniqueDevices = $devices->unique('expo_token');

        if ($uniqueDevices->isEmpty()) {
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

        foreach ($uniqueDevices as $device) {
            // Create comprehensive card data
            $deviceData = array_merge($data, [
                'type' => 'card_to_device',
                'device_id' => (string) $device->id,
                'card_id' => (string) $card->id,
                'group_id' => (string) $card->group_id, // CRITICAL: Add group_id for frontend filtering
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

            $result = $this->sendToDevice($device, $card->parent_name, $body, $deviceData, $card);
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

            // Send Expo notification
            $response = $this->sendExpoNotification($card->expo_token, $title, $body, $data);

            if ($response['success']) {
                return true;
            } else {
                return false;
            }
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Send notification directly to any expo token (for routing to correct parent)
     */
    public function sendExpoNotificationDirect(string $expoToken, string $title, string $body, array $data = [])
    {
        if (!$expoToken) {
            return ['success' => false, 'response' => null];
        }

        try {
            // Send Expo notification
            return $this->sendExpoNotification($expoToken, $title, $body, $data);
        } catch (\Exception $e) {
            return ['success' => false, 'response' => null];
        }
    }

    /**
     * Send a silent notification to trigger dismissal on other devices
     * Uses minimal content to ensure delivery even when app is closed
     * The frontend will detect and dismiss matching notifications
     */
    public function dismissNotificationOnDevice(string $expoToken, string $cardId)
    {
        if (!$expoToken) {
            return ['success' => false, 'response' => null];
        }

        try {
            // CRITICAL: Both iOS AND Android need title/body to trigger background services
            // - iOS: Needs mutableContent to trigger Notification Service Extension
            // - Android: FCM data-only messages DON'T trigger onMessageReceived when app is killed
            //            We MUST send title/body for FirebaseMessagingService to be called
            
            // Send dismissal notification with minimal title/body
            $dismissalPayload = [
                'to' => $expoToken,
                'title' => '',  // CRITICAL: Required for Android FCM to trigger service
                'body' => '',   // CRITICAL: Required for Android FCM to trigger service
                'priority' => 'high',
                'sound' => null,
                'badge' => 0,
                'channelId' => 'dismissal',
                'data' => [
                    'type' => 'card_accepted_elsewhere',
                    'card_id' => (string) $cardId,
                    'action' => 'dismiss',
                    'timestamp' => now()->toISOString(),
                ],
            ];
            
            // Add type and card_id at root level for easier access in native code
            $dismissalPayload['type'] = 'card_accepted_elsewhere';
            $dismissalPayload['card_id'] = (string) $cardId;
            
            // For iOS: Add mutableContent to trigger Notification Service Extension
            $dismissalPayload['mutableContent'] = true;
            $dismissalPayload['contentAvailable'] = true;
            
            // The native services (iOS NSE and Android FCM) will detect these markers
            // and suppress the notification from showing while dismissing old notifications

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$dismissalPayload]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['data'][0]['status']) && $responseData['data'][0]['status'] === 'ok') {
                    \Log::info('Silent dismissal notification sent successfully', [
                        'card_id' => $cardId,
                        'expo_token' => substr($expoToken, 0, 20) . '...',
                    ]);
                    return ['success' => true, 'response' => $responseData];
                } elseif (isset($responseData[0]['status']) && $responseData[0]['status'] === 'ok') {
                    \Log::info('Silent dismissal notification sent successfully', [
                        'card_id' => $cardId,
                        'expo_token' => substr($expoToken, 0, 20) . '...',
                    ]);
                    return ['success' => true, 'response' => $responseData];
                }
            }

            \Log::warning('Failed to send silent dismissal notification', [
                'card_id' => $cardId,
                'expo_token' => substr($expoToken, 0, 20) . '...',
            ]);
            return ['success' => false, 'response' => null];
        } catch (\Exception $e) {
            \Log::error('Error sending silent dismissal notification', [
                'card_id' => $cardId,
                'error' => $e->getMessage(),
            ]);
            return ['success' => false, 'response' => null];
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

            // CRITICAL: Add categoryId and channelId for card notifications to enable action buttons (Accept/Dismiss)
            // This allows users to accept notifications without opening the app
            if (isset($data['type']) && $data['type'] === 'card_to_device') {
                $payload['categoryId'] = 'card_notification'; // For iOS
                $payload['categoryIdentifier'] = 'card_notification'; // For iOS compatibility
                $payload['channelId'] = 'card_notification'; // CRITICAL: For Android - use specific channel with actions
            }

               if (isset($data['type'])) {
                $payload['type'] = $data['type'];
              }
              if (isset($data['card_id'])) {
                $payload['card_id'] = $data['card_id'];
              }
              if (isset($data['notification_id'])) {
                $payload['notification_id'] = $data['notification_id'];
              }
              if (isset($data['card_phone'])) {
                $payload['card_phone'] = $data['card_phone'];
              }
              if (isset($data['image_url'])) {
                $payload['image_url'] = $data['image_url'];
              }
             if (isset($data['active_garden_image'])) {
               // Send active_garden_image as JSON string for Android compatibility
                  $payload['active_garden_image_json'] = json_encode($data['active_garden_image']);
              }
            // Add image support - use card image if available, fallback to active_garden_image
            $imageUrl = null;
            if (isset($data['image_url']) && !empty($data['image_url'])) {
                $imageUrl = $data['image_url'];
            } elseif (isset($data['active_garden_image']['image_url']) && !empty($data['active_garden_image']['image_url'])) {
                $imageUrl = $data['active_garden_image']['image_url'];
            }

            if ($imageUrl) {
                // Get optimized image URL (converts HTTP to HTTPS, adds CDN resize params if supported)
                $optimizedImageUrl = NotificationImageService::getOptimizedImageUrl($imageUrl);

                if ($optimizedImageUrl) {
                    $payload['data'] = $data;
                    $payload['icon'] = '';

                    // CRITICAL: This triggers the Notification Service Extension on iOS
                    $payload['mutableContent'] = true;

                    // Fix array syntax error and use correct PHP array assignment
                    $payload['richContent'] = [
                        'image' => $optimizedImageUrl
                    ];
                } else {
                    \Log::warning('ExpoNotificationService: Image optimization failed', [
                        'original_url' => $imageUrl,
                        'title' => $title
                    ]);
                }
            }

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

        // Get all notifications for the device from the start of today (00:00)
        $allNotifications = Notification::where('device_id', $deviceId)
            ->where('created_at', '>=', now()->startOfDay())
            ->with(['card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        // Add formatted dates to each notification and auto-cancel old pending ones
        $allNotifications = $allNotifications->map(function($notification) use ($fiveMinutesAgo) {
            $debugTimestamp = $notification->created_at->format('Y-m-d H:i:s');
            $notification->debug_timestamp = $debugTimestamp;
            $notification->sent_at_formatted = $notification->sent_at ? $notification->sent_at->format('Y-m-d H:i:s') : null;
            $notification->created_at_iso = $notification->created_at->toISOString();
            $notification->sent_at_iso = $notification->sent_at ? $notification->sent_at->toISOString() : null;
            
            // Add debug_timestamp to the data field
            $data = is_string($notification->data) ? json_decode($notification->data, true) : $notification->data;
            $data = $data ?? [];
            $data['debug_timestamp'] = $debugTimestamp;
            
            // Auto-cancel pending notifications older than 5 minutes
            if ($notification->status === 'sent' && $notification->created_at < $fiveMinutesAgo) {
                $notification->status = 'canceled';
                // Also update card_status in data field
                if (isset($data['card_status'])) {
                    $data['card_status'] = 'canceled';
                }
            }
            
            $notification->data = $data;
            
            return $notification;
        });
 
        \Log::info('allNotifications', ['allNotifications' => $allNotifications[0]]);
        return $allNotifications;
    }
}
