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
                'data' => $data,
                'expo_token' => $device->expo_token,
                'device_id' => $device->id,
                'card_id' => $card?->id,
                'status' => 'pending',
            ]);

            $response = $this->sendExpoNotification($device->expo_token, $title, $body, $data);

            if ($response['success']) {
                $notification->update([
                    'status' => 'sent',
                    'sent_at' => now(),
                ]);
                return true;
            } else {
                $notification->update([
                    'status' => 'failed',
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
    public function sendToMultipleDevices(array $devices, string $title, string $body, array $data = [], ?Card $card = null)
    {
        $results = [];
        
        foreach ($devices as $device) {
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
     * Send card information notification
     */
    public function sendCardInfo(Device $device, Card $card, string $action = 'updated')
    {
        $title = "Card {$action}";
        $body = "Card {$card->phone} has been {$action}";
        
        $data = [
            'type' => 'card_info',
            'action' => $action,
            'card_id' => $card->id,
            'card_phone' => $card->phone,
            'card_status' => $card->status,
            'garden_name' => $card->group?->garden?->name ?? 'Unknown Garden',
            'cards' => $this->getFullCardData($card),
        ];

        return $this->sendToDevice($device, $title, $body, $data, $card);
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
            ];

            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Accept-encoding' => 'gzip, deflate',
                'Content-Type' => 'application/json',
            ])->post($this->expoApiUrl, [$payload]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                if (isset($responseData[0]['status']) && $responseData[0]['status'] === 'ok') {
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
     * Get notification history for a device
     */
    public function getDeviceNotifications(int $deviceId, int $limit = 50)
    {
        return Notification::where('device_id', $deviceId)
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
