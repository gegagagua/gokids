<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CalledCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'card_id',
        'create_date',
    ];

    protected $casts = [
        'create_date' => 'datetime',
    ];

    /**
     * Get the card that was called
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Scope to get called cards for a specific card
     */
    public function scopeForCard($query, $cardId)
    {
        return $query->where('card_id', $cardId);
    }

    /**
     * Scope to get called cards within a date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('create_date', [$startDate, $endDate]);
    }

    /**
     * Scope to get called cards for today
     */
    public function scopeToday($query)
    {
        return $query->whereDate('create_date', today());
    }

    /**
     * Scope to get called cards for a specific date
     */
    public function scopeOnDate($query, $date)
    {
        return $query->whereDate('create_date', $date);
    }
}
