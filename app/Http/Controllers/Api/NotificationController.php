<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Notification;
use App\Models\Device;
use App\Models\Card;
use App\Models\CalledCard;
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
     *     path="/api/notifications/card-to-all-devices",
     *     operationId="sendCardToAllDevicesNotification",
     *     tags={"Notifications"},
     *     summary="Send notification from card or people to all devices in the group",
     *     description="Send notification from a specific card or people to all devices that have this card's group in their active_garden_groups. Only the specified card's information is sent.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"title"},
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID (either card_id or people_id must be provided)"),
     *             @OA\Property(property="people_id", type="integer", example=1, description="People ID (either card_id or people_id must be provided)"),
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
     *             @OA\Property(property="remaining_calls", type="integer", example=4, description="Remaining calls for paid gardens without license"),
     *             @OA\Property(property="source_type", type="string", example="card", description="Source type: card or people"),
     *             @OA\Property(property="source_id", type="integer", example=1, description="Source entity ID"),
     *             @OA\Property(property="cards_notified", type="integer", example=2, description="Number of cards in group that were notified"),
     *             @OA\Property(property="group", type="object", description="Group information"),
     *             @OA\Property(property="cards_in_group", type="array", @OA\Items(type="object"), description="List of cards in the group"),
     *             @OA\Property(property="notification_results", type="array", @OA\Items(type="object"), description="Results for each card notification")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - missing or invalid parameters",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Either card_id or people_id must be provided"),
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error_code", type="string", example="MISSING_IDENTIFIER")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card/People not found or no group found",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="No group found for the provided identifier"),
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error_code", type="string", example="NO_GROUP_FOUND")
     *         )
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Too Many Requests - call limit exceeded",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Call limit exceeded. You have reached the maximum of 5 notification calls per day for paid gardens without a license."),
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error_code", type="string", example="CALL_LIMIT_EXCEEDED"),
     *             @OA\Property(property="call_limit", type="integer", example=5),
     *             @OA\Property(property="remaining_calls", type="integer", example=0)
     *         )
     *     )
     * )
     */
    public function sendCardToAllDevices(Request $request)
    {
        $validated = $request->validate([
            'card_id' => 'nullable|integer|exists:cards,id',
            'people_id' => 'nullable|integer|exists:people,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        // Ensure either card_id or people_id is provided, but not both
        $hasCardId = isset($validated['card_id']) && $validated['card_id'];
        $hasPeopleId = isset($validated['people_id']) && $validated['people_id'];
        
        if (!$hasCardId && !$hasPeopleId) {
            return response()->json([
                'message' => 'Either card_id or people_id must be provided',
                'success' => false,
                'error_code' => 'MISSING_IDENTIFIER'
            ], 400);
        }

        // Get the source entity (card or people) and determine the group
        $sourceEntity = null;
        $group = null;
        $isFromPeople = false;

        if ($hasCardId) {
            $sourceEntity = Card::with(['group.garden:id,name,country_id', 'group.garden.countryData', 'personType', 'group.garden.images'])->findOrFail($validated['card_id']);
            $group = $sourceEntity->group;
        } else {
            $people = \App\Models\People::with(['card.group.garden:id,name,country_id', 'card.group.garden.countryData', 'card.personType', 'card.group.garden.images'])->findOrFail($validated['people_id']);
            $sourceEntity = $people;
            $group = $people->card->group;
            $isFromPeople = true;
        }

        if (!$group) {
            return response()->json([
                'message' => 'No group found for the provided identifier',
                'success' => false,
                'error_code' => 'NO_GROUP_FOUND'
            ], 404);
        }

        // Get all cards in the same group for sending notifications
        $cardsInGroup = Card::where('group_id', $group->id)
            ->where('is_deleted', false)
            ->with(['group.garden:id,name,country_id', 'group.garden.countryData', 'personType', 'group.garden.images'])
            ->get();

        if ($cardsInGroup->isEmpty()) {
            return response()->json([
                'message' => 'No active cards found in the group',
                'success' => false,
                'error_code' => 'NO_CARDS_IN_GROUP'
            ], 404);
        }

        // Use the first card for license checking (all cards in group should have same garden)
        $card = $cardsInGroup->first();
        
        // Check if card can make unlimited calls (free garden or has valid license)
        if (!$card->canMakeUnlimitedCalls()) {
            // Check call limit for paid gardens without license
            $callLimit = 5; // Maximum 5 calls per day
            $since = now()->startOfDay();
            
            // Use the source entity ID for call tracking
            $sourceId = $isFromPeople ? $sourceEntity->id : $card->id;
            $callType = $isFromPeople ? 'people_to_all_devices' : 'card_to_all_devices';
            
            if (\App\Models\CardNotificationCall::hasExceededLimit($sourceId, $callType, $callLimit, $since)) {
                $remainingCalls = \App\Models\CardNotificationCall::getRemainingCalls($sourceId, $callType, $callLimit, $since);
                
                return response()->json([
                    'message' => 'Call limit exceeded. You have reached the maximum of ' . $callLimit . ' notification calls per day for paid gardens without a license.',
                    'success' => false,
                    'error_code' => 'CALL_LIMIT_EXCEEDED',
                    'call_limit' => $callLimit,
                    'remaining_calls' => $remainingCalls,
                    'source_type' => $isFromPeople ? 'people' : 'card',
                    'source_id' => $sourceId,
                    'group' => $group,
                    'cards_in_group' => $cardsInGroup->count()
                ], 429); // HTTP 429 Too Many Requests
            }
        }
        
        $expoService = new ExpoNotificationService();
        $allResults = [];
        $totalSuccessCount = 0;

        // Send notification only for the specific card_id (not all cards in group)
        if ($hasCardId) {
            // Use the specific card that was requested
            $cardToNotify = $sourceEntity;
        } else {
            // If people_id was provided, use their card
            $cardToNotify = $sourceEntity->card;
        }


        // Delete existing called card records for this card
        CalledCard::where('card_id', $card->id)->delete();

        // Wait half a second before sending notification
        usleep(300000); // 500,000 microseconds = 0.5 seconds

        $results = $expoService->sendCardToAllDevices(
            $cardToNotify, 
            $validated['title'], 
            $validated['body'] ?? '', 
            $validated['data'] ?? []
        );

        $successCount = is_array($results) ? count(array_filter($results)) : 0;
        $totalSuccessCount = $successCount;
        $allResults[] = [
            'card_id' => $cardToNotify->id,
            'success_count' => $successCount,
            'results' => $results
        ];

        // Record the notification call if it was successful
        if ($totalSuccessCount > 0) {
            $sourceId = $isFromPeople ? $sourceEntity->id : $card->id;
            $callType = $isFromPeople ? 'people_to_all_devices' : 'card_to_all_devices';
            \App\Models\CardNotificationCall::recordCall($sourceId, $callType);
        }

        // Get remaining calls for response
        $remainingCalls = null;
        if (!$card->canMakeUnlimitedCalls()) {
            $callLimit = 5;
            $since = now()->startOfDay();
            $sourceId = $isFromPeople ? $sourceEntity->id : $card->id;
            $callType = $isFromPeople ? 'people_to_all_devices' : 'card_to_all_devices';
            $remainingCalls = \App\Models\CardNotificationCall::getRemainingCalls($sourceId, $callType, $callLimit, $since);
        }

        return response()->json([
            'message' => ($isFromPeople ? 'People' : 'Card') . ' to all devices notification sent',
            'success' => $totalSuccessCount > 0,
            'devices_count' => $totalSuccessCount,
            'remaining_calls' => $remainingCalls,
            'source_type' => $isFromPeople ? 'people' : 'card',
            'source_id' => $isFromPeople ? $sourceEntity->id : $cardToNotify->id,
            'card_notified' => $cardToNotify->id,
            'group' => $group,
            'cards_in_group' => $cardsInGroup->map(function($card) {
                return [
                    'id' => $card->id,
                    'child_first_name' => $card->child_first_name,
                    'child_last_name' => $card->child_last_name,
                    'parent_name' => $card->parent_name,
                    'phone' => $card->phone,
                    'status' => $card->status,
                    'parent_code' => $card->parent_code,
                    'image_url' => $card->image_url,
                ];
            }),
            'notification_results' => $allResults
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
        // $limit = min($request->query('limit', 50), 50); // Cap at 50 maximum
        
        $expoService = new ExpoNotificationService();
        $notifications = $expoService->getDeviceNotifications((int) $deviceId, 500);
        
        return response()->json($notifications);
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
     *             ),
     *             @OA\Property(property="sender_device_id", type="integer", example=5, description="ID of the device that accepted the notification"),
     *             @OA\Property(property="sender_device_name", type="string", example="Garden Device 1", description="Name of the device that accepted the notification"),
     *             @OA\Property(property="total_notifications_sent", type="integer", example=3, description="Total number of notifications sent to cards and people"),
     *             @OA\Property(property="notification_results", type="array", @OA\Items(type="object"), description="Results for each card and people notification sent")
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

        // Get the sender device information
        $senderDevice = $notification->device;
        $senderDeviceId = $senderDevice ? $senderDevice->id : null;
        $senderDeviceName = $senderDevice ? $senderDevice->name : 'Unknown Device';

        // Initialize variables for notification sending
        $totalNotificationsSent = 0;
        $notificationResults = [];
        $expoService = new ExpoNotificationService();
        
        // Get the garden name
        $gardenName = 'Garden';
        if ($senderDevice && $senderDevice->garden_id) {
            $garden = \App\Models\Garden::find($senderDevice->garden_id);
            if ($garden) {
                $gardenName = $garden->name;
            }
        }

        // Send notification to the card owner
        if ($notification->card_id) {
            $card = Card::find($notification->card_id);

            // Create called card record when notification is accepted
            CalledCard::create([
                'card_id' => $notification->card_id,
                'create_date' => now()
            ]);

            if ($card && $card->expo_token) {
                
                $cardOwnerData = [
                    'type' => 'card_notification_accepted',
                    'card_id' => (string) $card->id,
                    'card_phone' => $card->phone,
                    'child_name' => $card->child_first_name . ' ' . $card->child_last_name,
                    'garden_name' => $gardenName,
                    'accepted_at' => now()->toISOString(),
                    'accepted_by_device' => $senderDeviceName,
                    'sender_device_id' => (string) $senderDeviceId,
                    'notification_id' => (string) $notification->id,
                    'image_url' => $card->image_url,
                ];

                $cardOwnerResult = $expoService->sendToCardOwner(
                    $card,
                    'Notification Accepted',
                    "Your notification was accepted at {$gardenName}",
                    $cardOwnerData
                );
                
                if ($cardOwnerResult) {
                    $totalNotificationsSent++;
                    $notificationResults[] = [
                        'type' => 'card_owner_notification',
                        'card_id' => $card->id,
                        'card_phone' => $card->phone,
                        'card_name' => $card->parent_name,
                        'notification_sent' => true
                    ];
                }
            }
        }

        // Reload the notification to get updated data
        $notification->refresh();

        return response()->json([
            'message' => 'Notification accepted successfully',
            'notification' => $notification,
            'sender_device_id' => $senderDeviceId,
            'sender_device_name' => $senderDeviceName,
            'total_notifications_sent' => $totalNotificationsSent,
            'notification_results' => $notificationResults
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
