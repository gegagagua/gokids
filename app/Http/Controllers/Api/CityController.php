<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Get(
 *     path="/api/cities",
 *     summary="Get all cities",
 *     @OA\Response(response=200, description="List of cities")
 * )
 */
class CityController extends Controller
{
    public function index(): JsonResponse
    {
        $cities = City::all(['id', 'name', 'country']);
        return response()->json($cities);
    }
}
