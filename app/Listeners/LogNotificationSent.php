<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use App\Events\NotificationSent;

class LogNotificationSent implements ShouldQueue
{
    use InteractsWithQueue;

    public $timeout = 60;
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(NotificationSent $event): void
    {
        Log::info('Notification sent event logged', [
            'title' => $event->title,
            'body' => $event->body,
            'device_ids' => $event->deviceIds,
            'card_id' => $event->cardId,
            'data' => $event->data,
            'results' => $event->results,
            'timestamp' => $event->timestamp,
        ]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(NotificationSent $event, \Throwable $exception): void
    {
        Log::error('Failed to log notification sent event', [
            'title' => $event->title,
            'device_ids' => $event->deviceIds,
            'error' => $exception->getMessage()
        ]);
    }
}
