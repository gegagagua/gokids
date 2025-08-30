<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BogPayment extends Model
{
    protected $fillable = [
        'order_id',
        'bog_transaction_id',
        'amount',
        'currency',
        'status',
        'user_id',
        'card_id',
        'garden_id',
        'payment_method',
        'saved_card_id',
        'payment_details',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_details' => 'array',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the user that owns the payment.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the card associated with the payment.
     */
    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * Get the garden associated with the payment.
     */
    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    /**
     * Scope for pending payments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for completed payments
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed payments
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
