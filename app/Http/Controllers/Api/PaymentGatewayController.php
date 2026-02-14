<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGateway;

/**
 * @OA\Tag(
 *     name="Payment Gateways",
 *     description="API Endpoints for payment gateway management"
 * )
 */
class PaymentGatewayController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/payment-gateways",
     *     operationId="getPaymentGateways",
     *     tags={"Payment Gateways"},
     *     summary="Get available payment gateways",
     *     description="Retrieve a list of all available payment gateways from database",
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="BOG"),
     *                     @OA\Property(property="currency", type="string", example="GEL", description="Default currency for this payment gateway")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $gateways = PaymentGateway::all()->map(function ($gateway) {
            return [
                'id' => $gateway->id,
                'name' => $gateway->name,
                'currency' => $gateway->currency,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $gateways,
        ]);
    }
}
