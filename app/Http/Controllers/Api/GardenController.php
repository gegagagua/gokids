<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;

/**
 * @OA\Get(
 *     path="/api/gardens",
 *     summary="Get all gardens",
 *     @OA\Response(response=200, description="List of gardens")
 * )
 */
class GardenController extends Controller
{
    public function index()
    {
        return Garden::with('city')->get();
    }
}
