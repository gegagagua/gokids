<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Device;
use App\Models\Garden;
use App\Services\ExpoNotificationService;
use Illuminate\Support\Facades\Log;

class CardNotificationService
{
    protected $expoService;

    public function __construct()
    {
        $this->expoService = new ExpoNotificationService();
    }

    /**
     * Send notification when card is created
     */
    public function cardCreated(Card $card)
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card created notification");
                return false;
            }

            $title = "New Card Added";
            $body = "New card {$card->phone} has been added to {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_created',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card created notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card created notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when card is updated
     */
    public function cardUpdated(Card $card, array $changes = [])
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card updated notification");
                return false;
            }

            $title = "Card Updated";
            $body = "Card {$card->phone} has been updated in {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_updated',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
                'changes' => $changes,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card updated notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card updated notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when card status changes
     */
    public function cardStatusChanged(Card $card, string $oldStatus, string $newStatus)
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card status changed notification");
                return false;
            }

            $title = "Card Status Changed";
            $body = "Card {$card->phone} status changed from {$oldStatus} to {$newStatus} in {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_status_changed',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card status changed notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card status changed notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when card is deleted
     */
    public function cardDeleted(Card $card)
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card deleted notification");
                return false;
            }

            $title = "Card Deleted";
            $body = "Card {$card->phone} has been deleted from {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_deleted',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card deleted notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card deleted notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when card is moved to different group
     */
    public function cardMovedToGroup(Card $card, int $oldGroupId, int $newGroupId)
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card moved notification");
                return false;
            }

            $title = "Card Moved";
            $body = "Card {$card->phone} has been moved to a different group in {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_moved',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
                'old_group_id' => $oldGroupId,
                'new_group_id' => $newGroupId,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card moved notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'old_group_id' => $oldGroupId,
                'new_group_id' => $newGroupId,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card moved notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send notification when card is marked as spam
     */
    public function cardMarkedAsSpam(Card $card)
    {
        try {
            $devices = $this->getGardenDevices($card);
            
            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$card->group->garden->id} to send card spam notification");
                return false;
            }

            $title = "Card Marked as Spam";
            $body = "Card {$card->phone} has been marked as spam in {$card->group->garden->name}";
            
            $data = [
                'type' => 'card_spam',
                'card_id' => $card->id,
                'card_phone' => $card->phone,
                'garden_name' => $card->group->garden->name,
                'garden_id' => $card->group->garden->id,
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data,
                $card
            );

            Log::info("Card spam notification sent to " . count($devices) . " devices", [
                'card_id' => $card->id,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send card spam notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all devices for a specific garden
     */
    protected function getGardenDevices(Card $card)
    {
        if (!$card->group || !$card->group->garden) {
            Log::warning("Card {$card->id} has no group or garden assigned");
            return collect();
        }

        return Device::where('garden_id', $card->group->garden->id)
            ->where('status', 'active')
            ->whereNotNull('expo_token')
            ->get();
    }

    /**
     * Send custom notification to specific garden devices
     */
    public function sendCustomNotificationToGarden(int $gardenId, string $title, string $body, array $data = [])
    {
        try {
            $devices = Device::where('garden_id', $gardenId)
                ->where('status', 'active')
                ->whereNotNull('expo_token')
                ->get();

            if ($devices->isEmpty()) {
                Log::info("No devices found for garden {$gardenId} to send custom notification");
                return false;
            }

            $results = $this->expoService->sendToMultipleDevices(
                $devices,
                $title,
                $body,
                $data
            );

            Log::info("Custom notification sent to " . count($devices) . " devices in garden {$gardenId}", [
                'title' => $title,
                'body' => $body,
                'results' => $results
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send custom notification to garden: ' . $e->getMessage());
            return false;
        }
    }
}
