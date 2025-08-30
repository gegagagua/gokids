<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationSent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $title;
    public $body;
    public $deviceIds;
    public $cardId;
    public $data;
    public $results;
    public $timestamp;

    /**
     * Create a new event instance.
     */
    public function __construct(string $title, string $body, array $deviceIds, ?int $cardId = null, array $data = [], array $results = [])
    {
        $this->title = $title;
        $this->body = $body;
        $this->deviceIds = $deviceIds;
        $this->cardId = $cardId;
        $this->data = $data;
        $this->results = $results;
        $this->timestamp = now();
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notifications'),
            new Channel('garden-notifications'),
        ];
    }

    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
            'device_ids' => $this->deviceIds,
            'card_id' => $this->cardId,
            'data' => $this->data,
            'results' => $this->results,
            'timestamp' => $this->timestamp->toISOString(),
        ];
    }
}
