<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\ExpoNotificationService;
use App\Models\Device;
use App\Models\Card;
use Illuminate\Support\Facades\Log;

class SendNotificationJob implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    public $timeout = 120; // 2 minutes timeout
    public $tries = 3; // Retry 3 times if fails

    protected $title;
    protected $body;
    protected $deviceIds;
    protected $cardId;
    protected $data;

    /**
     * Create a new job instance.
     */
    public function __construct(string $title, string $body, array $deviceIds, ?int $cardId = null, array $data = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->deviceIds = $deviceIds;
        $this->cardId = $cardId;
        $this->data = $data;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $devices = Device::whereIn('id', $this->deviceIds)
                ->where('status', 'active')
                ->whereNotNull('expo_token')
                ->get();

            if ($devices->isEmpty()) {
                Log::warning('No active devices with expo tokens found for notification job', [
                    'device_ids' => $this->deviceIds,
                    'title' => $this->title
                ]);
                return;
            }

            $card = $this->cardId ? Card::find($this->cardId) : null;
            
            $expoService = new ExpoNotificationService();
            $results = $expoService->sendToMultipleDevices(
                $devices,
                $this->title,
                $this->body,
                $this->data,
                $card
            );

            Log::info('Notification job completed successfully', [
                'title' => $this->title,
                'devices_count' => count($devices),
                'results' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Notification job failed: ' . $e->getMessage(), [
                'title' => $this->title,
                'device_ids' => $this->deviceIds,
                'card_id' => $this->cardId
            ]);
            
            throw $e; // Re-throw to trigger retry
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job failed permanently', [
            'title' => $this->title,
            'device_ids' => $this->deviceIds,
            'card_id' => $this->cardId,
            'error' => $exception->getMessage()
        ]);
    }
}
