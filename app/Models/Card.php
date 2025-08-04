<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_first_name',
        'child_last_name',
        'parent_name',
        'phone',
        'status',
        'group_id',
        'person_type_id',
        'parent_code',
        'image_path',
        'parent_verification',
        'license',
        'deleted',
    ];

    protected $casts = [
        'parent_verification' => 'boolean',
        'license' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($card) {
            if (empty($card->parent_code)) {
                $card->parent_code = self::generateParentCode();
            }
        });
    }

    /**
     * Generate a unique 6-character parent code
     */
    public static function generateParentCode()
    {
        do {
            // Generate 6-character code with letters and numbers only (more readable)
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $code = '';
            
            for ($i = 0; $i < 6; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }
        } while (self::where('parent_code', $code)->exists());

        return $code;
    }

    /**
     * Set license as boolean
     */
    public function setLicenseBoolean($value)
    {
        $this->license = ['type' => 'boolean', 'value' => (bool) $value];
        return $this;
    }

    /**
     * Set license as date
     */
    public function setLicenseDate($date)
    {
        $this->license = ['type' => 'date', 'value' => $date instanceof Carbon ? $date->toDateString() : $date];
        return $this;
    }

    /**
     * Get license value
     */
    public function getLicenseValue()
    {
        if (!$this->license) {
            return null;
        }
        
        return $this->license['value'] ?? null;
    }

    /**
     * Get license type
     */
    public function getLicenseType()
    {
        if (!$this->license) {
            return null;
        }
        
        return $this->license['type'] ?? null;
    }

    /**
     * Check if license is boolean
     */
    public function isLicenseBoolean()
    {
        return $this->getLicenseType() === 'boolean';
    }

    /**
     * Check if license is date
     */
    public function isLicenseDate()
    {
        return $this->getLicenseType() === 'date';
    }

    public function group()
    {
        return $this->belongsTo(GardenGroup::class);
    }

    public function parents()
    {
        return $this->hasMany(ParentModel::class);
    }

    public function people()
    {
        return $this->hasMany(People::class);
    }

    public function personType()
    {
        return $this->belongsTo(\App\Models\PersonType::class);
    }
}
