<?php

namespace App\Http\Controllers\Api;

use App\Models\GardenGroup;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

/**
 * @OA\Tag(
 *     name="Garden Groups",
 *     description="API Endpoints for managing garden groups"
 * )
 */
class GardenGroupController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/garden-groups",
     *     operationId="getGardenGroups",
     *     tags={"Garden Groups"},
     *     summary="Get all garden groups",
     *     description="Retrieve a list of all garden groups with their associated garden information",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Group A"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="garden",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                     @OA\Property(property="address", type="string", example="123 Main St")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        return GardenGroup::with('garden')->get();
    }

    /**
     * @OA\Post(
     *     path="/api/garden-groups",
     *     operationId="createGardenGroup",
     *     tags={"Garden Groups"},
     *     summary="Create a new garden group",
     *     description="Create a new garden group with the provided information",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "garden_id"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Group B", description="Name of the garden group"),
     *             @OA\Property(property="garden_id", type="integer", example=1, description="ID of the associated garden")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Garden group created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=2),
     *             @OA\Property(property="name", type="string", example="Group B"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
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
     *                 @OA\Property(property="garden_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'garden_id' => 'required|exists:gardens,id',
        ]);

        return GardenGroup::create($validated);
    }

    /**
     * @OA\Get(
     *     path="/api/garden-groups/{id}",
     *     operationId="getGardenGroup",
     *     tags={"Garden Groups"},
     *     summary="Get a specific garden group",
     *     description="Retrieve detailed information about a specific garden group",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Group A"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="garden",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                 @OA\Property(property="address", type="string", example="123 Main St")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\GardenGroup]")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        return GardenGroup::with('garden')->findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/garden-groups/{id}",
     *     operationId="updateGardenGroup",
     *     tags={"Garden Groups"},
     *     summary="Update a garden group",
     *     description="Update an existing garden group with new information",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Updated Group A", description="Name of the garden group"),
     *             @OA\Property(property="garden_id", type="integer", example=2, description="ID of the associated garden")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden group updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Updated Group A"),
     *             @OA\Property(property="garden_id", type="integer", example=2),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\GardenGroup]")
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
     *                 @OA\Property(property="garden_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $group = GardenGroup::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'garden_id' => 'sometimes|required|exists:gardens,id',
        ]);

        $group->update($validated);

        return $group;
    }

    /**
     * @OA\Delete(
     *     path="/api/garden-groups/{id}",
     *     operationId="deleteGardenGroup",
     *     tags={"Garden Groups"},
     *     summary="Delete a garden group",
     *     description="Permanently delete a garden group",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Garden group ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Garden group deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Garden group not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\GardenGroup]")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $group = GardenGroup::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
