<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class GardenOtp extends Model
{
    protected $fillable = [
        'email',
        'otp',
        'expires_at',
        'used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean'
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is valid (not expired and not used)
     */
    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->used;
    }

    /**
     * Generate a 6-digit OTP
     */
    public static function generateOtp(): string
    {
        return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Create a new OTP for email
     */
    public static function createOtp(string $email): self
    {
        // Mark all existing OTPs for this email as used
        self::where('email', $email)->update(['used' => true]);

        return self::create([
            'email' => $email,
            'otp' => self::generateOtp(),
            'expires_at' => Carbon::now()->addMinutes(10), // 10 minutes expiry
            'used' => false
        ]);
    }
}
