<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\CardOtp;
use App\Services\SmsService;
use App\Rules\LicenseValueRule;
use App\Exports\CardsExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * @OA\Tag(
 *     name="Cards",
 *     description="API Endpoints for managing child cards"
 * )
 */
class CardController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/cards",
     *     operationId="getCards",
     *     tags={"Cards"},
     *     summary="Get all cards",
     *     description="Retrieve a paginated list of all child cards with their associated group and person type information. Supports filtering by search (child's or parent's name fields), phone, status, group_id, person_type_id, parent_code.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in child's and parent's name fields", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"pending","active","inactive"})),
     *     @OA\Parameter(name="group_id", in="query", required=false, description="Filter by group ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="person_type_id", in="query", required=false, description="Filter by person type ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="parent_code", in="query", required=false, description="Filter by parent code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="parent_verification", in="query", required=false, description="Filter by parent verification status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="license_type", in="query", required=false, description="Filter by license type", @OA\Schema(type="string", enum={"boolean", "date"})),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number", @OA\Schema(type="integer", default=1)),
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
     *                     @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                     @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                     @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}),
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="person_type_id", type="integer", example=1, nullable=true),
     *                     @OA\Property(property="parent_code", type="string", example="K9#mP2", nullable=true),
     *                     @OA\Property(property="parent_verification", type="boolean", example=false, nullable=true),
     *                     @OA\Property(property="license", type="object", nullable=true,
     *                         @OA\Property(property="type", type="string", example="boolean"),
     *                         @OA\Property(property="value", example=true, description="Boolean value (true/false)")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="group", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Group A")
     *                     ),
     *                     @OA\Property(property="personType", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="ბავშვი")
     *                     ),
     *                     @OA\Property(property="parents", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="ნინო დავითაშვილი"),
     *                             @OA\Property(property="phone", type="string", example="+995599123456"),
     *                             @OA\Property(property="email", type="string", example="nino@example.com"),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time")
     *                         )
     *                     ),
     *                     @OA\Property(property="people", type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="id", type="integer", example=1),
     *                             @OA\Property(property="name", type="string", example="გიორგი დავითაშვილი"),
     *                             @OA\Property(property="phone", type="string", example="+995599123456"),
     *                             @OA\Property(property="email", type="string", example="giorgi@example.com"),
     *                             @OA\Property(property="relationship", type="string", example="მამა"),
     *                             @OA\Property(property="created_at", type="string", format="date-time"),
     *                             @OA\Property(property="updated_at", type="string", format="date-time")
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
    // ყველა ბარათის წამოღება
    public function index(Request $request)
    {
        $query = Card::with(['group', 'personType', 'parents', 'people']);
        
        // Filter by garden_id if authenticated user is a garden user
        if ($request->user() && $request->user()->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $request->user()->email)->first();
            if ($garden) {
                $query->whereHas('group', function ($q) use ($garden) {
                    $q->where('garden_id', $garden->id);
                });
            }
        } elseif ($request->user() instanceof \App\Models\User && $request->user()->type === 'dister') {
            $dister = \App\Models\Dister::where('email', $request->user()->email)->first();
            $allowedGardenIds = $dister->gardens ?? [];
            if (!empty($allowedGardenIds)) {
                $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                    $q->whereIn('garden_id', $allowedGardenIds);
                });
            } else {
                // Return empty result if no gardens assigned
                return $query->whereRaw('1 = 0')->paginate($request->query('per_page', 15));
            }
        }
        
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('child_first_name', 'like', "%$search%")
                    ->orWhere('child_last_name', 'like', "%$search%")
                    ->orWhere('parent_name', 'like', "%$search%")
                ;
            });
        }
        if ($request->filled('phone')) {
            $query->where('phone', 'like', '%' . $request->query('phone') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('group_id')) {
            $query->where('group_id', $request->query('group_id'));
        }
        if ($request->filled('person_type_id')) {
            $query->where('person_type_id', $request->query('person_type_id'));
        }
        if ($request->filled('parent_code')) {
            $query->where('parent_code', $request->query('parent_code'));
        }
        if ($request->filled('parent_verification')) {
            $query->where('parent_verification', $request->query('parent_verification'));
        }
        if ($request->filled('license_type')) {
            $query->whereJsonContains('license->type', $request->query('license_type'));
        }
        $perPage = $request->query('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * @OA\Get(
     *     path="/api/cards/{id}",
     *     operationId="getCard",
     *     tags={"Cards"},
     *     summary="Get a specific card",
     *     description="Retrieve detailed information about a specific child card",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *             @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="parent_code", type="string", example="K9#mP2", nullable=true),
     *             @OA\Property(property="parent_verification", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="license", type="object", nullable=true,
     *                 @OA\Property(property="type", type="string", example="boolean"),
     *                 @OA\Property(property="value", example=true)
     *             ),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="group",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Group A")
     *             ),
     *             @OA\Property(property="personType", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="ბავშვი")
     *             ),
     *             @OA\Property(property="parents", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="ნინო დავითაშვილი"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="nino@example.com"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="people", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="გიორგი დავითაშვილი"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="giorgi@example.com"),
     *                     @OA\Property(property="relationship", type="string", example="მამა"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
     *         )
     *     )
     * )
     */
    // ერთი ბარათის დეტალები
    public function show(Request $request, $id)
    {
        $query = Card::with([
            'group', 
            'personType', 
            'parents' => function($query) {
                $query->select('id', 'first_name', 'last_name', 'status', 'phone', 'code', 'group_id', 'card_id', 'created_at', 'updated_at');
            },
            'people' => function($query) {
                $query->with('personType:id,name');
            }
        ]);
        
        // Filter by garden if authenticated garden user or dister user
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            if (!empty($allowedGardenIds)) {
                $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                    $q->whereIn('garden_id', $allowedGardenIds);
                });
            } else {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        }
        
        $card = $query->findOrFail($id);
        
        // Format the response to include full names for parents and people
        $card->parents = $card->parents->map(function($parent) {
            $parent->full_name = $parent->first_name . ' ' . $parent->last_name;
            return $parent;
        });
        
        $card->people = $card->people->map(function($person) {
            $person->full_name = $person->first_name . ' ' . $person->last_name;
            return $person;
        });
        
        return $card;
    }

    /**
     * @OA\Post(
     *     path="/api/cards",
     *     operationId="createCard",
     *     tags={"Cards"},
     *     summary="Create a new card",
     *     description="Create a new child card with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"child_first_name", "child_last_name", "parent_name", "phone", "group_id"},
     *             @OA\Property(property="child_first_name", type="string", maxLength=255, example="Giorgi", description="Child's first name"),
     *             @OA\Property(property="child_last_name", type="string", maxLength=255, example="Davitashvili", description="Child's last name"),
     *             @OA\Property(property="parent_name", type="string", maxLength=255, example="Nino Davitashvili", description="Parent's full name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Contact phone number"),
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}, description="Card status"),
     *             @OA\Property(property="group_id", type="integer", example=1, description="ID of the associated garden group"),
     *             @OA\Property(property="person_type_id", type="integer", example=1, nullable=true, description="Person type ID from person-types"),
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="K9M2P5", nullable=true, description="Optional parent access code (auto-generated if not provided)"),
     *             @OA\Property(property="parent_verification", type="boolean", example=false, nullable=true, description="Parent verification status"),
     *             @OA\Property(property="license", type="object", nullable=true, description="License information",
     *                 @OA\Property(property="type", type="string", example="boolean", enum={"boolean", "date"}, description="License type"),
     *                 @OA\Property(property="value", description="License value (boolean for boolean type, date string for date type)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=2),
     *             @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *             @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="person_type_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="parent_code", type="string", example="K9#mP2", nullable=true),
     *             @OA\Property(property="parent_verification", type="boolean", example=false, nullable=true),
     *             @OA\Property(property="license", type="object", nullable=true,
     *                 @OA\Property(property="type", type="string", example="boolean"),
     *                 @OA\Property(property="value", example=true)
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
     *                 @OA\Property(property="child_first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="child_last_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="person_type_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    // ბარათის შექმნა
    public function store(Request $request)
    {
        $validated = $request->validate([
            'child_first_name' => 'required|string|max:255',
            'child_last_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'status' => 'nullable|string|in:pending,active,inactive',
            'group_id' => 'required|exists:garden_groups,id',
            'person_type_id' => 'nullable|exists:person_types,id',
            'parent_code' => 'nullable|string|max:255',
            'parent_verification' => 'nullable|boolean',
            'license' => 'nullable|array',
            'license.type' => 'nullable|string|in:boolean,date',
            'license.value' => ['nullable', new LicenseValueRule],
        ]);

        if (!isset($validated['status'])) {
            $validated['status'] = 'pending';
        }

        // Set default values for parent_verification and license
        if (!isset($validated['parent_verification'])) {
            $validated['parent_verification'] = false;
        }
        
        if (!isset($validated['license'])) {
            $validated['license'] = [
                'type' => 'boolean',
                'value' => false
            ];
        }

        // If authenticated user is a garden user, validate that the group belongs to their garden
        if ($request->user() && $request->user()->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $request->user()->email)->first();
            if ($garden) {
                $gardenId = $garden->id;
                $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                    ->where('garden_id', $gardenId)
                    ->first();
                
                if (!$group) {
                    return response()->json(['message' => 'Group does not belong to your garden'], 403);
                }
            }
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                ->whereIn('garden_id', $allowedGardenIds)
                ->first();
            if (!$group) {
                return response()->json(['message' => 'Group does not belong to your allowed gardens'], 403);
            }
        }

        $card = Card::create($validated);
        $card->load(['group', 'personType', 'parents', 'people']);

        return response()->json($card, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/cards/{id}",
     *     operationId="updateCard",
     *     tags={"Cards"},
     *     summary="Update a card",
     *     description="Update an existing child card with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="child_first_name", type="string", maxLength=255, example="Updated Giorgi", description="Child's first name"),
     *             @OA\Property(property="child_last_name", type="string", maxLength=255, example="Updated Davitashvili", description="Child's last name"),
     *             @OA\Property(property="parent_name", type="string", maxLength=255, example="Updated Nino Davitashvili", description="Parent's full name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="status", type="string", example="inactive", enum={"pending", "active", "inactive"}, description="Card status"),
     *             @OA\Property(property="group_id", type="integer", example=2, description="ID of the associated garden group"),
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="K9#mP2", nullable=true, description="Optional parent access code (auto-generated if not provided)"),
     *             @OA\Property(property="parent_verification", type="boolean", example=true, nullable=true, description="Parent verification status"),
     *             @OA\Property(property="license", type="object", nullable=true, description="License information",
     *                 @OA\Property(property="type", type="string", example="date", enum={"boolean", "date"}, description="License type"),
     *                 @OA\Property(property="value", example="2025-12-31", description="License value (boolean for boolean type, date string for date type)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="child_first_name", type="string", example="Updated Giorgi"),
     *             @OA\Property(property="child_last_name", type="string", example="Updated Davitashvili"),
     *             @OA\Property(property="parent_name", type="string", example="Updated Nino Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599654321"),
     *             @OA\Property(property="status", type="string", example="inactive"),
     *             @OA\Property(property="group_id", type="integer", example=2),
     *             @OA\Property(property="parent_code", type="string", example="K9#mP2", nullable=true),
     *             @OA\Property(property="parent_verification", type="boolean", example=true, nullable=true),
     *             @OA\Property(property="license", type="object", nullable=true,
     *                 @OA\Property(property="type", type="string", example="date"),
     *                 @OA\Property(property="value", example="2025-12-31")
     *             ),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
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
     *                 @OA\Property(property="child_first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="child_last_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    // განახლება
    public function update(Request $request, $id)
    {
        $query = Card::query();
        
        // Filter by garden if authenticated garden user or dister user
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }
        
        $card = $query->findOrFail($id);

        $validated = $request->validate([
            'child_first_name' => 'sometimes|required|string|max:255',
            'child_last_name' => 'sometimes|required|string|max:255',
            'parent_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'status' => 'sometimes|required|string|in:pending,active,inactive',
            'group_id' => 'sometimes|required|exists:garden_groups,id',
            'parent_code' => 'nullable|string|max:255',
            'parent_verification' => 'nullable|boolean',
            'license' => 'nullable|array',
            'license.type' => 'nullable|string|in:boolean,date',
            'license.value' => ['nullable', new LicenseValueRule],
        ]);

        // If authenticated user is a garden user and group_id is being updated, validate that the group belongs to their garden
        if ($request->user() && $request->user()->garden_id && isset($validated['group_id'])) {
            $gardenId = $request->user()->garden_id;
            $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                ->where('garden_id', $gardenId)
                ->first();
            
            if (!$group) {
                return response()->json(['message' => 'Group does not belong to your garden'], 403);
            }
        } elseif ($request->user() instanceof \App\Models\Dister && isset($validated['group_id'])) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                ->whereIn('garden_id', $allowedGardenIds)
                ->first();
            if (!$group) {
                return response()->json(['message' => 'Group does not belong to your allowed gardens'], 403);
            }
        }

        $card->update($validated);
        $card->load(['group', 'personType', 'parents', 'people']);

        return response()->json($card);
    }

    /**
     * @OA\Patch(
     *     path="/api/cards/{id}/parent-verification",
     *     operationId="updateCardParentVerification",
     *     tags={"Cards"},
     *     summary="Update parent verification status",
     *     description="Update only the parent verification status of a specific card",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"parent_verification"},
     *             @OA\Property(property="parent_verification", type="boolean", example=false, description="Parent verification status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Parent verification updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="parent_verification", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Parent verification updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
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
     *                 @OA\Property(property="parent_verification", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateParentVerification(Request $request, $id)
    {
        $validated = $request->validate([
            'parent_verification' => 'required|boolean',
        ]);

        $card = Card::findOrFail($id);
        $card->parent_verification = $validated['parent_verification'];
        $card->save();

        return response()->json([
            'id' => $card->id,
            'parent_verification' => $card->parent_verification,
            'message' => 'Parent verification updated successfully',
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/cards/{id}/license",
     *     operationId="updateCardLicense",
     *     tags={"Cards"},
     *     summary="Update license information",
     *     description="Update only the license information of a specific card",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"license"},
     *             @OA\Property(property="license", type="object", description="License information",
     *                 @OA\Property(property="type", type="string", example="boolean", enum={"boolean", "date"}, description="License type"),
     *                 @OA\Property(property="value", description="License value (boolean for boolean type, date string for date type)")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="License updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="license", type="object",
     *                 @OA\Property(property="type", type="string", example="boolean"),
     *                 @OA\Property(property="value", example=true)
     *             ),
     *             @OA\Property(property="message", type="string", example="License updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
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
     *                 @OA\Property(property="license", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="license.type", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="license.value", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateLicense(Request $request, $id)
    {
        $validated = $request->validate([
            'license' => 'required|array',
            'license.type' => 'required|string|in:boolean,date',
            'license.value' => ['required', new \App\Rules\LicenseValueRule],
        ]);

        $card = Card::findOrFail($id);
        $card->license = $validated['license'];
        $card->save();

        return response()->json([
            'id' => $card->id,
            'license' => $card->license,
            'message' => 'License updated successfully',
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/cards/{id}/status",
     *     operationId="updateCardStatus",
     *     tags={"Cards"},
     *     summary="Update card status",
     *     description="Update only the status of a specific card",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}, description="Card status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="message", type="string", example="Status updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
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
        $validated = $request->validate([
            'status' => 'required|string|in:pending,active,inactive',
        ]);

        $card = Card::findOrFail($id);
        $card->status = $validated['status'];
        $card->save();

        return response()->json([
            'id' => $card->id,
            'status' => $card->status,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/cards/{id}",
     *     operationId="deleteCard",
     *     tags={"Cards"},
     *     summary="Delete a card",
     *     description="Permanently delete a child card",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Card]")
     *         )
     *     )
     * )
     */
    // წაშლა
    public function destroy(Request $request, $id)
    {
        $query = Card::query();
        
        // Filter by garden if authenticated garden user or dister user
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }
        
        $card = $query->findOrFail($id);
        $card->deleted = true;
        $card->save();

        return response()->json(['message' => 'Card deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/{id}/regenerate-code",
     *     operationId="regenerateCardCode",
     *     tags={"Cards"},
     *     summary="Regenerate card code and reset parent verification",
     *     description="Generate a new random 6-character code for the specified card and set parent_verification to false",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code regenerated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card code regenerated successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="parent_code", type="string", example="X7K9M2"),
     *                 @OA\Property(property="parent_verification", type="boolean", example=false),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function regenerateCode(Request $request, $id)
    {
        // Enforce access for garden and dister users
        $query = Card::query();
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }
        $card = $query->findOrFail($id);
        
        // Generate new unique code
        do {
            $newCode = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        } while (Card::where('parent_code', $newCode)->where('id', '!=', $id)->exists());
        
        // Update card with new code and reset parent verification
        $card->parent_code = $newCode;
        $card->parent_verification = false;
        $card->save();
        
        return response()->json([
            'message' => 'Card code regenerated successfully',
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'parent_code' => $card->parent_code,
                'parent_verification' => $card->parent_verification,
                'updated_at' => $card->updated_at
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/{id}/restore",
     *     operationId="restoreCard",
     *     tags={"Cards"},
     *     summary="Restore deleted card",
     *     description="Restore a deleted card by setting deleted to false",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card restored successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card restored successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="deleted", type="boolean", example=false),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=404, description="Card not found")
     * )
     */
    public function restore(Request $request, $id)
    {
        $query = Card::query();
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }
        $card = $query->findOrFail($id);
        
        $card->deleted = false;
        $card->save();
        
        return response()->json([
            'message' => 'Card restored successfully',
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'deleted' => $card->deleted,
                'updated_at' => $card->updated_at
            ]
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/cards/bulk-delete",
     *     operationId="bulkDeleteCards",
     *     tags={"Cards"},
     *     summary="Delete multiple cards",
     *     description="Permanently delete multiple child cards by their IDs",
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
     *         description="Cards deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cards deleted"),
     *             @OA\Property(property="deleted_count", type="integer", example=3)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No valid IDs provided")
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

        $query = Card::whereIn('id', $ids);
        
        // Filter by garden if authenticated garden user or dister user
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }

        $deleted = $query->delete();

        return response()->json([
            'message' => 'Cards deleted',
            'deleted_count' => $deleted,
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/cards/{id}/image",
     *     operationId="uploadCardImage",
     *     tags={"Cards"},
     *     summary="Upload or replace card image",
     *     description="Upload a new image for the card. If an image already exists, it will be replaced.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *             @OA\Schema(
     *                 @OA\Property(
     *                     property="image",
     *                     type="string",
     *                     format="binary",
     *                     description="Image file"
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Image uploaded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="image_path", type="string", example="cards/12345.jpg")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function uploadImage(Request $request, $id)
    {
        $query = Card::query();
        
        // Filter by garden for authenticated garden or dister users
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }
        
        $card = $query->findOrFail($id);

        $request->validate([
            'image' => 'required|image|max:2048', // 2MB max
        ]);

        // წაშალე ძველი სურათი თუ არსებობს
        if ($card->image_path && \Storage::disk('public')->exists($card->image_path)) {
            \Storage::disk('public')->delete($card->image_path);
        }

        // ატვირთე ახალი სურათი
        $path = $request->file('image')->store('cards', 'public');
        $card->image_path = $path;
        $card->save();

        $fullUrl = asset('storage/' . $path);
        return response()->json(['image_path' => $fullUrl]);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/move-to-group",
     *     operationId="moveCardsToGroup",
     *     tags={"Cards"},
     *     summary="Move multiple cards to a different group",
     *     description="Move multiple cards to a different garden group by providing an array of card IDs and a new group ID",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_ids", "group_id"},
     *             @OA\Property(
     *                 property="card_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 example={1, 2, 3, 4, 5},
     *                 description="Array of card IDs to move"
     *             ),
     *             @OA\Property(
     *                 property="group_id",
     *                 type="integer",
     *                 example=2,
     *                 description="Target group ID"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Cards moved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cards moved to group successfully"),
     *             @OA\Property(property="moved_count", type="integer", example=5),
     *             @OA\Property(property="target_group_id", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No valid card IDs provided")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Group does not belong to user's garden",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Group does not belong to your garden")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Target group not found")
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
     *                 @OA\Property(property="card_ids", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function moveToGroup(Request $request)
    {
        $validated = $request->validate([
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'integer|exists:cards,id',
            'group_id' => 'required|integer|exists:garden_groups,id',
        ]);

        $cardIds = $validated['card_ids'];
        $groupId = $validated['group_id'];

        // Check if target group exists and belongs to user's garden
        $targetGroup = \App\Models\GardenGroup::findOrFail($groupId);
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            if ($targetGroup->garden_id != $gardenId) {
                return response()->json([
                    'message' => 'Group does not belong to your garden'
                ], 403);
            }
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            if (!in_array($targetGroup->garden_id, $allowedGardenIds, true)) {
                return response()->json([
                    'message' => 'Group does not belong to your allowed gardens'
                ], 403);
            }
        }

        // Get cards that belong to user's garden (if garden user)
        $query = Card::whereIn('id', $cardIds);
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            $query->whereHas('group', function ($q) use ($gardenId) {
                $q->where('garden_id', $gardenId);
            });
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            $query->whereHas('group', function ($q) use ($allowedGardenIds) {
                $q->whereIn('garden_id', $allowedGardenIds);
            });
        }

        $cards = $query->get();
        
        if ($cards->isEmpty()) {
            return response()->json([
                'message' => 'No valid cards found for your garden'
            ], 400);
        }

        // Update all cards to the new group
        $updatedCount = $query->update(['group_id' => $groupId]);

        return response()->json([
            'message' => 'Cards moved to group successfully',
            'moved_count' => $updatedCount,
            'target_group_id' => $groupId,
            'target_group_name' => $targetGroup->name
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/send-otp",
     *     operationId="sendCardOtp",
     *     tags={"Cards"},
     *     summary="Send OTP for card login",
     *     description="Send OTP to the phone number associated with a card",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="+995599123456", description="Phone number associated with the card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="OTP sent successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="OTP sent successfully"),
     *             @OA\Property(property="phone", type="string", example="+995599123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid phone number")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *
     *             )
     *         )
     *     )
     * )
     */
    public function sendOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',

        ]);

        // First verify the card exists with this phone number
        $card = Card::where('phone', $request->phone)->first();

        if (!$card) {
            return response()->json([
                'message' => 'Invalid phone number'
            ], 401);
        }

        // Generate and save OTP
        $otp = CardOtp::createOtp($request->phone);

        // Send SMS
        $smsService = new SmsService();
        $smsResult = $smsService->sendOtp($request->phone, $otp->otp);

        if (!$smsResult['success']) {
            // If SMS fails, delete the OTP and return error
            $otp->delete();
            return response()->json([
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully',
            'phone' => $request->phone
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/verify-otp",
     *     operationId="verifyCardOtp",
     *     tags={"Cards"},
     *     summary="Verify OTP and login",
     *     description="Verify OTP and return the complete card object if valid",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "otp"},
     *             @OA\Property(property="phone", type="string", example="+995599123456", description="Phone number associated with the card"),
     *             @OA\Property(property="otp", type="string", example="123456", description="6-digit OTP code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="parent_code", type="string", example="K9M2P5", nullable=true),
     *                 @OA\Property(property="image_path", type="string", example="cards/abc123.jpg", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A"),
     *                     @OA\Property(property="garden_id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="personType", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Parent")
     *                 ),
     *                 @OA\Property(property="parents", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="ნინო დავითაშვილი"),
     *                         @OA\Property(property="phone", type="string", example="+995599123456"),
     *                         @OA\Property(property="email", type="string", example="nino@example.com")
     *                     )
     *                 ),
     *                 @OA\Property(property="people", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="გიორგი დავითაშვილი"),
     *                         @OA\Property(property="phone", type="string", example="+995599123456"),
     *                         @OA\Property(property="email", type="string", example="giorgi@example.com"),
     *                         @OA\Property(property="relationship", type="string", example="მამა")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid OTP",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid or expired OTP")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="otp", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
            'otp' => 'required|string|size:6',
        ]);

        // Find the OTP record
        $otpRecord = CardOtp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 401);
        }

        // Mark OTP as used
        $otpRecord->update(['used' => true]);

        // Get the card data
        $card = Card::with(['group', 'personType', 'parents', 'people'])
            ->where('phone', $request->phone)
            ->first();

        if (!$card) {
            return response()->json([
                'message' => 'Card not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Login successful',
            'card' => $card
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/cards/login",
     *     operationId="cardLogin",
     *     tags={"Cards"},
     *     summary="Card login with phone number",
     *     description="Authenticate a card using phone number and automatically send OTP",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"phone", "parent_code"},
     *             @OA\Property(property="phone", type="string", example="+995599123456", description="Phone number associated with the card"),
     *             @OA\Property(property="parent_code", type="string", example="K9M2P5", description="Parent access code for the card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="parent_code", type="string", example="K9M2P5", nullable=true),
     *                 @OA\Property(property="image_path", type="string", example="cards/abc123.jpg", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A"),
     *                     @OA\Property(property="garden_id", type="integer", example=1)
     *                 ),
     *                 @OA\Property(property="personType", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Parent")
     *                 ),
     *                 @OA\Property(property="parents", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="ნინო დავითაშვილი"),
     *                         @OA\Property(property="phone", type="string", example="+995599123456"),
     *                         @OA\Property(property="email", type="string", example="nino@example.com")
     *                     )
     *                 ),
     *                 @OA\Property(property="people", type="array",
     *                     @OA\Items(
     *                         type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="გიორგი დავითაშვილი"),
     *                         @OA\Property(property="phone", type="string", example="+995599123456"),
     *                         @OA\Property(property="email", type="string", example="giorgi@example.com"),
     *                         @OA\Property(property="relationship", type="string", example="მამა")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid credentials",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid phone number or parent code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_code", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
        ]);

        $card = Card::with(['group', 'personType', 'parents', 'people'])
            ->where('phone', $request->phone)
            ->first();

        if (!$card) {
            return response()->json([
                'message' => 'Invalid phone number'
            ], 401);
        }

        // Automatically send OTP
        $otp = \App\Models\CardOtp::create([
            'card_id' => $card->id,
            'otp' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'expires_at' => now()->addMinutes(10),
        ]);

        // Send OTP via SMS
        $smsService = new \App\Services\SmsService();
        $smsService->sendSms($card->phone, "Your OTP code is: {$otp->otp}");

        return response()->json([
            'message' => 'OTP sent successfully',
            'card_id' => $card->id,
            'phone' => $card->phone
        ]);
    }
    
    /**
     * @OA\Get(
     *     path="/api/cards/export",
     *     operationId="exportCards",
     *     tags={"Cards"},
     *     summary="Export cards to Excel",
     *     description="Download an Excel report of cards. Optionally filter by card IDs. If dister is logged in, export is restricted to their assigned gardens.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="ids", in="query", required=false, description="Comma-separated card IDs or multiple ids[] query params", @OA\Schema(type="string", example="1,2,3")),
     *     @OA\Response(response=200, description="Excel file")
     * )
     */
    public function export(Request $request)
    {
        // Parse requested card IDs
        $requestedIds = [];
        $idsParam = $request->query('ids');
        if (is_string($idsParam)) {
            $requestedIds = array_values(array_filter(array_map('intval', explode(',', $idsParam))));
        } elseif (is_array($request->query('ids'))) {
            $requestedIds = array_values(array_filter(array_map('intval', (array) $request->query('ids'))));
        }

        // Allowed gardens for dister
        $allowedGardenIds = [];
        if ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = array_values(array_filter((array) ($request->user()->gardens ?? []), 'is_numeric'));
        } elseif ($request->user() && $request->user()->garden_id) {
            $allowedGardenIds = [(int) $request->user()->garden_id];
        }

        return Excel::download(new CardsExport($allowedGardenIds, $requestedIds), 'cards.xlsx');
    }
}
