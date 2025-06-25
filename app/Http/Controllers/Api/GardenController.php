<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;

class GardenController extends Controller
{
    public function index()
    {
        return Garden::with('city')->get();
    }
}
