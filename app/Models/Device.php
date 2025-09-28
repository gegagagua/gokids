<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'code',
        'expo_token',
        'status',
        'garden_id',
        'garden_groups',
        'active_garden_groups',
        'is_logged_in',
        'last_login_at',
        'session_token',
        'session_expires_at',
    ];

    protected $casts = [
        'garden_groups' => 'array',
        'active_garden_groups' => 'array',
        'is_logged_in' => 'boolean',
        'last_login_at' => 'datetime',
        'session_expires_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($device) {
            if (empty($device->code)) {
                $device->code = self::generateDeviceCode();
            }
        });
    }

    /**
     * Generate a unique 6-character device code
     */
    public static function generateDeviceCode()
    {
        do {
            // Generate 6-character code with letters and numbers only (more readable)
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function garden()
    {
        return $this->belongsTo(\App\Models\Garden::class);
    }

    public function gardenGroups()
    {
        if (empty($this->garden_groups)) {
            return \App\Models\GardenGroup::whereIn('id', []);
        }
        
        return \App\Models\GardenGroup::whereIn('id', $this->garden_groups);
    }

    public function activeGardenGroups()
    {
        if (empty($this->active_garden_groups)) {
            return \App\Models\GardenGroup::whereIn('id', []);
        }
        
        return \App\Models\GardenGroup::whereIn('id', $this->active_garden_groups);
    }

    /**
     * Check if device is currently logged in
     * Sessions are unlimited until explicit logout
     */
    public function isLoggedIn()
    {
        return $this->is_logged_in;
    }

    /**
     * Start a new session for the device
     * Sessions are unlimited until explicit logout
     */
    public function startSession($sessionDurationMinutes = null)
    {
        $result = $this->update([
            'is_logged_in' => true,
            'last_login_at' => now(),
            'session_token' => \Str::random(32),
            'session_expires_at' => null, // No expiration - unlimited session
        ]);
        
        // Log the session start for debugging
        \Log::info("Device session started (unlimited)", [
            'device_id' => $this->id,
            'device_code' => $this->code,
            'is_logged_in' => $this->is_logged_in,
            'session_expires_at' => $this->session_expires_at,
            'update_result' => $result
        ]);
        
        return $result;
    }

    /**
     * End the current session for the device
     */
    public function endSession()
    {
        $this->update([
            'is_logged_in' => false,
            'session_token' => null,
            'session_expires_at' => null,
        ]);
        
        // Refresh the model to ensure the changes are reflected
        $this->refresh();
    }

    /**
     * Extend the current session
     * Not needed for unlimited sessions
     */
    public function extendSession($sessionDurationMinutes = null)
    {
        // Sessions are unlimited, no need to extend
        return true;
    }

    /**
     * Check if session is expired
     * Sessions are unlimited, so this always returns false
     */
    public function isSessionExpired()
    {
        return false; // Sessions are unlimited until explicit logout
    }

    /**
     * Clean up all expired sessions for all devices
     */
    public static function cleanupExpiredSessions()
    {
        $expiredDevices = self::where('is_logged_in', true)
            ->where('session_expires_at', '<', now())
            ->get();

        foreach ($expiredDevices as $device) {
            $device->endSession();
        }

        return $expiredDevices->count();
    }
}
