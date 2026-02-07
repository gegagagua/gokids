<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\Garden;
use App\Services\ProCreditPaymentService;
use Illuminate\Support\Facades\Log;

class ProCreditPaymentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/procredit-payment",
     *     operationId="createProCreditPayment",
     *     tags={"ProCredit Payments"},
     *     summary="Create ProCredit payment",
     *     description="Create order via ProCredit E-commerce PG (Internet Shop Integration v1.1). Returns redirect_url to Hosted Payment Page (HPP); user completes payment there, then is redirected to success page.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_id"},
     *             @OA\Property(property="card_id", type="integer", example=1, description="Card ID (amount and currency from card's country tariff; default 10 GEL if not found)"),
     *             @OA\Property(property="description", type="string", example="Payment for services", description="Payment description (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="payment", type="object"),
     *             @OA\Property(property="redirect_url", type="string", example="https://hpp.bank.com/flex?id=1234&password=..."),
     *             @OA\Property(property="bog_transaction_id", type="string", example="1027"),
     *             @OA\Property(property="order_id", type="string", example="PC_ABC12_1234567890", description="Internal order_id for status/redirect")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=503, description="ProCredit gateway not configured (edit config/services.php)"),
     *     @OA\Response(response=500, description="Payment creation failed")
     * )
     */
    public function createPayment(Request $request)
    {
        $validated = $request->validate([
            'card_id' => 'required|integer|exists:cards,id',
            'description' => 'nullable|string|max:255',
        ]);

        $card = Card::with(['group.garden.countryData'])->find($validated['card_id']);
        if (!$card || !$card->group || !$card->group->garden) {
            return response()->json([
                'success' => false,
                'message' => 'Card has no garden. Cannot determine amount.',
            ], 422);
        }

        $garden = $card->group->garden;
        $country = $garden->countryData;
        $amount = 10.0;
        $currency = 'GEL';
        
        if ($country) {
            $tariff = $country->tariff ?? 0;
            if ($tariff > 0) {
                $amount = (float) $tariff;
            }
            // if (!empty($country->currency)) {
            //     $currency = $country->currency;
            // }

            $paymentGateway = $country->paymentGateway;
            Log::info('ProCredit createPayment: country & payment gateway info', [
                'country_id' => $country->id,
                'country_name' => $country->name,
                'tariff' => $country->tariff,
                'currency' => $country->currency,
                'payment_gateway_id' => $country->payment_gateway_id,
                'payment_gateway' => $paymentGateway ? [
                    'id' => $paymentGateway->id,
                    'name' => $paymentGateway->name ?? null,
                    'currency' => $paymentGateway->currency ?? null,
                    'is_active' => $paymentGateway->is_active ?? null,
                ] : null,
                'resolved_amount' => $amount,
                'resolved_currency' => $currency,
            ]);
        }

        $validated['user_id'] = auth()->id();
        $validated['amount'] = $amount;
        $validated['currency'] = $currency;
        $validated['garden_id'] = $garden->id;
        $validated['payment_details'] = [
            'description' => $validated['description'] ?? ('Payment for card ' . $card->id),
        ];

        $proCreditService = new ProCreditPaymentService();
        $result = $proCreditService->createPayment($validated);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'redirect_url' => $result['redirect_url'],
                'bog_transaction_id' => $result['bog_transaction_id'] ?? null,
                'order_id' => $result['order_id'] ?? $result['payment']->order_id,
            ], 201);
        }

        $error = $result['error'] ?? 'Payment creation failed';
        $isConfigError = str_contains($error, 'not configured') || str_contains($error, 'not set') || str_contains($error, 'placeholder');
        return response()->json([
            'success' => false,
            'error' => $error,
            'hint' => $isConfigError ? 'Edit config/services.php → procredit (order_endpoint, merchant_id, cert_path, key_path, ca_path). See PROCREDIT_ECOMMERCE_SETUP.md.' : null,
        ], $isConfigError ? 503 : 500);
    }

    /**
     * @OA\Post(
     *     path="/api/procredit-payment/callback",
     *     operationId="handleProCreditCallback",
     *     tags={"ProCredit Payments"},
     *     summary="Handle ProCredit callback",
     *     description="Handle callback: pass order_id (our internal) to sync via Get Order Details, or bank_order_id + status from PG. On completed status, creates Payment record and updates dister balance.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="order_id", type="string", example="PC_ABC123_1234567890"),
     *             @OA\Property(property="bank_order_id", type="string"),
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="transaction_id", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Callback handled successfully"),
     *     @OA\Response(response=500, description="Failed to handle callback")
     * )
     */
    public function handleCallback(Request $request)
    {
        $callbackData = $request->all();
        $proCreditService = new ProCreditPaymentService();
        $result = $proCreditService->handleCallback($callbackData);

        if ($result) {
            return response()->json(['success' => true, 'message' => 'Callback handled successfully']);
        }
        return response()->json(['success' => false, 'message' => 'Failed to handle callback'], 500);
    }

    /**
     * @OA\Get(
     *     path="/api/procredit-payment/status/{orderId}",
     *     operationId="getProCreditPaymentStatus",
     *     tags={"ProCredit Payments"},
     *     summary="Get ProCredit payment status",
     *     description="Get payment status by order_id. If order is still pending, syncs with E-commerce PG via Get Order Details.",
     *     @OA\Parameter(name="orderId", in="path", required=true, description="Our internal order_id (e.g. PC_XXX_timestamp)"),
     *     @OA\Response(response=200, description="Payment status"),
     *     @OA\Response(response=404, description="Order not found")
     * )
     */
    public function getPaymentStatus(Request $request, string $orderId)
    {
        $proCreditService = new ProCreditPaymentService();
        $status = $proCreditService->getPaymentStatus($orderId);
        if ($status === null) {
            return response()->json(['success' => false, 'message' => 'Order not found'], 404);
        }
        return response()->json(array_merge(['success' => true], $status));
    }

    /**
     * @OA\Post(
     *     path="/api/procredit-payment/bulk",
     *     operationId="createBulkProCreditPayment",
     *     tags={"ProCredit Payments"},
     *     summary="Create bulk ProCredit payments for cards",
     *     description="Create one ProCredit E-commerce order per card (Create Order). Returns redirect_url for each; user completes each payment on HPP. Use GET /api/procredit-payment/status/{orderId} or callback to confirm.",
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_ids", "garden_id"},
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="card_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=200, description="Payments created"),
     *     @OA\Response(response=403, description="Access denied. Only garden users."),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=500, description="Payment creation failed")
     * )
     */
    public function createBulkPayment(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->type !== 'garden') {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Only garden users can use this endpoint.'
            ], 403);
        }

        $validated = $request->validate([
            'garden_id' => 'required|integer|exists:gardens,id',
            'card_ids' => 'required|array|min:1',
            'card_ids.*' => 'required|integer|exists:cards,id',
            'description' => 'nullable|string|max:255',
        ]);

        $garden = Garden::with('countryData')->find($validated['garden_id']);
        if (!$garden) {
            return response()->json(['success' => false, 'message' => 'Garden not found.'], 404);
        }
        if ($garden->email !== $user->email) {
            return response()->json(['success' => false, 'message' => 'Access denied. This garden does not belong to you.'], 403);
        }
        if (!$garden->countryData) {
            return response()->json(['success' => false, 'message' => 'Garden country not found.'], 422);
        }

        $country = $garden->countryData;
        $tariff = $country->tariff ?? 0;
        $currency = $country->currency ?? 'GEL';
        if ($tariff <= 0) {
            return response()->json(['success' => false, 'message' => 'Country tariff is zero or invalid.'], 422);
        }

        $cardIds = $validated['card_ids'];
        $description = $validated['description'] ?? 'Bulk payment for cards';
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

        $proCreditService = new ProCreditPaymentService();
        $payments = [];
        $totalAmount = $tariff * count($cards);
        $successCount = 0;
        $failedCount = 0;

        foreach ($cards as $card) {
            try {
                $amount = $tariff;
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

                $result = $proCreditService->createPayment($paymentData);

                if ($result['success']) {
                    $successCount++;
                    $payments[] = [
                        'card_id' => $card->id,
                        'payment_id' => $result['payment']->id,
                        'order_id' => $result['order_id'] ?? $result['payment']->order_id,
                        'redirect_url' => $result['redirect_url'],
                        'amount' => $amount,
                        'currency' => $currency,
                        'status' => 'pending',
                    ];
                } else {
                    $failedCount++;
                    Log::error('ProCredit bulk: create order failed for card', ['card_id' => $card->id, 'error' => $result['error'] ?? 'Unknown']);
                }
            } catch (\Exception $e) {
                $failedCount++;
                Log::error('ProCredit bulk exception', ['card_id' => $card->id, 'error' => $e->getMessage()]);
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
