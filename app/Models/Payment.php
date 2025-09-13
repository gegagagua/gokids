<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'transaction_number',
        'transaction_number_bank',
        'card_number',
        'card_id',
        'amount',
        'currency',
        'comment',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * Get the card that owns the payment.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
