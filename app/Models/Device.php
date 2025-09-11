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
    ];

    protected $casts = [
        'garden_groups' => 'array',
        'active_garden_groups' => 'array',
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
}
