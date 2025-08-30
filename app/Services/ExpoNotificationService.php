<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;
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
        ];

        return $this->sendToDevice($device, $title, $body, $data, $card);
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
