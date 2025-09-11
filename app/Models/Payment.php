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
        'currency',
        'comment',
        'type',
    ];

    /**
     * Get the card that owns the payment.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
