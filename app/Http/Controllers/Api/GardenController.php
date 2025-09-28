<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;
use App\Models\User;
use App\Models\Payment;
use App\Models\GardenOtp;
use App\Services\SmsService;
use App\Services\GardenMailService;
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
     *     description="Retrieve a paginated list of all gardens with their associated city and images. Supports filtering by name, address, referral, dister_id, tax_id, phone, email.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by garden name or referral code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="address", in="query", required=false, description="Filter by address", @OA\Schema(type="string")),
     *     @OA\Parameter(name="referral", in="query", required=false, description="Filter by referral (requires name parameter)", @OA\Schema(type="string")),
     *     @OA\Parameter(name="dister_id", in="query", required=false, description="Filter by dister ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="country", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="tax_id", in="query", required=false, description="Filter by tax ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", required=false, description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"active", "paused", "inactive"})),
     *     @OA\Parameter(name="balance_min", in="query", required=false, description="Filter by minimum balance", @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="balance_max", in="query", required=false, description="Filter by maximum balance", @OA\Schema(type="number", format="float")),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number (pagination)", @OA\Schema(type="integer", default=1)),
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
     *                     @OA\Property(property="referral_code", type="string", example="REF123456789", nullable=true),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="sunshine@garden.ge"),
     *                     @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}),
 *                     @OA\Property(property="balance", type="number", format="float", example=150.00, nullable=true),
 *                     @OA\Property(property="formatted_balance", type="string", example="150.00 ₾", nullable=true),
 *                     @OA\Property(property="balance_comment", type="string", example="Balance adjustment comment", nullable=true, description="Comment explaining balance changes"),
 *                     @OA\Property(property="percents", type="number", format="float", example=15.50, nullable=true),
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
     *                             @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *                             @OA\Property(property="index", type="integer", example=1)
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
        $query = Garden::with(['city', 'countryData', 'images']);

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
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->query('name') . '%')
                  ->orWhere('referral_code', 'like', '%' . $request->query('name') . '%');
            });
        }

        if ($request->filled('address')) {
            $query->where('address', 'like', '%' . $request->query('address') . '%');
        }

        if ($request->filled('dister_id')) {
            $dister = \App\Models\Dister::find($request->query('dister_id'));
            if ($dister && $dister->gardens) {
                $query->whereIn('id', $dister->gardens);
            } else {
                // If dister not found or has no gardens, return empty result
                $query->whereRaw('1 = 0');
            }
        }
        if ($request->filled('country')) {
            $query->where('country_id', $request->query('country'));
        }
        if ($request->filled('country_id')) {
            $query->where('country_id', $request->query('country_id'));
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
        $page = $request->query('page', 1);
        $gardens = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
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
 *             @OA\Property(property="balance_comment", type="string", example="Balance adjustment comment", nullable=true, description="Comment explaining balance changes"),
 *             @OA\Property(property="percents", type="number", format="float", example=15.50, nullable=true),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="city", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Tbilisi"),
     *                 @OA\Property(property="region", type="string", example="Tbilisi")
     *             ),
     *             @OA\Property(property="country", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Georgia"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00)
     *             ),
     *             @OA\Property(property="images", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Main Entrance"),
     *                     @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *                     @OA\Property(property="index", type="integer", example=1)
     *                 )
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
    public function show($garden)
    {
        // Restrict dister to only their gardens
        if (request()->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = request()->user()->gardens ?? [];
            if (!in_array((int)$garden, $allowedGardenIds, true)) {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }

        $garden = Garden::with(['city', 'countryData', 'images'])->findOrFail($garden);
        $garden->makeVisible('referral_code');
        return $garden;
    }

    /**
     * @OA\Get(
     *     path="/api/gardens/export",
     *     operationId="exportGardens",
     *     tags={"Gardens"},
     *     summary="Export gardens to Excel",
     *     description="Download an Excel report of gardens with full information. Optionally filter by garden IDs, country, or other filters. If dister is logged in, export is automatically restricted to their assigned gardens.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="ids", in="query", required=false, description="Comma-separated garden IDs or multiple ids[] query params", @OA\Schema(type="string", example="37,42")),
     *     @OA\Parameter(name="country", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="country_id", in="query", required=false, description="Filter by country ID (alternative parameter)", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by garden name or referral code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="address", in="query", required=false, description="Filter by address", @OA\Schema(type="string")),
     *     @OA\Parameter(name="tax_id", in="query", required=false, description="Filter by tax ID", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="email", in="query", required=false, description="Filter by email", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"active", "paused", "inactive"})),
     *     @OA\Parameter(name="balance_min", in="query", required=false, description="Filter by minimum balance", @OA\Schema(type="number")),
     *     @OA\Parameter(name="balance_max", in="query", required=false, description="Filter by maximum balance", @OA\Schema(type="number")),
     *     @OA\Response(response=200, description="Excel file"),
     *     @OA\Response(
     *         response=404,
     *         description="Garden IDs not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Some garden IDs do not exist: 8,9"),
     *             @OA\Property(property="non_existent_ids", type="array", @OA\Items(type="integer"), example={8,9}),
     *             @OA\Property(property="existing_ids", type="array", @OA\Items(type="integer"), example={4,5,6})
     *         )
     *     )
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

        // Collect filter parameters
        $filters = [
            'name' => $request->query('name'),
            'address' => $request->query('address'),
            'country' => $request->query('country') ?: $request->query('country_id'),
            'tax_id' => $request->query('tax_id'),
            'phone' => $request->query('phone'),
            'email' => $request->query('email'),
            'status' => $request->query('status'),
            'balance_min' => $request->query('balance_min'),
            'balance_max' => $request->query('balance_max'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

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
            // If no specific IDs requested, export all available gardens
            if (is_array($allowedIds)) {
                $finalIds = $allowedIds;
            } else {
                // For non-dister users, export all gardens (empty array means all)
                $finalIds = [];
            }
        }

        // Check if any of the requested IDs exist
        if (!empty($requestedIds)) {
            $existingIds = Garden::whereIn('id', $requestedIds)->pluck('id')->toArray();
            $nonExistentIds = array_diff($requestedIds, $existingIds);
            
            if (!empty($nonExistentIds)) {
                return response()->json([
                    'message' => 'Some garden IDs do not exist: ' . implode(', ', $nonExistentIds),
                    'non_existent_ids' => $nonExistentIds,
                    'existing_ids' => $existingIds
                ], 404);
            }
        }

        return Excel::download(new GardensExport($finalIds, $filters), 'gardens.xlsx');
    }

    /**
     * @OA\Post(
     *     path="/api/gardens",
     *     operationId="createGarden",
     *     tags={"Gardens"},
     *     summary="Create a new garden",
     *     description="Create a new garden with the provided information",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "address", "tax_id", "city_id", "phone", "email", "password"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="New Garden", description="Garden name"),
     *             @OA\Property(property="address", type="string", maxLength=255, example="456 Oak Avenue", description="Garden address"),
     *             @OA\Property(property="tax_id", type="string", maxLength=255, example="987654321", description="Tax identification number"),
     *             @OA\Property(property="city_id", type="integer", example=1, description="ID of the associated city"),
     *             @OA\Property(property="country_id", type="integer", example=1, nullable=true, description="Optional country ID"),
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="newgarden@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="password123", description="Garden access password"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true, description="Optional referral code"),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "paused", "inactive"}, nullable=true, description="Garden status (defaults to active)"),
 *             @OA\Property(property="balance", type="number", format="float", example=100.00, nullable=true, description="Optional garden balance"),
 *             @OA\Property(property="balance_comment", type="string", maxLength=1000, example="Initial balance comment", nullable=true, description="Comment explaining balance changes"),
 *             @OA\Property(property="percents", type="number", format="float", example=15.50, nullable=true, description="Optional garden percentage (0-100)")
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
 *                 @OA\Property(property="balance_comment", type="string", example="Initial balance comment", nullable=true),
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
            'country_id' => 'nullable|exists:countries,id',
            'phone' => 'required|string|max:255',
            'email' => 'required|email|unique:gardens,email|unique:users,email',
            'password' => 'required|string|min:6',
            'referral' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,paused,inactive',
            'balance' => 'nullable|numeric|min:0|max:9999999.99',
            'balance_comment' => 'nullable|string|max:1000',
            'percents' => 'nullable|numeric|min:0|max:100.00',
        ]);

        // Create user for the garden
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
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
                'phone' => $user->phone,
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
     *             @OA\Property(property="country_id", type="integer", example=1, nullable=true, description="Optional country ID"),
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599111222", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123", description="Garden access password"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true, description="Optional referral code"),
     *             @OA\Property(property="status", type="string", example="paused", enum={"active", "paused", "inactive"}, nullable=true, description="Garden status"),
 *             @OA\Property(property="balance", type="number", format="float", example=200.00, nullable=true, description="Optional garden balance"),
 *             @OA\Property(property="balance_comment", type="string", maxLength=1000, example="Updated balance comment", nullable=true, description="Comment explaining balance changes"),
 *             @OA\Property(property="percents", type="number", format="float", example=15.50, nullable=true, description="Optional garden percentage (0-100)")
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
     *             @OA\Property(property="country_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="phone", type="string", example="+995599111222"),
     *             @OA\Property(property="email", type="string", example="updated@garden.ge"),
     *             @OA\Property(property="referral", type="string", example="REF123", nullable=true),
     *             @OA\Property(property="status", type="string", example="paused", enum={"active", "paused", "inactive"}),
     *             @OA\Property(property="percents", type="number", format="float", example=15.50, nullable=true),
 *             @OA\Property(property="balance", type="number", format="float", example=200.00, nullable=true),
 *             @OA\Property(property="formatted_balance", type="string", example="200.00 ₾", nullable=true),
 *             @OA\Property(property="balance_comment", type="string", example="Updated balance comment", nullable=true),
 *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="city", type="object",
     *                 @OA\Property(property="id", type="integer", example=2),
     *                 @OA\Property(property="name", type="string", example="Tbilisi"),
     *                 @OA\Property(property="region", type="string", example="Tbilisi")
     *             ),
     *             @OA\Property(property="country", type="object", nullable=true,
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Georgia"),
     *                 @OA\Property(property="tariff", type="number", format="float", example=0.00)
     *             ),
     *             @OA\Property(property="images", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Main Entrance"),
     *                     @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *                     @OA\Property(property="index", type="integer", example=1)
     *                 )
     *             )
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
     *                 @OA\Property(property="percents", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $garden)
    {
        $garden = Garden::findOrFail($garden);


        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string|max:255',
            'tax_id' => 'sometimes|required|string|max:255',
            'city_id' => 'sometimes|required|exists:cities,id',
            'country_id' => 'sometimes|required|exists:countries,id',
            'phone' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:gardens,email,' . $garden->id,
            'password' => 'sometimes|required|string|min:6',
            'referral' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:active,paused,inactive',
            'balance' => 'nullable|numeric|min:0|max:9999999.99',
            'balance_comment' => 'nullable|string|max:1000',
            'percents' => 'nullable|numeric|min:0|max:100.00',
        ]);

        // Hash the password if it's being updated
        if (isset($validated['password'])) {
            $validated['password'] = bcrypt($validated['password']);
        }

        $garden->update($validated);
        
        // Load relationships and make referral_code visible
        $garden->load(['city', 'countryData', 'images']);
        $garden->makeVisible('referral_code');

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
    public function destroy($garden)
    {
        $garden = Garden::findOrFail($garden);
        
        // Find and delete the associated user
        $user = \App\Models\User::where('email', $garden->email)->where('type', 'garden')->first();
        if ($user) {
            $user->delete();
        }
        
        $garden->delete();

        return response()->json(['message' => 'Garden and associated user deleted']);
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

        // Update garden with new referral code
        $garden->update([
            'referral_code' => $newReferralCode,
        ]);

        // Remove this garden ID from ALL existing disters' gardens array
        // This ensures each garden has only one dister (one-to-one relationship)
        $allDisters = \App\Models\Dister::whereJsonContains('gardens', $garden->id)->get();
        foreach ($allDisters as $existingDister) {
            $existingGardens = $existingDister->gardens ?? [];
            // Filter out the current garden ID and reindex array
            $existingGardens = array_values(array_filter($existingGardens, function($gardenId) use ($garden) {
                return $gardenId != $garden->id;
            }));
            $existingDister->update(['gardens' => $existingGardens]);
        }

        // Update new dister's gardens array to include this garden
        $disterGardens = $dister->gardens ?? [];
        if (!in_array($garden->id, $disterGardens)) {
            $disterGardens[] = $garden->id;
            $dister->update(['gardens' => $disterGardens]);
        }

        // Get the dister data using the accessor
        $gardenDister = $garden->dister;

        return response()->json([
            'id' => $garden->id,
            'name' => $garden->name,
            'dister_id' => $dister->id,
            'referral_code' => $garden->referral_code,
            'updated_at' => $garden->updated_at,
            'dister' => $gardenDister ? [
                'id' => $gardenDister->id,
                'name' => $gardenDister->name,
                'email' => $gardenDister->email,
            ] : null,
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

    /**
     * Update garden balance and balance comment
     *
     * @OA\Patch(
     *     path="/api/gardens/{id}/balance",
     *     operationId="updateGardenBalance",
     *     tags={"Gardens"},
     *     summary="Update garden balance and balance comment",
     *     description="Update the balance and balance_comment fields of a specific garden",
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
     *             required={"balance"},
     *             @OA\Property(property="balance", type="number", format="float", example=250.50, description="New garden balance"),
     *             @OA\Property(property="balance_comment", type="string", maxLength=1000, example="Balance adjustment due to payment", nullable=true, description="Comment explaining balance changes (use empty string to clear comment)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden balance updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Garden balance updated successfully"),
     *             @OA\Property(property="garden", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                 @OA\Property(property="balance", type="number", format="float", example=250.50),
     *                 @OA\Property(property="formatted_balance", type="string", example="250.50 ₾"),
     *                 @OA\Property(property="balance_comment", type="string", example="Balance adjustment due to payment", nullable=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden not found")
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
     *                 @OA\Property(property="balance", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="balance_comment", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateBalance(Request $request, $id)
    {
        $validated = $request->validate([
            'balance' => 'required|numeric|min:0|max:9999999.99',
            'balance_comment' => 'nullable|string|max:1000',
        ]);

        $garden = Garden::findOrFail($id);

        // Check if user has access to this garden
        if ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            if (!in_array((int)$id, $allowedGardenIds, true)) {
                return response()->json(['message' => 'Garden not found or access denied'], 404);
            }
        }

        // Store the old balance for comparison
        $oldBalance = $garden->balance;
        
        // Update the balance and balance_comment fields
        $garden->update([
            'balance' => $validated['balance'],
            'balance_comment' => $validated['balance_comment'] === '' ? null : $validated['balance_comment']
        ]);

        // Create a payment record for the balance change
        // $balanceChange = $validated['balance'] - $oldBalance;
        
        if ($validated['balance'] ) {
            // Generate a unique transaction number for the balance change
            $transactionNumber = 'GARDEN_BALANCE_' . $garden->id . '_' . time();
            
            // Create payment record
            Payment::create([
                'transaction_number' => $transactionNumber,
                'transaction_number_bank' => null,
                'card_number' => 'GARDEN_BALANCE_UPDATE',
                'card_id' => null, // No specific card for garden balance updates
                'amount' => abs($validated['balance']), // Use absolute value of balance change
                'currency' => 'GEL', // Default currency
                'comment' => $validated['balance_comment'] ?? 'Garden balance updated',
                'type' => 'garden_balance',
            ]);
        }

        return response()->json([
            'message' => 'Garden balance updated successfully',
            'garden' => [
                'id' => $garden->id,
                'name' => $garden->name,
                'balance' => $garden->balance,
                'formatted_balance' => $garden->formatted_balance,
                'balance_comment' => $garden->balance_comment,
                'updated_at' => $garden->updated_at,
            ],
            'balance_change' => $validated['balance'],
            'old_balance' => $oldBalance
        ], 200);
    }

    /**
     * @OA\Post(
     *     path="/api/gardens/send-otp",
     *     operationId="sendGardenOtp",
     *     tags={"Gardens"},
     *     summary="Send OTP to garden email",
     *     description="Send a 6-digit OTP code to the garden's email address for verification",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="garden@example.com", description="Garden email address")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="OTP sent to email"),
     *             @OA\Property(property="email", type="string", example="garden@example.com")
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
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function sendOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $email = $validated['email'];

        // Create OTP
        $otp = GardenOtp::createOtp($email);

        // Send OTP via Mail service
        $mailService = new GardenMailService();
        $mailResult = $mailService->sendOtp($email, $otp->otp);
        
        if (!$mailResult['success']) {
            \Log::error('Failed to send garden OTP email: ' . $mailResult['message']);
        }

        return response()->json([
            'message' => 'OTP sent to email',
            'email' => $email
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/gardens/verify-otp",
     *     operationId="verifyGardenOtp",
     *     tags={"Gardens"},
     *     summary="Verify garden OTP",
     *     description="Verify the OTP code sent to garden email and mark email as verified",
     *     security={},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "otp"},
     *             @OA\Property(property="email", type="string", format="email", example="garden@example.com", description="Garden email address"),
     *             @OA\Property(property="otp", type="string", example="123456", description="6-digit OTP code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP verified successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Email verified successfully"),
     *             @OA\Property(property="email", type="string", example="garden@example.com"),
     *             @OA\Property(property="verified", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid OTP",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid or expired OTP"),
     *             @OA\Property(property="verified", type="boolean", example=false)
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
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="otp", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'otp' => 'required|string|size:6',
        ]);

        $email = $validated['email'];
        $otpCode = $validated['otp'];

        // Find valid OTP
        $otp = GardenOtp::where('email', $email)
            ->where('otp', $otpCode)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otp) {
            return response()->json([
                'message' => 'Invalid or expired OTP',
                'verified' => false
            ], 400);
        }

        // Mark OTP as used
        $otp->update(['used' => true]);

        return response()->json([
            'message' => 'Email verified successfully',
            'email' => $email,
            'verified' => true
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/gardens/{id}/referred",
     *     operationId="getReferredGardens",
     *     tags={"Gardens"},
     *     summary="Get gardens referred by a specific garden",
     *     description="Retrieve all gardens that have been referred by the specified garden ID",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
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
     *                     @OA\Property(property="name", type="string", example="Referred Garden"),
     *                     @OA\Property(property="email", type="string", example="referred@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="address", type="string", example="123 Main St"),
     *                     @OA\Property(property="tax_id", type="string", example="123456789"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="balance", type="number", format="float", example=100.50),
     *                     @OA\Property(property="percents", type="number", format="float", example=15.00),
     *                     @OA\Property(property="referral_code", type="string", example="REF123ABC"),
     *                     @OA\Property(property="referral", type="string", example="REF456DEF"),
     *                     @OA\Property(property="city", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Tbilisi")
     *                     ),
     *                     @OA\Property(property="countryData", type="object", nullable=true,
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Georgia"),
     *                         @OA\Property(property="phone_index", type="string", example="+995")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
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
    public function getReferredGardens(Request $request, $id)
    {
        // Verify the garden exists
        $referringGarden = Garden::findOrFail($id);
        
        // Get the referral code of the referring garden
        $referralCode = $referringGarden->referral_code;
        
        if (!$referralCode) {
            return response()->json([
                'message' => 'This garden does not have a referral code',
                'data' => [],
                'total' => 0
            ]);
        }
        
        // Find all gardens that have this garden's referral code
        $query = Garden::with(['city', 'countryData'])
            ->where('referral', $referralCode);
        
        $perPage = $request->query('per_page', 15);
        $referredGardens = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        return $referredGardens;
    }
}
