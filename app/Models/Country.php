<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $fillable = [
        'name',
        'tariff',
    ];

    protected $casts = [
        'tariff' => 'decimal:2',
    ];

    public function cities()
    {
        return $this->hasMany(City::class);
    }

    /**
     * Check if the country is free (tariff = 0)
     */
    public function isFree()
    {
        return $this->tariff == 0;
    }

    /**
     * Get formatted tariff display
     */
    public function getFormattedTariffAttribute()
    {
        if ($this->isFree()) {
            return 'უფასო';
        }
        return number_format($this->tariff, 2) . ' ₾';
    }
}
