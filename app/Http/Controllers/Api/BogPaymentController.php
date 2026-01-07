<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BogPayment;
use App\Models\Card;
use App\Models\Garden;
use App\Services\BogPaymentService;
use Illuminate\Support\Facades\Log;

class BogPaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/bog-payment",
     *     operationId="createBogPayment",
     *     tags={"BOG Payments"},
     *     summary="Create BOG payment",
     *     description="Create a payment through Bank of Georgia payment gateway",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="number", example=100.00, description="Payment amount"),
     *             @OA\Property(property="currency", type="string", example="GEL", description="Payment currency"),
     *             @OA\Property(property="card_id", type="integer", example=1, nullable=true, description="Card ID"),
     *             @OA\Property(property="garden_id", type="integer", example=1, nullable=true, description="Garden ID"),
     *             @OA\Property(property="description", type="string", example="Payment for services", description="Payment description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="redirect_url", type="string", example="https://payment.bog.ge/payment/..."),
     *             @OA\Property(property="bog_transaction_id", type="string", example="bog_123456")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Payment creation failed"
     *     )
     * )
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'string|in:GEL,USD,EUR|max:3',
            'card_id' => 'nullable|integer|exists:cards,id',
            'garden_id' => 'nullable|integer|exists:gardens,id',
            'description' => 'string|max:255',
        ]);

        $validated['user_id'] = auth()->id();
        $validated['currency'] = $validated['currency'] ?? 'GEL';

        $bogService = new BogPaymentService();
        $result = $bogService->createPayment($validated);

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
     *     path="/api/bog-payment/callback",
     *     operationId="handleBogCallback",
     *     tags={"BOG Payments"},
     *     summary="Handle BOG callback",
     *     description="Handle payment callback from BOG payment gateway. When payment status is 'success', automatically creates a Payment record in the payments table with all required fields (transaction_number, card_number, amount, status, payment_gateway_id, etc.)",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_id", type="string", example="BOG_ABC123_1234567890", description="BOG order ID"),
     *             @OA\Property(property="status", type="string", example="success", description="Payment status (success, failed, cancelled)"),
     *             @OA\Property(property="transaction_id", type="string", example="bog_123456", description="BOG transaction ID")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Callback handled successfully. If status is 'success', a Payment record is automatically created in the payments table.",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Callback handled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Failed to handle callback",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Failed to handle callback")
     *         )
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
     * @OA\Post(
     *     path="/api/bog-payment/bulk",
     *     operationId="createBulkBogPayment",
     *     tags={"BOG Payments"},
     *     summary="Create bulk BOG payments for cards",
     *     description="Create payments for multiple cards. Garden can pay for their cards. Calculates total amount, creates BOG payments for each card, and automatically processes callbacks with 'completed' status.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_ids", "garden_id"},
     *             @OA\Property(property="garden_id", type="integer", example=1, description="Garden ID"),
     *             @OA\Property(
     *                 property="card_ids",
     *                 type="array",
     *                 description="Array of card IDs to pay for",
     *                 @OA\Items(type="integer", example=1)
     *             ),
     *             @OA\Property(property="description", type="string", example="Bulk payment for cards", description="Payment description")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payments created and processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="total_amount", type="number", example=300.00, description="Total amount for all cards"),
     *             @OA\Property(property="payments_count", type="integer", example=3, description="Number of payments created"),
     *             @OA\Property(
     *                 property="payments",
     *                 type="array",
     *                 description="Array of created payments",
     *                 @OA\Items(type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied. Only garden users can use this endpoint."
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Payment creation failed"
     *     )
     * )
     */
    public function createBulkPayment(Request $request)
    {
        // Check if user is garden type
        $user = $request->user();
        if (!$user || $user->type !== 'garden') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only garden users can use this endpoint.'
            ], 403);
        }

        // Validate request
        $validated = $request->validate([
            'garden_id' => 'required|integer|exists:gardens,id',
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'required|integer|exists:cards,id',
            'description' => 'nullable|string|max:255',
        ]);

        // Get garden by ID
        $garden = Garden::with('countryData')->find($validated['garden_id']);
        if (!$garden) {
            return response()->json([
                'success' => false,
                'message' => 'Garden not found.'
            ], 404);
        }

        // Verify garden belongs to this user
        if ($garden->email !== $user->email) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. This garden does not belong to you.'
            ], 403);
        }

        // Get country tariff
        if (!$garden->countryData) {
            return response()->json([
                'success' => false,
                'message' => 'Garden country not found.'
            ], 422);
        }

        $country = $garden->countryData;
        $tariff = $country->tariff ?? 0;
        $currency = $country->currency ?? 'GEL';

        if ($tariff <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Country tariff is zero or invalid. Cannot process payment.'
            ], 422);
        }

        $cardIds = $validated['card_ids'];
        $description = $validated['description'] ?? 'Bulk payment for cards';

        // Verify all cards belong to this garden
        $cards = Card::with(['group.garden'])->whereIn('id', $cardIds)->get();
        
        $invalidCards = [];
        foreach ($cards as $card) {
            if (!$card->group || !$card->group->garden || $card->group->garden->id !== $garden->id) {
                $invalidCards[] = $card->id;
            }
        }

        if (!empty($invalidCards)) {
            return response()->json([
                'success' => false,
                'message' => 'Some cards do not belong to your garden.',
                'invalid_card_ids' => $invalidCards
            ], 422);
        }

        // Calculate payment amounts and create payments
        $bogService = new BogPaymentService();
        $payments = [];
        $totalAmount = $tariff * count($cards); // Total amount = tariff * number of cards
        $successCount = 0;
        $failedCount = 0;

        foreach ($cards as $card) {
            try {
                // Use garden's country tariff for all cards
                $amount = $tariff;

                // Create BOG payment
                $paymentData = [
                    'amount' => $amount,
                    'currency' => $currency,
                    'card_id' => $card->id,
                    'garden_id' => $garden->id,
                    'user_id' => $user->id,
                    'payment_details' => [
                        'description' => $description . ' - Card ID: ' . $card->id,
                        'bulk_payment' => true,
                        'country_tariff' => $tariff,
                        'country_id' => $country->id,
                    ],
                ];

                $result = $bogService->createPayment($paymentData);

                if ($result['success']) {
                    $payment = $result['payment'];
                    
                    // Automatically process callback with 'completed' status
                    $callbackData = [
                        'order_id' => $payment->order_id,
                        'status' => 'success', // BOG uses 'success' for completed
                        'transaction_id' => $result['bog_transaction_id'] ?? null,
                    ];

                    $callbackResult = $bogService->handleCallback($callbackData);

                    if ($callbackResult) {
                        $successCount++;
                        $payments[] = [
                            'card_id' => $card->id,
                            'payment_id' => $payment->id,
                            'order_id' => $payment->order_id,
                            'amount' => $amount,
                            'currency' => $currency,
                            'status' => 'completed',
                        ];
                    } else {
                        $failedCount++;
                        Log::error('Failed to process callback for bulk payment', [
                            'card_id' => $card->id,
                            'payment_id' => $payment->id,
                            'order_id' => $payment->order_id,
                        ]);
                    }
                } else {
                    $failedCount++;
                    Log::error('Failed to create BOG payment for card in bulk payment', [
                        'card_id' => $card->id,
                        'error' => $result['error'] ?? 'Unknown error',
                    ]);
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('Exception while processing card payment in bulk', [
                    'card_id' => $card->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'total_amount' => round($totalAmount, 2),
            'payments_count' => count($payments),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'payments' => $payments,
        ], 200);
    }
}