<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class CardOtp extends Model
{
    protected $fillable = [
        'phone',
        'otp',
        'expires_at',
        'used',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];

    public function isExpired()
    {
        return $this->expires_at->isPast();
    }

    public function isValid()
    {
        return !$this->used && !$this->isExpired();
    }

    public static function generateOtp()
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public static function createOtp($phone, $expiresInMinutes = 5)
    {
        // Invalidate any existing OTPs for this phone
        self::where('phone', $phone)->update(['used' => true]);

        return self::create([
            'phone' => $phone,
            'otp' => self::generateOtp(),
            'expires_at' => Carbon::now()->addMinutes($expiresInMinutes),
            'used' => false,
        ]);
    }
}
