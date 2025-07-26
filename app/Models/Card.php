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
        'parent_name',
        'phone',
        'status',
        'group_id',
        'person_type_id',
        'parent_code',
        'image_path',
    ];

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
