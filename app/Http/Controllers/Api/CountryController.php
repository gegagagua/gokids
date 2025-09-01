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
 *                     @OA\Property(property="currency", type="string", example="GEL", description="Country currency code"),
 *                     @OA\Property(property="garden_percent", type="number", format="float", example=15.00, description="Garden percentage for this country"),
 *                     @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *                     @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *                     @OA\Property(property="price", type="number", format="float", example=10.00),
      *                     @OA\Property(property="formatted_price", type="string", example="10.00 ₾"),
 *                     @OA\Property(property="formatted_garden_percent", type="string", example="15.00%"),
 *                     @OA\Property(property="dister", type="object", nullable=true, description="Dister information",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="Dister Name"),
 *                         @OA\Property(property="email", type="string", example="dister@example.com")
 *                     ),
 *                     @OA\Property(property="sms_gateway", type="object", nullable=true, description="SMS Gateway information",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="Geo Sms - ubill")
 *                     ),
 *                     @OA\Property(property="payment_gateway", type="object", nullable=true, description="Payment Gateway information",
 *                         @OA\Property(property="id", type="integer", example=1),
 *                         @OA\Property(property="name", type="string", example="BOG")
 *                     ),
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
        $query = Country::with(['dister', 'smsGateway', 'paymentGateway']);
        
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
      *             @OA\Property(property="currency", type="string", example="GEL", description="Country currency code"),
 *             @OA\Property(property="garden_percent", type="number", format="float", example=15.00, description="Garden percentage for this country"),
 *             @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *             @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *             @OA\Property(property="price", type="number", format="float", example=10.00),
      *             @OA\Property(property="formatted_price", type="string", example="10.00 ₾"),
 *             @OA\Property(property="formatted_garden_percent", type="string", example="15.00%"),
 *             @OA\Property(property="dister", type="object", nullable=true, description="Dister information",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dister Name"),
     *                 @OA\Property(property="email", type="string", example="dister@example.com")
     *             ),
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
        return Country::with(['dister', 'smsGateway', 'paymentGateway'])->findOrFail($id);
    }
    /**
     * @OA\Post(
     *     path="/api/countries",
     *     operationId="storeCountry",
     *     tags={"Countries"},
     *     summary="Create a new country",
     *     description="Create a new country with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name","tariff","price"},
      *             @OA\Property(property="name", type="string", maxLength=255, example="საქართველო", description="Country name"),
 *             @OA\Property(property="currency", type="string", maxLength=10, example="GEL", description="Country currency code (defaults to GEL)"),
 *             @OA\Property(property="garden_percent", type="number", format="float", example=15.00, description="Garden percentage for this country (0-100)"),
 *             @OA\Property(property="tariff", type="number", format="float", example=0.00, description="Tariff amount (0 for free)"),
 *             @OA\Property(property="price", type="number", format="float", example=10.00, description="Price amount"),
     *             @OA\Property(property="dister", type="integer", example=1, nullable=true, description="Optional dister ID"),
     *             @OA\Property(property="sms_gateway_id", type="integer", example=1, nullable=true, description="Optional SMS gateway ID"),
     *             @OA\Property(property="payment_gateway_id", type="integer", example=1, nullable=true, description="Optional payment gateway ID"),
             @OA\Property(property="language", type="string", maxLength=10, example="ka", nullable=true, description="Optional language code (e.g., ka, en, ru)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Country created successfully",
     *         @OA\JsonContent(
     *             type="object",
      *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="საქართველო"),
 *             @OA\Property(property="currency", type="string", example="GEL"),
 *             @OA\Property(property="garden_percent", type="number", format="float", example=15.00),
 *             @OA\Property(property="tariff", type="number", format="float", example=0.00),
     *             @OA\Property(property="formatted_tariff", type="string", example="უფასო"),
     *             @OA\Property(property="price", type="number", format="float", example=10.00),
      *             @OA\Property(property="formatted_price", type="string", example="10.00 ₾"),
 *             @OA\Property(property="formatted_garden_percent", type="string", example="15.00%"),
 *             @OA\Property(property="dister", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dister Name"),
     *                 @OA\Property(property="email", type="string", example="dister@example.com")
     *             ),
     *             @OA\Property(property="sms_gateway", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="SMS Gateway Name")
     *             ),
     *             @OA\Property(property="payment_gateway", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Payment Gateway Name")
     *             ),
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
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 )
     *             )
     *         )
     *     )
     * )
     */

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:countries,name',
            'currency' => 'nullable|string|max:10',
            'garden_percent' => 'nullable|numeric|min:0|max:100',
            'tariff' => 'required|numeric|min:0|max:999999.99',
            'price' => 'required|numeric|min:0|max:999999.99',
            'dister' => 'nullable|exists:disters,id',
            'sms_gateway_id' => 'nullable|exists:sms_gateways,id',
            'payment_gateway_id' => 'nullable|exists:payment_gateways,id',
            'language' => 'nullable|string|max:10',
        ]);

        $country = Country::create($validated);
        $country->load(['dister', 'smsGateway', 'paymentGateway']);

        return response()->json($country, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/countries/{id}",
     *     operationId="updateCountry",
     *     tags={"Countries"},
     *     summary="Update a country",
     *     description="Update an existing country by ID",
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
     *             @OA\Property(property="name", type="string", example="Georgia"),
      *             @OA\Property(property="currency", type="string", example="GEL", description="Country currency code"),
 *             @OA\Property(property="garden_percent", type="number", format="float", example=15.00, description="Garden percentage for this country (0-100)"),
 *             @OA\Property(property="tariff", type="number", format="float", example=10.5),
     *             @OA\Property(property="price", type="number", format="float", example=100.0),
     *             @OA\Property(property="dister", type="integer", nullable=true, example=1),
     *             @OA\Property(property="sms_gateway_id", type="integer", nullable=true, example=1),
     *             @OA\Property(property="payment_gateway_id", type="integer", nullable=true, example=1)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Country updated successfully",
     *         @OA\JsonContent(
      *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="name", type="string", example="Georgia"),
 *             @OA\Property(property="currency", type="string", example="GEL"),
 *             @OA\Property(property="garden_percent", type="number", format="float", example=15.00),
 *             @OA\Property(property="tariff", type="number", format="float", example=10.5),
     *             @OA\Property(property="price", type="number", format="float", example=100.0),
     *             @OA\Property(property="dister", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dister Name"),
     *                 @OA\Property(property="email", type="string", example="dister@example.com")
     *             ),
     *             @OA\Property(property="sms_gateway", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="SMS Gateway Name")
     *             ),
     *             @OA\Property(property="payment_gateway", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Payment Gateway Name")
     *             ),
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
     *                 @OA\Property(
     *                     property="name",
     *                     type="array",
     *                     @OA\Items(type="string", example="The name field is required.")
     *                 )
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
            'currency' => 'nullable|string|max:10',
            'garden_percent' => 'nullable|numeric|min:0|max:100',
            'tariff' => 'sometimes|required|numeric|min:0|max:999999.99',
            'price' => 'sometimes|required|numeric|min:0|max:999999.99',
            'exchange_rate' => 'nullable|numeric|min:0|max:999999.9999',
            'dister' => 'nullable|exists:disters,id',
            'sms_gateway_id' => 'nullable|exists:sms_gateways,id',
            'payment_gateway_id' => 'nullable|exists:payment_gateways,id',
            'language' => 'nullable|string|max:10',
        ]);

        $country->update($validated);
        $country->load(['dister', 'smsGateway', 'paymentGateway']);

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
