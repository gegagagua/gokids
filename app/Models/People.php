<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class People extends Model
{
    use HasApiTokens;

    protected $fillable = [
        'name',
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
