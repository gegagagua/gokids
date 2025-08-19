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
        'country',
        'phone',
        'email',
        'password',
        'referral_code',
        'referral',
        'status',
    ];

    protected $hidden = ['password'];

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

    public function country()
    {
        return $this->belongsTo(Country::class);
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
}
