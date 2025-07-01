<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use HasFactory;

    protected $fillable = [
        'child_first_name',
        'child_last_name',
        'father_name',
        'parent_first_name',
        'parent_last_name',
        'phone',
        'status',
        'group_id',
        'parent_code',
    ];

    public function group()
    {
        return $this->belongsTo(GardenGroup::class);
    }
}
