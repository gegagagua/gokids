<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Garden extends Model
{
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
}
