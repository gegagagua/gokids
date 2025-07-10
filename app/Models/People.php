<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class People extends Model
{
    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'person_type_id',
        'card_id'
    ];

    public function personType()
    {
        return $this->belongsTo(PersonType::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
