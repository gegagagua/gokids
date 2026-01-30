<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProCreditPayment extends Model
{
    protected $table = 'bog_payments';

    protected $fillable = [
        'order_id',
        'bank_order_id',
        'bank_order_password',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
