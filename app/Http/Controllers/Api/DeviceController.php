<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;

/**
 * @OA\Tag(
 *     name="Devices",
 *     description="API Endpoints for managing devices"
 * )
 */
class DeviceController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/devices",
     *     operationId="getDevices",
     *     tags={"Devices"},
     *     summary="Get all devices",
     *     description="Retrieve a list of all devices. Supports filtering by name, status, garden_id. Pagination supported.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by device name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"active","inactive"})),
     *     @OA\Parameter(name="garden_id", in="query", required=false, description="Filter by garden ID", @OA\Schema(type="integer")),
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
     *                     @OA\Property(property="name", type="string", example="Device 1"),
     *                     @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        $userGardenId = null;
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $userGardenId = $garden->id;
                $query->where('garden_id', $userGardenId);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        $perPage = $request->query('per_page', 15);
        return $query->paginate($perPage);
    }

    /**
     * @OA\Post(
     *     path="/api/devices",
     *     operationId="createDevice",
     *     tags={"Devices"},
     *     summary="Create a new device",
     *     description="Create a new device for a garden. garden_groups is an array of group IDs.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "status", "garden_id", "garden_groups"},
     *             @OA\Property(property="name", type="string", maxLength=255, example="Device 1"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Device created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Device 1"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'status' => 'required|in:active,inactive',
            'garden_id' => 'required|exists:gardens,id',
            'garden_groups' => 'required|array',
            'garden_groups.*' => 'integer|exists:garden_groups,id',
        ]);
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        $userGardenId = null;
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $userGardenId = $garden->id;
            }
        }
        
        // If user is a garden user, validate that they can only create devices for their own garden
        if ($userGardenId && $validated['garden_id'] != $userGardenId) {
            return response()->json(['message' => 'You can only create devices for your own garden'], 403);
        }
        
        // Validate that all garden_groups belong to the garden
        $gardenIdToCheck = $userGardenId ?: $validated['garden_id'];
        $gardenGroups = \App\Models\GardenGroup::whereIn('id', $validated['garden_groups'])
            ->where('garden_id', $gardenIdToCheck)
            ->count();
        
        if ($gardenGroups != count($validated['garden_groups'])) {
            return response()->json(['message' => 'Some groups do not belong to your garden'], 403);
        }
        
        $device = Device::create([
            'name' => $validated['name'],
            'status' => $validated['status'],
            'garden_id' => $validated['garden_id'],
            'garden_groups' => $validated['garden_groups'],
        ]);
        return response()->json($device, 201);
    }

    /**
     * @OA\Get(
     *     path="/api/devices/{id}",
     *     operationId="getDevice",
     *     tags={"Devices"},
     *     summary="Get a specific device",
     *     description="Retrieve detailed information about a specific device.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Device 1"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Device]")
     *         )
     *     )
     * )
     */
    public function show(Request $request, $id)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where('garden_id', $garden->id);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        return $query->findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/devices/{id}",
     *     operationId="updateDevice",
     *     tags={"Devices"},
     *     summary="Update a device",
     *     description="Update an existing device. garden_groups is an array of group IDs.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", maxLength=255, example="Device 1"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Device 1"),
     *             @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Device]")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        $userGardenId = null;
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $userGardenId = $garden->id;
                $query->where('garden_id', $userGardenId);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        $device = $query->findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',
            'garden_id' => 'sometimes|required|exists:gardens,id',
            'garden_groups' => 'sometimes|required|array',
            'garden_groups.*' => 'integer|exists:garden_groups,id',
        ]);
        
        // If user is a garden user, validate that they can only update devices for their own garden
        if ($userGardenId && isset($validated['garden_id']) && $validated['garden_id'] != $userGardenId) {
            return response()->json(['message' => 'You can only update devices for your own garden'], 403);
        }
        
        // If garden_groups is being updated, validate that all groups belong to the garden
        if (isset($validated['garden_groups'])) {
            $gardenIdToCheck = $userGardenId ?: $validated['garden_id'];
            $gardenGroups = \App\Models\GardenGroup::whereIn('id', $validated['garden_groups'])
                ->where('garden_id', $gardenIdToCheck)
                ->count();
            
            if ($gardenGroups != count($validated['garden_groups'])) {
                return response()->json(['message' => 'Some groups do not belong to your garden'], 403);
            }
        }
        
        $device->update($validated);
        return response()->json($device);
    }

    /**
     * @OA\Delete(
     *     path="/api/devices/{id}",
     *     operationId="deleteDevice",
     *     tags={"Devices"},
     *     summary="Delete a device",
     *     description="Delete a specific device by ID.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Device deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Device deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Device]")
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, $id)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where('garden_id', $garden->id);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        $device = $query->findOrFail($id);
        $device->delete();
        return response()->json(['message' => 'Device deleted']);
    }

    /**
     * @OA\Get(
     *     path="/api/devices/{id}/cards",
     *     operationId="getDeviceCards",
     *     tags={"Devices"},
     *     summary="Get cards associated with a device",
     *     description="Retrieve all cards that belong to the garden groups associated with this device.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
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
     *                     @OA\Property(property="status", type="string", example="active"),
     *                     @OA\Property(property="group_id", type="integer", example=1),
     *                     @OA\Property(property="person_type_id", type="integer", example=1),
     *                     @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time"),
     *                     @OA\Property(property="group", type="object"),
     *                     @OA\Property(property="personType", type="object"),
     *                     @OA\Property(property="parents", type="array"),
     *                     @OA\Property(property="people", type="array")
     *                 )
     *             ),
     *             @OA\Property(property="last_page", type="integer", example=5),
     *             @OA\Property(property="per_page", type="integer", example=15),
     *             @OA\Property(property="total", type="integer", example=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Device]")
     *         )
     *     )
     * )
     */
    public function getCards(Request $request, $id)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where('garden_id', $garden->id);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        $device = $query->findOrFail($id);
        
        // Get the garden groups associated with this device
        $deviceGroupIds = $device->garden_groups;
        
        if (empty($deviceGroupIds)) {
            return response()->json(['data' => [], 'current_page' => 1, 'last_page' => 1, 'per_page' => 15, 'total' => 0]);
        }
        
        // Get cards that belong to these groups
        $cardsQuery = \App\Models\Card::with(['group', 'personType', 'parents', 'people'])
            ->whereIn('group_id', $deviceGroupIds);
        
        // Apply filters
        if ($request->filled('search')) {
            $search = $request->query('search');
            $cardsQuery->where(function ($query) use ($search) {
                $query->where('child_first_name', 'like', '%' . $search . '%')
                      ->orWhere('child_last_name', 'like', '%' . $search . '%')
                      ->orWhere('parent_name', 'like', '%' . $search . '%');
            });
        }
        
        if ($request->filled('phone')) {
            $cardsQuery->where('phone', 'like', '%' . $request->query('phone') . '%');
        }
        
        if ($request->filled('status')) {
            $cardsQuery->where('status', $request->query('status'));
        }
        
        if ($request->filled('group_id')) {
            $cardsQuery->where('group_id', $request->query('group_id'));
        }
        
        if ($request->filled('person_type_id')) {
            $cardsQuery->where('person_type_id', $request->query('person_type_id'));
        }
        
        if ($request->filled('parent_code')) {
            $cardsQuery->where('parent_code', $request->query('parent_code'));
        }
        
        if ($request->filled('parent_verification')) {
            $cardsQuery->where('parent_verification', $request->query('parent_verification'));
        }
        
        if ($request->filled('license_type')) {
            $cardsQuery->whereJsonContains('license->type', $request->query('license_type'));
        }
        
        $perPage = $request->query('per_page', 15);
        return $cardsQuery->paginate($perPage);
    }

    /**
     * @OA\Post(
     *     path="/api/devices/{id}/cards",
     *     operationId="createDeviceCard",
     *     tags={"Devices"},
     *     summary="Create a new card for a device",
     *     description="Create a new card that will be associated with the garden groups of this device.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"child_first_name", "child_last_name", "parent_name", "phone", "group_id"},
     *             @OA\Property(property="child_first_name", type="string", maxLength=255, example="Giorgi"),
     *             @OA\Property(property="child_last_name", type="string", maxLength=255, example="Davitashvili"),
     *             @OA\Property(property="parent_name", type="string", maxLength=255, example="Nino Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", enum={"pending","active","inactive"}, example="pending"),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="person_type_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="parent_code", type="string", example="ABC123", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Card created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *             @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *             @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *             @OA\Property(property="phone", type="string", example="+995599123456"),
     *             @OA\Property(property="status", type="string", example="pending"),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="person_type_id", type="integer", example=1),
     *             @OA\Property(property="parent_code", type="string", example="ABC123"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function createCard(Request $request, $id)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        $userGardenId = null;
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $userGardenId = $garden->id;
                $query->where('garden_id', $userGardenId);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        $device = $query->findOrFail($id);
        
        $validated = $request->validate([
            'child_first_name' => 'required|string|max:255',
            'child_last_name' => 'required|string|max:255',
            'parent_name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'status' => 'sometimes|in:pending,active,inactive',
            'group_id' => 'required|exists:garden_groups,id',
            'person_type_id' => 'sometimes|exists:person_types,id',
            'parent_code' => 'sometimes|string|max:255|unique:cards,parent_code',
            'parent_verification' => 'nullable|boolean',
            'license' => 'nullable|array',
            'license.type' => 'nullable|string|in:boolean,date',
            'license.value' => 'nullable',
        ]);
        
        // Validate that the group belongs to one of the device's garden groups
        if (!in_array($validated['group_id'], $device->garden_groups)) {
            return response()->json(['message' => 'The selected group does not belong to this device'], 403);
        }
        
        // Set default status if not provided
        if (!isset($validated['status'])) {
            $validated['status'] = 'pending';
        }
        
        $card = \App\Models\Card::create($validated);
        return response()->json($card->load(['group', 'personType', 'parents', 'people']), 201);
    }

    /**
     * @OA\Get(
     *     path="/api/devices/{id}/cards/{cardId}",
     *     operationId="getDeviceCard",
     *     tags={"Devices"},
     *     summary="Get a specific card for a device",
     *     description="Retrieve a specific card that belongs to the garden groups of this device.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="cardId", in="path", required=true, description="Card ID", @OA\Schema(type="integer")),
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
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="group_id", type="integer", example=1),
     *             @OA\Property(property="person_type_id", type="integer", example=1),
     *             @OA\Property(property="parent_code", type="string", example="ABC123"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="updated_at", type="string", format="date-time"),
     *             @OA\Property(property="group", type="object"),
     *             @OA\Property(property="personType", type="object"),
     *             @OA\Property(property="parents", type="array"),
     *             @OA\Property(property="people", type="array")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device or card not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No query results for model [App\\Models\\Device]")
     *         )
     *     )
     * )
     */
    public function getCard(Request $request, $id, $cardId)
    {
        $query = Device::query();
        
        // Get garden_id from authenticated user if they are a garden user
        $user = $request->user();
        
        if ($user->type === 'garden') {
            $garden = \App\Models\Garden::where('email', $user->email)->first();
            if ($garden) {
                $query->where('garden_id', $garden->id);
            }
        } else {
            // For admin users, allow filtering by garden_id if provided
            if ($request->filled('garden_id')) {
                $query->where('garden_id', $request->query('garden_id'));
            }
        }
        
        $device = $query->findOrFail($id);
        
        // Get the garden groups associated with this device
        $deviceGroupIds = $device->garden_groups;
        
        if (empty($deviceGroupIds)) {
            return response()->json(['message' => 'No groups associated with this device'], 404);
        }
        
        // Get the specific card that belongs to these groups
        $card = \App\Models\Card::with(['group', 'personType', 'parents', 'people'])
            ->whereIn('group_id', $deviceGroupIds)
            ->findOrFail($cardId);
        
        return response()->json($card);
    }
}

/**
 * @OA\Schema(
 *     schema="Device",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Device 1"),
 *     @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
 *     @OA\Property(property="garden_id", type="integer", example=1),
 *     @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
