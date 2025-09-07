<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name',
        'address',
        'tax_id',
        'city_id',
        'country_id',
        'phone',
        'email',
        'password',
        'referral_code',
        'referral',
        'status',
        'balance',
        'balance_comment',
        'percents',
    ];

    protected $hidden = ['password'];

    protected $casts = [
        'balance' => 'decimal:2',
        'percents' => 'decimal:2',
    ];

    protected $appends = ['dister', 'country'];

    public static function generateUniqueReferralCode()
    {
        do {
            $code = strtoupper(uniqid('REF'));
        } while (self::where('referral_code', $code)->exists());
        return $code;
    }

    public function city()
    {
        return $this->belongsTo(City::class);
    }

    public function countryRelation()
    {
        return $this->belongsTo(Country::class, 'country_id', 'id');
    }

    public function groups()
    {
        return $this->hasMany(GardenGroup::class);
    }

    public function images()
    {
        return $this->hasMany(\App\Models\GardenImage::class);
    }

    /**
     * Get the dister associated with this garden
     */
    public function dister()
    {
        return $this->belongsTo(\App\Models\Dister::class, 'id', 'gardens')
            ->whereJsonContains('gardens', $this->id);
    }

    /**
     * Get the dister associated with this garden (accessor)
     */
    public function getDisterAttribute()
    {
        return \App\Models\Dister::whereJsonContains('gardens', $this->id)->first();
    }

    /**
     * Get the country associated with this garden (accessor)
     */
    public function getCountryAttribute()
    {
        return $this->countryRelation;
    }

    /**
     * Check if garden is active
     */
    public function isActive()
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Check if garden is paused
     */
    public function isPaused()
    {
        return $this->status === self::STATUS_PAUSED;
    }

    /**
     * Check if garden is inactive
     */
    public function isInactive()
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    /**
     * Get all possible status values
     */
    public static function getStatusOptions()
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_PAUSED => 'Paused',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    /**
     * Get formatted balance display
     */
    public function getFormattedBalanceAttribute()
    {
        if ($this->balance === null) {
            return null;
        }
        return number_format($this->balance, 2) . ' ₾';
    }
}
