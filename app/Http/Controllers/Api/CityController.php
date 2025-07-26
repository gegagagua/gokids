<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Cities",
 *     description="API Endpoints for managing cities"
 * )
 */
class CityController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cities",
     *     operationId="getCities",
     *     tags={"Cities"},
     *     summary="Get all cities",
     *     description="Retrieve a list of all cities with their country information",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="თბილისი"),
     *                 @OA\Property(property="country_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="country",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="საქართველო"),
     *                     @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                     @OA\Property(property="formatted_tariff", type="string", example="უფასო")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $cities = City::with('country')->get();
        return response()->json($cities);
    }
}
