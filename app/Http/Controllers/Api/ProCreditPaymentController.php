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

            $paymentGateway = $country->paymentGateway;
            if ($paymentGateway && !empty($paymentGateway->currency)) {
                $currency = $paymentGateway->currency;
            }
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
        $hppStatus = $request->query('hpp_status'); // STATUS from HPP redirect URL
        $proCreditService = new ProCreditPaymentService();
        $status = $proCreditService->getPaymentStatus($orderId, $hppStatus);
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
     *     summary="Create bulk ProCredit payment for multiple cards",
     *     description="Creates ONE ProCredit order for the total amount (tariff × number of cards). User pays once on HPP. On completion, each card gets its license activated, payment records created, and balances updated.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"card_ids", "garden_id"},
     *             @OA\Property(property="garden_id", type="integer", example=1),
     *             @OA\Property(property="card_ids", type="array", @OA\Items(type="integer")),
     *             @OA\Property(property="description", type="string")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Bulk payment created — single redirect_url for total amount"),
     *     @OA\Response(response=403, description="Access denied. Only garden users."),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=503, description="ProCredit gateway not configured"),
     *     @OA\Response(response=500, description="Payment creation failed")
     * )
     */
    public function createBulkPayment(Request $request)
    {
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
        if (!$garden->countryData) {
            return response()->json(['success' => false, 'message' => 'Garden country not found.'], 422);
        }

        $country = $garden->countryData;
        $tariff = $country->tariff ?? 0;
        $currency = 'GEL';

        if ($tariff <= 0) {
            return response()->json(['success' => false, 'message' => 'Country tariff is zero or invalid.'], 422);
        }

        // Use payment gateway currency if available (same as single payment)
        $paymentGateway = $country->paymentGateway;
        if ($paymentGateway && !empty($paymentGateway->currency)) {
            $currency = $paymentGateway->currency;
        }

        $cardIds = $validated['card_ids'];
        $description = $validated['description'] ?? 'Bulk payment for ' . count($cardIds) . ' cards';
        $cards = Card::with(['group.garden'])->whereIn('id', $cardIds)->get();

        // Validate all cards belong to this garden
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

        $totalAmount = round($tariff * count($cards), 2);

        Log::info('ProCredit bulk: creating single order for multiple cards', [
            'garden_id' => $garden->id,
            'card_ids' => $cardIds,
            'cards_count' => count($cards),
            'tariff_per_card' => $tariff,
            'total_amount' => $totalAmount,
            'currency' => $currency,
        ]);

        // Create ONE payment for the total amount, store card_ids in payment_details
        $proCreditService = new ProCreditPaymentService();
        $paymentData = [
            'amount' => $totalAmount,
            'currency' => $currency,
            'card_id' => null, // bulk — no single card
            'garden_id' => $garden->id,
            'user_id' => auth()->id(),
            'payment_details' => [
                'description' => $description,
                'bulk_payment' => true,
                'bulk_card_ids' => $cardIds,
                'tariff_per_card' => $tariff,
                'cards_count' => count($cards),
                'country_id' => $country->id,
            ],
        ];

        $result = $proCreditService->createPayment($paymentData);

        if ($result['success']) {
            return response()->json([
                'success' => true,
                'payment' => $result['payment'],
                'redirect_url' => $result['redirect_url'],
                'order_id' => $result['order_id'] ?? $result['payment']->order_id,
                'total_amount' => $totalAmount,
                'currency' => $currency,
                'cards_count' => count($cards),
                'card_ids' => $cardIds,
                'tariff_per_card' => $tariff,
            ], 201);
        }

        $error = $result['error'] ?? 'Payment creation failed';
        $isConfigError = str_contains($error, 'not configured') || str_contains($error, 'not set') || str_contains($error, 'placeholder');
        return response()->json([
            'success' => false,
            'error' => $error,
            'hint' => $isConfigError ? 'Edit config/services.php → procredit. See PROCREDIT_ECOMMERCE_SETUP.md.' : null,
        ], $isConfigError ? 503 : 500);
    }
}
