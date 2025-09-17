<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\City;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by city name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="country_id", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (pagination)", @OA\Schema(type="integer", default=15)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="თბილისი"),
     *                     @OA\Property(property="country_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(
     *                         property="country",
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="საქართველო"),
     *                         @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                         @OA\Property(property="price", type="number", format="float", example=10.00),
     *                         @OA\Property(property="dister", type="integer", nullable=true, example=null),
     *                         @OA\Property(property="created_at", type="string", format="date-time"),
     *                         @OA\Property(property="updated_at", type="string", format="date-time")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = City::with('country');
        
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->query('country_id'));
        }
        
        $perPage = $request->query('per_page', 15);
        $cities = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return response()->json($cities);
    }

    /**
     * @OA\Get(
     *     path="/api/cities/{id}",
     *     operationId="getCity",
     *     tags={"Cities"},
     *     summary="Get a specific city",
     *     description="Retrieve detailed information about a specific city",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="თბილისი"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="country",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="საქართველო"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                 @OA\Property(property="price", type="number", format="float", example=10.00),
     *                 @OA\Property(property="dister", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\City]")
     *         )
     *     )
     * )
     */
    public function show($id): JsonResponse
    {
        $city = City::with('country')->findOrFail($id);
        return response()->json($city);
    }

    /**
     * @OA\Post(
     *     path="/api/cities",
     *     operationId="createCity",
     *     tags={"Cities"},
     *     summary="Create a new city",
     *     description="Create a new city with name and country",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "country_id"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="ქუთაისი", description="City name"),
     *             @OA\Property(property="country_id", type="integer", example=1, description="Country ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="City created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="ქუთაისი"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="country",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="საქართველო"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                 @OA\Property(property="price", type="number", format="float", example=10.00),
     *                 @OA\Property(property="dister", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="country_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:cities,name',
            'country_id' => 'required|integer|exists:countries,id',
        ]);

        $city = City::create($validated);
        $city->load('country');

        return response()->json($city, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/cities/{id}",
     *     operationId="updateCity",
     *     tags={"Cities"},
     *     summary="Update a city",
     *     description="Update an existing city with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated ქუთაისი", description="City name"),
     *             @OA\Property(property="country_id", type="integer", example=1, description="Country ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="City updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated ქუთაისი"),
     *             @OA\Property(property="country_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="country",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="საქართველო"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                 @OA\Property(property="price", type="number", format="float", example=10.00),
     *                 @OA\Property(property="dister", type="integer", nullable=true, example=null),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\City]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="country_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id): JsonResponse
    {
        $city = City::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:cities,name,' . $id,
            'country_id' => 'sometimes|required|integer|exists:countries,id',
        ]);

        $city->update($validated);
        $city->load('country');

        return response()->json($city);
    }

    /**
     * @OA\Delete(
     *     path="/api/cities/{id}",
     *     operationId="deleteCity",
     *     tags={"Cities"},
     *     summary="Delete a city",
     *     description="Permanently delete a city. Cannot delete if city is being used by gardens.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="City ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="City deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="City deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="City not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\City]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot delete city - city is in use",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot delete city. This city is being used by gardens."),
     *             @OA\Property(property="usage_count", type="integer", example=5, description="Number of gardens using this city")
     *         )
     *     )
     * )
     */
    public function destroy($id): JsonResponse
    {
        $city = City::findOrFail($id);
        
        // Check if city is being used by any gardens
        $gardenCount = $city->gardens()->count();
        
        if ($gardenCount > 0) {
            return response()->json([
                'message' => 'Cannot delete city. This city is being used by gardens.',
                'usage_count' => $gardenCount
            ], 422);
        }
        
        $city->delete();

        return response()->json(['message' => 'City deleted']);
    }
}
