<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\People;

/**
 * @OA\Tag(
 *     name="People",
 *     description="API Endpoints for managing people"
 * )
 */
class PeopleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/people",
     *     operationId="getPeople",
     *     tags={"People"},
     *     summary="Get all people",
     *     description="Retrieve a list of all people with their associated person type and card information. Supports filtering by name, person_type_id, and card_id.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="name",
     *         in="query",
     *         required=false,
     *         description="Filter by name",
     *         @OA\Schema(type="string", example="John Doe")
     *     ),
     *     @OA\Parameter(
     *         name="person_type_id",
     *         in="query",
     *         required=false,
     *         description="Filter by person type ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="card_id",
     *         in="query",
     *         required=false,
     *         description="Filter by card ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="John Doe"),
     *
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="person_type_id", type="integer", example=1),
     *                 @OA\Property(property="card_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="person_type",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Parent")
     *                 ),
     *                 @OA\Property(
     *                     property="card",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                     @OA\Property(property="child_last_name", type="string", example="Davitashvili")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = People::with(['personType', 'card']);
        
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        if ($request->filled('person_type_id')) {
            $query->where('person_type_id', $request->query('person_type_id'));
        }
        if ($request->filled('card_id')) {
            $query->where('card_id', $request->query('card_id'));
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @OA\Get(
     *     path="/api/people/{id}",
     *     operationId="getPerson",
     *     tags={"People"},
     *     summary="Get a specific person",
     *     description="Retrieve detailed information about a specific person",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Person ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="person_type_id", type="integer", example=1),
     *             @OA\Property(property="card_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="person_type",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Parent")
     *             ),
     *             @OA\Property(
     *                 property="card",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Person not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\People]")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        return People::with(['personType', 'card'])->findOrFail($id);
    }

    /**
     * @OA\Post(
     *     path="/api/people",
     *     operationId="createPerson",
     *     tags={"People"},
     *     summary="Create a new person",
     *     description="Create a new person with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "phone", "person_type_id", "card_id"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="John Doe", description="Person's full name"),
     *
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Contact phone number"),
     *             @OA\Property(property="person_type_id", type="integer", example=1, description="ID of the associated person type"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="ID of the associated card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Person created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="person_type_id", type="integer", example=1),
     *             @OA\Property(property="card_id", type="integer", example=1),
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
     *
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="person_type_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="card_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'person_type_id' => 'required|exists:person_types,id',
            'card_id' => 'required|exists:cards,id',
        ]);

        $person = People::create($validated);

        return response()->json($person, 201);
    }

    /**
     * @OA\Put(
     *     path="/api/people/{id}",
     *     operationId="updatePerson",
     *     tags={"People"},
     *     summary="Update a person",
     *     description="Update an existing person with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Person ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated John Doe", description="Person's full name"),
     *
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="person_type_id", type="integer", example=2, description="ID of the associated person type"),
     *             @OA\Property(property="card_id", type="integer", example=2, description="ID of the associated card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Person updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated John Doe"),
     *
     *             @OA\Property(property="phone", type="string", example="+995599654321"),
     *             @OA\Property(property="person_type_id", type="integer", example=2),
     *             @OA\Property(property="card_id", type="integer", example=2),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Person not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\People]")
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
     *
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="person_type_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="card_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $person = People::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'person_type_id' => 'sometimes|required|exists:person_types,id',
            'card_id' => 'sometimes|required|exists:cards,id',
        ]);

        $person->update($validated);

        return response()->json($person);
    }

    /**
     * @OA\Delete(
     *     path="/api/people/{id}",
     *     operationId="deletePerson",
     *     tags={"People"},
     *     summary="Delete a person",
     *     description="Permanently delete a person",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Person ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Person deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Person deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Person not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\People]")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $person = People::findOrFail($id);
        $person->delete();

        return response()->json(['message' => 'Person deleted']);
    }
}
