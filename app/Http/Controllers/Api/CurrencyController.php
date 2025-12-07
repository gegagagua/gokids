<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NbgCurrencyService;
use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    protected $nbgCurrencyService;

    public function __construct(NbgCurrencyService $nbgCurrencyService)
    {
        $this->nbgCurrencyService = $nbgCurrencyService;
    }

    /**
     * @OA\Get(
     *     path="/api/currency/exchange-rate",
     *     operationId="getExchangeRate",
     *     tags={"Currency"},
     *     summary="Get currency exchange rate from NBG",
     *     description="Fetches the exchange rate of a given currency relative to GEL from National Bank of Georgia. Default currency is USD. Results are cached for 1 hour.",
     *     @OA\Parameter(
     *         name="currency",
     *         in="query",
     *         description="Currency code (e.g., USD, EUR, GBP). Default is USD",
     *         required=false,
     *         @OA\Schema(type="string", default="USD", example="USD")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Exchange rate retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="currency_name", type="string", example="US Dollar"),
     *             @OA\Property(property="rate", type="number", format="float", example=2.7850),
     *             @OA\Property(property="quantity", type="integer", example=1),
     *             @OA\Property(property="date", type="string", example="2025-12-07"),
     *             @OA\Property(property="cached", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid currency code or error fetching data",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Currency not found or invalid currency code")
     *         )
     *     )
     * )
     */
    public function getExchangeRate(Request $request)
    {
        $currency = $request->input('currency', 'USD');
        
        $result = $this->nbgCurrencyService->getExchangeRate($currency);
        
        if ($result['success']) {
            return response()->json($result, 200);
        }
        
        return response()->json($result, 400);
    }
}

