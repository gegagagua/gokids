<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Dister extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'email',
        'phone',
        'password',
        'first_name',
        'last_name',
        'country_id',
        'city_id',
        'gardens',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'gardens' => 'array',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the country that owns the dister.
     */
    public function country()
    {
        return $this->belongsTo(Country::class);
    }

    /**
     * Get the city that owns the dister.
     */
    public function city()
    {
        return $this->belongsTo(City::class);
    }

    /**
     * Get the gardens associated with the dister.
     */
    public function gardenList()
    {
        return $this->belongsToMany(Garden::class, 'dister_gardens');
    }

    /**
     * Get the full name of the dister.
     */
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName()
    {
        return 'id';
    }
}
