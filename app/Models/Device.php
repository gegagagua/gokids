<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    protected $fillable = [
        'name',
        'status',
        'garden_id',
        'garden_groups',
    ];

    protected $casts = [
        'garden_groups' => 'array',
    ];

    public function garden()
    {
        return $this->belongsTo(\App\Models\Garden::class);
    }
}
