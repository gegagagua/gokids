<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;

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
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="group", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Group A")
     *                     ),
     *                     @OA\Property(property="personType", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="ბავშვი")
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
        $query = Card::with(['group', 'personType']);
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
            });
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
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="group",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Group A")
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
        $query = Card::with(['group', 'personType']);
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
            });
        }
        
        return $query->findOrFail($id);
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
     *             required={"child_first_name", "child_last_name", "parent_name", "phone", "status", "group_id"},
     *             @OA\Property(property="child_first_name", type="string", maxLength=255, example="Giorgi", description="Child's first name"),
     *             @OA\Property(property="child_last_name", type="string", maxLength=255, example="Davitashvili", description="Child's last name"),
     *             @OA\Property(property="parent_name", type="string", maxLength=255, example="Nino Davitashvili", description="Parent's full name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Contact phone number"),
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}, description="Card status"),
     *             @OA\Property(property="group_id", type="integer", example=1, description="ID of the associated garden group"),
     *             @OA\Property(property="person_type_id", type="integer", example=1, nullable=true, description="Person type ID from person-types"),
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="ABC123", nullable=true, description="Optional parent access code (auto-generated if not provided)")
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
            'status' => 'required|string|in:pending,active,inactive',
            'group_id' => 'required|exists:garden_groups,id',
            'person_type_id' => 'nullable|exists:person_types,id',
            'parent_code' => 'nullable|string|max:255',
        ]);

        // If garden_id is provided (garden user), validate that the group belongs to their garden
        if ($request->filled('garden_id')) {
            $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                ->where('garden_id', $request->query('garden_id'))
                ->first();
            
            if (!$group) {
                return response()->json(['message' => 'Group does not belong to your garden'], 403);
            }
        }

        $card = Card::create($validated);

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
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="K9#mP2", nullable=true, description="Optional parent access code (auto-generated if not provided)")
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
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
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
        ]);

        // If garden_id is provided and group_id is being updated, validate that the group belongs to their garden
        if ($request->filled('garden_id') && isset($validated['group_id'])) {
            $group = \App\Models\GardenGroup::where('id', $validated['group_id'])
                ->where('garden_id', $request->query('garden_id'))
                ->first();
            
            if (!$group) {
                return response()->json(['message' => 'Group does not belong to your garden'], 403);
            }
        }

        $card->update($validated);

        return response()->json($card);
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
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
            });
        }
        
        $card = $query->findOrFail($id);
        $card->delete();

        return response()->json(['message' => 'Card deleted']);
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
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
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
        
        // Filter by garden_id if provided (for garden users)
        if ($request->filled('garden_id')) {
            $query->whereHas('group', function ($q) use ($request) {
                $q->where('garden_id', $request->query('garden_id'));
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
}
