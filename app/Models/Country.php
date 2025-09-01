<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'currency',
        'tariff',
        'price',
        'exchange_rate',
        'dister',
        'sms_gateway_id',
        'payment_gateway_id',
        'language',
    ];

    protected $casts = [
        'tariff' => 'decimal:2',
        'price' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected $appends = [
        'formatted_tariff',
        'formatted_price',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    public function gardens()
    {
        return $this->hasMany(Garden::class);
    }

    /**
     * Get the dister that owns the country.
     */
    public function dister()
    {
        return $this->belongsTo(Dister::class, 'dister');
    }

    /**
     * Get the SMS gateway for this country.
     */
    public function smsGateway()
    {
        return $this->belongsTo(SmsGateway::class, 'sms_gateway_id');
    }

    /**
     * Get the payment gateway for this country.
     */
    public function paymentGateway()
    {
        return $this->belongsTo(PaymentGateway::class, 'payment_gateway_id');
    }

    /**
     * Check if the country is free (tariff = 0)
     */
    public function isFree()
    {
        return $this->tariff == 0;
    }

    /**
     * Get formatted tariff display
     */
    public function getFormattedTariffAttribute()
    {
        if ($this->isFree()) {
            return 'უფასო';
        }
        return number_format($this->tariff, 2) . ' ₾';
    }

    /**
     * Get formatted price display
     */
    public function getFormattedPriceAttribute()
    {
        return number_format($this->price, 2) . ' ₾';
    }
}
