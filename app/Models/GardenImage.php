<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GardenImage extends Model
{
    protected $fillable = [
        'title',
        'garden_id',
        'image',
        'index',
    ];

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    public function getImageUrlAttribute()
    {
        if ($this->image) {
            return asset('storage/' . $this->image);
        }
        return null;
    }
}
