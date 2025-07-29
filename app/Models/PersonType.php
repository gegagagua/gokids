<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonType extends Model
{
    protected $fillable = [
        'name',
    ];

    public function people()
    {
        return $this->hasMany(People::class);
    }
}
