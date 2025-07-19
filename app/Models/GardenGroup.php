<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GardenGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'garden_id'];

    public function garden()
    {
        return $this->belongsTo(Garden::class);
    }

    public function cards()
    {
        return $this->hasMany(Card::class, 'group_id');
    }

    public function parents()
    {
        return $this->hasMany(ParentModel::class);
    }
}
