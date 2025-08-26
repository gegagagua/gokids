<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\SmsGateway;
use App\Services\SmsService;

/**
 * @OA\Tag(
 *     name="SMS Gateways",
 *     description="API Endpoints for SMS gateway management"
 * )
 */
class SmsGatewayController extends Controller
{
    protected $smsService;

    public function __construct(SmsService $smsService)
    {
        $this->smsService = $smsService;
    }

    /**
     * @OA\Get(
     *     path="/api/sms-gateways",
     *     operationId="getSmsGateways",
     *     tags={"SMS Gateways"},
     *     summary="Get available SMS gateways",
     *     description="Retrieve a list of all available SMS gateways",
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
     *                     @OA\Property(property="name", type="string", example="Geo Sms - ubill")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index()
    {
        $gateways = collect(SmsGateway::cases())->map(function ($gateway) {
            return [
                'id' => $gateway->value,
                'name' => $gateway->name()
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $gateways
        ]);
    }
}
