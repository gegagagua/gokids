<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Device;
use App\Rules\LicenseValueRule;

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
     *     description="Retrieve a list of all devices. Supports filtering by name, status, garden_id. Pagination supported with default 15 items per page.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="name", in="query", required=false, description="Filter by device name", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"active","inactive"})),
     *     @OA\Parameter(name="garden_id", in="query", required=false, description="Filter by garden ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (pagination). Default: 15", @OA\Schema(type="integer", default=15, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number for pagination", @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Device 1"),
     *                     @OA\Property(property="code", type="string", example="X7K9M2", description="Auto-generated 6-character unique code"),
     *                     @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *                     @OA\Property(property="is_logged_in_status", type="boolean", example=true, description="Whether the device is currently logged in"),
     *                     @OA\Property(property="last_login_at", type="string", format="date-time", example="2023-12-01T12:00:00.000000Z", nullable=true, description="Last login timestamp"),
     *                     @OA\Property(property="session_expires_at", type="string", format="date-time", example="2023-12-01T13:00:00.000000Z", nullable=true, description="Session expiration timestamp"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="first_page_url", type="string", example="http://localhost/api/devices?page=1"),
     *             @OA\Property(property="from", type="integer", example=1, description="First item number on current page"),
     *             @OA\Property(property="last_page", type="integer", example=5, description="Last page number"),
     *             @OA\Property(property="last_page_url", type="string", example="http://localhost/api/devices?page=5"),
     *             @OA\Property(property="next_page_url", type="string", example="http://localhost/api/devices?page=2", nullable=true),
     *             @OA\Property(property="path", type="string", example="http://localhost/api/devices"),
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
        } elseif ($user instanceof \App\Models\User && $user->type === 'dister') {
            $dister = \App\Models\Dister::where('email', $user->email)->first();
            $allowedGardenIds = $dister->gardens ?? [];
            if (!empty($allowedGardenIds)) {
                $query->whereIn('garden_id', $allowedGardenIds);
            } else {
                // Return empty when no gardens assigned
                return $query->whereRaw('1 = 0')->paginate($request->query('per_page', 15));
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
        $page = $request->query('page', 1);
        
        $devices = $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
        
        // Add login status to each device
        $devices->getCollection()->transform(function ($device) {
            $device->is_logged_in_status = $device->isLoggedIn();
            $device->last_login_at = $device->last_login_at;
            $device->session_expires_at = $device->session_expires_at;
            return $device;
        });
        
        return $devices;
    }

    /**
     * @OA\Post(
     *     path="/api/devices",
     *     operationId="createDevice",
     *     tags={"Devices"},
     *     summary="Create a new device",
     *     description="Create a new device for a garden. garden_groups is an array of group IDs. Code will be auto-generated.",
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
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="Auto-generated 6-character unique code"),
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
            'code' => 'nullable|string|max:6|unique:devices,code',
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
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="Auto-generated 6-character unique code"),
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
        
        $device = $query->findOrFail($id);
        
        // Load garden groups data
        $gardenGroups = $device->gardenGroups()->get();
        $activeGardenGroups = $device->activeGardenGroups()->get();
        
        // Add garden groups data to device response
        $deviceData = $device->toArray();
        $deviceData['garden_groups_data'] = $gardenGroups;
        $deviceData['active_garden_groups_data'] = $activeGardenGroups;
        
        return $deviceData;
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
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="Auto-generated 6-character unique code"),
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
        
        // If garden_groups was updated, automatically add new groups to active_garden_groups
        if (isset($validated['garden_groups'])) {
            $currentActiveGroups = $device->active_garden_groups ?? [];
            $newGroups = $validated['garden_groups'];
            
            // Find groups that are new (not currently active)
            $groupsToAdd = array_diff($newGroups, $currentActiveGroups);
            
            if (!empty($groupsToAdd)) {
                // Add new groups to active_garden_groups
                $updatedActiveGroups = array_merge($currentActiveGroups, $groupsToAdd);
                $device->update(['active_garden_groups' => $updatedActiveGroups]);
                
                \Log::info("Automatically added new groups to active_garden_groups", [
                    'device_id' => $device->id,
                    'new_groups' => $groupsToAdd,
                    'updated_active_groups' => $updatedActiveGroups
                ]);
            }
        }
        
        return response()->json($device);
    }

    /**
     * @OA\Patch(
     *     path="/api/devices/{id}/status",
     *     operationId="updateDeviceStatus",
     *     tags={"Devices"},
     *     summary="Update device status",
     *     description="Update only the status of a specific device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(property="status", type="string", example="active", enum={"active", "inactive"}, description="Device status")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Device 1"),
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="Auto-generated 6-character unique code"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="message", type="string", example="Status updated successfully")
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
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="status", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateStatus(Request $request, $id)
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
        }
        
        $device = $query->findOrFail($id);
        
        $validated = $request->validate([
            'status' => 'required|string|in:active,inactive',
        ]);

        $device->status = $validated['status'];
        $device->save();

        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'code' => $device->code,
            'status' => $device->status,
            'garden_id' => $device->garden_id,
            'garden_groups' => $device->garden_groups,
            'message' => 'Status updated successfully',
        ]);
    }

    /**
     * @OA\Patch(
     *     path="/api/devices/{id}/active-garden-groups",
     *     operationId="updateDeviceActiveGardenGroups",
     *     tags={"Devices"},
     *     summary="Update device active garden groups",
     *     description="Update the active garden groups for a specific device. Active garden groups must be a subset of the device's assigned garden groups. Groups can only be removed if at least one other device in the same garden has those groups active.",
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"active_garden_groups"},
     *             @OA\Property(property="active_garden_groups", type="array", @OA\Items(type="integer"), example={1,2}, description="Array of garden group IDs to set as active")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Active garden groups updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="name", type="string", example="Device 1"),
     *             @OA\Property(property="code", type="string", example="X7K9M2"),
     *             @OA\Property(property="status", type="string", example="active"),
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *             @OA\Property(property="active_garden_groups", type="array", @OA\Items(type="integer"), example={1,2}),
     *             @OA\Property(property="active_garden_groups_data", type="array", @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Group 1"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )),
     *             @OA\Property(property="message", type="string", example="Active garden groups updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid active garden groups",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Active garden groups must be a subset of assigned garden groups")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot remove groups that are not active on other devices",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Cannot remove groups that are not active on any other device in the same garden")
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
    public function updateActiveGardenGroups(Request $request, $id)
    {
        $device = Device::findOrFail($id);
        
        $validated = $request->validate([
            'active_garden_groups' => 'required|array',
            'active_garden_groups.*' => 'integer|exists:garden_groups,id',
        ]);
        
        // Validate that active garden groups are a subset of assigned garden groups
        $assignedGroups = $device->garden_groups ?? [];
        $activeGroups = $validated['active_garden_groups'];
        
        if (!empty(array_diff($activeGroups, $assignedGroups))) {
            return response()->json([
                'message' => 'Active garden groups must be a subset of assigned garden groups'
            ], 400);
        }
        
        // Get current active groups for this device
        $currentActiveGroups = $device->active_garden_groups ?? [];
        
        // Find groups that are being removed (were active but are not in the new list)
        $removedGroups = array_diff($currentActiveGroups, $activeGroups);
        
        // Check if any of the removed groups are still active on other devices
        // Only allow removal if at least one other device has these groups active
        if (!empty($removedGroups)) {
            $otherDevicesWithActiveGroups = Device::where('id', '!=', $device->id)
                ->where('garden_id', $device->garden_id)
                ->where('is_logged_in', true) // Only consider logged in devices
                ->where(function ($query) use ($removedGroups) {
                    foreach ($removedGroups as $groupId) {
                        $query->orWhereJsonContains('active_garden_groups', $groupId);
                    }
                })
                ->exists();
            
            if (!$otherDevicesWithActiveGroups) {
                return response()->json([
                    'message' => 'Cannot remove groups that are not active on any other logged in device in the same garden'
                ], 422);
            }
        }
        
        $device->update(['active_garden_groups' => $activeGroups]);
        
        // Load active garden groups data
        $activeGardenGroups = $device->activeGardenGroups()->get();
        
        // Find groups that were added (for logging)
        $addedGroups = array_diff($activeGroups, $currentActiveGroups);
        $removedGroups = array_diff($currentActiveGroups, $activeGroups);
        
        $message = 'Active garden groups updated successfully';
        if (!empty($addedGroups)) {
            $message .= '. Added groups: ' . implode(', ', $addedGroups);
        }
        if (!empty($removedGroups)) {
            $message .= '. Removed groups: ' . implode(', ', $removedGroups);
        }
        
        return response()->json([
            'id' => $device->id,
            'name' => $device->name,
            'code' => $device->code,
            'status' => $device->status,
            'garden_id' => $device->garden_id,
            'garden_groups' => $device->garden_groups,
            'active_garden_groups' => $device->active_garden_groups,
            'active_garden_groups_data' => $activeGardenGroups,
            'message' => $message,
            'changes' => [
                'added_groups' => $addedGroups,
                'removed_groups' => $removedGroups
            ]
        ]);
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
     * @OA\Post(
     *     path="/api/devices/{id}/regenerate-code",
     *     operationId="regenerateDeviceCode",
     *     tags={"Devices"},
     *     summary="Regenerate device code",
     *     description="Generate a new random 6-character code for the specified device",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Code regenerated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Device code regenerated successfully"),
     *             @OA\Property(property="device", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Device 1"),
     *                 @OA\Property(property="code", type="string", example="X7K9M2"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=403, description="Unauthorized"),
     *     @OA\Response(response=404, description="Device not found")
     * )
     */
    public function regenerateCode(Request $request, $id)
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
        
        // Generate new unique code
        do {
            $newCode = strtoupper(substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6));
        } while (Device::where('code', $newCode)->where('id', '!=', $id)->exists());
        
        // Update device with new code
        $device->code = $newCode;
        $device->save();
        
        return response()->json([
            'message' => 'Device code regenerated successfully',
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'code' => $device->code,
                'status' => $device->status,
                'garden_id' => $device->garden_id,
                'updated_at' => $device->updated_at
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/devices/{id}/cards",
     *     operationId="getDeviceCards",
     *     tags={"Devices"},
     *     summary="Get cards associated with a device",
     *     description="Retrieve all cards that belong to the garden groups associated with this device. Pagination supported with default 15 items per page.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(name="id", in="path", required=true, description="Device ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="search", in="query", required=false, description="Search in child's and parent's name fields", @OA\Schema(type="string")),
     *     @OA\Parameter(name="phone", in="query", required=false, description="Filter by phone", @OA\Schema(type="string")),
     *     @OA\Parameter(name="status", in="query", required=false, description="Filter by status", @OA\Schema(type="string", enum={"pending","active","inactive"})),
     *     @OA\Parameter(name="group_id", in="query", required=false, description="Filter by group ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="person_type_id", in="query", required=false, description="Filter by person type ID", @OA\Schema(type="integer")),
     *     @OA\Parameter(name="parent_code", in="query", required=false, description="Filter by parent code", @OA\Schema(type="string")),
     *     @OA\Parameter(name="per_page", in="query", required=false, description="Items per page (pagination). Default: 15", @OA\Schema(type="integer", default=15, minimum=1, maximum=100)),
     *     @OA\Parameter(name="page", in="query", required=false, description="Page number for pagination", @OA\Schema(type="integer", default=1, minimum=1)),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="current_page", type="integer", example=1, description="Current page number"),
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
     *                     @OA\Property(property="parents", type="array", @OA\Items(type="object")),
     *                     @OA\Property(property="people", type="array", @OA\Items(type="object"))
     *                 )
     *             ),
     *             @OA\Property(property="first_page_url", type="string", example="http://localhost/api/devices/1/cards?page=1"),
     *             @OA\Property(property="from", type="integer", example=1, description="First item number on current page"),
     *             @OA\Property(property="last_page", type="integer", example=5, description="Last page number"),
     *             @OA\Property(property="last_page_url", type="string", example="http://localhost/api/devices/1/cards?page=5"),
     *             @OA\Property(property="next_page_url", type="string", example="http://localhost/api/devices/1/cards?page=2", nullable=true),
     *             @OA\Property(property="path", type="string", example="http://localhost/api/devices/1/cards"),
     *             @OA\Property(property="per_page", type="integer", example=15, description="Items per page"),
     *             @OA\Property(property="prev_page_url", type="string", example=null, nullable=true),
     *             @OA\Property(property="to", type="integer", example=15, description="Last item number on current page"),
     *             @OA\Property(property="total", type="integer", example=50, description="Total number of items")
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
            'license.value' => ['nullable', new LicenseValueRule],
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
     *             @OA\Property(property="parents", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="people", type="array", @OA\Items(type="object"))
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

    /**
     * @OA\Post(
     *     path="/api/devices/login",
     *     operationId="deviceLogin",
     *     tags={"Devices"},
     *     summary="Device login with code",
     *     description="Authenticate a device using its unique code and return device information",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="6-character unique device code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Login successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Device login successful"),
     *             @OA\Property(property="device", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Device 1"),
     *                 @OA\Property(property="code", type="string", example="X7K9M2"),
     *                 @OA\Property(property="status", type="string", enum={"active","inactive"}, example="active"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *                 @OA\Property(property="garden_groups_data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group 1"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="active_garden_groups", type="array", @OA\Items(type="integer"), example={1,2}),
     *                 @OA\Property(property="active_garden_groups_data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group 1"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="garden", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Garden Name"),
     *                     @OA\Property(property="address", type="string", example="Garden Address"),
     *                     @OA\Property(property="email", type="string", example="garden@example.com"),
     *                     @OA\Property(property="phone", type="string", example="+995599123456")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid device code",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid device code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="code", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function deviceLogin(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $device = Device::with(['garden'])
            ->where('code', $request->code)
            ->where('status', 'active')
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Invalid device code or device is inactive'
            ], 401);
        }

        // Check if device is already logged in with an active session
        if ($device->isLoggedIn()) {
            return response()->json([
                'message' => 'Device is already logged in. Please logout from the current session first.'
            ], 409); // 409 Conflict
        }

        // Check if session is expired and clean it up
        if ($device->isSessionExpired()) {
            $device->endSession();
        }

        // Start a new session (unlimited duration)
        $device->startSession();
        
        // Refresh the device to ensure the session data is properly loaded
        $device->refresh();
        
        // Verify the login status was properly set
        if (!$device->is_logged_in) {
            \Log::error("Device login status not properly set after session start", [
                'device_id' => $device->id,
                'device_code' => $device->code,
                'is_logged_in' => $device->is_logged_in
            ]);
            
            // Force update the status
            $device->is_logged_in = true;
            $device->save();
        }

        // If device has no active garden groups, set all garden groups as active by default
        if (empty($device->active_garden_groups)) {
            $device->active_garden_groups = $device->garden_groups;
            $device->save();
        }

        // Load garden groups data
        $gardenGroups = $device->gardenGroups()->get();
        $activeGardenGroups = $device->activeGardenGroups()->get();
        
        // Add garden groups data to device response
        $deviceData = $device->toArray();
        $deviceData['garden_groups_data'] = $gardenGroups;
        $deviceData['active_garden_groups_data'] = $activeGardenGroups;

        return response()->json([
            'message' => 'Device login successful',
            'device' => $deviceData,
            'session_token' => $device->session_token,
            'session_expires_at' => $device->session_expires_at,
            'is_logged_in' => $device->is_logged_in,
            'login_status' => $device->isLoggedIn()
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/devices/update-expo-token",
     *     operationId="updateDeviceExpoToken",
     *     tags={"Devices"},
     *     summary="Update device Expo push token",
     *     description="Update the Expo push notification token for a specific device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id","expo_token"},
     *             @OA\Property(property="device_id", type="integer", example=1, description="Device ID"),
     *             @OA\Property(property="expo_token", type="string", example="ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]", description="Expo push notification token")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Expo token updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Expo token updated successfully"),
     *             @OA\Property(property="device", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Device 1"),
     *                 @OA\Property(property="expo_token", type="string", example="ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Device not found")
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
     *                 @OA\Property(property="device_id", type="array", @OA\Items(type="string")),
     *                 @OA\Property(property="expo_token", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function updateExpoToken(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'expo_token' => 'required|string|max:255',
        ]);

        $device = Device::findOrFail($validated['device_id']);
        $device->update(['expo_token' => $validated['expo_token']]);

        return response()->json([
            'message' => 'Expo token updated successfully',
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'expo_token' => $device->expo_token,
                'updated_at' => $device->updated_at,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/devices/logout",
     *     operationId="deviceLogout",
     *     tags={"Devices"},
     *     summary="Device logout",
     *     description="Logout a device and end its current session",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="6-character unique device code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Logout successful",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Device logout successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid device code or device not logged in",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Invalid device code or device is not logged in")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 @OA\Property(property="code", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function deviceLogout(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $device = Device::where('code', $request->code)
            ->where('status', 'active')
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Invalid device code or device is inactive'
            ], 401);
        }

        if (!$device->isLoggedIn()) {
            return response()->json([
                'message' => 'Device is not currently logged in'
            ], 401);
        }

        // End the session
        $device->endSession();
        
        // Refresh the device to ensure the session is cleared
        $device->refresh();

        return response()->json([
            'message' => 'Device logout successful'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/devices/me",
     *     operationId="getDeviceMe",
     *     tags={"Devices"},
     *     summary="Get device information by code",
     *     description="Get device information using device code, returns similar data to login endpoint",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="X7K9M2", description="Device code")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Device information retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Device information retrieved successfully"),
     *             @OA\Property(property="device", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="name", type="string", example="Device 1"),
     *                 @OA\Property(property="code", type="string", example="X7K9M2"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="garden_id", type="integer", example=1),
     *                 @OA\Property(property="garden_groups", type="array", @OA\Items(type="integer"), example={1,2,3}),
     *                 @OA\Property(property="active_garden_groups", type="array", @OA\Items(type="integer"), example={1,2}),
     *                 @OA\Property(property="expo_token", type="string", example="ExponentPushToken[xxxxxxxxxxxxxxxxxxxxxx]"),
     *                 @OA\Property(property="is_logged_in", type="boolean", example=true),
     *                 @OA\Property(property="last_login_at", type="string", format="date-time"),
     *                 @OA\Property(property="session_token", type="string", example="abc123..."),
     *                 @OA\Property(property="session_expires_at", type="string", format="date-time"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="garden_groups_data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group 1"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 )),
     *                 @OA\Property(property="active_garden_groups_data", type="array", @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Group 1"),
     *                     @OA\Property(property="garden_id", type="integer", example=1),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time")
     *                 ))
     *             ),
     *             @OA\Property(property="session_token", type="string", example="abc123..."),
     *             @OA\Property(property="session_expires_at", type="string", format="date-time"),
     *             @OA\Property(property="is_logged_in", type="boolean", example=true),
     *             @OA\Property(property="login_status", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Invalid device code or device is inactive",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Invalid device code or device is inactive")
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
    public function deviceMe(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $device = Device::with(['garden'])
            ->where('code', $request->code)
            ->where('status', 'active')
            ->first();

        if (!$device) {
            return response()->json([
                'message' => 'Invalid device code or device is inactive'
            ], 401);
        }

        // Load garden groups data
        $gardenGroups = $device->gardenGroups()->get();
        $activeGardenGroups = $device->activeGardenGroups()->get();
        
        // Add garden groups data to device response
        $deviceData = $device->toArray();
        $deviceData['garden_groups_data'] = $gardenGroups;
        $deviceData['active_garden_groups_data'] = $activeGardenGroups;

        return response()->json([
            'message' => 'Device information retrieved successfully',
            'device' => $deviceData,
            'session_token' => $device->session_token,
            'session_expires_at' => $device->session_expires_at,
            'is_logged_in' => $device->is_logged_in,
            'login_status' => $device->isLoggedIn()
        ]);
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
