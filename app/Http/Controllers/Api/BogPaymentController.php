<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BogPayment;
use App\Services\BogPaymentService;
use Illuminate\Support\Facades\Log;

class BogPaymentController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/bog-payments",
     *     operationId="getBogPayments",
     *     tags={"BOG Payments"},
     *     summary="Get all BOG payments",
     *     description="Retrieve a paginated list of all BOG payments",
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
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "failed", "cancelled"})
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
     *                     @OA\Property(property="order_id", type="string", example="BOG_ABC123_1234567890"),
     *                     @OA\Property(property="amount", type="number", example=100.00),
     *                     @OA\Property(property="currency", type="string", example="GEL"),
     *                     @OA\Property(property="status", type="string", example="completed"),
     *                     @OA\Property(property="payment_method", type="string", example="card"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = BogPayment::query();
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        $perPage = $request->query('per_page', 15);
        $page = $request->query('page', 1);
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * @OA\Post(
     *     path="/api/bog-payments",
     *     operationId="createBogPayment",
     *     tags={"BOG Payments"},
     *     summary="Create a new BOG payment",
     *     description="Initiate a new payment through Bank of Georgia payment gateway",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=100.00, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *             @OA\Property(property="card_id", type="integer", example=1, nullable=true, description="Card ID"),
     *             @OA\Property(property="garden_id", type="integer", example=1, nullable=true, description="Garden ID"),
     *             @OA\Property(property="payment_method", type="string", example="card", description="Payment method"),
     *             @OA\Property(property="save_card", type="boolean", example=false, description="Save card for future use"),
     *             @OA\Property(property="payment_details", type="object", description="Additional payment details")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment initiated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="redirect_url", type="string", example="https://payment.bog.ge/..."),
     *             @OA\Property(property="bog_transaction_id", type="string", example="bog_123456")
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
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'string|in:GEL,USD,EUR|max:3',
            'card_id' => 'nullable|integer|exists:cards,id',
            'garden_id' => 'nullable|integer|exists:gardens,id',
            'payment_method' => 'string|in:card,subscription|max:50',
            'save_card' => 'boolean',
            'payment_details' => 'nullable|array',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['currency'] = $validated['currency'] ?? 'GEL';

        $bogService = new BogPaymentService();

        if ($validated['save_card'] ?? false) {
            $result = $bogService->initiatePaymentWithCardSaving($validated);
        } else {
            $result = $bogService->initiatePayment($validated);
        }

        if ($result['success']) {
            return response()->json($result, 201);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bog-payments/saved-card",
     *     operationId="payWithSavedCard",
     *     tags={"BOG Payments"},
     *     summary="Pay with saved card",
     *     description="Make a payment using a previously saved card",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount","saved_card_id"},
     *             @OA\Property(property="amount", type="number", example=100.00, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *             @OA\Property(property="saved_card_id", type="string", example="bog_saved_123", description="BOG saved card ID"),
     *             @OA\Property(property="card_id", type="integer", example=1, nullable=true, description="Card ID"),
     *             @OA\Property(property="garden_id", type="integer", example=1, nullable=true, description="Garden ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="bog_transaction_id", type="string", example="bog_123456")
     *         )
     *     )
     * )
     */
    public function payWithSavedCard(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'string|in:GEL,USD,EUR|max:3',
            'saved_card_id' => 'required|string',
            'card_id' => 'nullable|integer|exists:cards,id',
            'garden_id' => 'nullable|integer|exists:gardens,id',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['currency'] = $validated['currency'] ?? 'GEL';

        $bogService = new BogPaymentService();
        $result = $bogService->payWithSavedCard($validated);

        if ($result['success']) {
            return response()->json($result);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bog-payments/subscription",
     *     operationId="createSubscription",
     *     tags={"BOG Payments"},
     *     summary="Create subscription",
     *     description="Create a subscription payment through BOG",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=100.00, description="Subscription amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Currency"),
     *             @OA\Property(property="subscription_type", type="string", example="monthly", description="Subscription type"),
     *             @OA\Property(property="subscription_duration", type="integer", example=30, description="Duration in days"),
     *             @OA\Property(property="card_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="garden_id", type="integer", example=1, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Subscription created successfully"
     *     )
     * )
     */
    public function createSubscription(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'string|in:GEL,USD,EUR|max:3',
            'subscription_type' => 'string|in:monthly,weekly,yearly',
            'subscription_duration' => 'integer|min:1',
            'card_id' => 'nullable|integer|exists:cards,id',
            'garden_id' => 'nullable|integer|exists:gardens,id',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['currency'] = $validated['currency'] ?? 'GEL';

        $bogService = new BogPaymentService();
        $result = $bogService->createSubscription($validated);

        if ($result['success']) {
            return response()->json($result, 201);
        } else {
            return response()->json([
                'success' => false,
                'error' => $result['error']
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/bog-payments/status/{orderId}",
     *     operationId="getPaymentStatus",
     *     tags={"BOG Payments"},
     *     summary="Get payment status",
     *     description="Get the status of a specific payment",
     *     @OA\Parameter(
     *         name="orderId",
     *         in="path",
     *         required=true,
     *         description="Order ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully"
     *     )
     * )
     */
    public function getPaymentStatus(string $orderId)
    {
        $bogService = new BogPaymentService();
        $status = $bogService->getPaymentStatus($orderId);

        if ($status) {
            return response()->json($status);
        } else {
            return response()->json(['error' => 'Payment not found'], 404);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/bog-payments/callback",
     *     operationId="handleCallback",
     *     tags={"BOG Payments"},
     *     summary="Handle BOG callback",
     *     description="Handle payment callback from BOG payment gateway",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_id", type="string", example="BOG_ABC123_1234567890"),
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="transaction_id", type="string", example="bog_123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback handled successfully"
     *     )
     * )
     */
    public function handleCallback(Request $request)
    {
        $callbackData = $request->all();
        
        $bogService = new BogPaymentService();
        $result = $bogService->handleCallback($callbackData);

        if ($result) {
            return response()->json(['success' => true, 'message' => 'Callback handled successfully']);
        } else {
            return response()->json(['success' => false, 'message' => 'Failed to handle callback'], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
