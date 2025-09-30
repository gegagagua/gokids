<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;
use App\Services\ExpoNotificationService;
use App\Exports\NotificationExport;
use Maatwebsite\Excel\Facades\Excel;

class NotificationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/notifications",
     *     operationId="getNotifications",
     *     tags={"Notifications"},
     *     summary="Get all notifications",
     *     description="Retrieve a paginated list of all notifications",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "sent", "failed"})
     *     ),
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
     *                     @OA\Property(property="title", type="string", example="Card Updated"),
     *                     @OA\Property(property="body", type="string", example="Card +995555123456 has been updated"),
     *                     @OA\Property(property="status", type="string", example="sent"),
     *                     @OA\Property(property="device", type="object", description="Device information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Device Name")
     *                     ),
     *                     @OA\Property(property="card", type="object", description="Card information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="phone", type="string", example="+995555123456")
     *                     ),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = Notification::with(['device:id,name', 'card:id,phone,status']);
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications",
     *     operationId="storeNotification",
     *     tags={"Notifications"},
     *     summary="Send a notification",
     *     description="Send a notification to a specific device or multiple devices",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title","body","device_ids"},
     *             @OA\Property(property="title", type="string", example="Card Updated", description="Notification title"),
     *             @OA\Property(property="body", type="string", example="Card has been updated", description="Notification body"),
     *             @OA\Property(property="device_ids", type="array", @OA\Items(type="integer"), example={1,2,3}, description="Array of device IDs"),
     *             @OA\Property(property="card_id", type="integer", example=1, nullable=true, description="Card ID (optional)"),
     *             @OA\Property(property="data", type="object", description="Additional data (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Notification sent successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Notifications sent successfully"),
     *             @OA\Property(property="results", type="array", @OA\Items(type="boolean"))
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'device_ids' => 'required|array|min:1',
            'device_ids.*' => 'integer|exists:devices,id',
            'card_id' => 'nullable|integer|exists:cards,id',
            'data' => 'nullable|array',
            'type' => 'nullable|string',
        ]);

        $devices = Device::whereIn('id', $validated['device_ids'])->get();
        $card = $validated['card_id'] ? Card::find($validated['card_id']) : null;
        
        $expoService = new ExpoNotificationService();
        
        // Special handling for child call notifications
        if ($validated['type'] === 'child_call' && $card) {
            $results = [];
            foreach ($devices as $device) {
                $results[] = $expoService->sendChildCall($device, $card);
            }
        } else {
            $results = $expoService->sendToMultipleDevices(
                $devices,
                $validated['title'],
                $validated['body'],
                $validated['data'] ?? [],
                $card
            );
        }

        return response()->json([
            'message' => 'Notifications sent successfully',
            'results' => $results
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/{id}",
     *     operationId="getNotification",
     *     tags={"Notifications"},
     *     summary="Get a specific notification",
     *     description="Retrieve detailed information about a specific notification",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="id", type="integer", example=1),
     *             @OA\Property(property="title", type="string", example="Card Updated"),
     *             @OA\Property(property="body", type="string", example="Card has been updated"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="status", type="string", example="sent"),
     *             @OA\Property(property="device", type="object"),
     *             @OA\Property(property="card", type="object"),
     *             @OA\Property(property="created_at", type="string", format="date-time"),
     *             @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found"
     *     )
     * )
     */
    public function show(string $id)
    {
        return Notification::with(['device:id,name', 'card:id,phone,status'])->findOrFail($id);
    }

    /**
     * @OA\Put(
     *     path="/api/notifications/{id}",
     *     operationId="updateNotification",
     *     tags={"Notifications"},
     *     summary="Update a notification",
     *     description="Update an existing notification",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Updated Title"),
     *             @OA\Property(property="body", type="string", example="Updated body text"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification updated successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found"
     *     )
     * )
     */
    public function update(Request $request, string $id)
    {
        $notification = Notification::findOrFail($id);
        
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'body' => 'sometimes|required|string',
            'data' => 'nullable|array',
        ]);

        $notification->update($validated);
        
        return response()->json($notification);
    }

    /**
     * @OA\Delete(
     *     path="/api/notifications/{id}",
     *     operationId="deleteNotification",
     *     tags={"Notifications"},
     *     summary="Delete a notification",
     *     description="Permanently delete a notification",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Notification deleted")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found"
     *     )
     * )
     */
    public function destroy(string $id)
    {
        $notification = Notification::findOrFail($id);
        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/send-card-info",
     *     operationId="sendCardInfoNotification",
     *     tags={"Notifications"},
     *     summary="Send card info notification",
     *     description="Send a notification with card information to a device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id","card_id","action"},
     *             @OA\Property(property="device_id", type="integer", example=1, description="Device ID"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID"),
     *             @OA\Property(property="action", type="string", example="updated", description="Action performed on card")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Card info notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card info notification sent"),
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     )
     * )
     */
    public function sendCardInfo(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'card_id' => 'required|integer|exists:cards,id',
            'action' => 'required|string|in:created,updated,deleted,status_changed',
        ]);

        $device = Device::findOrFail($validated['device_id']);
        $card = Card::with(['group.garden:id,name'])->findOrFail($validated['card_id']);
        
        $expoService = new ExpoNotificationService();
        $success = $expoService->sendCardInfo($device, $card, $validated['action']);

        return response()->json([
            'message' => 'Card info notification sent',
            'success' => $success
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/device-to-card",
     *     operationId="sendDeviceToCardNotification",
     *     tags={"Notifications"},
     *     summary="Send notification from device to card (parent)",
     *     description="Send notification from device to card - only to the card's phone (parent)",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id","card_id","title","body"},
     *             @OA\Property(property="device_id", type="integer", example=1, description="Device ID"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID"),
     *             @OA\Property(property="title", type="string", example="Child Call", description="Notification title"),
     *             @OA\Property(property="body", type="string", example="Child called from device", description="Notification body"),
     *             @OA\Property(property="data", type="object", description="Additional data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Device to card notification sent"),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599654321"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                 @OA\Property(property="image_path", type="string", example="images/card1.jpg"),
     *                 @OA\Property(property="active_garden_image", type="integer", example=1),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/images/card1.jpg"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object"),
     *                 @OA\Property(property="personType", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function sendDeviceToCard(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'card_id' => 'required|integer|exists:cards,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $device = Device::findOrFail($validated['device_id']);
        $card = Card::with(['group.garden:id,name', 'personType', 'group.garden.images'])->findOrFail($validated['card_id']);
        
        $expoService = new ExpoNotificationService();
        $success = $expoService->sendDeviceToCard(
            $device, 
            $card, 
            $validated['title'], 
            $validated['body'], 
            $validated['data'] ?? []
        );

        // Return the same structure as sendCardInfo
        return response()->json([
            'message' => 'Device to card notification sent',
            'success' => $success,
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'group_id' => $card->group_id,
                'person_type_id' => $card->person_type_id,
                'parent_code' => $card->parent_code,
                'image_path' => $card->image_path,
                'active_garden_image' => $card->active_garden_image,
                'image_url' => $card->image_url,
                'created_at' => $card->created_at,
                'updated_at' => $card->updated_at,
                'group' => $card->group,
                'personType' => $card->personType,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/card-to-device",
     *     operationId="sendCardToDeviceNotification",
     *     tags={"Notifications"},
     *     summary="Send notification from card to device",
     *     description="Send notification from card to device - only to the specific device",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"device_id","card_id","title","body"},
     *             @OA\Property(property="device_id", type="integer", example=1, description="Device ID"),
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID"),
     *             @OA\Property(property="title", type="string", example="Parent Call", description="Notification title"),
     *             @OA\Property(property="body", type="string", example="Parent called from card", description="Notification body"),
     *             @OA\Property(property="data", type="object", description="Additional data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card to device notification sent"),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599654321"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                 @OA\Property(property="image_path", type="string", example="images/card1.jpg"),
     *                 @OA\Property(property="active_garden_image", type="integer", example=1),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/images/card1.jpg"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object"),
     *                 @OA\Property(property="personType", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function sendCardToDevice(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|integer|exists:devices,id',
            'card_id' => 'required|integer|exists:cards,id',
            'title' => 'required|string|max:255',
            'body' => 'required|string',
            'data' => 'nullable|array',
        ]);

        $device = Device::findOrFail($validated['device_id']);
        $card = Card::with(['group.garden:id,name', 'personType', 'group.garden.images'])->findOrFail($validated['card_id']);
        
        $expoService = new ExpoNotificationService();
        $success = $expoService->sendCardToDevice(
            $device, 
            $card, 
            $validated['title'], 
            $validated['body'], 
            $validated['data'] ?? []
        );

        // Return the same structure as sendCardInfo
        return response()->json([
            'message' => 'Card to device notification sent',
            'success' => $success,
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'group_id' => $card->group_id,
                'person_type_id' => $card->person_type_id,
                'parent_code' => $card->parent_code,
                'image_path' => $card->image_path,
                'active_garden_image' => $card->active_garden_image,
                'image_url' => $card->image_url,
                'created_at' => $card->created_at,
                'updated_at' => $card->updated_at,
                'group' => $card->group,
                'personType' => $card->personType,
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/notifications/card-to-all-devices",
     *     operationId="sendCardToAllDevicesNotification",
     *     tags={"Notifications"},
     *     summary="Send notification from card to all devices in the group",
     *     description="Send notification from a card to all devices that have this card's group in their active_garden_groups",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_id","title"},
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID"),
     *             @OA\Property(property="title", type="string", example="Parent Call", description="Notification title"),
     *             @OA\Property(property="body", type="string", example="Parent called from card", description="Notification body (optional)"),
     *             @OA\Property(property="data", type="object", description="Additional data")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification sent successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Card to all devices notification sent"),
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="devices_count", type="integer", example=3),
     *             @OA\Property(property="card", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="child_first_name", type="string", example="Giorgi"),
     *                 @OA\Property(property="child_last_name", type="string", example="Davitashvili"),
     *                 @OA\Property(property="parent_name", type="string", example="Nino Davitashvili"),
     *                 @OA\Property(property="phone", type="string", example="+995599654321"),
     *                 @OA\Property(property="status", type="string", example="active"),
     *                 @OA\Property(property="group_id", type="integer", example=1),
     *                 @OA\Property(property="person_type_id", type="integer", example=1),
     *                 @OA\Property(property="parent_code", type="string", example="ABC123"),
     *                 @OA\Property(property="image_path", type="string", example="images/card1.jpg"),
     *                 @OA\Property(property="active_garden_image", type="integer", example=1),
     *                 @OA\Property(property="image_url", type="string", example="https://example.com/images/card1.jpg"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time"),
     *                 @OA\Property(property="group", type="object"),
     *                 @OA\Property(property="personType", type="object")
     *             )
     *         )
     *     )
     * )
     */
    public function sendCardToAllDevices(Request $request)
    {
        $validated = $request->validate([
            'card_id' => 'required|integer|exists:cards,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        $card = Card::with(['group.garden:id,name', 'personType', 'group.garden.images'])->findOrFail($validated['card_id']);
        
        $expoService = new ExpoNotificationService();
        $results = $expoService->sendCardToAllDevices(
            $card, 
            $validated['title'], 
            $validated['body'] ?? '', 
            $validated['data'] ?? []
        );

        // Count successful notifications
        $successCount = is_array($results) ? count(array_filter($results)) : 0;

        return response()->json([
            'message' => 'Card to all devices notification sent',
            'success' => $successCount > 0,
            'devices_count' => $successCount,
            'card' => [
                'id' => $card->id,
                'child_first_name' => $card->child_first_name,
                'child_last_name' => $card->child_last_name,
                'parent_name' => $card->parent_name,
                'phone' => $card->phone,
                'status' => $card->status,
                'group_id' => $card->group_id,
                'person_type_id' => $card->person_type_id,
                'parent_code' => $card->parent_code,
                'image_path' => $card->image_path,
                'active_garden_image' => $card->active_garden_image,
                'image_url' => $card->image_url,
                'created_at' => $card->created_at,
                'updated_at' => $card->updated_at,
                'group' => $card->group,
                'personType' => $card->personType,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/device/{deviceId}",
     *     operationId="getDeviceNotifications",
     *     tags={"Notifications"},
     *     summary="Get notifications for a specific device",
     *     description="Retrieve notifications sent to a specific device from the last 24 hours (max 50 notifications)",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="deviceId",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of notifications to retrieve (max 50, from last 24 hours)",
     *         required=false,
     *         @OA\Schema(type="integer", default=50, maximum=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer"),
     *                 @OA\Property(property="title", type="string"),
     *                 @OA\Property(property="body", type="string"),
     *                 @OA\Property(property="status", type="string"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function getDeviceNotifications(string $deviceId, Request $request)
    {
        $limit = min($request->query('limit', 50), 50); // Cap at 50 maximum
        
        $expoService = new ExpoNotificationService();
        $notifications = $expoService->getDeviceNotifications((int) $deviceId, $limit);
        
        return response()->json($notifications);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/stats",
     *     operationId="getNotificationStats",
     *     tags={"Notifications"},
     *     summary="Get notification statistics",
     *     description="Retrieve statistics about notifications",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="total", type="integer", example=100),
     *             @OA\Property(property="pending", type="integer", example=5),
     *             @OA\Property(property="sent", type="integer", example=90),
     *             @OA\Property(property="failed", type="integer", example=5)
     *         )
     *     )
     * )
     */
    public function getStats()
    {
        $expoService = new ExpoNotificationService();
        $stats = $expoService->getNotificationStats();
        
        return response()->json($stats);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/device/{deviceId}/child-calls",
     *     operationId="getDeviceChildCallNotifications",
     *     tags={"Notifications"},
     *     summary="Get child call notifications for a specific device",
     *     description="Retrieve the last 10 child call notifications sent to a specific device from the last 24 hours",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="deviceId",
     *         in="path",
     *         required=true,
     *         description="Device ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="device_id", type="integer", example=1),
     *             @OA\Property(property="device_name", type="string", example="Device Name"),
     *             @OA\Property(property="notifications", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="title", type="string", example="Child Call Notification"),
     *                     @OA\Property(property="body", type="string", example="Child called from device"),
     *                     @OA\Property(property="data", type="object", description="Notification data with card information"),
     *                     @OA\Property(property="status", type="string", example="sent"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(property="total_count", type="integer", example=10)
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found"
     *     )
     * )
     */
    public function getDeviceChildCallNotifications(string $deviceId)
    {
        $device = Device::findOrFail($deviceId);
        
        // Get the last 10 child call notifications for this device from the last 24 hours
        $notifications = Notification::where('device_id', $deviceId)
            ->where('data', 'like', '%child_call%')
            ->where('created_at', '>=', now()->subHours(24))
            ->with(['card:id,child_first_name,child_last_name,phone,status'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'device_id' => $device->id,
            'device_name' => $device->name,
            'notifications' => $notifications,
            'total_count' => $notifications->count()
        ]);
    }


    /**
     * @OA\Post(
     *     path="/api/notifications/{notificationId}/accept",
     *     operationId="acceptNotification",
     *     tags={"Notifications"},
     *     summary="Accept a notification",
     *     description="Mark a notification as accepted when device button is pressed. Sends confirmation notifications to parent devices and people linked to the card.",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="notificationId",
     *         in="path",
     *         required=true,
     *         description="Notification ID",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Notification accepted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Notification accepted successfully"),
     *             @OA\Property(property="notification", type="object",
     *                 @OA\Property(property="id", type="integer", example=1),
     *                 @OA\Property(property="title", type="string", example="Card Info"),
     *                 @OA\Property(property="body", type="string", example="Card information sent"),
     *                 @OA\Property(property="status", type="string", example="accepted"),
     *                 @OA\Property(property="accepted_at", type="string", format="date-time"),
     *                 @OA\Property(property="card", type="object", description="Card information")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Notification not found"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Notification already accepted or invalid status"
     *     )
     * )
     */
    public function acceptNotification(string $notificationId)
    {
        $notification = Notification::with(['card:id,child_first_name,child_last_name,phone,status,group_id', 'device.garden'])
            ->findOrFail($notificationId);

        // Check if notification is already accepted
        if ($notification->status === 'accepted') {
            return response()->json([
                'message' => 'Notification already accepted',
                'notification' => $notification
            ], 400);
        }

        // Update notification status to accepted
        $notification->status = 'accepted';
        $notification->accepted_at = now();
        $notification->save();

        // Send notification back to the card's parent (the one who sent the notification)
        if ($notification->card) {
            $card = $notification->card;
            $currentDevice = $notification->device;
            
            // Load the card's group and garden relationships
            $card->load(['group.garden']);
            
            // Get the garden name from the device's garden_id
            $gardenName = 'Garden';
            if ($currentDevice->garden_id) {
                $garden = \App\Models\Garden::find($currentDevice->garden_id);
                if ($garden) {
                    $gardenName = $garden->name;
                }
            }
            
            $expoService = new ExpoNotificationService();
            
            // Get devices that have this card's group in their active_garden_groups
            // but are not the current device (these are the parent devices)
            if (!$card->group || !$card->group->garden) {
                \Log::error("Card {$card->id} has no group or garden relationship");
                return response()->json([
                    'message' => 'Card has no group or garden relationship',
                    'notification' => $notification
                ], 500);
            }
            
        
            $parentDevices = Device::where('garden_id', $card->group->garden->id)
                ->where('id', '!=', $currentDevice->id)
                ->where('status', 'active')
                ->whereNotNull('expo_token')
                ->where(function($query) use ($card) {
                    // Find parent devices using multiple strategies:
                    // 1. Devices with "Parent" in their name (most common)
                    // 2. Devices that have this card's group in active_garden_groups
                    // 3. Devices with null or empty active_garden_groups (default parent behavior)
                    // 4. Devices that don't have "Garden" in their name (to exclude garden devices)
                    $query->where('name', 'like', '%Parent%')
                          ->orWhere(function($subQuery) use ($card) {
                              $subQuery->where(function($groupQuery) use ($card) {
                                  $groupQuery->whereJsonContains('active_garden_groups', $card->group_id)
                                           ->orWhereNull('active_garden_groups')
                                           ->orWhere('active_garden_groups', '[]')
                                           ->orWhere('active_garden_groups', '');
                              })
                              ->where('name', 'not like', '%Garden%');
                          });
                })
                ->get();
            
            // If no parent devices found with the primary strategy, try a fallback
            if ($parentDevices->count() === 0) {
                \Log::warning("No parent devices found with primary strategy, trying fallback for card {$card->id}");
                
                $parentDevices = Device::where('garden_id', $card->group->garden->id)
                    ->where('id', '!=', $currentDevice->id)
                    ->where('status', 'active')
                    ->whereNotNull('expo_token')
                    ->where(function($query) {
                        // Fallback: any device that looks like a parent device
                        $query->where('name', 'like', '%Parent%')
                              ->orWhere('name', 'like', '%Mobile%')
                              ->orWhere(function($subQuery) {
                                  $subQuery->where('name', 'not like', '%Garden%')
                                           ->where('name', 'not like', '%Device -%'); 
                              });
                    })
                    ->get();
                    
        
            }
            
            foreach ($parentDevices as $parentDevice) {
                try {
                    $acceptanceData = [
                        'type' => 'card_accepted',
                        'card_id' => (string) $card->id,
                        'card_phone' => $card->phone,
                        'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                        'garden_name' => $gardenName,
                        'accepted_at' => now()->toISOString(),
                        'accepted_by_device' => $currentDevice->name ?? 'Device',
                    ];
                    
                 
                    $result = $expoService->sendToDevice(
                        $parentDevice,
                        'Card Accepted',
                        "Card for {$card->child_first_name} was accepted at the garden",
                        $acceptanceData,
                        $card
                    );
                    
                } catch (\Exception $e) {                    // Silent fail for individual devices
                }
            }

            // Send notification to people who have this card linked to them
            $people = \App\Models\People::where('card_id', $card->id)->get();
            
            foreach ($people as $person) {
                try {
                    $acceptanceData = [
                        'type' => 'notification_accepted',
                        'notification_id' => (string) $notification->id,
                        'card_id' => (string) $card->id,
                        'card_phone' => $card->phone,
                        'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                        'garden_name' => $gardenName,
                        'accepted_at' => now()->toISOString(),
                        'accepted_by_device' => $currentDevice->name ?? 'Device',
                        'person_name' => $person->name,
                        'person_phone' => $person->phone,
                    ];
                    
                    // Create a notification record for the person
                    \App\Models\Notification::create([
                        'title' => 'Notification Accepted',
                        'body' => "Your notification for {$card->child_first_name} was accepted at the garden",
                        'data' => $acceptanceData,
                        'expo_token' => null, // People don't have expo tokens, this is just for record keeping
                        'device_id' => null, // People don't have devices
                        'card_id' => $card->id,
                        'status' => 'sent', // Mark as sent since it's a confirmation
                        'sent_at' => now(),
                    ]);
                    
                    \Log::info("Notification acceptance confirmation created for person {$person->id} ({$person->name}) for card {$card->id}");
                    
                } catch (\Exception $e) {
                    \Log::error("Failed to create notification acceptance confirmation for person {$person->id}: " . $e->getMessage());
                }
            }
        }

        // Reload the notification to get updated data
        $notification->refresh();

        return response()->json([
            'message' => 'Notification accepted successfully',
            'notification' => $notification
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/export",
     *     operationId="exportNotifications",
     *     tags={"Notifications"},
     *     summary="Export today's notifications",
     *     description="Export notifications from the current day only (not last 24 hours) to Excel file",
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Excel file download",
     *         @OA\Header(
     *             header="Content-Disposition",
     *             description="Attachment filename",
     *             @OA\Schema(type="string", example="attachment; filename=notifications.xlsx")
     *         ),
     *         @OA\Header(
     *             header="Content-Type",
     *             description="File content type",
     *             @OA\Schema(type="string", example="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet")
     *         )
     *     )
     * )
     */
    public function export()
    {
        return Excel::download(new NotificationExport, 'notifications.xlsx');
    }
}
