<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;

class NotificationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get some sample devices and cards for testing
        $devices = Device::whereNotNull('expo_token')->take(5)->get();
        $cards = Card::with(['group.garden'])->take(3)->get();

        if ($devices->isEmpty()) {
            $this->command->warn('No devices with expo tokens found. Skipping notification seeding.');
            return;
        }

        if ($cards->isEmpty()) {
            $this->command->warn('No cards found. Skipping notification seeding.');
            return;
        }

        $this->command->info('Seeding notifications...');

        // Create sample notifications
        foreach ($devices as $device) {
            foreach ($cards as $card) {
                // Create different types of notifications
                $types = [
                    [
                        'title' => 'Card Updated',
                        'body' => "Card {$card->phone} has been updated in {$card->group->garden->name}",
                        'data' => ['type' => 'card_updated', 'card_id' => $card->id],
                        'status' => 'sent'
                    ],
                    [
                        'title' => 'New Card Added',
                        'body' => "New card {$card->phone} has been added to {$card->group->garden->name}",
                        'data' => ['type' => 'card_created', 'card_id' => $card->id],
                        'status' => 'sent'
                    ],
                    [
                        'title' => 'Card Status Changed',
                        'body' => "Card {$card->phone} status changed to active in {$card->group->garden->name}",
                        'data' => ['type' => 'card_status_changed', 'card_id' => $card->id],
                        'status' => 'sent'
                    ]
                ];

                foreach ($types as $type) {
                    Notification::create([
                        'title' => $type['title'],
                        'body' => $type['body'],
                        'data' => $type['data'],
                        'expo_token' => $device->expo_token,
                        'device_id' => $device->id,
                        'card_id' => $card->id,
                        'status' => $type['status'],
                        'sent_at' => now(),
                    ]);
                }
            }
        }

        $this->command->info('Notifications seeded successfully!');
    }
}
