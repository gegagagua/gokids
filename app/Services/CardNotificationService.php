<?php

namespace App\Services;

use App\Models\Card;
use App\Models\Device;
use App\Models\Garden;
use App\Models\People;
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
                'cards' => $this->getFullCardData($card),
            ];

            $results = $this->expoService->sendToMultipleDevices(
                $devices->toArray(),
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
