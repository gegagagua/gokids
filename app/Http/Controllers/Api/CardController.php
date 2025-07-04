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
     *     description="Retrieve a list of all child cards with their associated group information",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="father_name", type="string", example="Davit"),
     *                 @OA\Property(property="parent_first_name", type="string", example="Nino"),
     *                 @OA\Property(property="parent_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="group",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    // ყველა ბარათის წამოღება
    public function index()
    {
        return Card::with('group')->get();
    }

    /**
     * @OA\Get(
     *     path="/api/cards/{id}",
     *     operationId="getCard",
     *     tags={"Cards"},
     *     summary="Get a specific card",
     *     description="Retrieve detailed information about a specific child card",
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
     *             @OA\Property(property="father_name", type="string", example="Davit"),
     *             @OA\Property(property="parent_first_name", type="string", example="Nino"),
     *             @OA\Property(property="parent_last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="parent_code", type="string", example="ABC123", nullable=true),
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
    public function show($id)
    {
        return Card::with('group')->findOrFail($id);
    }

    /**
     * @OA\Post(
     *     path="/api/cards",
     *     operationId="createCard",
     *     tags={"Cards"},
     *     summary="Create a new card",
     *     description="Create a new child card with the provided information",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"child_first_name", "child_last_name", "father_name", "parent_first_name", "parent_last_name", "phone", "status", "group_id"},
     *             @OA\Property(property="child_first_name", type="string", maxLength=255, example="Giorgi", description="Child's first name"),
     *             @OA\Property(property="child_last_name", type="string", maxLength=255, example="Davitashvili", description="Child's last name"),
     *             @OA\Property(property="father_name", type="string", maxLength=255, example="Davit", description="Father's name"),
     *             @OA\Property(property="parent_first_name", type="string", maxLength=255, example="Nino", description="Parent's first name"),
     *             @OA\Property(property="parent_last_name", type="string", maxLength=255, example="Davitashvili", description="Parent's last name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Contact phone number"),
     *             @OA\Property(property="status", type="string", example="active", enum={"pending", "active", "inactive"}, description="Card status"),
     *             @OA\Property(property="group_id", type="integer", example=1, description="ID of the associated garden group"),
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="ABC123", nullable=true, description="Optional parent access code")
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
     *             @OA\Property(property="father_name", type="string", example="Davit"),
     *             @OA\Property(property="parent_first_name", type="string", example="Nino"),
     *             @OA\Property(property="parent_last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="parent_code", type="string", example="ABC123", nullable=true),
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
     *                 @OA\Property(property="father_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_last_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string"))
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
            'father_name' => 'required|string|max:255',
            'parent_first_name' => 'required|string|max:255',
            'parent_last_name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'status' => 'required|string|in:pending,active,inactive',
            'group_id' => 'required|exists:garden_groups,id',
            'parent_code' => 'nullable|string|max:255',
        ]);

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
     *             @OA\Property(property="father_name", type="string", maxLength=255, example="Updated Davit", description="Father's name"),
     *             @OA\Property(property="parent_first_name", type="string", maxLength=255, example="Updated Nino", description="Parent's first name"),
     *             @OA\Property(property="parent_last_name", type="string", maxLength=255, example="Updated Davitashvili", description="Parent's last name"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="status", type="string", example="inactive", enum={"pending", "active", "inactive"}, description="Card status"),
     *             @OA\Property(property="group_id", type="integer", example=2, description="ID of the associated garden group"),
     *             @OA\Property(property="parent_code", type="string", maxLength=255, example="XYZ789", nullable=true, description="Optional parent access code")
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
     *             @OA\Property(property="father_name", type="string", example="Updated Davit"),
     *             @OA\Property(property="parent_first_name", type="string", example="Updated Nino"),
     *             @OA\Property(property="parent_last_name", type="string", example="Updated Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599654321"),
     *             @OA\Property(property="status", type="string", example="inactive"),
     *             @OA\Property(property="group_id", type="integer", example=2),
     *             @OA\Property(property="parent_code", type="string", example="XYZ789", nullable=true),
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
     *                 @OA\Property(property="father_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="parent_last_name", type="array", @OA\Items(type="string")),
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
        $card = Card::findOrFail($id);

        $validated = $request->validate([
            'child_first_name' => 'sometimes|required|string|max:255',
            'child_last_name' => 'sometimes|required|string|max:255',
            'father_name' => 'sometimes|required|string|max:255',
            'parent_first_name' => 'sometimes|required|string|max:255',
            'parent_last_name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'status' => 'sometimes|required|string|in:pending,active,inactive',
            'group_id' => 'sometimes|required|exists:garden_groups,id',
            'parent_code' => 'nullable|string|max:255',
        ]);

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
    public function destroy($id)
    {
        $card = Card::findOrFail($id);
        $card->delete();

        return response()->json(['message' => 'Card deleted']);
    }
}
