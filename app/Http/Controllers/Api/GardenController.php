<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Exports\GardensExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Gardens",
 *     description="API Endpoints for managing gardens"
 * )
 */
class GardenController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/gardens",
     *     operationId="getGardens",
     *     tags={"Gardens"},
     *     summary="Get all gardens",
     *     description="Retrieve a paginated list of all gardens with their associated city and images. Supports filtering by name, address, city_id, tax_id, phone, email.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by garden name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="address", in="query", required=false, description="Filter by address", @OA\Schema(type="string")),
     *     @OA\Parameter(name="city_id", in="query", required=false, description="Filter by city ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="country", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tax_id", in="query", required=false, description="Filter by tax ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", required=false, description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"active", "paused", "inactive"})),
     *     @OA\Parameter(name="balance_min", in="query", required=false, description="Filter by minimum balance", @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="balance_max", in="query", required=false, description="Filter by maximum balance", @OA\Schema(type="number", format="float")),
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
     *                     @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                     @OA\Property(property="address", type="string", example="123 Main Street"),
     *                     @OA\Property(property="tax_id", type="string", example="123456789"),
     *                     @OA\Property(property="city_id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="sunshine@garden.ge"),
     *                     @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}),
     *                     @OA\Property(property="balance", type="number", format="float", example=150.00, nullable=true),
     *                     @OA\Property(property="formatted_balance", type="string", example="150.00 ₾", nullable=true),
     *                     @OA\Property(property="percent", type="number", format="float", example=15.50, nullable=true),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="city", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Tbilisi"),
     *                         @OA\Property(property="region", type="string", example="Tbilisi")
     *                     ),
     *                     @OA\Property(property="country", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Georgia"),
     *                         @OA\Property(property="tariff", type="number", format="float", example=0.00)
     *                     ),
     *                     @OA\Property(property="images", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="title", type="string", example="Main Entrance"),
     *                             @OA\Property(property="image", type="string", example="garden_images/abc123.jpg")
     *                         )
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
    public function index(Request $request)
    {
        $query = Garden::with(['city', 'country', 'images']);

        // If logged-in user is a dister, restrict to their assigned gardens
        if ($request->user() instanceof \App\Models\User && $request->user()->type === 'dister') {
            $dister = \App\Models\Dister::where('email', $request->user()->email)->first();
            $allowedGardenIds = $dister->gardens ?? [];
            if (empty($allowedGardenIds)) {
                // Return empty when no gardens assigned
                return $query->whereRaw('1 = 0')->paginate($request->query('per_page', 15));
            }
            $query->whereIn('id', $allowedGardenIds);
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->query('address') . '%');
        }
        if ($request->filled('city_id')) {
            $query->where('city_id', $request->query('city_id'));
        }
        if ($request->filled('country')) {
            $query->where('country', $request->query('country'));
        }
        if ($request->filled('tax_id')) {
            $query->where('tax_id', $request->query('tax_id'));
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->query('phone') . '%');
        }
        if ($request->filled('email')) {
            $query->where('email', 'like', '%' . $request->query('email') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('balance_min')) {
            $query->where('balance', '>=', $request->query('balance_min'));
        }
        if ($request->filled('balance_max')) {
            $query->where('balance', '<=', $request->query('balance_max'));
        }

        $perPage = $request->query('per_page', 15);
        $gardens = $query->paginate($perPage);
        // დაამატე referral_code ყველა garden-ს
        $gardens->getCollection()->transform(function ($garden) {
            $garden->makeVisible('referral_code');
            return $garden;
        });
        return $gardens;
    }

    /**
     * @OA\Get(
     *     path="/api/gardens/{id}",
     *     operationId="getGarden",
     *     tags={"Gardens"},
     *     summary="Get a specific garden",
     *     description="Retrieve detailed information about a specific garden",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *             @OA\Property(property="address", type="string", example="123 Main Street"),
     *             @OA\Property(property="tax_id", type="string", example="123456789"),
     *             @OA\Property(property="city_id", type="integer", example=1),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="email", example="sunshine@garden.ge"),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}),
     *             @OA\Property(property="balance", type="number", format="float", example=150.00, nullable=true),
     *             @OA\Property(property="formatted_balance", type="string", example="150.00 ₾", nullable=true),
     *             @OA\Property(property="percent", type="number", format="float", example=15.50, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="city",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Tbilisi"),
     *                 @OA\Property(property="region", type="string", example="Tbilisi")
     *             ),
     *             @OA\Property(
     *                 property="country",
     *                 type="object",
     *                 nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Georgia"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Garden]")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        // Restrict dister to only their gardens
        if (request()->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = request()->user()->gardens ?? [];
            if (!in_array((int)$id, $allowedGardenIds, true)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $garden = Garden::with(['city', 'country', 'images'])->findOrFail($id);
        $garden->makeVisible('referral_code');
        return $garden;
    }

    /**
     * @OA\Get(
     *     path="/api/gardens/export",
     *     operationId="exportGardens",
     *     tags={"Gardens"},
     *     summary="Export gardens to Excel",
     *     description="Download an Excel report of gardens with full information. Optionally filter by garden IDs. If dister is logged in, export is automatically restricted to their assigned gardens.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="ids", in="query", required=false, description="Comma-separated garden IDs or multiple ids[] query params", @OA\Schema(type="string", example="37,42")),
     *     @OA\Response(response=200, description="Excel file")
     * )
     */
    public function export(Request $request)
    {
        // Parse requested IDs from query: supports ids=1,2,3 or ids[]=1&ids[]=2
        $requestedIds = [];
        $idsParam = $request->query('ids');
        if (is_string($idsParam)) {
            $requestedIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));
        } elseif (is_array($request->query('ids'))) {
            $requestedIds = array_values(array_filter(array_map('intval', (array) $request->query('ids'))));
        }

        // If dister, restrict export to their assigned gardens
        $allowedIds = null;
        if ($request->user() instanceof \App\Models\Dister) {
            $allowedIds = array_values(array_filter((array) ($request->user()->gardens ?? []), 'is_numeric'));
        }

        // Decide final IDs to export
        $finalIds = [];
        if (!empty($requestedIds)) {
            $finalIds = $requestedIds;
            if (is_array($allowedIds)) {
                $finalIds = array_values(array_intersect($finalIds, $allowedIds));
            }
        } else {
            if (is_array($allowedIds)) {
                $finalIds = $allowedIds;
            } else {
                $finalIds = [];
            }
        }

        return Excel::download(new GardensExport($finalIds), 'gardens.xlsx');
    }

    /**
     * @OA\Post(
     *     path="/api/gardens",
     *     operationId="createGarden",
     *     tags={"Gardens"},
     *     summary="Create a new garden",
     *     description="Create a new garden with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address", "tax_id", "city_id", "phone", "email", "password"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="New Garden", description="Garden name"),
     *             @OA\Property(property="address", type="string", maxLength=255, example="456 Oak Avenue", description="Garden address"),
     *             @OA\Property(property="tax_id", type="string", maxLength=255, example="987654321", description="Tax identification number"),
     *             @OA\Property(property="city_id", type="integer", example=1, description="ID of the associated city"),
     *             @OA\Property(property="country", type="integer", example=1, nullable=true, description="Optional country ID"),
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="newgarden@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="password123", description="Garden access password"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true, description="Optional referral code"),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}, nullable=true, description="Garden status (defaults to active)"),
     *             @OA\Property(property="balance", type="number", format="float", example=100.00, nullable=true, description="Optional garden balance"),
     *             @OA\Property(property="percent", type="number", format="float", example=15.50, nullable=true, description="Optional garden percentage (0-100)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Garden created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="garden", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="New Garden"),
     *                 @OA\Property(property="address", type="string", example="456 Oak Avenue"),
     *                 @OA\Property(property="tax_id", type="string", example="987654321"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="+995599654321"),
     *                 @OA\Property(property="email", type="string", example="newgarden@garden.ge"),
     *                 @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}),
     *                 @OA\Property(property="balance", type="number", format="float", example=100.00, nullable=true),
     *                 @OA\Property(property="formatted_balance", type="string", example="100.00 ₾", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             ),
     *             @OA\Property(property="user", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="New Garden"),
     *                 @OA\Property(property="email", type="string", example="newgarden@garden.ge"),
     *                 @OA\Property(property="type", type="string", example="garden")
     *             ),
     *             @OA\Property(property="message", type="string", example="Garden and user account created successfully")
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
     *                 @OA\Property(property="address", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="tax_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="city_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="country", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="balance", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'tax_id' => 'required|string|max:255',
            'city_id' => 'required|exists:cities,id',
            'country' => 'nullable|exists:countries,id',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|unique:gardens,email|unique:users,email',
            'password' => 'required|string|min:6',
            'referral' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,paused,inactive',
            'balance' => 'nullable|numeric|min:0|max:9999999.99',
            'percent' => 'nullable|numeric|min:0|max:100.00',
        ]);

        // Create user for the garden
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'type' => 'garden',
        ]);

        // Create garden
        $gardenData = $validated;
        $gardenData['password'] = bcrypt($validated['password']);
        $gardenData['referral_code'] = \App\Models\Garden::generateUniqueReferralCode();
        $garden = Garden::create($gardenData);

        return response()->json([
            'garden' => $garden,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'type' => $user->type,
            ],
            'message' => 'Garden and user account created successfully'
        ], 201);
    }

    /**
     * @OA\Put(
     *     path="/api/gardens/{id}",
     *     operationId="updateGarden",
     *     tags={"Gardens"},
     *     summary="Update a garden",
     *     description="Update an existing garden with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Garden", description="Garden name"),
     *             @OA\Property(property="address", type="string", maxLength=255, example="789 Pine Street", description="Garden address"),
     *             @OA\Property(property="tax_id", type="string", maxLength=255, example="111222333", description="Tax identification number"),
     *             @OA\Property(property="city_id", type="integer", example=2, description="ID of the associated city"),
     *             @OA\Property(property="country", type="integer", example=1, nullable=true, description="Optional country ID"),
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599111222", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123", description="Garden access password"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true, description="Optional referral code"),
     *             @OA\Property(property="status", type="string", example="paused", enum={"active", "paused", "inactive"}, nullable=true, description="Garden status"),
     *             @OA\Property(property="balance", type="number", format="float", example=200.00, nullable=true, description="Optional garden balance"),
     *             @OA\Property(property="percent", type="number", format="float", example=15.50, nullable=true, description="Optional garden percentage (0-100)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Garden"),
     *             @OA\Property(property="address", type="string", example="789 Pine Street"),
     *             @OA\Property(property="tax_id", type="string", example="111222333"),
     *             @OA\Property(property="city_id", type="integer", example=2),
     *             @OA\Property(property="country", type="integer", example=1, nullable=true),
     *             @OA\Property(property="phone", type="string", example="+995599111222"),
     *             @OA\Property(property="email", type="string", example="updated@garden.ge"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true),
     *             @OA\Property(property="status", type="string", example="paused", enum={"active", "paused", "inactive"}),
     *             @OA\Property(property="percent", type="number", format="float", example=15.50, nullable=true),
     *             @OA\Property(property="balance", type="number", format="float", example=200.00, nullable=true),
     *             @OA\Property(property="formatted_balance", type="string", example="200.00 ₾", nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Garden]")
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
     *                 @OA\Property(property="address", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="tax_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="city_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="country", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="referral", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="balance", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="percent", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $garden = Garden::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'tax_id' => 'sometimes|required|string|max:255',
            'city_id' => 'sometimes|required|exists:cities,id',
            'country' => 'nullable|exists:countries,id',
            'phone' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:gardens,email,' . $id,
            'password' => 'sometimes|required|string|min:6',
            'referral' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,paused,inactive',
            'balance' => 'nullable|numeric|min:0|max:9999999.99',
            'percent' => 'nullable|numeric|min:0|max:100.00',
        ]);

        // Hash the password if it's being updated
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $garden->update($validated);

        return response()->json($garden);
    }

    /**
     * @OA\Delete(
     *     path="/api/gardens/{id}",
     *     operationId="deleteGarden",
     *     tags={"Gardens"},
     *     summary="Delete a garden",
     *     description="Permanently delete a garden",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Garden]")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $garden = Garden::findOrFail($id);
        $garden->delete();

        return response()->json(['message' => 'Garden deleted']);
    }

    /**
     * @OA\Patch(
     *     path="/api/gardens/{id}/status",
     *     operationId="updateGardenStatus",
     *     tags={"Gardens"},
     *     summary="Update garden status",
     *     description="Update only the status of a specific garden",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"active", "paused", "inactive"},
     *                 example="paused",
     *                 description="New garden status"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Garden Name"),
     *             @OA\Property(property="status", type="string", example="paused", enum={"active", "paused", "inactive"}),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Garden]")
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
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $garden = Garden::findOrFail($id);

        $validated = $request->validate([
            'status' => 'required|string|in:active,paused,inactive',
        ]);

        $garden->update(['status' => $validated['status']]);

        return response()->json([
            'id' => $garden->id,
            'name' => $garden->name,
            'status' => $garden->status,
            'updated_at' => $garden->updated_at,
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/gardens/{id}/dister",
     *     operationId="updateGardenDister",
     *     tags={"Gardens"},
     *     summary="Update garden dister and referral",
     *     description="Update the dister assignment for a garden and generate new referral code",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"dister_id"},
     *             @OA\Property(
     *                 property="dister_id",
     *                 type="integer",
     *                 example=1,
     *                 description="ID of the dister to assign to the garden"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden dister updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Garden Name"),
     *             @OA\Property(property="dister_id", type="integer", example=1),
     *             @OA\Property(property="referral_code", type="string", example="REF123ABC"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="dister",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Dister Name"),
     *                 @OA\Property(property="email", type="string", example="dister@example.com")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden or dister not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden or dister not found")
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
     *                 @OA\Property(property="dister_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateDister(Request $request, $id)
    {
        $garden = Garden::findOrFail($id);
        $dister = \App\Models\Dister::findOrFail($request->dister_id);

        $validated = $request->validate([
            'dister_id' => 'required|exists:disters,id',
        ]);

        // Generate new referral code
        $newReferralCode = Garden::generateUniqueReferralCode();

        // Update garden with new dister and referral code
        $garden->update([
            'referral_code' => $newReferralCode,
        ]);

        // Update dister's gardens array to include this garden
        $disterGardens = $dister->gardens ?? [];
        if (!in_array($garden->id, $disterGardens)) {
            $disterGardens[] = $garden->id;
            $dister->update(['gardens' => $disterGardens]);
        }

        // Load the updated dister data
        $garden->load('dister');

        return response()->json([
            'id' => $garden->id,
            'name' => $garden->name,
            'dister_id' => $dister->id,
            'referral_code' => $garden->referral_code,
            'updated_at' => $garden->updated_at,
            'dister' => [
                'id' => $dister->id,
                'name' => $dister->name,
                'email' => $dister->email,
            ],
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/gardens/bulk-delete",
     *     operationId="bulkDeleteGardens",
     *     tags={"Gardens"},
     *     summary="Delete multiple gardens",
     *     description="Permanently delete multiple gardens by their IDs",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"ids"},
     *             @OA\Property(
     *                 property="ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Gardens deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Gardens deleted"),
     *             @OA\Property(property="deleted_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", example="No valid IDs provided")
     *         )
     *     )
     * )
     */
    public function bulkDestroy(Request $request)
    {
        $ids = $request->input('ids');

        if (!is_array($ids) || empty($ids)) {
            return response()->json(['message' => 'No valid IDs provided'], 400);
        }

        $deleted = Garden::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => 'Gardens deleted',
            'deleted_count' => $deleted,
        ]);
    }
}
