<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonType;

class PersonTypeController extends Controller
{
    public function index()
    {
        return response()->json(PersonType::all());
    }
}