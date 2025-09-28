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
     */
    public function isLoggedIn()
    {
        return $this->is_logged_in && 
               $this->session_expires_at && 
               $this->session_expires_at->isFuture();
    }

    /**
     * Start a new session for the device
     */
    public function startSession($sessionDurationMinutes = 60)
    {
        $result = $this->update([
            'is_logged_in' => true,
            'last_login_at' => now(),
            'session_token' => \Str::random(32),
            'session_expires_at' => now()->addMinutes($sessionDurationMinutes),
        ]);
        
        // Log the session start for debugging
        \Log::info("Device session started", [
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
     */
    public function extendSession($sessionDurationMinutes = 60)
    {
        if ($this->isLoggedIn()) {
            $this->update([
                'session_expires_at' => now()->addMinutes($sessionDurationMinutes),
            ]);
        }
    }

    /**
     * Check if session is expired
     */
    public function isSessionExpired()
    {
        return $this->session_expires_at && $this->session_expires_at->isPast();
    }
}
