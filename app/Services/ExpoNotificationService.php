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
        try {
            $notification = Notification::create([
                'title' => $title,
                'body' => $body,
                'data' => $data, // Laravel will automatically cast to JSON
                'expo_token' => $device->expo_token,
                'device_id' => $device->id,
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
                    'data' => $data, // Update with notification ID
                ]);
                return true;
            } else {
                $notification->update([
                    'status' => 'failed',
                    'data' => $data, // Update with notification ID
                ]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Failed to send notification: ' . $e->getMessage());
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
     * Send notification to all devices of a specific garden
     */
    public function sendToGardenDevices(int $gardenId, string $title, string $body, array $data = [], ?Card $card = null)
    {
        $devices = Device::whereHas('garden', function ($query) use ($gardenId) {
            $query->where('id', $gardenId);
        })->get();

        return $this->sendToMultipleDevices($devices, $title, $body, $data, $card);
    }

    /**
     * Send card information notification (without full card data to avoid Expo limits)
     */
    public function sendCardInfo(Device $device, Card $card, string $action = 'updated')
    {
        // Check for recent duplicate notification to prevent spam
        $recentNotification = Notification::where('device_id', $device->id)
            ->where('card_id', $card->id)
            ->where('data', 'like', '%"type":"card_info"%')
            ->where('data', 'like', '%"action":"' . $action . '"%')
            ->where('created_at', '>=', now()->subMinutes(1)) // Within last 1 minute (reduced from 2)
            ->first();

        if ($recentNotification) {
            Log::info("Duplicate notification prevented for device {$device->id}, card {$card->id}, action {$action}");
            return true; // Return success to avoid error handling
        }

        $title = "Card {$action}";
        $body = "Card {$card->phone} has been {$action}";
        
        // Load necessary relationships
        $card->load(['personType', 'group.garden.images']);
        
        // Find the active garden image
        $activeGardenImage = null;
        if ($card->active_garden_image && $card->group?->garden?->images) {
            $activeGardenImage = $card->group->garden->images->where('id', $card->active_garden_image)->first();
        }
        
        $data = [
            'type' => 'card_info',
            'action' => $action,
            'notification_id' => null, // Will be set after notification is created
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
            // No full card data to stay under Expo limits
        ];

        return $this->sendToDevice($device, $title, $body, $data, $card);
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
     * Send notification from device to card (parent) - only to the card's phone
     */
    public function sendDeviceToCard(Device $device, Card $card, string $title, string $body, array $data = [])
    {
        // Check for recent duplicate notification to prevent spam
        $recentNotification = Notification::where('device_id', $device->id)
            ->where('card_id', $card->id)
            ->where('data', 'like', '%"type":"device_to_card"%')
            ->where('title', $title)
            ->where('created_at', '>=', now()->subMinutes(1)) // Within last 1 minute
            ->first();

        if ($recentNotification) {
            Log::info("Duplicate device-to-card notification prevented for device {$device->id}, card {$card->id}");
            return true; // Return success to avoid error handling
        }

        // This should send to the card's phone number (parent)
        // For now, we'll send to the device that initiated the call
        // In a real implementation, this would send SMS or call to the parent's phone
        
        $data = array_merge($data, [
            'type' => 'device_to_card',
            'device_id' => (string) $device->id,
            'card_id' => (string) $card->id,
            'card_phone' => $card->phone,
            'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
        ]);

        return $this->sendToDevice($device, $title, $body, $data, $card);
    }

    /**
     * Send notification from card to device - only to the specific device
     */
    public function sendCardToDevice(Device $device, Card $card, string $title, string $body, array $data = [])
    {
        // Check for recent duplicate notification to prevent spam
        $recentNotification = Notification::where('device_id', $device->id)
            ->where('card_id', $card->id)
            ->where('data', 'like', '%"type":"card_to_device"%')
            ->where('title', $title)
            ->where('created_at', '>=', now()->subMinutes(1)) // Within last 1 minute
            ->first();

        if ($recentNotification) {
            Log::info("Duplicate card-to-device notification prevented for device {$device->id}, card {$card->id}");
            return true; // Return success to avoid error handling
        }

        // This sends only to the specific device, not all devices in the garden
        
        $data = array_merge($data, [
            'type' => 'card_to_device',
            'device_id' => (string) $device->id,
            'card_id' => (string) $card->id,
            'card_phone' => $card->phone,
            'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
        ]);

        return $this->sendToDevice($device, $title, $body, $data, $card);
    }

    /**
     * Send notification from card to all devices that have this card's group in their active_garden_groups
     */
    public function sendCardToAllDevices(Card $card, string $title, string $body, array $data = [])
    {
        if (!$card->group || !$card->group->garden) {
            Log::warning("Card {$card->id} has no group or garden assigned");
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

        Log::info("Sending card notification to {$devices->count()} devices for card {$card->id} in garden {$card->group->garden->name}");

        // Load necessary relationships for card data
        $card->load(['personType', 'group.garden.images']);
        
        // Find the active garden image
        $activeGardenImage = null;
        if ($card->active_garden_image && $card->group?->garden?->images) {
            $activeGardenImage = $card->group->garden->images->where('id', $card->active_garden_image)->first();
        }

        $results = [];
        foreach ($devices as $device) {
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
                    'created_at' => $activeGardenImage->created_at,
                ] : null,
                'person_type' => $card->personType ? [
                    'id' => (string) $card->personType->id,
                    'name' => $card->personType->name,
                    'description' => $card->personType->description,
                ] : null,
            ]);

            $results[] = $this->sendToDevice($device, $title, $body, $deviceData, $card);
        }

        return $results;
    }

    /**
     * Get full card data with people information (same as cards/me and verifyOtp)
     */
    public function getFullCardData(Card $card)
    {
        // Load all related data like in verify-otp
        $card->load(['group.garden.images', 'personType', 'parents', 'people']);

        // Get all people with this phone number (same as verifyOtp)
        $people = People::with(['personType', 'card.group.garden.images', 'card.personType', 'card.parents', 'card.people'])
            ->where('phone', $card->phone)
            ->get();

        // Transform card to include garden images and garden info (same as verifyOtp)
        $transformedCard = [
            'id' => $card->id,
            'child_first_name' => $card->child_first_name,
            'child_last_name' => $card->child_last_name,
            'parent_name' => $card->parent_name,
            'phone' => $card->phone,
            'status' => $card->status,
            'group_id' => $card->group_id,
            'person_type_id' => $card->person_type_id,
            'parent_code' => $card->parent_code,
            'image_path' => $card->image_path,
            'active_garden_image' => $card->active_garden_image,
            'image_url' => $card->image_url,
            'created_at' => $card->created_at,
            'updated_at' => $card->updated_at,
            'group' => $card->group,
            'personType' => $card->personType,
            'parents' => $card->parents,
            'people' => $card->people,
            'garden_images' => $card->garden_images,
            'garden' => $card->garden,
            'main_parent' => true
        ];

        // Transform people to include full card data (same as verifyOtp)
        $transformedPeople = $people->map(function ($person) {
            $baseData = [
                'id' => $person->id,
                'name' => $person->name,
                'phone' => $person->phone,
                'person_type_id' => $person->person_type_id,
                'card_id' => $person->card_id,
                'created_at' => $person->created_at,
                'updated_at' => $person->updated_at,
                'person_type' => $person->personType,
                'main_parent' => false
            ];

            // If person has a card, merge card data directly into the base data
            if ($person->card) {
                $cardData = [
                    'card_id' => $person->card->id,
                    'child_first_name' => $person->card->child_first_name,
                    'child_last_name' => $person->card->child_last_name,
                    'parent_name' => $person->card->parent_name,
                    'card_phone' => $person->card->phone,
                    'status' => $person->card->status,
                    'parent_code' => $person->card->parent_code,
                    'image_url' => $person->card->image_url,
                    'parent_verification' => $person->card->parent_verification,
                    'license' => $person->card->license,
                    'active_garden_image' => $person->card->active_garden_image,
                    'card_created_at' => $person->card->created_at,
                    'card_updated_at' => $person->card->updated_at,
                    'group' => $person->card->group,
                    'card_person_type' => $person->card->personType,
                    'parents' => $person->card->parents,
                    'people' => $person->card->people,
                    'garden_images' => $person->card->garden_images,
                    'garden' => $person->card->garden
                ];
                
                // Merge card data into base data (like JavaScript spread operator)
                $baseData = array_merge($baseData, $cardData);
            }

            return $baseData;
        });

        // Combine cards and people into one array (same as verifyOtp)
        return collect([$transformedCard])->concat($transformedPeople);
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
                // Enable iOS notification service extension for image processing
                'mutableContent' => true,
                // Android notification channel and appearance
                'channelId' => 'default',
            ];
            
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
                    // Fallback for different response structure
                    return ['success' => true, 'response' => $responseData];
                } else {
                    Log::warning('Expo notification failed: ' . json_encode($responseData));
                    return ['success' => false, 'response' => $responseData];
                }
            } else {
                Log::error('Expo API request failed: ' . $response->status() . ' - ' . $response->body());
                return ['success' => false, 'response' => null];
            }
        } catch (\Exception $e) {
            Log::error('Exception in Expo notification: ' . $e->getMessage());
            return ['success' => false, 'response' => null];
        }
    }

    /**
     * Get notification history for a device (last 24 hours, max 50 notifications)
     */
    public function getDeviceNotifications(int $deviceId, int $limit = 50)
    {
        return Notification::where('device_id', $deviceId)
            ->where('created_at', '>=', now()->subHours(24))
            ->with(['card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get notification statistics
     */
    public function getNotificationStats()
    {
        return [
            'total' => Notification::count(),
            'pending' => Notification::where('status', 'pending')->count(),
            'sent' => Notification::where('status', 'sent')->count(),
            'failed' => Notification::where('status', 'failed')->count(),
        ];
    }
}
