<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\CardOtp;
use App\Models\People;
use App\Models\PeopleOtp;
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
     *     description="Retrieve a paginated list of all child cards with their associated group and person type information. Supports filtering by search (child's or parent's name fields), phone, status, group_id, person_type_id, parent_code, garden_id, country_id, city_id.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in child's and parent's name fields", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"pending","active","inactive"})),
     *     @OA\Parameter(name="group_id", in="query", required=false, description="Filter by group ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="garden_id", in="query", required=false, description="Filter by garden ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="country_id", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", required=false, description="Filter by city ID", @OA\Schema(type="integer")),
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
 *                     @OA\Property(property="comment", type="string", example="Special notes about this child", nullable=true, description="Additional comments about the card"),
 *                     @OA\Property(property="spam_comment", type="string", example="Spam reason comment", nullable=true, description="Comment explaining why the card was marked as spam"),
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
        $query = Card::with(['group.garden.countryData', 'personType', 'parents', 'people']);
        
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
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
            });
        }
        if ($request->filled('country_id')) {
            $query->whereHas('group.garden', function ($q) use ($request) {
                $q->where('country_id', $request->query('country_id'));
            });
        }
        if ($request->filled('city_id')) {
            $query->whereHas('group.garden', function ($q) use ($request) {
                $q->where('city_id', $request->query('city_id'));
            });
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
        
        // Include deleted cards that can be restored (within 20 days)
        $query->where(function($q) {
            $q->where('is_deleted', false)
              ->orWhere(function($subQ) {
                  $subQ->where('is_deleted', true)
                       ->where('deleted_at', '>=', now()->subDays(20));
              });
        });
        
        $perPage = $request->query('per_page', 15);
        $cards = $query->orderBy('created_at', 'desc')->paginate($perPage);
        
        // Add country tariff information and soft delete info to each card
        $cards->getCollection()->transform(function ($card) {
            if ($card->group && $card->group->garden && $card->group->garden->countryData) {
                $card->country_tariff = [
                    'tariff' => $card->group->garden->countryData->tariff,
                    'formatted_tariff' => $card->group->garden->countryData->formatted_tariff,
                    'currency' => $card->group->garden->countryData->currency,
                    'country_name' => $card->group->garden->countryData->name,
                    'phone_index' => $card->group->garden->countryData->phone_index,
                ];
            } else {
                $card->country_tariff = null;
            }
            
            // Add soft delete information
            $card->soft_delete_info = [
                'is_deleted' => $card->is_deleted,
                'deleted_at' => $card->deleted_at,
                'can_restore' => $card->canBeRestored(),
                'days_since_deletion' => $card->getDaysSinceDeletion(),
                'restore_until' => $card->is_deleted && $card->deleted_at ? 
                    $card->deleted_at->addDays(20)->toISOString() : null
            ];
            
            return $card;
        });
        
        return $cards;
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
 *             @OA\Property(property="comment", type="string", example="Special notes about this child", nullable=true, description="Additional comments about the card"),
 *             @OA\Property(property="spam_comment", type="string", example="Spam reason comment", nullable=true, description="Comment explaining why the card was marked as spam"),
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
            'group.garden.countryData', 
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
            $parent->full_name = ($parent->first_name ?? '') . ' ' . ($parent->last_name ?? '');
            return $parent;
        });
        
        $card->people = $card->people->map(function($person) {
            $person->full_name = $person->name;
            return $person;
        });
        
        // Add country tariff information
        if ($card->group && $card->group->garden && $card->group->garden->countryData) {
            $card->country_tariff = [
                'tariff' => $card->group->garden->countryData->tariff,
                'formatted_tariff' => $card->group->garden->countryData->formatted_tariff,
                'currency' => $card->group->garden->countryData->currency,
                'country_name' => $card->group->garden->countryData->name,
                'phone_index' => $card->group->garden->countryData->phone_index,
            ];
        } else {
            $card->country_tariff = null;
        }
        
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
 *             @OA\Property(property="comment", type="string", maxLength=1000, example="Special notes about this child", nullable=true, description="Additional comments about the card"),
 *             @OA\Property(property="spam_comment", type="string", maxLength=1000, example="Spam reason comment", nullable=true, description="Comment explaining why the card was marked as spam"),
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
 *             @OA\Property(property="comment", type="string", example="Special notes about this child", nullable=true),
 *             @OA\Property(property="spam_comment", type="string", example="Spam reason comment", nullable=true),
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
            'comment' => 'nullable|string|max:1000',
            'spam_comment' => 'nullable|string|max:1000',
            'license' => 'nullable|array',
            'license.type' => 'nullable|string|in:boolean,date',
            'license.value' => ['nullable', new LicenseValueRule],
        ]);

        if (!isset($validated['status'])) {
            $validated['status'] = 'pending';
        }

        // Check if phone number has been verified before
        $phoneVerified = false;
        
        // Check if there are any cards with this phone number that are already verified
        $existingVerifiedCard = Card::where('phone', $validated['phone'])
            ->where('parent_verification', true)
            ->where('spam', '!=', 1)
            ->first();
            
        if ($existingVerifiedCard) {
            $phoneVerified = true;
        } else {
            // Check if there are any used OTPs for this phone number (indicating previous verification)
            $usedOtp = CardOtp::where('phone', $validated['phone'])
                ->where('used', true)
                ->first();
                
            if (!$usedOtp) {
                // Also check PeopleOtp table
                $usedPeopleOtp = \App\Models\PeopleOtp::where('phone', $validated['phone'])
                    ->where('used', true)
                    ->first();
                    
                if ($usedPeopleOtp) {
                    $phoneVerified = true;
                }
            } else {
                $phoneVerified = true;
            }
        }

        // Set default values for parent_verification and license
        if (!isset($validated['parent_verification'])) {
            $validated['parent_verification'] = $phoneVerified;
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
 *             @OA\Property(property="comment", type="string", maxLength=1000, example="Updated notes about this child", nullable=true, description="Additional comments about the card"),
 *             @OA\Property(property="spam_comment", type="string", maxLength=1000, example="Updated spam reason comment", nullable=true, description="Comment explaining why the card was marked as spam"),
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
 *             @OA\Property(property="comment", type="string", example="Updated notes about this child", nullable=true),
 *             @OA\Property(property="spam_comment", type="string", example="Updated spam reason comment", nullable=true),
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
            'comment' => 'nullable|string|max:1000',
            'spam_comment' => 'nullable|string|max:1000',
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

        // Check if phone is being updated and if it already exists in other cards
        if (isset($validated['phone']) && $validated['phone'] !== $card->phone) {
            $existingCard = Card::where('phone', $validated['phone'])
                ->where('id', '!=', $card->id)
                ->where('is_deleted', false)
                ->first();
            
            if ($existingCard) {
                // Phone already exists in another card, set parent_verification to true
                $validated['parent_verification'] = true;
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
     *     description="Soft delete a child card. Card will be visible for 20 days with restore option.",
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
     *             @OA\Property(property="message", type="string", example="Card deleted successfully"),
     *             @OA\Property(property="can_restore", type="boolean", example=true),
     *             @OA\Property(property="restore_until", type="string", format="date-time", example="2025-10-07T22:55:44.000000Z")
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
        
        // Check if already deleted
        if ($card->is_deleted) {
            return response()->json(['message' => 'Card is already deleted'], 400);
        }
        
        // Soft delete the card
        $card->softDelete();

        return response()->json([
            'message' => 'Card deleted successfully',
            'can_restore' => true,
            'restore_until' => now()->addDays(20)->toISOString(),
        ]);
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
                'active_garden_image' => $card->active_garden_image,
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
        
        // Check if card is deleted
        if (!$card->is_deleted) {
            return response()->json(['message' => 'Card is not deleted'], 400);
        }
        
        // Check if card can be restored (within 20 days)
        if (!$card->canBeRestored()) {
            return response()->json([
                'message' => 'Card cannot be restored. 20-day restoration period has expired.',
                'deleted_at' => $card->deleted_at,
                'days_since_deletion' => $card->getDaysSinceDeletion()
            ], 400);
        }
        
        // Restore the card
        $card->restore();
        
        return response()->json([
            'message' => 'Card restored successfully',
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'is_deleted' => $card->is_deleted,
                'active_garden_image' => $card->active_garden_image,
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
     *     description="Soft delete multiple child cards by their IDs. Cards will be visible for 20 days with restore option.",
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
     *             @OA\Property(property="message", type="string", example="Cards deleted successfully"),
     *             @OA\Property(property="deleted_count", type="integer", example=3),
     *             @OA\Property(property="can_restore", type="boolean", example=true),
     *             @OA\Property(property="restore_until", type="string", format="date-time", example="2025-10-07T22:55:44.000000Z")
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

        // Get cards that are not already deleted
        $cards = $query->where('is_deleted', false)->get();
        
        if ($cards->isEmpty()) {
            return response()->json(['message' => 'No cards found to delete'], 400);
        }

        // Soft delete all cards
        $deletedCount = 0;
        foreach ($cards as $card) {
            $card->softDelete();
            $deletedCount++;
        }

        return response()->json([
            'message' => 'Cards deleted successfully',
            'deleted_count' => $deletedCount,
            'can_restore' => true,
            'restore_until' => now()->addDays(20)->toISOString(),
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

        // Check if phone exists in either cards or people
        $cards = Card::where('phone', $request->phone)->get();
        $people = People::where('phone', $request->phone)->get();

        if ($cards->isEmpty() && $people->isEmpty()) {
            return response()->json([
                'message' => 'Invalid phone number'
            ], 401);
        }

        // Generate and save OTP (use CardOtp for both)
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
     *     description="Verify OTP and return all cards associated with the phone number if valid",
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
     *             @OA\Property(property="cards", type="array", @OA\Items(
     *                 type="object",
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
                 *                 @OA\Property(property="image_url", type="string", example="http://localhost/storage/cards/abc123.jpg", nullable=true),
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
     *                 ),
     *                 @OA\Property(property="garden_images", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Main Entrance"),
     *                     @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *                     @OA\Property(property="image_url", type="string", example="http://localhost/storage/garden_images/abc123.jpg"),
     *                     @OA\Property(property="index", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="garden", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                     @OA\Property(property="address", type="string", example="123 Main Street"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="garden@example.com"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     * )
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
        \Log::info('=== VERIFY OTP START ===');
        \Log::info('Request data:', $request->all());

        try {
            $request->validate([
                'phone' => 'required|string|max:255',
                'otp' => 'required|string|size:6',
                'expo_token' => 'nullable|string|max:255',
            ]);
            \Log::info('Validation passed');
        } catch (\Exception $e) {
            \Log::error('Validation failed:', ['error' => $e->getMessage()]);
            throw $e;
        }

        // Find the OTP record
        \Log::info('Searching for OTP record', [
            'phone' => $request->phone,
            'otp' => $request->otp,
            'current_time' => now()
        ]);

        $otpRecord = CardOtp::where('phone', $request->phone)
            ->where('otp', $request->otp)
            ->where('used', false)
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpRecord) {
            \Log::warning('OTP verification failed - OTP not found or invalid', [
                'phone' => $request->phone,
                'otp' => $request->otp
            ]);

            // Log all OTPs for this phone for debugging
            $allOtps = CardOtp::where('phone', $request->phone)->get();
            \Log::info('All OTPs for this phone:', $allOtps->toArray());

            return response()->json([
                'message' => 'Invalid or expired OTP'
            ], 401);
        }

        \Log::info('OTP record found', ['otp_id' => $otpRecord->id]);

        // Mark OTP as used
        $otpRecord->update(['used' => true]);
        \Log::info('OTP marked as used');

        // Get all cards with this phone number
        \Log::info('Fetching cards for phone', ['phone' => $request->phone]);
        $cards = Card::with(['group.garden.images', 'personType', 'parents', 'people'])
            ->where('phone', $request->phone)
            ->where('spam', '!=', 1)
            ->get();

        \Log::info('Cards found', ['count' => $cards->count()]);
        if ($cards->isNotEmpty()) {
            \Log::info('Card IDs:', $cards->pluck('id')->toArray());
        }

        // Update parent verification status and expo_token for all cards associated with this phone number
        if ($cards->isNotEmpty()) {
            $updateData = ['parent_verification' => true];

            // If expo_token is provided, save it to all cards with this phone
            if ($request->expo_token) {
                $updateData['expo_token'] = $request->expo_token;
                \Log::info('Expo token provided', ['expo_token' => $request->expo_token]);
            }

            \Log::info('Updating cards with:', $updateData);
            Card::where('phone', $request->phone)
                ->where('spam', '!=', 1)
                ->update($updateData);
            \Log::info('Cards updated successfully');
        }

        // Get all people with this phone number
        \Log::info('Fetching people for phone', ['phone' => $request->phone]);
        $people = People::with(['personType', 'card.group.garden.images', 'card.personType', 'card.parents', 'card.people'])
            ->where('phone', $request->phone)
            ->get();

        \Log::info('People found', ['count' => $people->count()]);
        if ($people->isNotEmpty()) {
            \Log::info('People IDs:', $people->pluck('id')->toArray());
        }

        // Generate token for the first card or person
        $token = null;
        $userType = null;

        try {
            if ($cards->isNotEmpty()) {
                \Log::info('Generating token for card', ['card_id' => $cards->first()->id]);
                $token = $cards->first()->createToken('card-token')->plainTextToken;
                $userType = 'card';
                \Log::info('Token generated successfully for card');
            } elseif ($people->isNotEmpty()) {
                \Log::info('Generating token for person', ['person_id' => $people->first()->id]);
                $token = $people->first()->createToken('people-token')->plainTextToken;
                $userType = 'people';
                \Log::info('Token generated successfully for person');
            }
        } catch (\Exception $e) {
            \Log::error('Token generation failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        if ($cards->isEmpty() && $people->isEmpty()) {
            \Log::warning('No cards or people found for phone number', ['phone' => $request->phone]);
            return response()->json([
                'message' => 'No cards or people found for this phone number'
            ], 404);
        }

        // Transform cards to include garden images and garden info
        \Log::info('Transforming cards data');
        try {
            $transformedCards = $cards->map(function ($card) {
                \Log::info('Transforming card', ['card_id' => $card->id]);
                return [
                    'id' => $card->id,
                    'child_first_name' => $card->child_first_name,
                    'child_last_name' => $card->child_last_name,
                    'parent_name' => $card->parent_name,
                    'phone' => $card->phone,
                    'status' => $card->status,
                    'group_id' => $card->group_id,
                    'person_type_id' => $card->person_type_id,
                    'parent_code' => $card->parent_code,
                    'image_path' => $card->image_path,
                    'active_garden_image' => $card->active_garden_image,
                    'image_url' => $card->image_url,
                    'is_deleted' => $card->is_deleted,
                    'deleted_at' => $card->deleted_at,
                    'created_at' => $card->created_at,
                    'updated_at' => $card->updated_at,
                    'group' => $card->group,
                    'personType' => $card->personType,
                    'parents' => $card->parents,
                    'people' => $card->people,
                    'garden_images' => $card->garden_images,
                    'garden' => $card->garden,
                    'main_parent' => true
                ];
            });
            \Log::info('Cards transformation completed');
        } catch (\Exception $e) {
            \Log::error('Card transformation failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        // Transform people to include full card data
        \Log::info('Transforming people data');
        try {
            $transformedPeople = $people->map(function ($person) {
                \Log::info('Transforming person', ['person_id' => $person->id]);
                $baseData = [
                    'name' => $person->name,
                    'phone' => $person->phone,
                    'person_type_id' => $person->person_type_id,
                    'card_id' => $person->card_id,
                    'created_at' => $person->created_at,
                    'updated_at' => $person->updated_at,
                    'person_type' => $person->personType,
                    'main_parent' => false
                ];

                // If person has a card, merge card data directly into the base data
                if ($person->card) {
                    \Log::info('Person has card, merging card data', ['card_id' => $person->card->id]);
                    $cardData = [
                        'id' => $person->card->id,
                        'child_first_name' => $person->card->child_first_name,
                        'child_last_name' => $person->card->child_last_name,
                        'parent_name' => $person->card->parent_name,
                        'card_phone' => $person->card->phone,
                        'status' => $person->card->status,
                        'parent_code' => $person->card->parent_code,
                        'image_url' => $person->card->image_url,
                        'parent_verification' => $person->card->parent_verification,
                        'license' => $person->card->license,
                        'active_garden_image' => $person->card->active_garden_image,
                        'is_deleted' => $person->card->is_deleted,
                        'deleted_at' => $person->card->deleted_at,
                        'card_created_at' => $person->card->created_at,
                        'card_updated_at' => $person->card->updated_at,
                        'group' => $person->card->group,
                        'card_person_type' => $person->card->personType,
                        'parents' => $person->card->parents,
                        'people' => $person->card->people,
                        'garden_images' => $person->card->garden_images,
                        'garden' => $person->card->garden
                    ];

                    // Merge card data into base data (like JavaScript spread operator)
                    $baseData = array_merge($baseData, $cardData);
                }

                return $baseData;
            });
            \Log::info('People transformation completed');
        } catch (\Exception $e) {
            \Log::error('People transformation failed:', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        // Combine cards and people into one array
        \Log::info('Combining cards and people');
        $allCards = $transformedCards->concat($transformedPeople);
        \Log::info('Final combined count', ['count' => $allCards->count()]);

        \Log::info('=== VERIFY OTP SUCCESS ===', [
            'user_type' => $userType,
            'cards_count' => $allCards->count(),
            'token_generated' => $token ? 'yes' : 'no'
        ]);

        return response()->json([
            'message' => 'Login successful',
            'cards' => $allCards,
            'user_type' => $userType,
            'token' => $token
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
     *             required={"phone"},
     *             @OA\Property(property="phone", type="string", example="+995599123456", description="Phone number associated with the card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Login successful"),
     *             @OA\Property(property="token", type="string", example="1|randomTokenStringHere", description="API token for authentication")
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
    public function login(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|max:255',
        ]);

        // Check if phone exists in either cards or people
        $cards = Card::where('phone', $request->phone)->get();
        $people = People::where('phone', $request->phone)->get();

        if ($cards->isEmpty() && $people->isEmpty()) {
            return response()->json([
                'message' => 'Invalid phone number'
            ], 401);
        }

        // Automatically send OTP
        $otp = \App\Models\CardOtp::createOtp($request->phone, 10);

        // Send OTP via SMS
        $smsService = new \App\Services\SmsService();
        $smsResult = $smsService->sendOtp($request->phone, $otp->otp);

        if (!$smsResult['success']) {
            // If SMS fails, delete the OTP and return error
            $otp->delete();
            return response()->json([
                'message' => 'Failed to send OTP. Please try again.'
            ], 500);
        }

        return response()->json([
            'message' => 'OTP sent successfully. Please verify to complete login.',
            'phone' => $request->phone
        ]);
    }


    
    /**
     * @OA\Get(
     *     path="/api/cards/export",
     *     operationId="exportCards",
     *     tags={"Cards"},
     *     summary="Export cards to Excel",
     *     description="Download an Excel report of cards. Optionally filter by card IDs, country, city, garden, or other filters. If dister is logged in, export is restricted to their assigned gardens.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="ids", in="query", required=false, description="Comma-separated card IDs or multiple ids[] query params", @OA\Schema(type="string", example="1,2,3")),
     *     @OA\Parameter(name="country_id", in="query", required=false, description="Filter by country ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="city_id", in="query", required=false, description="Filter by city ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="garden_id", in="query", required=false, description="Filter by garden ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in child's and parent's name fields", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"pending","active","inactive"})),
     *     @OA\Parameter(name="group_id", in="query", required=false, description="Filter by group ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="person_type_id", in="query", required=false, description="Filter by person type ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="parent_code", in="query", required=false, description="Filter by parent code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="parent_verification", in="query", required=false, description="Filter by parent verification status", @OA\Schema(type="boolean")),
     *     @OA\Parameter(name="license_type", in="query", required=false, description="Filter by license type", @OA\Schema(type="string", enum={"boolean", "date"})),
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

        // Collect filter parameters
        $filters = [
            'search' => $request->query('search'),
            'phone' => $request->query('phone'),
            'status' => $request->query('status'),
            'group_id' => $request->query('group_id'),
            'garden_id' => $request->query('garden_id'),
            'country_id' => $request->query('country_id'),
            'city_id' => $request->query('city_id'),
            'person_type_id' => $request->query('person_type_id'),
            'parent_code' => $request->query('parent_code'),
            'parent_verification' => $request->query('parent_verification'),
            'license_type' => $request->query('license_type'),
        ];

        // Remove empty filters
        $filters = array_filter($filters, function($value) {
            return $value !== null && $value !== '';
        });

        // Allowed gardens for dister
        $allowedGardenIds = [];
        if ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = array_values(array_filter((array) ($request->user()->gardens ?? []), 'is_numeric'));
        } elseif ($request->user() && $request->user()->garden_id) {
            $allowedGardenIds = [(int) $request->user()->garden_id];
        }

        return Excel::download(new CardsExport($allowedGardenIds, $requestedIds, $filters), 'cards.xlsx');
    }

    /**
     * @OA\Get(
     *     path="/api/cards/me",
     *     operationId="getCardMe",
     *     tags={"Cards"},
     *     summary="Get authenticated card data",
     *     description="Get card data using token authentication and optional phone validation",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="phone",
     *         in="query",
     *         required=false,
     *         description="Phone number to validate against the authenticated card",
     *         @OA\Schema(type="string", example="+995599123456")
     *     ),
     *     @OA\Parameter(
     *         name="expo_token",
     *         in="query",
     *         required=false,
     *         description="Expo push notification token to update for this card",
     *         @OA\Schema(type="string", example="ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card data retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card data retrieved successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="John"),
     *                 @OA\Property(property="child_last_name", type="string", example="Doe"),
     *                 @OA\Property(property="parent_name", type="string", example="Jane Doe"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                 @OA\Property(property="image_url", type="string", example="http://localhost/storage/cards/abc123.jpg", nullable=true),
     *                 @OA\Property(property="parent_verification", type="boolean", example=true),
     *                 @OA\Property(property="license", type="object", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object", nullable=true),
     *                 @OA\Property(property="personType", type="object", nullable=true),
     *                 @OA\Property(property="parents", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="people", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="garden_images", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Main Entrance"),
     *                     @OA\Property(property="image", type="string", example="garden_images/abc123.jpg"),
     *                     @OA\Property(property="image_url", type="string", example="http://localhost/storage/garden_images/abc123.jpg"),
     *                     @OA\Property(property="index", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="garden", type="object", nullable=true,
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                     @OA\Property(property="address", type="string", example="123 Main Street"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456"),
     *                     @OA\Property(property="email", type="string", example="garden@example.com"),
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="created_at", type="string", format="date-time")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Unauthenticated")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Phone number mismatch",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Phone number does not match authenticated card")
     *         )
     *     )
     * )
     */
    public function me(Request $request)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated'
            ], 401);
        }

        // Check if user is a Card or People
        if ($user instanceof Card) {
            // If phone is provided, validate it matches the authenticated card
            if ($request->has('phone') && $request->phone !== $user->phone) {
                return response()->json([
                    'message' => 'Phone number does not match authenticated card'
                ], 403);
            }

            // Update expo_token if provided (like in verifyOtp)
            if ($request->has('expo_token') && $request->expo_token) {
                Card::where('phone', $user->phone)
                    ->where('spam', '!=', 1)
                    ->update(['expo_token' => $request->expo_token]);
                
                \Log::info('CardController::me - Updated expo_token for cards', [
                    'phone' => $user->phone,
                    'expo_token' => $request->expo_token
                ]);
            }

            // Get ALL cards with this phone number (same as verifyOtp)
            $cards = Card::with(['group.garden.images', 'personType', 'parents', 'people'])
                ->where('phone', $user->phone)
                ->where('spam', '!=', 1)
                ->get();

            // If no cards found, try to find in People table
            if ($cards->isEmpty()) {
                \Log::info('CardController::me - No cards found for phone, checking People table', [
                    'phone' => $user->phone
                ]);
                
                $people = People::with(['personType', 'card.group.garden.images', 'card.personType', 'card.parents', 'card.people'])
                    ->where('phone', $user->phone)
                    ->get();
                
                if ($people->isEmpty()) {
                    \Log::warning('CardController::me - No cards or people found', [
                        'phone' => $user->phone
                    ]);
                    
                    return response()->json([
                        'message' => 'No data found for this phone number',
                        'cards' => [],
                        'user_type' => 'card'
                    ]);
                }
                
                // Transform people to include full card data in same format as Cards
                $transformedPeople = $people->map(function ($person) {
                    // If person has a card, return in Card format (same as normal cards)
                    if ($person->card) {
                        return [
                            'id' => $person->card->id,
                            'child_first_name' => $person->card->child_first_name,
                            'child_last_name' => $person->card->child_last_name,
                            'parent_name' => $person->card->parent_name,
                            'phone' => $person->card->phone,
                            'status' => $person->card->status,
                            'group_id' => $person->card->group_id,
                            'person_type_id' => $person->card->person_type_id,
                            'parent_code' => $person->card->parent_code,
                            'image_path' => $person->card->image_path,
                            'active_garden_image' => $person->card->active_garden_image,
                            'image_url' => $person->card->image_url,
                            'is_deleted' => $person->card->is_deleted,
                            'deleted_at' => $person->card->deleted_at,
                            'created_at' => $person->card->created_at,
                            'updated_at' => $person->card->updated_at,
                            'group' => $person->card->group,
                            'personType' => $person->card->personType,
                            'parents' => $person->card->parents,
                            'people' => $person->card->people,
                            'garden_images' => $person->card->garden_images,
                            'garden' => $person->card->garden,
                            'main_parent' => false
                        ];
                    }
                    
                    // If no card, return minimal person data
                    return [
                        'id' => null,
                        'name' => $person->name,
                        'phone' => $person->phone,
                        'person_type_id' => $person->person_type_id,
                        'person_type' => $person->personType,
                        'main_parent' => false
                    ];
                });
                
                return response()->json([
                    'message' => 'People data retrieved successfully',
                    'cards' => $transformedPeople,
                    'user_type' => 'people'
                ]);
            }

            // Get all people with this phone number (same as verifyOtp)
            $people = People::with(['personType', 'card.group.garden.images', 'card.personType', 'card.parents', 'card.people'])
                ->where('phone', $user->phone)
                ->get();

            // Transform cards to include garden images and garden info (same as verifyOtp)
            $transformedCards = $cards->map(function ($card) {
                return [
                    'id' => $card->id,
                    'child_first_name' => $card->child_first_name,
                    'child_last_name' => $card->child_last_name,
                    'parent_name' => $card->parent_name,
                    'phone' => $card->phone,
                    'status' => $card->status,
                    'group_id' => $card->group_id,
                    'person_type_id' => $card->person_type_id,
                    'parent_code' => $card->parent_code,
                    'image_path' => $card->image_path,
                    'active_garden_image' => $card->active_garden_image,
                    'image_url' => $card->image_url,
                    'is_deleted' => $card->is_deleted,
                    'deleted_at' => $card->deleted_at,
                    'created_at' => $card->created_at,
                    'updated_at' => $card->updated_at,
                    'group' => $card->group,
                    'personType' => $card->personType,
                    'parents' => $card->parents,
                    'people' => $card->people,
                    'garden_images' => $card->garden_images,
                    'garden' => $card->garden,
                    'main_parent' => true
                ];
            });

            // Transform people to include full card data (same as verifyOtp)
            $transformedPeople = $people->map(function ($person) {
                $baseData = [
                    'name' => $person->name,
                    'phone' => $person->phone,
                    'person_type_id' => $person->person_type_id,
                    'card_id' => $person->card_id,
                    'created_at' => $person->created_at,
                    'updated_at' => $person->updated_at,
                    'person_type' => $person->personType,
                    'main_parent' => false
                ];

                // If person has a card, merge card data directly into the base data
                if ($person->card) {
                    $cardData = [
                        'id' => $person->card->id,
                        'child_first_name' => $person->card->child_first_name,
                        'child_last_name' => $person->card->child_last_name,
                        'parent_name' => $person->card->parent_name,
                        'card_phone' => $person->card->phone,
                        'status' => $person->card->status,
                        'parent_code' => $person->card->parent_code,
                        'image_url' => $person->card->image_url,
                        'parent_verification' => $person->card->parent_verification,
                        'license' => $person->card->license,
                        'active_garden_image' => $person->card->active_garden_image,
                        'is_deleted' => $person->card->is_deleted,
                        'deleted_at' => $person->card->deleted_at,
                        'card_created_at' => $person->card->created_at,
                        'card_updated_at' => $person->card->updated_at,
                        'group' => $person->card->group,
                        'card_person_type' => $person->card->personType,
                        'parents' => $person->card->parents,
                        'people' => $person->card->people,
                        'garden_images' => $person->card->garden_images,
                        'garden' => $person->card->garden
                    ];
                    
                    // Merge card data into base data (like JavaScript spread operator)
                    $baseData = array_merge($baseData, $cardData);
                }

                return $baseData;
            });

            // Combine cards and people into one array (same as verifyOtp)
            $allCards = $transformedCards->concat($transformedPeople);

            return response()->json([
                'message' => 'Card data retrieved successfully',
                'cards' => $allCards, // Same structure as verifyOtp
                'user_type' => 'card'
            ]);
        } elseif ($user instanceof People) {
            // If phone is provided, validate it matches the authenticated person
            if ($request->has('phone') && $request->phone !== $user->phone) {
                return response()->json([
                    'message' => 'Phone number does not match authenticated person'
                ], 403);
            }

            // Load all related data like in verify-otp
            $user->load(['personType']);
            
            // Only load card relationship if card_id exists
            if ($user->card_id) {
                try {
                    $user->load(['card.group.garden.images', 'card.personType', 'card.parents', 'card.people']);
                } catch (\Exception $e) {
                    // If card doesn't exist, continue without card data
                    $user->card = null;
                }
            }

            // Transform people to include full card data (same as verifyOtp)
            $baseData = [
                'id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'person_type_id' => $user->person_type_id,
                'card_id' => $user->card_id,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
                'person_type' => $user->personType,
                'main_parent' => false
            ];

            // If person has a card, merge card data directly into the base data
            if ($user->card) {
                $cardData = [
                    'card_id' => $user->card->id,
                    'child_first_name' => $user->card->child_first_name,
                    'child_last_name' => $user->card->child_last_name,
                    'parent_name' => $user->card->parent_name,
                    'card_phone' => $user->card->phone,
                    'status' => $user->card->status,
                    'parent_code' => $user->card->parent_code,
                    'image_url' => $user->card->image_url,
                    'parent_verification' => $user->card->parent_verification,
                    'license' => $user->card->license,
                    'active_garden_image' => $user->card->active_garden_image,
                    'is_deleted' => $user->card->is_deleted,
                    'deleted_at' => $user->card->deleted_at,
                    'card_created_at' => $user->card->created_at,
                    'card_updated_at' => $user->card->updated_at,
                    'group' => $user->card->group,
                    'card_person_type' => $user->card->personType,
                    'parents' => $user->card->parents,
                    'people' => $user->card->people,
                    'garden_images' => $user->card->garden_images,
                    'garden' => $user->card->garden
                ];
                
                // Merge card data into base data (like JavaScript spread operator)
                $baseData = array_merge($baseData, $cardData);
            }

            return response()->json([
                'message' => 'People data retrieved successfully',
                'cards' => [$baseData], // Same structure as verifyOtp
                'user_type' => 'people'
            ]);
        }

        return response()->json([
            'message' => 'This endpoint is only for Card or People authentication. Current user type: ' . get_class($user)
        ], 400);
    }

    /**
     * Change main garden image for a card
     *
     * @OA\Patch(
     *     path="/api/cards/{id}/change-main-garden-image",
     *     operationId="changeMainGardenImage",
     *     tags={"Cards"},
     *     summary="Change main garden image for a card",
     *     description="Update the active_garden_image field for a specific card with garden image ID",
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
     *             required={"garden_image_id"},
     *             @OA\Property(
     *                 property="garden_image_id",
     *                 type="integer",
     *                 example=1,
     *                 description="ID of the garden image from garden_images table"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Main garden image updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Main garden image updated successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="active_garden_image", type="integer", example=1),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card or garden image not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card or garden image not found")
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
     *                 @OA\Property(property="garden_image_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function changeMainGardenImage(Request $request, $id)
    {
        $request->validate([
            'garden_image_id' => 'required|integer|exists:garden_images,id',
        ]);

        $card = Card::findOrFail($id);

        // Check if the garden image belongs to the same garden as the card
        $gardenImage = \App\Models\GardenImage::findOrFail($request->garden_image_id);
        
        if ($card->group && $card->group->garden) {
            if ($gardenImage->garden_id !== $card->group->garden->id) {
                return response()->json([
                    'message' => 'Garden image does not belong to the same garden as the card'
                ], 403);
            }
        }

        // Update the active_garden_image field
        $card->update([
            'active_garden_image' => $request->garden_image_id
        ]);

        // Clean up empty values in active_garden_groups for all devices in this garden
        if ($card->group && $card->group->garden) {
            $devices = \App\Models\Device::where('garden_id', $card->group->garden->id)->get();
            
            foreach ($devices as $device) {
                if (!empty($device->active_garden_groups)) {
                    // Get valid group IDs for this garden
                    $validGroupIds = \App\Models\GardenGroup::where('garden_id', $card->group->garden->id)
                        ->pluck('id')
                        ->toArray();
                    
                    // Remove null, empty, or invalid values, keeping only valid group IDs
                    $cleanedGroups = array_filter($device->active_garden_groups, function($groupId) use ($validGroupIds) {
                        return !is_null($groupId) && 
                               $groupId !== '' && 
                               is_numeric($groupId) && 
                               in_array((int)$groupId, $validGroupIds);
                    });
                    
                    // Re-index the array to remove gaps
                    $cleanedGroups = array_values($cleanedGroups);
                    
                    if ($cleanedGroups !== $device->active_garden_groups) {
                        $device->update(['active_garden_groups' => $cleanedGroups]);
                    }
                }
            }
        }

        return response()->json([
            'message' => 'Main garden image updated successfully',
            'card' => [
                'id' => $card->id,
                'active_garden_image' => $card->active_garden_image,
                'updated_at' => $card->updated_at,
            ]
        ], 200);
    }

    /**
     * @OA\Get(
     *     path="/api/get-spam-cards",
     *     operationId="getAllSpamCards",
     *     tags={"Cards"},
     *     summary="Get all spam cards",
     *     description="Retrieve all cards that have been marked as spam (spam = 1) with their associated group and garden information. Supports filtering by garden name or referral code.",
     *     @OA\Parameter(name="garden_filter", in="query", required=false, description="Filter by garden name or referral code", @OA\Schema(type="string")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", enum={"pending","active","inactive"}, example="active"),
     *                 @OA\Property(property="spam", type="boolean", example=true),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group 1"),
     *                     @OA\Property(property="garden", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Garden Name"),
     *                         @OA\Property(property="referral_code", type="string", example="REF123456")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getAllSpamCards(Request $request)
    {
        $query = Card::with(['group.garden:id,name,referral_code'])
            ->where('spam', 1);

        // Add garden filter if provided
        if ($request->filled('garden_filter')) {
            $gardenFilter = $request->query('garden_filter');
            $query->whereHas('group.garden', function ($q) use ($gardenFilter) {
                $q->where('name', 'like', '%' . $gardenFilter . '%')
                  ->orWhere('referral_code', 'like', '%' . $gardenFilter . '%');
            });
        }

        return $query->get();
    }

    /**
     * Mark card as spam
     *
     * @OA\Patch(
     *     path="/api/cards/{id}/delete-as-spam",
     *     operationId="deleteAsSpam",
     *     tags={"Cards"},
     *     summary="Mark card as spam",
     *     description="Mark a specific card as spam by setting the spam field to 1",
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
     *         description="Card marked as spam successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card marked as spam successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="spam", type="boolean", example=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card not found")
     *         )
     *     )
     * )
     */
    public function deleteAsSpam($id)
    {
        $card = Card::findOrFail($id);

        // Mark card as spam
        $card->update([
            'spam' => true
        ]);

        return response()->json([
            'message' => 'Card marked as spam successfully',
            'card' => [
                'id' => $card->id,
                'spam' => $card->spam,
                'updated_at' => $card->updated_at,
            ]
        ], 200);
    }

    /**
     * Update card comment
     *
     * @OA\Patch(
     *     path="/api/cards/{id}/comment",
     *     operationId="updateCardComment",
     *     tags={"Cards"},
     *     summary="Update card comment",
     *     description="Update the comment field of a specific card",
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
     *             required={"comment"},
     *             @OA\Property(property="comment", type="string", maxLength=1000, example="Updated comment about this child", description="Comment text (use empty string to clear comment)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card comment updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card comment updated successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="comment", type="string", example="Updated comment about this child", nullable=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card not found")
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
     *                 @OA\Property(property="comment", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateComment(Request $request, $id)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $card = Card::findOrFail($id);

        // Check if user has access to this card
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            if (!$card->group || $card->group->garden_id !== $gardenId) {
                return response()->json(['message' => 'Card not found or access denied'], 404);
            }
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            if (!$card->group || !in_array($card->group->garden_id, $allowedGardenIds)) {
                return response()->json(['message' => 'Card not found or access denied'], 404);
            }
        }

        // Update the comment field
        $card->update([
            'comment' => $validated['comment'] === '' ? null : $validated['comment']
        ]);

        return response()->json([
            'message' => 'Card comment updated successfully',
            'card' => [
                'id' => $card->id,
                'comment' => $card->comment,
                'updated_at' => $card->updated_at,
            ]
        ], 200);
    }

    /**
     * Update card spam comment
     *
     * @OA\Patch(
     *     path="/api/cards/{id}/spam-comment",
     *     operationId="updateCardSpamComment",
     *     tags={"Cards"},
     *     summary="Update card spam comment",
     *     description="Update the spam_comment field of a specific card",
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
     *             required={"spam_comment"},
     *             @OA\Property(property="spam_comment", type="string", maxLength=1000, example="Updated spam reason comment", description="Comment explaining why the card was marked as spam (use empty string to clear comment)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card spam comment updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card spam comment updated successfully"),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="spam_comment", type="string", example="Updated spam reason comment", nullable=true),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card not found")
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
     *                 @OA\Property(property="spam_comment", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateSpamComment(Request $request, $id)
    {
        $validated = $request->validate([
            'spam_comment' => 'required|string|max:1000',
        ]);

        $card = Card::findOrFail($id);

        // Check if user has access to this card
        if ($request->user() && $request->user()->garden_id) {
            $gardenId = $request->user()->garden_id;
            if (!$card->group || $card->group->garden_id !== $gardenId) {
                return response()->json(['message' => 'Card not found or access denied'], 404);
            }
        } elseif ($request->user() instanceof \App\Models\Dister) {
            $allowedGardenIds = $request->user()->gardens ?? [];
            if (!$card->group || !in_array($card->group->garden_id, $allowedGardenIds)) {
                return response()->json(['message' => 'Card not found or access denied'], 404);
            }
        }

        // Update the spam_comment field
        $card->update([
            'spam_comment' => $validated['spam_comment'] === '' ? null : $validated['spam_comment']
        ]);

        return response()->json([
            'message' => 'Card spam comment updated successfully',
            'card' => [
                'id' => $card->id,
                'spam_comment' => $card->spam_comment,
                'updated_at' => $card->updated_at,
            ]
        ], 200);
    }
}
