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
}