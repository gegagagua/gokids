<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GardenImage extends Model
{
    protected $fillable = [
        'title',
        'garden_id',
        'image',
    ];

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }
}
