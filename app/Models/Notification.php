<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'title',
        'body',
        'data',
        'expo_token',
        'device_id',
        'card_id',
        'status',
        'sent_at',
        'accepted_at',
    ];

    protected $casts = [
        'data' => 'array',
        'sent_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    /**
     * Get the device that owns the notification.
     */
    public function device()
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * Get the card associated with the notification.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
