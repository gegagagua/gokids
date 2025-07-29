<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PersonType;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Person Types",
 *     description="API Endpoints for managing person types"
 * )
 */
class PersonTypeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/person-types",
     *     operationId="getPersonTypes",
     *     tags={"Person Types"},
     *     summary="Get all person types",
     *     description="Retrieve a list of all person types",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Parent"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return response()->json(PersonType::all());
    }

    /**
     * @OA\Post(
     *     path="/api/person-types",
     *     operationId="createPersonType",
     *     tags={"Person Types"},
     *     summary="Create a new person type",
     *     description="Create a new person type with the provided name",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Grandparent", description="Name of the person type")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Person type created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=3),
     *             @OA\Property(property="name", type="string", example="Grandparent"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:person_types,name',
        ]);

        $personType = PersonType::create([
            'name' => $request->name,
        ]);

        return response()->json($personType, 201);
    }

    /**
     * @OA\Delete(
     *     path="/api/person-types/{id}",
     *     operationId="deletePersonType",
     *     tags={"Person Types"},
     *     summary="Delete a person type",
     *     description="Delete a person type by ID",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Person type ID",
     *         @OA\Schema(type="integer", example=3)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Person type deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Person type deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Person type not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Person type not found")
     *         )
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Cannot delete person type - it is being used",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Cannot delete person type as it is being used by cards or people")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $personType = PersonType::find($id);

        if (!$personType) {
            return response()->json(['message' => 'Person type not found'], 404);
        }

        // Check if this person type is being used by any cards or people
        $usedInCards = \App\Models\Card::where('person_type_id', $id)->exists();
        $usedInPeople = $personType->people()->exists();

        if ($usedInCards || $usedInPeople) {
            return response()->json([
                'message' => 'Cannot delete person type as it is being used by cards or people'
            ], 409);
        }

        $personType->delete();

        return response()->json(['message' => 'Person type deleted successfully']);
    }
}