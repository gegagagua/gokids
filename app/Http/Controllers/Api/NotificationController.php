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
        ]);

        $devices = Device::whereIn('id', $validated['device_ids'])->get();
        $card = $validated['card_id'] ? Card::find($validated['card_id']) : null;
        
        $expoService = new ExpoNotificationService();
        $results = $expoService->sendToMultipleDevices(
            $devices,
            $validated['title'],
            $validated['body'],
            $validated['data'] ?? [],
            $card
        );

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
     * @OA\Get(
     *     path="/api/notifications/device/{deviceId}",
     *     operationId="getDeviceNotifications",
     *     tags={"Notifications"},
     *     summary="Get notifications for a specific device",
     *     description="Retrieve notifications sent to a specific device",
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
     *         description="Number of notifications to retrieve",
     *         required=false,
     *         @OA\Schema(type="integer", default=50)
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
        $limit = $request->query('limit', 50);
        
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
     *     path="/api/notifications/export",
     *     operationId="exportNotifications",
     *     tags={"Notifications"},
     *     summary="Export all notifications",
     *     description="Export all notifications data to Excel file",
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
