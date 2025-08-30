<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ExpoNotificationService;
use App\Models\Device;
use App\Models\Garden;
use App\Jobs\SendNotificationJob;

class SendBulkNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:send-bulk 
                            {title : Notification title}
                            {body : Notification body}
                            {--garden= : Garden ID to send to specific garden}
                            {--devices=* : Specific device IDs to send to}
                            {--data= : Additional data in JSON format}
                            {--queue : Send via queue instead of immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send bulk notifications to devices';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $title = $this->argument('title');
        $body = $this->argument('body');
        $gardenId = $this->option('garden');
        $deviceIds = $this->option('devices');
        $data = $this->option('data') ? json_decode($this->option('data'), true) : [];
        $useQueue = $this->option('queue');

        $this->info("Sending notification: {$title}");
        $this->info("Body: {$body}");

        try {
            if ($gardenId) {
                // Send to specific garden
                $garden = Garden::find($gardenId);
                if (!$garden) {
                    $this->error("Garden with ID {$gardenId} not found!");
                    return 1;
                }

                $devices = Device::where('garden_id', $gardenId)
                    ->where('status', 'active')
                    ->whereNotNull('expo_token')
                    ->get();

                $this->info("Found " . count($devices) . " active devices in garden: {$garden->name}");

                if ($devices->isEmpty()) {
                    $this->warn("No active devices found in garden {$garden->name}");
                    return 0;
                }

                $deviceIds = $devices->pluck('id')->toArray();
            } elseif (empty($deviceIds)) {
                // Send to all active devices
                $devices = Device::where('status', 'active')
                    ->whereNotNull('expo_token')
                    ->get();

                $this->info("Found " . count($devices) . " active devices total");

                if ($devices->isEmpty()) {
                    $this->warn("No active devices found");
                    return 0;
                }

                $deviceIds = $devices->pluck('id')->toArray();
            } else {
                // Send to specific devices
                $devices = Device::whereIn('id', $deviceIds)
                    ->where('status', 'active')
                    ->whereNotNull('expo_token')
                    ->get();

                $this->info("Found " . count($devices) . " active devices from specified IDs");
            }

            if ($useQueue) {
                // Send via queue
                SendNotificationJob::dispatch($title, $body, $deviceIds, null, $data);
                $this->info("Notification job queued for " . count($deviceIds) . " devices");
            } else {
                // Send immediately
                $expoService = new ExpoNotificationService();
                $results = $expoService->sendToMultipleDevices(
                    $devices,
                    $title,
                    $body,
                    $data
                );

                $successCount = count(array_filter($results, fn($r) => $r === true));
                $this->info("Notification sent to {$successCount}/" . count($deviceIds) . " devices");
            }

            $this->info("Notification process completed successfully!");
            return 0;

        } catch (\Exception $e) {
            $this->error("Error sending notification: " . $e->getMessage());
            return 1;
        }
    }
}
