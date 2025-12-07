<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class NbgCurrencyService
{
    private $nbgApiUrl = 'https://nbg.gov.ge/gw/api/ct/monetarypolicy/currencies';

    /**
     * Get currency exchange rate from NBG API
     * Returns the rate of given currency relative to GEL
     * 
     * @param string $currency Currency code (e.g., USD, EUR, GBP)
     * @return array
     */
    public function getExchangeRate(string $currency = 'USD')
    {
        try {
            $currency = strtoupper($currency);
            
            // Cache key for this currency
            $cacheKey = "nbg_currency_{$currency}";
            
            // Check if we have cached data (cache for 1 hour)
            $cachedData = Cache::get($cacheKey);
            if ($cachedData) {
                Log::info('NBG Currency: Using cached data', [
                    'currency' => $currency,
                    'data' => $cachedData
                ]);
                return [
                    'success' => true,
                    'currency' => $currency,
                    'rate' => $cachedData['rate'],
                    'date' => $cachedData['date'],
                    'cached' => true
                ];
            }

            // Fetch from NBG API
            $response = Http::timeout(10)
                ->get($this->nbgApiUrl, [
                    'currencies' => $currency
                ]);

            if (!$response->successful()) {
                Log::error('NBG API request failed', [
                    'currency' => $currency,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to fetch exchange rate from NBG',
                    'error' => $response->body()
                ];
            }

            $data = $response->json();
            
            // NBG API returns array with nested structure: [{ "date": "...", "currencies": [{ "code": "USD", "rate": 2.7, ... }] }]
            if (empty($data) || !is_array($data) || count($data) === 0) {
                Log::error('NBG API returned empty data', [
                    'currency' => $currency,
                    'response' => $data
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Currency not found or invalid currency code',
                ];
            }

            // Get the first element which contains the date and currencies array
            $responseData = $data[0];
            
            // Check if currencies array exists
            if (!isset($responseData['currencies']) || empty($responseData['currencies'])) {
                Log::error('NBG API: currencies array not found', [
                    'currency' => $currency,
                    'data' => $responseData
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Currency not found or invalid currency code',
                ];
            }
            
            // Get the first (and should be only) currency data from the currencies array
            $currencyData = $responseData['currencies'][0];
            
            // Extract rate and date
            $rate = $currencyData['rate'] ?? null;
            $date = $responseData['date'] ?? null; // Date is in the parent object
            $quantity = $currencyData['quantity'] ?? 1;
            $currencyName = $currencyData['name'] ?? null;

            if (!$rate) {
                Log::error('NBG API: Rate not found in response', [
                    'currency' => $currency,
                    'data' => $currencyData
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Exchange rate not found in response',
                ];
            }

            // Calculate actual rate (rate per 1 unit of currency)
            $actualRate = $rate / $quantity;

            // Cache the result for 1 hour
            $cacheData = [
                'rate' => $actualRate,
                'date' => $date,
                'name' => $currencyName,
                'quantity' => $quantity
            ];
            Cache::put($cacheKey, $cacheData, now()->addHour());

            Log::info('NBG Currency: Successfully fetched', [
                'currency' => $currency,
                'rate' => $actualRate,
                'date' => $date
            ]);

            return [
                'success' => true,
                'currency' => $currency,
                'currency_name' => $currencyName,
                'rate' => $actualRate,
                'quantity' => $quantity,
                'date' => $date,
                'cached' => false
            ];

        } catch (\Exception $e) {
            Log::error('NBG Currency Service Error', [
                'currency' => $currency,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Error fetching exchange rate',
                'error' => $e->getMessage()
            ];
        }
    }
}

