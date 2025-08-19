<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Country;

/**
 * @OA\Tag(
 *     name="Countries",
 *     description="API Endpoints for managing countries and tariffs"
 * )
 */
class CountryController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/countries",
     *     operationId="getCountries",
     *     tags={"Countries"},
     *     summary="Get all countries",
     *     description="Retrieve a list of all countries with their tariffs. Supports filtering by name and pagination.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by country name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="dister", in="query", required=false, description="Filter by dister ID", @OA\Schema(type="integer")),
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
     *                     @OA\Property(property="name", type="string", example="საქართველო"),
     *                     @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                     @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *                     @OA\Property(property="dister", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Country::with('dister');
        
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        
        if ($request->filled('dister')) {
            $query->where('dister', $request->query('dister'));
        }
        
        $perPage = $request->query('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * @OA\Get(
     *     path="/api/countries/{id}",
     *     operationId="getCountry",
     *     tags={"Countries"},
     *     summary="Get a specific country",
     *     description="Retrieve detailed information about a specific country",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="საქართველო"),
     *             @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *             @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Country]")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        return Country::with('dister')->findOrFail($id);
    }

    /**
     * @OA\Post(
     *     path="/api/countries",
     *     operationId="createCountry",
     *     tags={"Countries"},
     *     summary="Create a new country",
     *     description="Create a new country with name and tariff",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "tariff"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="საქართველო", description="Country name"),
     *             @OA\Property(property="tariff", type="number", format="float", example=0.00, description="Tariff amount (0 for free)"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true, description="Optional dister ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Country created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="საქართველო"),
     *             @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *             @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
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
     *                 @OA\Property(property="tariff", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="dister", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'tariff' => 'required|numeric|min:0|max:999999.99',
            'dister' => 'nullable|exists:disters,id',
        ]);

        $country = Country::create($validated);
        $country->load('dister');

        return response()->json($country, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/countries/{id}",
     *     operationId="updateCountry",
     *     tags={"Countries"},
     *     summary="Update a country",
     *     description="Update an existing country with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated საქართველო", description="Country name"),
     *             @OA\Property(property="tariff", type="number", format="float", example=10.50, description="Tariff amount (0 for free)"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true, description="Optional dister ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Country updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated საქართველო"),
     *             @OA\Property(property="tariff", type="number", format="float", example=10.50),
     *             @OA\Property(property="formatted_tariff", type="string", example="10.50 ₾"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Country]")
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
     *                 @OA\Property(property="tariff", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="dister", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $country = Country::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255|unique:countries,name,' . $id,
            'tariff' => 'sometimes|required|numeric|min:0|max:999999.99',
            'dister' => 'nullable|exists:disters,id',
        ]);

        $country->update($validated);
        $country->load('dister');

        return response()->json($country);
    }

    /**
     * @OA\Delete(
     *     path="/api/countries/{id}",
     *     operationId="deleteCountry",
     *     tags={"Countries"},
     *     summary="Delete a country",
     *     description="Permanently delete a country",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Country ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Country deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Country deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Country not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Country]")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $country = Country::findOrFail($id);
        $country->delete();

        return response()->json(['message' => 'Country deleted']);
    }
}
