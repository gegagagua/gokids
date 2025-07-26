<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class City extends Model
{
    protected $fillable = [
        'name',
        'country_id',
    ];

    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    public function gardens()
    {
        return $this->hasMany(Garden::class);
    }

    /**
     * Get the country name (for backward compatibility)
     */
    public function getCountryNameAttribute()
    {
        return $this->country ? $this->country->name : null;
    }
}
