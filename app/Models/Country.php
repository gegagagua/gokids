<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'index',
        'phone_index',
        'currency',
        'garden_percent',
        'tariff',
        'price',
        'exchange_rate',
        'dister',
        'sms_gateway_id',
        'payment_gateway_id',
        'language',
    ];

    protected $casts = [
        'garden_percent' => 'decimal:2',
        'tariff' => 'decimal:2',
        'price' => 'decimal:2',
        'exchange_rate' => 'decimal:4',
    ];

    protected $appends = [
        'index',
        'formatted_tariff',
        'formatted_price',
        'formatted_garden_percent',
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
        return $this->belongsTo(Dister::class, 'dister', 'referral');
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

    /**
     * Get formatted garden percent display
     */
    public function getFormattedGardenPercentAttribute()
    {
        return number_format($this->garden_percent ?? 0, 2) . '%';
    }

    /**
     * Backward-compatible alias for phone_index.
     */
    public function getIndexAttribute()
    {
        return $this->phone_index;
    }

    /**
     * Allow writing `index` in create/update requests.
     */
    public function setIndexAttribute($value): void
    {
        $this->attributes['phone_index'] = $this->normalizePhoneIndex($value);
    }

    /**
     * Normalize phone index to +NNN format.
     */
    public function setPhoneIndexAttribute($value): void
    {
        $this->attributes['phone_index'] = $this->normalizePhoneIndex($value);
    }

    protected function normalizePhoneIndex($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        // Keep only + and digits, then normalize to +[digits]
        $normalized = preg_replace('/[^0-9+]/', '', $value) ?? '';
        if ($normalized === '') {
            return null;
        }

        $digits = ltrim($normalized, '+');
        if ($digits === '') {
            return null;
        }

        return '+' . $digits;
    }
}
