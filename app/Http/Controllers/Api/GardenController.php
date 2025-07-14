<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Garden;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

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
     *     description="Retrieve a list of all gardens with their associated city information",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                 @OA\Property(property="address", type="string", example="123 Main Street"),
     *                 @OA\Property(property="tax_id", type="string", example="123456789"),
     *                 @OA\Property(property="city_id", type="integer", example=1),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="email", type="string", example="sunshine@garden.ge"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="city",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Tbilisi"),
     *                     @OA\Property(property="region", type="string", example="Tbilisi")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return Garden::with('city')->get();
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
     *             @OA\Property(property="email", type="string", example="sunshine@garden.ge"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="city",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Tbilisi"),
     *                 @OA\Property(property="region", type="string", example="Tbilisi")
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
        return Garden::with('city')->findOrFail($id);
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
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="newgarden@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="password123", description="Garden access password")
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
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string"))
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
            'phone' => 'required|string|max:255',
            'email' => 'required|email|unique:gardens,email|unique:users,email',
            'password' => 'required|string|min:6',
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
     *             @OA\Property(property="phone", type="string", maxLength=255, example="+995599111222", description="Contact phone number"),
     *             @OA\Property(property="email", type="string", format="email", example="updated@garden.ge", description="Contact email address"),
     *             @OA\Property(property="password", type="string", minLength=6, example="newpassword123", description="Garden access password")
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
     *             @OA\Property(property="phone", type="string", example="+995599111222"),
     *             @OA\Property(property="email", type="string", example="updated@garden.ge"),
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
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="email", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="password", type="array", @OA\Items(type="string"))
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
            'phone' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:gardens,email,' . $id,
            'password' => 'sometimes|required|string|min:6',
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
}
