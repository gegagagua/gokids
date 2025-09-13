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
     *     description="Retrieve a paginated list of all garden groups with their associated garden information. Supports filtering by name and garden_id.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by group name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="garden_id", in="query", required=false, description="Filter by garden ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (pagination). Default: 15", @OA\Schema(type="integer", default=15, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number for pagination. Default: 1", @OA\Schema(type="integer", default=1, minimum=1)),
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
     *                     @OA\Property(property="name", type="string", example="Group A"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="garden", type="object",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Sunshine Garden"),
     *                         @OA\Property(property="address", type="string", example="123 Main St")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="first_page_url", type="string", example="http://localhost/api/garden-groups?page=1"),
     *             @OA\Property(property="from", type="integer", example=1, description="First item number on current page"),
     *             @OA\Property(property="last_page", type="integer", example=5, description="Last page number"),
     *             @OA\Property(property="last_page_url", type="string", example="http://localhost/api/garden-groups?page=5"),
     *             @OA\Property(property="next_page_url", type="string", example="http://localhost/api/garden-groups?page=2", nullable=true),
     *             @OA\Property(property="path", type="string", example="http://localhost/api/garden-groups"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Items per page"),
     *             @OA\Property(property="prev_page_url", type="string", example=null, nullable=true),
     *             @OA\Property(property="to", type="integer", example=15, description="Last item number on current page"),
     *             @OA\Property(property="total", type="integer", example=50, description="Total number of items")
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = GardenGroup::with('garden');

        $user = $request->user();
        if ($user && $user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where('garden_id', $garden->id);
            } else {
                return collect([]);
            }
        } elseif ($user instanceof \App\Models\User && $user->type === 'dister') {
            $dister = \App\Models\Dister::where('email', $user->email)->first();
            $allowedGardenIds = $dister->gardens ?? [];
            if (!empty($allowedGardenIds)) {
                $query->whereIn('garden_id', $allowedGardenIds);
            } else {
                // Return empty when no gardens assigned
                return collect([]);
            }
        } else if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->query('garden_id'));
        }

        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }

        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        $groups = $query->withCount('cards')->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        // დაამატე cards_count ველი
        $groups->getCollection()->transform(function ($group) {
            $group->cards_count = $group->cards_count ?? 0;
            return $group;
        });
        return $groups;
    }

    /**
     * @OA\Post(
     *     path="/api/garden-groups",
     *     operationId="createGardenGroup",
     *     tags={"Garden Groups"},
     *     summary="Create a new garden group",
     *     description="Create a new garden group with the provided information",
     *     security={{"sanctum":{}}},
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
     *     security={{"sanctum":{}}},
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
     *     security={{"sanctum":{}}},
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
     *     security={{"sanctum":{}}},
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

    /**
     * @OA\Delete(
     *     path="/api/garden-groups/bulk-delete",
     *     operationId="bulkDeleteGardenGroups",
     *     tags={"Garden Groups"},
     *     summary="Delete multiple garden groups",
     *     description="Permanently delete multiple garden groups by their IDs",
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
     *         description="Garden groups deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Garden groups deleted"),
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

        $deleted = GardenGroup::whereIn('id', $ids)->delete();

        return response()->json([
            'message' => 'Garden groups deleted',
            'deleted_count' => $deleted,
        ]);
    }
}
