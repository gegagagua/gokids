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

            // CRITICAL DEBUG: Get recipient device full information
            // Note: $device already has the data, but we fetch from DB for complete info
            // This is acceptable as it's only for logging purposes
            $recipientDevice = $deviceId ? \App\Models\Device::find($deviceId) : null;
            
            // CRITICAL DEBUG: Get sender device information from data
            $senderExpoToken = $data['sender_expo_token'] ?? null;
            $senderDevice = null;
            if ($senderExpoToken) {
                $senderDevice = \App\Models\Device::where('expo_token', $senderExpoToken)->first();
            }

            // CRITICAL DEBUG: Log before creating notification with full device info
            $notificationCreatedAt = now();
            \Log::info('ExpoNotificationService::sendToDevice: CREATING NOTIFICATION', [
                'timestamp' => $notificationCreatedAt->toIso8601String(),
                'recipient_device_id' => $deviceId,
                'recipient_device_name' => $device->name ?? null,
                'recipient_device_status' => $device->status ?? null,
                'recipient_device_garden_id' => $device->garden_id ?? null,
                'recipient_device_is_logged_in' => $device->is_logged_in ?? null,
                'recipient_device_active_garden_groups' => $device->active_garden_groups ?? null,
                'recipient_device_created_at' => $recipientDevice?->created_at?->toIso8601String(),
                'recipient_device_updated_at' => $recipientDevice?->updated_at?->toIso8601String(),
                'recipient_expo_token' => substr($device->expo_token ?? '', 0, 20) . '...',
                'sender_expo_token' => $senderExpoToken ? substr($senderExpoToken, 0, 20) . '...' : null,
                'sender_device_id' => $senderDevice?->id,
                'sender_device_name' => $senderDevice?->name,
                'sender_device_status' => $senderDevice?->status,
                'sender_device_garden_id' => $senderDevice?->garden_id,
                'card_id' => $card?->id,
                'card_child_first_name' => $card?->child_first_name,
                'card_child_last_name' => $card?->child_last_name,
                'card_child_full_name' => $card ? ($card->child_first_name . ' ' . $card->child_last_name) : null,
                'card_parent_name' => $card?->parent_name,
                'title' => $title,
                'body' => $body,
                'body_length' => strlen($body),
                'body_empty' => empty($body),
                'data_type' => $data['type'] ?? null,
                'data_child_name' => $data['child_name'] ?? null,
                'data_parent_name' => $data['parent_name'] ?? null,
                'data_sender_name' => $data['sender_name'] ?? null,
            ]);

            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'expo_token' => $device->expo_token,
                'device_id' => $deviceId,
                'card_id' => $card?->id,
                'status' => 'pending',
            ]);

            // CRITICAL DEBUG: Log after creating notification with timestamps
            $notificationCreatedTimestamp = now();
            \Log::info('ExpoNotificationService::sendToDevice: NOTIFICATION CREATED', [
                'timestamp' => $notificationCreatedTimestamp->toIso8601String(),
                'notification_id' => $notification->id,
                'notification_title' => $notification->title,
                'notification_body' => $notification->body,
                'notification_card_id' => $notification->card_id,
                'notification_device_id' => $notification->device_id,
                'notification_status' => $notification->status,
                'notification_created_at' => $notification->created_at?->toIso8601String(),
                'recipient_device_id' => $deviceId,
                'recipient_device_name' => $recipientDevice?->name,
                'sender_device_id' => $senderDevice?->id,
                'sender_device_name' => $senderDevice?->name,
            ]);

            // Add notification ID to data
            $data['notification_id'] = (string) $notification->id;

            // CRITICAL FIX for Android: Encode notification_id in title for killed app scenario
            $encodedTitle = $title;
            if (isset($data['type']) && ($data['type'] === 'card_to_device' || $data['type'] === 'card_accepted')) {

                $encodedTitle = $title . ' - '  . $notification->id;


            }

            // CRITICAL DEBUG: Log before sending to Expo with timestamp
            $sendingToExpoAt = now();
            \Log::info('ExpoNotificationService::sendToDevice: SENDING TO EXPO', [
                'timestamp' => $sendingToExpoAt->toIso8601String(),
                'notification_id' => $notification->id,
                'recipient_device_id' => $deviceId,
                'recipient_device_name' => $recipientDevice?->name,
                'recipient_expo_token' => substr($device->expo_token ?? '', 0, 20) . '...',
                'sender_device_id' => $senderDevice?->id,
                'sender_device_name' => $senderDevice?->name,
                'encoded_title' => $encodedTitle,
                'body' => $body,
                'body_length' => strlen($body),
                'data_type' => $data['type'] ?? null,
                'time_since_notification_created' => $sendingToExpoAt->diffInMilliseconds($notificationCreatedTimestamp) . 'ms',
            ]);

            // Send Expo notification with encoded title and clean body
            // Note: $sendingToExpoAt is defined above for logging
            $response = $this->sendExpoNotification($device->expo_token, $encodedTitle, $body, $data);

            if ($response['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                    'data' => $data,
                ]);
                $notificationSentAt = now();
                \Log::info('ExpoNotificationService::sendToDevice: SUCCESS', [
                    'timestamp' => $notificationSentAt->toIso8601String(),
                    'notification_id' => $notification->id,
                    'recipient_device_id' => $deviceId,
                    'recipient_device_name' => $recipientDevice?->name,
                    'recipient_expo_token' => substr($device->expo_token ?? '', 0, 20) . '...',
                    'sender_device_id' => $senderDevice?->id,
                    'sender_device_name' => $senderDevice?->name,
                    'title' => $title,
                    'body' => $body,
                    'card_id' => $card?->id,
                    'card_child_name' => $card ? ($card->child_first_name . ' ' . $card->child_last_name) : null,
                    'notification_sent_at' => $notification->sent_at?->toIso8601String(),
                    'total_time_ms' => $notificationSentAt->diffInMilliseconds($notificationCreatedTimestamp ?? now()) . 'ms',
                ]);
                return true;
            } else {
                $notification->update([
                    'status' => 'failed',
                    'data' => $data,
                ]);
                \Log::warning('ExpoNotificationService::sendToDevice: FAILED', [
                    'notification_id' => $notification->id,
                    'device_id' => $deviceId,
                    'expo_token' => substr($device->expo_token, 0, 20) . '...',
                    'response' => $response,
                ]);
                return false;
            }
        } catch (\Exception $e) {
            \Log::error('ExpoNotificationService::sendToDevice: EXCEPTION', [
                'device_id' => $device->id ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
        // CRITICAL DEBUG: Get sender device info
        $senderExpoTokenStart = $data['sender_expo_token'] ?? null;
        $senderDeviceStart = null;
        if ($senderExpoTokenStart) {
            $senderDeviceStart = \App\Models\Device::where('expo_token', $senderExpoTokenStart)->first();
        }

        // CRITICAL DEBUG: Log card data and notification parameters with timestamp
        $sendCardToAllDevicesStartAt = now();
        \Log::info('ExpoNotificationService::sendCardToAllDevices START', [
            'timestamp' => $sendCardToAllDevicesStartAt->toIso8601String(),
            'card_id' => $card->id,
            'card_group_id' => $card->group_id,
            'card_child_first_name' => $card->child_first_name,
            'card_child_last_name' => $card->child_last_name,
            'card_child_full_name' => $card->child_first_name . ' ' . $card->child_last_name,
            'card_parent_name' => $card->parent_name,
            'card_phone' => $card->phone,
            'card_status' => $card->status,
            'sender_expo_token' => $senderExpoTokenStart ? substr($senderExpoTokenStart, 0, 20) . '...' : null,
            'sender_device_id' => $senderDeviceStart?->id,
            'sender_device_name' => $senderDeviceStart?->name,
            'sender_device_status' => $senderDeviceStart?->status,
            'sender_device_garden_id' => $senderDeviceStart?->garden_id,
            'title' => $title,
            'body' => $body,
            'body_length' => strlen($body),
            'body_empty' => empty($body),
            'data' => $data,
            'data_type' => $data['type'] ?? null,
            'data_sender_name' => $data['sender_name'] ?? null,
        ]);

        if (!$card->group_id) {
            \Log::warning('ExpoNotificationService::sendCardToAllDevices: No group_id', [
                'card_id' => $card->id,
            ]);
            return false;
        }

        // Get all devices that have this card's group in their active_garden_groups
        $targetGroupId = $card->group_id;

        // LOG: Device query
        \Log::info('ExpoNotificationService::sendCardToAllDevices: Querying devices', [
            'target_group_id' => $targetGroupId,
        ]);

        $devices = Device::where('status', 'active')
            ->where('is_logged_in', true)
            ->whereNotNull('expo_token')
            ->whereJsonContains('active_garden_groups', $targetGroupId)
            ->get();

        // CRITICAL DEBUG: Log devices found with full information and timestamps
        $devicesFoundAt = now();
        \Log::info('ExpoNotificationService::sendCardToAllDevices: Devices found (before dedup)', [
            'timestamp' => $devicesFoundAt->toIso8601String(),
            'count' => $devices->count(),
            'devices' => $devices->map(function($d) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status,
                    'is_logged_in' => $d->is_logged_in,
                    'garden_id' => $d->garden_id,
                    'expo_token' => $d->expo_token ? substr($d->expo_token, 0, 20) . '...' : 'NULL',
                    'active_garden_groups' => $d->active_garden_groups,
                    'created_at' => $d->created_at?->toIso8601String(),
                    'updated_at' => $d->updated_at?->toIso8601String(),
                ];
            })->toArray(),
        ]);

        // CRITICAL FIX: Deduplicate by expo_token to prevent duplicate notifications
        // Group by expo_token and keep only the first device for each unique token
        $uniqueDevices = $devices->unique('expo_token');

        // CRITICAL DEBUG: Log unique devices after deduplication with full info
        $dedupCompletedAt = now();
        \Log::info('ExpoNotificationService::sendCardToAllDevices: Unique devices (after dedup)', [
            'timestamp' => $dedupCompletedAt->toIso8601String(),
            'unique_count' => $uniqueDevices->count(),
            'dedup_time_ms' => $dedupCompletedAt->diffInMilliseconds($devicesFoundAt ?? now()) . 'ms',
            'unique_devices' => $uniqueDevices->map(function($d) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status,
                    'is_logged_in' => $d->is_logged_in,
                    'garden_id' => $d->garden_id,
                    'expo_token' => $d->expo_token ? substr($d->expo_token, 0, 20) . '...' : 'NULL',
                ];
            })->toArray(),
        ]);

        if ($uniqueDevices->isEmpty()) {
            \Log::warning('ExpoNotificationService::sendCardToAllDevices: No devices found', [
                'card_id' => $card->id,
                'target_group_id' => $targetGroupId,
            ]);
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

            // CRITICAL DEBUG: Get recipient device full info
            // Note: $device already has the data, but we fetch from DB for complete info
            // This is acceptable as it's only for logging purposes
            $recipientDeviceFull = \App\Models\Device::find($device->id);
            
            // CRITICAL DEBUG: Get sender device info from data
            $senderExpoTokenFromData = $deviceData['sender_expo_token'] ?? null;
            $senderDeviceFromData = null;
            if ($senderExpoTokenFromData) {
                $senderDeviceFromData = \App\Models\Device::where('expo_token', $senderExpoTokenFromData)->first();
            }

            // CRITICAL DEBUG: Log before sending to device with full info and timestamp
            $sendingToDeviceAt = now();
            \Log::info('ExpoNotificationService::sendCardToAllDevices: Sending to device', [
                'timestamp' => $sendingToDeviceAt->toIso8601String(),
                'recipient_device_id' => $device->id,
                'recipient_device_name' => $device->name,
                'recipient_device_status' => $device->status,
                'recipient_device_garden_id' => $device->garden_id,
                'recipient_device_is_logged_in' => $device->is_logged_in,
                'recipient_device_created_at' => $recipientDeviceFull?->created_at?->toIso8601String(),
                'recipient_device_updated_at' => $recipientDeviceFull?->updated_at?->toIso8601String(),
                'recipient_expo_token' => substr($device->expo_token, 0, 20) . '...',
                'sender_expo_token' => $senderExpoTokenFromData ? substr($senderExpoTokenFromData, 0, 20) . '...' : null,
                'sender_device_id' => $senderDeviceFromData?->id,
                'sender_device_name' => $senderDeviceFromData?->name,
                'sender_device_status' => $senderDeviceFromData?->status,
                'sender_device_garden_id' => $senderDeviceFromData?->garden_id,
                'card_id' => $card->id,
                'card_child_first_name' => $card->child_first_name,
                'card_child_last_name' => $card->child_last_name,
                'card_child_full_name' => $card->child_first_name . ' ' . $card->child_last_name,
                'card_parent_name' => $card->parent_name,
                'title_to_send' => $card->parent_name,
                'body_to_send' => $body,
                'body_length' => strlen($body),
                'device_data' => [
                    'child_name' => $deviceData['child_name'] ?? null,
                    'parent_name' => $deviceData['parent_name'] ?? null,
                    'sender_name' => $deviceData['sender_name'] ?? null,
                ],
            ]);

            $result = $this->sendToDevice($device, $card->parent_name, $body, $deviceData, $card);
            $results[] = $result;

            // CRITICAL DEBUG: Log device send result with timestamp
            $deviceSendCompletedAt = now();
            \Log::info('ExpoNotificationService::sendCardToAllDevices: Device send result', [
                'timestamp' => $deviceSendCompletedAt->toIso8601String(),
                'recipient_device_id' => $device->id,
                'recipient_device_name' => $device->name,
                'sender_device_id' => $senderDeviceFromData?->id,
                'sender_device_name' => $senderDeviceFromData?->name,
                'result' => $result,
                'send_time_ms' => $deviceSendCompletedAt->diffInMilliseconds($sendingToDeviceAt ?? now()) . 'ms',
            ]);

            if ($result) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        // LOG: Final results
        // CRITICAL DEBUG: Log end with summary and timestamps
        $sendCardToAllDevicesEndAt = now();
        \Log::info('ExpoNotificationService::sendCardToAllDevices END', [
            'timestamp' => $sendCardToAllDevicesEndAt->toIso8601String(),
            'card_id' => $card->id,
            'card_child_full_name' => $card->child_first_name . ' ' . $card->child_last_name,
            'sender_device_id' => $senderDeviceStart?->id,
            'sender_device_name' => $senderDeviceStart?->name,
            'sender_device_status' => $senderDeviceStart?->status,
            'sender_device_garden_id' => $senderDeviceStart?->garden_id,
            'total_devices' => $uniqueDevices->count(),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results,
            'total_time_ms' => $sendCardToAllDevicesEndAt->diffInMilliseconds($sendCardToAllDevicesStartAt ?? now()) . 'ms',
            'recipient_devices' => $uniqueDevices->map(function($d) {
                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'status' => $d->status,
                    'garden_id' => $d->garden_id,
                ];
            })->toArray(),
        ]);

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
     * IMPORTANT: This only sends to iOS devices via Expo.
     * Android notifications CANNOT be dismissed after being displayed in the notification tray.
     * This is a limitation of Android's notification system - Expo cannot remove already-displayed notifications.
     *
     * For Android: The notification will remain visible but won't be processed by the app.
     * For iOS: The Notification Service Extension will intercept and remove the notification.
     *
     * @param string $expoToken The Expo token of the device
     * @param string $cardId The card ID to dismiss
     * @param string $platform The device platform ('ios' or 'android'). Only iOS will receive the dismiss.
     */
    public function dismissNotificationOnDevice(string $expoToken, string $cardId, string $platform = 'ios')
    {
        if (!$expoToken) {
            return ['success' => false, 'response' => null];
        }

        // CRITICAL: Only send dismiss notifications to iOS devices
        // Android cannot dismiss notifications that are already displayed to the user
        // Sending a notification to Android won't help - the notification stays in the tray
        if (strtolower($platform) === 'android') {
            \Log::info('Skipping dismissal notification for Android device (Android limitation)', [
                'card_id' => $cardId,
                'platform' => $platform,
                'expo_token' => substr($expoToken, 0, 20) . '...',
            ]);
            // Return success so the backend doesn't log errors
            return ['success' => true, 'response' => null];
        }

        try {
            // CRITICAL: Send notification that triggers Notification Service Extension on iOS
            // Even when app is killed, the extension will process it and dismiss matching notifications
            $dismissalPayload = [
                'to' => $expoToken,
                'title' => ' ',  // Minimal space to ensure delivery
                'body' => ' ',   // Minimal space to ensure delivery
                'data' => [
                    'type' => 'card_accepted_elsewhere',
                    'card_id' => (string) $cardId,
                    'action' => 'dismiss',
                    'timestamp' => now()->toISOString(),
                ],
                'type' => 'card_accepted_elsewhere',  // Also at root level for iOS
                'card_id' => (string) $cardId,        // Also at root level for iOS
                'priority' => 'high',
                'sound' => null,         // No sound
                'badge' => 0,            // No badge
                'mutableContent' => true, // CRITICAL: Triggers Notification Service Extension even when app is killed
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$dismissalPayload]);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['data'][0]['status']) && $responseData['data'][0]['status'] === 'ok') {
                    \Log::info('Silent dismissal notification sent successfully to iOS', [
                        'card_id' => $cardId,
                        'expo_token' => substr($expoToken, 0, 20) . '...',
                    ]);
                    return ['success' => true, 'response' => $responseData];
                } elseif (isset($responseData[0]['status']) && $responseData[0]['status'] === 'ok') {
                    \Log::info('Silent dismissal notification sent successfully to iOS', [
                        'card_id' => $cardId,
                        'expo_token' => substr($expoToken, 0, 20) . '...',
                    ]);
                    return ['success' => true, 'response' => $responseData];
                }
            }

            \Log::warning('Failed to send silent dismissal notification to iOS', [
                'card_id' => $cardId,
                'expo_token' => substr($expoToken, 0, 20) . '...',
            ]);
            return ['success' => false, 'response' => null];
        } catch (\Exception $e) {
            \Log::error('Error sending silent dismissal notification to iOS', [
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
            // Also use custom notification sound for card_info and card_to_device notifications
            if (isset($data['type']) && ($data['type'] === 'card_to_device' || $data['type'] === 'card_info')) {
                $payload['categoryId'] = 'card_notification'; // For iOS
                $payload['categoryIdentifier'] = 'card_notification'; // For iOS compatibility
                $payload['channelId'] = 'card_notification'; // CRITICAL: For Android - use specific channel with actions
                $payload['sound'] = 'notificationsoundfordevices.wav'; // Custom sound for card notifications
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

            // CRITICAL DEBUG: Log payload before sending to Expo
            \Log::info('ExpoNotificationService::sendExpoNotification: SENDING TO EXPO API', [
                'expo_api_url' => $this->expoApiUrl,
                'expo_token' => substr($expoToken, 0, 20) . '...',
                'payload' => $payload,
                'payload_title' => $payload['title'] ?? null,
                'payload_body' => $payload['body'] ?? null,
                'payload_body_length' => isset($payload['body']) ? strlen($payload['body']) : 0,
                'payload_data' => $payload['data'] ?? null,
                'payload_data_type' => $payload['data']['type'] ?? null,
                'payload_data_child_name' => $payload['data']['child_name'] ?? null,
                'payload_data_parent_name' => $payload['data']['parent_name'] ?? null,
                'payload_data_sender_name' => $payload['data']['sender_name'] ?? null,
                'payload_data_card_id' => $payload['data']['card_id'] ?? null,
            ]);

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$payload]);

            // CRITICAL DEBUG: Log Expo API response with timestamps
            $expoResponseReceivedAt = now();
            \Log::info('ExpoNotificationService::sendExpoNotification: EXPO API RESPONSE', [
                'timestamp' => $expoResponseReceivedAt->toIso8601String(),
                'recipient_expo_token' => substr($expoToken, 0, 20) . '...',
                'response_status' => $response->status(),
                'response_successful' => $response->successful(),
                'response_body' => $response->body(),
                'response_json' => $response->json(),
                'response_headers' => $response->headers(),
                // Note: $sendingToExpoAt is from sendToDevice method, use now() as fallback
                'time_since_request_sent' => $expoResponseReceivedAt->diffInMilliseconds(now()) . 'ms',
            ]);

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

        return $allNotifications;
    }
}
