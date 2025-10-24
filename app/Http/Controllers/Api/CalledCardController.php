<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CalledCard;
use App\Models\Card;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class CalledCardController extends Controller
{
    /**
     * Add a new called card record
     * 
     * @OA\Post(
     *     path="/api/called-cards",
     *     operationId="addCalledCard",
     *     tags={"Called Cards"},
     *     summary="Add a new called card record",
     *     description="Create a new record when a card is called",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_id"},
     *             @OA\Property(property="card_id", type="integer", example=1, description="ID of the card that was called"),
     *             @OA\Property(property="create_date", type="string", format="datetime", example="2025-10-24 14:30:00", description="Optional: specific date/time when the card was called")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Called card record created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Called card record created successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="card_id", type="integer", example=1),
     *                 @OA\Property(property="create_date", type="string", format="datetime", example="2025-10-24T14:30:00.000000Z"),
     *                 @OA\Property(property="created_at", type="string", format="datetime", example="2025-10-24T14:30:00.000000Z"),
     *                 @OA\Property(property="updated_at", type="string", format="datetime", example="2025-10-24T14:30:00.000000Z")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Validation failed"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Card not found")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'card_id' => 'required|integer|exists:cards,id',
            'create_date' => 'nullable|date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 400);
        }

        // Check if card exists
        $card = Card::find($request->card_id);
        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }

        // Create called card record
        $calledCard = CalledCard::create([
            'card_id' => $request->card_id,
            'create_date' => $request->create_date ? Carbon::parse($request->create_date) : now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Called card record created successfully',
            'data' => $calledCard
        ], 201);
    }

    /**
     * Delete called cards by card ID
     * 
     * @OA\Delete(
     *     path="/api/called-cards/card/{cardId}",
     *     operationId="deleteCalledCardsByCardId",
     *     tags={"Called Cards"},
     *     summary="Delete called cards by card ID",
     *     description="Delete all called card records for a specific card",
     *     @OA\Parameter(
     *         name="cardId",
     *         in="path",
     *         required=true,
     *         description="ID of the card",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Called cards deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="2 called card records deleted successfully"),
     *             @OA\Property(property="deleted_count", type="integer", example=2)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Card not found")
     *         )
     *     )
     * )
     */
    public function deleteByCardId($cardId)
    {
        // Check if card exists
        $card = Card::find($cardId);
        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }

        // Get count of records to be deleted
        $deletedCount = CalledCard::where('card_id', $cardId)->count();

        // Delete all called card records for this card
        CalledCard::where('card_id', $cardId)->delete();

        return response()->json([
            'success' => true,
            'message' => $deletedCount . ' called card record' . ($deletedCount !== 1 ? 's' : '') . ' deleted successfully',
            'deleted_count' => $deletedCount
        ], 200);
    }

    /**
     * Check if a card exists in called cards
     * 
     * @OA\Get(
     *     path="/api/called-cards/exists/{cardId}",
     *     operationId="checkCalledCardExists",
     *     tags={"Called Cards"},
     *     summary="Check if a card exists in called cards",
     *     description="Check if a specific card ID exists in the CalledCard table",
     *     @OA\Parameter(
     *         name="cardId",
     *         in="path",
     *         required=true,
     *         description="ID of the card to check",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="exists", type="boolean", example=true),
     *             @OA\Property(property="card_id", type="integer", example=1),
     *             @OA\Property(property="message", type="string", example="Card exists in called cards")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Card not found")
     *         )
     *     )
     * )
     */
    public function exists($cardId)
    {
        // Check if card exists
        $card = Card::find($cardId);
        if (!$card) {
            return response()->json([
                'success' => false,
                'message' => 'Card not found'
            ], 404);
        }

        // Check if card exists in CalledCard table
        $exists = CalledCard::where('card_id', $cardId)->exists();

        return response()->json([
            'success' => true,
            'exists' => $exists,
            'card_id' => (int) $cardId,
            'message' => $exists ? 'Card exists in called cards' : 'Card does not exist in called cards'
        ], 200);
    }
}
