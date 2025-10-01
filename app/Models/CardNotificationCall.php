<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CardNotificationCall extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'notification_type',
        'called_at',
    ];

    protected $casts = [
        'called_at' => 'datetime',
    ];

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get call count for a card within a specific time period
     */
    public static function getCallCount($cardId, $notificationType = 'card_to_all_devices', $since = null)
    {
        $since = $since ?: now()->startOfDay();
        
        return self::where('card_id', $cardId)
            ->where('notification_type', $notificationType)
            ->where('called_at', '>=', $since)
            ->count();
    }

    /**
     * Record a notification call
     */
    public static function recordCall($cardId, $notificationType = 'card_to_all_devices')
    {
        return self::create([
            'card_id' => $cardId,
            'notification_type' => $notificationType,
            'called_at' => now(),
        ]);
    }

    /**
     * Check if card has exceeded call limit
     */
    public static function hasExceededLimit($cardId, $notificationType = 'card_to_all_devices', $limit = 5, $since = null)
    {
        $callCount = self::getCallCount($cardId, $notificationType, $since);
        return $callCount >= $limit;
    }

    /**
     * Get remaining calls for a card
     */
    public static function getRemainingCalls($cardId, $notificationType = 'card_to_all_devices', $limit = 5, $since = null)
    {
        $callCount = self::getCallCount($cardId, $notificationType, $since);
        return max(0, $limit - $callCount);
    }
}