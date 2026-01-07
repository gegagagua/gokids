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
        'garden_id',
        'amount',
        'currency',
        'comment',
        'type',
        'status',
        'payment_gateway_id',
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

    /**
     * Get the payment gateway for the payment.
     */
    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class);
    }

    /**
     * Get the garden that owns the payment.
     */
    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }
}
