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
        'phone',
        'email',
        'password',
    ];

    protected $hidden = ['password'];

    public function city()
    {
        return $this->belongsTo(City::class);
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
