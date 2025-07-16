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
        if ($request->filled('name')) {
            $query->where('name', 'like', '%' . $request->query('name') . '%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }
        if ($request->filled('garden_id')) {
            $query->where('garden_id', $request->query('garden_id'));
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
    public function show($id)
    {
        return Device::findOrFail($id);
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
        $device = Device::findOrFail($id);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'status' => 'sometimes|required|in:active,inactive',
            'garden_id' => 'sometimes|required|exists:gardens,id',
            'garden_groups' => 'sometimes|required|array',
            'garden_groups.*' => 'integer|exists:garden_groups,id',
        ]);
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
    public function destroy($id)
    {
        $device = Device::findOrFail($id);
        $device->delete();
        return response()->json(['message' => 'Device deleted']);
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
