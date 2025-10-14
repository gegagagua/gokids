<?php

namespace App\Services;

use App\Models\Device;
use App\Services\NotificationImageService;
use Illuminate\Support\Facades\Http;

class FCMService
{
    protected $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    protected $serverKey;

    public function __construct()
    {
        $this->serverKey = env('FCM_SERVER_KEY');
    }

    /**
     * Send FCM notification with image support
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = [])
    {
        try {
            if (!$this->serverKey) {
                \Log::error('FCM_SERVER_KEY not configured');
                return ['success' => false, 'error' => 'FCM not configured'];
            }

            // Extract and optimize image URL
            $imageUrl = $this->extractImageUrl($data);
            
            if ($imageUrl) {
                $optimizedImageUrl = NotificationImageService::getOptimizedImageUrl($imageUrl);
                if ($optimizedImageUrl) {
                    $data['notification_image'] = $optimizedImageUrl;
                    $data['image_url'] = $optimizedImageUrl;
                }
            }

            // Build FCM payload with notification AND data
            // For Android BigPictureStyle, use 'notification' field with 'image'
            $payload = [
                'to' => $fcmToken,
                'priority' => 'high',
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                ],
                'data' => $data,
            ];
            
            // Add image for Android BigPictureStyle (native FCM support!)
            if (isset($data['notification_image'])) {
                $payload['notification']['image'] = $data['notification_image'];
                
                // Also add to Android-specific config for guaranteed delivery
                $payload['android'] = [
                    'priority' => 'high',
                    'notification' => [
                        'image' => $data['notification_image'],
                        'channel_id' => 'default',
                    ],
                ];
            }

            \Log::info('FCMService: Sending notification', [
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
                'title' => $title,
                'has_image' => isset($data['notification_image']),
                'image_url' => $data['notification_image'] ?? null,
            ]);

            // Send to FCM
            $response = Http::withHeaders([
                'Authorization' => 'key=' . $this->serverKey,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                \Log::info('FCMService: Success', [
                    'response' => $responseData
                ]);
                
                if (isset($responseData['success']) && $responseData['success'] > 0) {
                    return ['success' => true, 'response' => $responseData];
                } else {
                    return ['success' => false, 'response' => $responseData];
                }
            } else {
                \Log::error('FCMService: Failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return ['success' => false, 'error' => $response->body()];
            }
        } catch (\Exception $e) {
            \Log::error('FCMService: Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send notification to device
     */
    public function sendToDevice(Device $device, string $title, string $body, array $data = [])
    {
        $token = $device->expo_token;
        
        if (!$token) {
            return ['success' => false, 'error' => 'No FCM token'];
        }

        return $this->sendNotification($token, $title, $body, $data);
    }

    /**
     * Extract image URL from data
     */
    private function extractImageUrl(array $data)
    {
        if (isset($data['active_garden_image']['image_url']) && !empty($data['active_garden_image']['image_url'])) {
            return $data['active_garden_image']['image_url'];
        } elseif (isset($data['image_url']) && !empty($data['image_url'])) {
            return $data['image_url'];
        }
        
        return null;
    }
}

