<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentModel;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Parents",
 *     description="API Endpoints for managing parent models"
 * )
 */
class ParentModelController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/parents",
     *     operationId="getParents",
     *     tags={"Parents"},
     *     summary="Get all parents",
     *     description="Retrieve a list of all parents with their associated group and card information",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="first_name", type="string", example="Nino"),
     *                 @OA\Property(property="last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "blocked"}),
     *                 @OA\Property(property="phone", type="string", example="+995599123456"),
     *                 @OA\Property(property="code", type="string", example="PARENT001", nullable=true),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="card_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(
     *                     property="group",
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group A")
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
    public function index()
    {
        return ParentModel::with(['group', 'card'])->get();
    }

    /**
     * @OA\Get(
     *     path="/api/parents/{id}",
     *     operationId="getParent",
     *     tags={"Parents"},
     *     summary="Get a specific parent",
     *     description="Retrieve detailed information about a specific parent",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Parent ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="Nino"),
     *             @OA\Property(property="last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "blocked"}),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="code", type="string", example="PARENT001", nullable=true),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="card_id", type="integer", example=1),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(
     *                 property="group",
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Group A")
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
     *         description="Parent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\ParentModel]")
     *         )
     *     )
     * )
     */
    public function show($id)
    {
        return ParentModel::with(['group', 'card'])->findOrFail($id);
    }

    /**
     * @OA\Post(
     *     path="/api/parents",
     *     operationId="createParent",
     *     tags={"Parents"},
     *     summary="Create a new parent",
     *     description="Create a new parent with the provided information",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"first_name", "last_name", "status", "phone", "group_id", "card_id"},
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="Nino", description="Parent's first name"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Davitashvili", description="Parent's last name"),
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "inactive", "blocked"}, description="Parent status"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599123456", description="Contact phone number"),
     *             @OA\Property(property="code", type="string", maxLength=255, example="PARENT001", nullable=true, description="Optional parent code"),
     *             @OA\Property(property="group_id", type="integer", example=1, description="ID of the associated garden group"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="ID of the associated child card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Parent created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=2),
     *             @OA\Property(property="first_name", type="string", example="Nino"),
     *             @OA\Property(property="last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="code", type="string", example="PARENT001", nullable=true),
     *             @OA\Property(property="group_id", type="integer", example=1),
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
     *                 @OA\Property(property="first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="last_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="card_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'status' => 'required|string|in:active,inactive,blocked',
            'phone' => 'required|string|max:20',
            'code' => 'nullable|string|max:255',
            'group_id' => 'required|exists:garden_groups,id',
            'card_id' => 'required|exists:cards,id',
        ]);

        return ParentModel::create($validated);
    }

    /**
     * @OA\Put(
     *     path="/api/parents/{id}",
     *     operationId="updateParent",
     *     tags={"Parents"},
     *     summary="Update a parent",
     *     description="Update an existing parent with new information",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Parent ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="first_name", type="string", maxLength=255, example="Updated Nino", description="Parent's first name"),
     *             @OA\Property(property="last_name", type="string", maxLength=255, example="Updated Davitashvili", description="Parent's last name"),
     *             @OA\Property(property="status", type="string", example="inactive", enum={"active", "inactive", "blocked"}, description="Parent status"),
     *             @OA\Property(property="phone", type="string", maxLength=20, example="+995599654321", description="Contact phone number"),
     *             @OA\Property(property="code", type="string", maxLength=255, example="UPDATED001", nullable=true, description="Optional parent code"),
     *             @OA\Property(property="group_id", type="integer", example=2, description="ID of the associated garden group"),
     *             @OA\Property(property="card_id", type="integer", example=2, description="ID of the associated child card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Parent updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="first_name", type="string", example="Updated Nino"),
     *             @OA\Property(property="last_name", type="string", example="Updated Davitashvili"),
     *             @OA\Property(property="status", type="string", example="inactive"),
     *             @OA\Property(property="phone", type="string", example="+995599654321"),
     *             @OA\Property(property="code", type="string", example="UPDATED001", nullable=true),
     *             @OA\Property(property="group_id", type="integer", example=2),
     *             @OA\Property(property="card_id", type="integer", example=2),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\ParentModel]")
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
     *                 @OA\Property(property="first_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="last_name", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="phone", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="group_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="card_id", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $parent = ParentModel::findOrFail($id);

        $validated = $request->validate([
            'first_name' => 'sometimes|required|string|max:255',
            'last_name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|string|in:active,inactive,blocked',
            'phone' => 'sometimes|required|string|max:20',
            'code' => 'nullable|string|max:255',
            'group_id' => 'sometimes|required|exists:garden_groups,id',
            'card_id' => 'sometimes|required|exists:cards,id',
        ]);

        $parent->update($validated);

        return $parent;
    }


    /**
     * @OA\Delete(
     *     path="/api/parents/{id}",
     *     operationId="deleteParent",
     *     tags={"Parents"},
     *     summary="Delete a parent",
     *     description="Permanently delete a parent",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Parent ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Parent deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Parent deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Parent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\ParentModel]")
     *         )
     *     )
     * )
     */
    public function destroy($id)
    {
        $parent = ParentModel::findOrFail($id);
        $parent->delete();

        return response()->json(['message' => 'Parent deleted']);
    }
}
