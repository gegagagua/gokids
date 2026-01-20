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
        // CRITICAL DEBUG: Log incoming request
        \Log::info('NotificationController::sendCardToAllDevices: INCOMING REQUEST', [
            'request_method' => $request->method(),
            'request_url' => $request->fullUrl(),
            'request_ip' => $request->ip(),
            'request_headers' => $request->headers->all(),
            'request_all_data' => $request->all(),
            'request_json' => $request->json()->all() ?? null,
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toIso8601String(),
        ]);

        $validated = $request->validate([
            'card_id' => 'nullable|integer|exists:cards,id',
            'people_id' => 'nullable|integer|exists:people,id',
            'title' => 'required|string|max:255',
            'body' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        // CRITICAL DEBUG: Log validated data
        \Log::info('NotificationController::sendCardToAllDevices: VALIDATED DATA', [
            'validated' => $validated,
            'card_id' => $validated['card_id'] ?? null,
            'people_id' => $validated['people_id'] ?? null,
            'title' => $validated['title'] ?? null,
            'body' => $validated['body'] ?? null,
            'body_length' => isset($validated['body']) ? strlen($validated['body']) : 0,
            'body_empty' => empty($validated['body'] ?? ''),
            'data' => $validated['data'] ?? null,
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
            
            // CRITICAL DEBUG: Log card data from database
            \Log::info('NotificationController::sendCardToAllDevices: CARD FROM DATABASE', [
                'card_id' => $sourceEntity->id,
                'card_child_first_name' => $sourceEntity->child_first_name,
                'card_child_last_name' => $sourceEntity->child_last_name,
                'card_child_full_name' => $sourceEntity->child_first_name . ' ' . $sourceEntity->child_last_name,
                'card_parent_name' => $sourceEntity->parent_name,
                'card_phone' => $sourceEntity->phone,
                'card_group_id' => $sourceEntity->group_id,
                'card_status' => $sourceEntity->status,
                'card_image_url' => $sourceEntity->image_url,
            ]);
        } else {
            $people = \App\Models\People::with(['card.group.garden:id,name,country_id', 'card.group.garden.countryData', 'card.personType', 'card.group.garden.images'])->findOrFail($validated['people_id']);
            $sourceEntity = $people;
            $group = $people->card->group;
            $isFromPeople = true;
            
            // CRITICAL DEBUG: Log people and card data from database
            \Log::info('NotificationController::sendCardToAllDevices: PEOPLE FROM DATABASE', [
                'people_id' => $people->id,
                'people_name' => $people->name,
                'people_phone' => $people->phone,
                'card_id' => $people->card->id ?? null,
                'card_child_first_name' => $people->card->child_first_name ?? null,
                'card_child_last_name' => $people->card->child_last_name ?? null,
                'card_child_full_name' => ($people->card->child_first_name ?? '') . ' ' . ($people->card->child_last_name ?? ''),
                'card_parent_name' => $people->card->parent_name ?? null,
            ]);
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

        // LOG STEP 6: License check
        $canMakeUnlimitedCalls = $card->canMakeUnlimitedCalls();

        // Check if card can make unlimited calls (free garden or has valid license)
        if (!$canMakeUnlimitedCalls) {
            // Determine which card to check for free_calls_remaining
            $cardToCheck = $isFromPeople ? $sourceEntity->card : $sourceEntity;
            
            // Refresh card to get latest free_calls_remaining value
            $cardToCheck->refresh();
            
            // Check if free_calls_remaining is 0 or less
            if ($cardToCheck->free_calls_remaining <= 0) {
                return response()->json([
                    'message' => 'Call limit exceeded. You have reached the maximum of free calls for paid gardens without a license.',
                    'success' => false,
                    'error_code' => 'CALL_LIMIT_EXCEEDED',
                    'free_calls_remaining' => $cardToCheck->free_calls_remaining,
                    'source_type' => $isFromPeople ? 'people' : 'card',
                    'source_id' => $isFromPeople ? $sourceEntity->id : $cardToCheck->id,
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

        // Refresh card to get latest free_calls_remaining value and load required relations
        $cardToNotify->refresh();
        $cardToNotify->load(['group.garden.countryData']);

        // CRITICAL DEBUG: Log card to notify data from database
        \Log::info('NotificationController::sendCardToAllDevices: CARD TO NOTIFY FROM DATABASE', [
            'card_id' => $cardToNotify->id,
            'card_child_first_name' => $cardToNotify->child_first_name,
            'card_child_last_name' => $cardToNotify->child_last_name,
            'card_child_full_name' => $cardToNotify->child_first_name . ' ' . $cardToNotify->child_last_name,
            'card_parent_name' => $cardToNotify->parent_name,
            'card_phone' => $cardToNotify->phone,
            'card_group_id' => $cardToNotify->group_id,
            'card_status' => $cardToNotify->status,
            'free_calls_remaining' => $cardToNotify->free_calls_remaining,
        ]);

        // - Paid countries with valid license: Always allowed, no decrement
        // - Paid countries without license: Decrement and check limit
        if (!$cardToNotify->decrementFreeCalls()) {
            return response()->json([
                'message' => 'No free calls remaining. You have used all your free calls.',
                'success' => false,
                'error_code' => 'NO_FREE_CALLS_REMAINING',
                'free_calls_remaining' => $cardToNotify->free_calls_remaining,
                'source_type' => $isFromPeople ? 'people' : 'card',
                'source_id' => $isFromPeople ? $sourceEntity->id : $cardToNotify->id,
            ], 403); // HTTP 403 Forbidden
        }

        // Refresh to get updated value after potential decrement
        $cardToNotify->refresh();

        // Delete existing called card records for this card
        CalledCard::where('card_id', $cardToNotify->id)->delete();

        // Wait half a second before sending notification
        usleep(300000); // 500,000 microseconds = 0.5 seconds

        // CRITICAL: Add sender information to notification data
        // This allows acceptance notification to route back to the correct parent
        $notificationData = $validated['data'] ?? [];

        // Include sender's expo_token directly in notification data
        // Use the expo_token from request if provided (frontend sends actual sender's token)
        // Otherwise fallback to card's expo_token
        $senderExpoToken = $notificationData['sender_expo_token'] ?? $cardToNotify->expo_token;

        if ($isFromPeople) {
            // Notification sent by shared parent (People)
            $notificationData['sender_expo_token'] = $senderExpoToken;
            $notificationData['sender_type'] = 'people';
            $notificationData['sender_people_id'] = $sourceEntity->id;
            $notificationData['sender_name'] = $sourceEntity->name;
        } else {
            // Notification sent by main parent (Card)
            $notificationData['sender_expo_token'] = $senderExpoToken;
            $notificationData['sender_type'] = 'card';
            $notificationData['sender_name'] = $cardToNotify->parent_name;
        }

        // CRITICAL DEBUG: Log before sending notification
        \Log::info('NotificationController::sendCardToAllDevices: PREPARING TO SEND', [
            'card_id' => $cardToNotify->id,
            'card_child_first_name_db' => $cardToNotify->child_first_name,
            'card_child_last_name_db' => $cardToNotify->child_last_name,
            'card_child_full_name_db' => $cardToNotify->child_first_name . ' ' . $cardToNotify->child_last_name,
            'card_parent_name_db' => $cardToNotify->parent_name,
            'title_from_request' => $validated['title'] ?? null,
            'body_from_request' => $validated['body'] ?? null,
            'body_provided' => isset($validated['body']),
            'body_empty' => empty($validated['body'] ?? ''),
            'body_length' => isset($validated['body']) ? strlen($validated['body']) : 0,
            'is_from_people' => $isFromPeople,
            'notification_data' => $notificationData,
            'sender_expo_token' => $senderExpoToken,
        ]);

        $results = $expoService->sendCardToAllDevices(
            $cardToNotify,
            $validated['title'],
            $validated['body'] ?? '',
            $notificationData
        );

        $successCount = is_array($results) ? count(array_filter($results)) : 0;
        $totalSuccessCount = $successCount;
        $allResults[] = [
            'card_id' => $cardToNotify->id,
            'success_count' => $successCount,
            'results' => $results
        ];

        // CRITICAL DEBUG: Log results from ExpoNotificationService
        \Log::info('NotificationController::sendCardToAllDevices: RESULTS FROM EXPO SERVICE', [
            'card_id' => $cardToNotify->id,
            'results' => $results,
            'success_count' => $successCount,
            'total_success_count' => $totalSuccessCount,
            'all_results' => $allResults,
        ]);

        // Record the notification call if it was successful
        if ($totalSuccessCount > 0) {
            $sourceId = $isFromPeople ? $sourceEntity->id : $card->id;
            $callType = $isFromPeople ? 'people_to_all_devices' : 'card_to_all_devices';
            \App\Models\CardNotificationCall::recordCall($sourceId, $callType);
        }

        // Refresh card to get updated free_calls_remaining after decrement
        $cardToNotify->refresh();

        // Get remaining calls for response (use free_calls_remaining)
        $remainingCalls = null;
        if (!$card->canMakeUnlimitedCalls()) {
            $remainingCalls = $cardToNotify->free_calls_remaining;
        }

        $responseData = [
            'message' => ($isFromPeople ? 'People' : 'Card') . ' to all devices notification sent',
            'success' => $totalSuccessCount > 0,
            'devices_count' => $totalSuccessCount,
            'remaining_calls' => $remainingCalls,
            'free_calls_remaining' => $cardToNotify->free_calls_remaining,
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
        ];

        // CRITICAL DEBUG: Log response before sending to mobile app
        \Log::info('NotificationController::sendCardToAllDevices: RESPONSE TO MOBILE APP', [
            'card_id' => $cardToNotify->id,
            'response_data' => $responseData,
            'response_success' => $responseData['success'],
            'response_devices_count' => $responseData['devices_count'],
            'response_remaining_calls' => $responseData['remaining_calls'],
            'response_free_calls_remaining' => $responseData['free_calls_remaining'],
            'response_notification_results' => $responseData['notification_results'],
        ]);

        return response()->json($responseData);
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
     * @OA\Get(
     *     path="/api/notifications/device/{deviceId}/history",
     *     operationId="getDeviceHistory",
     *     tags={"Notifications"},
     *     summary="Get device notification history",
     *     description="Retrieve all notifications for a specific device that have been successfully sent (status: 'sent'). This endpoint returns the complete history of sent notifications for the device, ordered by creation date (newest first). No authentication required.",
     *     @OA\Parameter(
     *         name="deviceId",
     *         in="path",
     *         required=true,
     *         description="The ID of the device to retrieve notification history for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation - Returns array of sent notifications",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
     *                 @OA\Property(property="title", type="string", example="Card Updated", description="Notification title"),
     *                 @OA\Property(property="body", type="string", example="Card +995555123456 has been updated", description="Notification body/content"),
     *                 @OA\Property(property="data", type="object", description="Additional notification data (JSON object)"),
     *                 @OA\Property(property="status", type="string", example="sent", description="Notification status (always 'sent' for this endpoint)"),
     *                 @OA\Property(property="device_id", type="integer", example=1, description="ID of the device that received the notification"),
     *                 @OA\Property(property="card_id", type="integer", example=1, nullable=true, description="ID of the associated card (if any)"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was sent"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was created"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was last updated"),
     *                 @OA\Property(property="device", type="object", nullable=true, description="Device information",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Garden Device 1")
     *                 ),
     *                 @OA\Property(property="card", type="object", nullable=true, description="Card information (if associated)",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="+995555123456"),
     *                     @OA\Property(property="status", type="string", example="active")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Device not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Device not found")
     *         )
     *     )
     * )
     */
    public function getDeviceHistory(string $deviceId)
    {
        // Verify device exists
        $device = Device::find($deviceId);
        if (!$device) {
            return response()->json([
                'message' => 'Device not found'
            ], 404);
        }

        // Get all notifications for this device with status 'sent' from the last 5 minutes
        $notifications = Notification::where('device_id', $deviceId)
            ->where('status', 'sent')
            ->where('created_at', '>=', now()->subMinutes(5))
            ->with(['device:id,name', 'card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/card/{cardId}/history",
     *     operationId="getCardHistory",
     *     tags={"Notifications"},
     *     summary="Get card notification history",
     *     description="Retrieve all notifications for a specific card from the last 20 minutes. This endpoint returns recent notifications associated with the card, ordered by creation date (newest first). No authentication required.",
     *     @OA\Parameter(
     *         name="cardId",
     *         in="path",
     *         required=true,
     *         description="The ID of the card to retrieve notification history for",
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation - Returns array of notifications from last 20 minutes",
     *         @OA\JsonContent(
     *             type="array",
     *             @OA\Items(
     *                 type="object",
     *                 @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
     *                 @OA\Property(property="title", type="string", example="Parent Call", description="Notification title"),
     *                 @OA\Property(property="body", type="string", example="A parent is calling from card", description="Notification body/content"),
     *                 @OA\Property(property="data", type="object", description="Additional notification data (JSON object)"),
     *                 @OA\Property(property="status", type="string", example="sent", description="Notification status"),
     *                 @OA\Property(property="device_id", type="integer", example=1, description="ID of the device that received the notification"),
     *                 @OA\Property(property="card_id", type="integer", example=1, description="ID of the associated card"),
     *                 @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was sent"),
     *                 @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true, example="2024-01-15T10:35:00.000000Z", description="Timestamp when notification was accepted"),
     *                 @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was created"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was last updated"),
     *                 @OA\Property(property="device", type="object", nullable=true, description="Device information",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Garden Device 1")
     *                 ),
     *                 @OA\Property(property="card", type="object", nullable=true, description="Card information",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="+995555123456"),
     *                     @OA\Property(property="status", type="string", example="active")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Card not found",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="Card not found")
     *         )
     *     )
     * )
     */
    public function getCardHistory(string $cardId)
    {
        // Verify card exists
        $card = Card::find($cardId);
        if (!$card) {
            return response()->json([
                'message' => 'Card not found'
            ], 404);
        }

        // Get notifications for this card from the last 20 minutes
        $twentyMinutesAgo = now()->subMinutes(20);

        $notifications = Notification::where('card_id', $cardId)
            ->where('created_at', '>=', $twentyMinutesAgo)
            ->with(['device:id,name', 'card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    /**
     * @OA\Get(
     *     path="/api/notifications/parent/{parentPhone}/history",
     *     operationId="getParentCardHistoryByPhone",
     *     tags={"Notifications"},
     *     summary="Get notification history for all cards attached to a parent",
     *     description="Retrieve all notifications for all cards associated with a parent (identified by phone number) from the last 20 minutes. This endpoint returns recent notifications across multiple cards, ordered by creation date (newest first). No authentication required.",
     *     @OA\Parameter(
     *         name="parentPhone",
     *         in="path",
     *         required=true,
     *         description="The phone number of the parent to retrieve card notification history for",
     *         @OA\Schema(type="string", example="+995555123456")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation - Returns array of notifications from last 20 minutes across all parent's cards",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="parent_phone", type="string", example="+995555123456", description="Phone number of the parent"),
     *             @OA\Property(property="cards_count", type="integer", example=2, description="Number of cards associated with this parent"),
     *             @OA\Property(property="cards", type="array", description="List of cards for this parent",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="phone", type="string", example="+995555123456"),
     *                     @OA\Property(property="parent_name", type="string", example="John Doe"),
     *                     @OA\Property(property="child_first_name", type="string", example="Alice"),
     *                     @OA\Property(property="child_last_name", type="string", example="Doe"),
     *                     @OA\Property(property="status", type="string", example="active")
     *                 )
     *             ),
     *             @OA\Property(property="notifications", type="array", description="Notifications from all cards (newest first)",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1, description="Notification ID"),
     *                     @OA\Property(property="title", type="string", example="Parent Call", description="Notification title"),
     *                     @OA\Property(property="body", type="string", example="A parent is calling from card", description="Notification body/content"),
     *                     @OA\Property(property="data", type="object", description="Additional notification data (JSON object)"),
     *                     @OA\Property(property="status", type="string", example="sent", description="Notification status"),
     *                     @OA\Property(property="device_id", type="integer", example=1, description="ID of the device that received the notification"),
     *                     @OA\Property(property="card_id", type="integer", example=1, description="ID of the associated card"),
     *                     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true, example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was sent"),
     *                     @OA\Property(property="accepted_at", type="string", format="date-time", nullable=true, example="2024-01-15T10:35:00.000000Z", description="Timestamp when notification was accepted"),
     *                     @OA\Property(property="created_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was created"),
     *                     @OA\Property(property="updated_at", type="string", format="date-time", example="2024-01-15T10:30:00.000000Z", description="Timestamp when notification was last updated"),
     *                     @OA\Property(property="device", type="object", nullable=true, description="Device information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="name", type="string", example="Garden Device 1")
     *                     ),
     *                     @OA\Property(property="card", type="object", nullable=true, description="Card information",
     *                         @OA\Property(property="id", type="integer", example=1),
     *                         @OA\Property(property="phone", type="string", example="+995555123456"),
     *                         @OA\Property(property="status", type="string", example="active")
     *                     )
     *                 )
     *             ),
     *             @OA\Property(property="total_notifications", type="integer", example=5, description="Total number of notifications across all cards")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="No cards found for the given parent phone number",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="No cards found for the provided parent phone number"),
     *             @OA\Property(property="parent_phone", type="string", example="+995555123456")
     *         )
     *     )
     * )
     */
    public function getParentCardHistoryByPhone(string $parentPhone)
    {
        // Find all cards associated with this parent phone number
        $cards = Card::where('phone', $parentPhone)
            ->where('is_deleted', false)
            ->get();

        if ($cards->isEmpty()) {
            return response()->json([
                'message' => 'No cards found for the provided parent phone number',
                'parent_phone' => $parentPhone
            ], 404);
        }

        // Extract card IDs
        $cardIds = $cards->pluck('id')->toArray();

        // Get notifications for all these cards from the last 20 minutes
        $twentyMinutesAgo = now()->subMinutes(20);

        $notifications = Notification::whereIn('card_id', $cardIds)
            ->where('created_at', '>=', $twentyMinutesAgo)
            ->with(['device:id,name', 'card:id,phone,status'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'parent_phone' => $parentPhone,
            'cards_count' => $cards->count(),
            'cards' => $cards->map(function($card) {
                return [
                    'id' => $card->id,
                    'phone' => $card->phone,
                    'parent_name' => $card->parent_name,
                    'child_first_name' => $card->child_first_name,
                    'child_last_name' => $card->child_last_name,
                    'status' => $card->status
                ];
            }),
            'notifications' => $notifications,
            'total_notifications' => $notifications->count()
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

        // CRITICAL: Update notification data to include accepted_at timestamp
        // This ensures mobile apps can check if notification has expired
        $notificationData = is_array($notification->data) ? $notification->data : json_decode($notification->data, true);
        $notificationData['accepted_at'] = now()->toISOString();
        $notification->data = $notificationData;

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

        // Send acceptance notification to the correct parent (main or shared)
        if ($notification->card_id) {
            $card = Card::find($notification->card_id);

            // Check if card already exists in CalledCard table
            $cardAlreadyCalled = CalledCard::where('card_id', $notification->card_id)->exists();

            if ($card && !$cardAlreadyCalled) {
                // CRITICAL FIX: Get sender's expo_token from notification data
                $notificationData = $notification->data ?? [];
                $senderExpoToken = $notificationData['sender_expo_token'] ?? $card->expo_token; // Fallback to card for old notifications
                $senderType = $notificationData['sender_type'] ?? 'card';
                $senderName = $notificationData['sender_name'] ?? $card->parent_name;

                // Send acceptance notification if we have a valid expo_token
                if ($senderExpoToken) {
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

                    // CRITICAL FIX: Encode card_id in title for Android background scenario
                    // Android strips custom data when app is in background, but title/body are preserved
                    $acceptanceTitle = "{$gardenName} - {$card->id}";

                    // Send to the correct parent using their expo_token
                    $response = $expoService->sendExpoNotificationDirect(
                        $senderExpoToken,
                        $acceptanceTitle,
                        'OK',
                        $cardOwnerData
                    );

                    if ($response['success']) {
                        $totalNotificationsSent++;
                        $notificationResults[] = [
                            'type' => 'acceptance_notification',
                            'sent_to' => $senderType,
                            'sent_to_name' => $senderName,
                            'card_id' => $card->id,
                            'notification_sent' => true
                        ];
                    }
                }
            }

            // Create called card record when notification is accepted
            CalledCard::create([
                'card_id' => $notification->card_id,
                'create_date' => now()
            ]);

            // Get all other pending notifications for this card_id and set their status to 'accepted'
            $pendingNotifications = \App\Models\Notification::where('card_id', $notification->card_id)
                ->where('id', '!=', $notification->id) // Exclude the current notification
                ->where('status', 'sent')
                ->get();

            foreach ($pendingNotifications as $pendingNotification) {
                $pendingNotification->status = 'accepted';
                $pendingNotification->accepted_at = now();

                // CRITICAL: Also update data field with accepted_at timestamp
                $pendingData = is_array($pendingNotification->data) ? $pendingNotification->data : json_decode($pendingNotification->data, true);
                $pendingData['accepted_at'] = now()->toISOString();
                $pendingNotification->data = $pendingData;

                $pendingNotification->save();
            }
        }

        // Reload the notification to get updated data
        $notification->refresh();

        // CRITICAL: WhatsApp-like notification dismissal behavior
        // Send silent push notification to all other devices to dismiss from notification tray
        // This works even when the app is completely closed/killed
        if ($notification->card_id) {
            try {
                $card = Card::find($notification->card_id);
                if ($card && $card->group) {
                    // Get all devices that have this card's group active
                    $otherDevices = Device::where('status', 'active')
                        ->where('is_logged_in', true)
                        ->whereNotNull('expo_token')
                        ->where('id', '!=', $senderDeviceId) // Exclude the device that accepted
                        ->whereJsonContains('active_garden_groups', $card->group_id)
                        ->get();

                    if ($otherDevices->isNotEmpty()) {
                        // Send silent dismissal notification to each device
                        // NOTE: Only iOS devices will actually receive the dismiss notification
                        // Android cannot dismiss notifications that are already displayed (Android OS limitation)
                        foreach ($otherDevices as $device) {
                            // Get platform from device, default to 'ios' for backward compatibility
                            $platform = $device->platform ?? 'ios';

                            $dismissResult = $expoService->dismissNotificationOnDevice(
                                $device->expo_token,
                                (string) $card->id,
                                $platform
                            );
                        }
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('Error sending dismissal notifications', [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

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
