<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParentModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'first_name',
        'last_name',
        'status',
        'phone',
        'code',
        'group_id',
        'card_id',
    ];

    public function group()
    {
        return $this->belongsTo(GardenGroup::class);
    }

    public function card()
    {
        return $this->belongsTo(Card::class);
    }
}
