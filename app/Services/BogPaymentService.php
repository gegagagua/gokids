<?php

namespace App\Services;

use App\Models\BogPayment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

class BogPaymentService
{
    /**
     * Get BOG configuration
     */
    protected function getBogConfig()
    {
        return [
            'merchant_id' => config('services.bog.merchant_id'),
            'api_key' => config('services.bog.api_key'),
            'base_url' => config('services.bog.base_url'),
            'payment_url' => config('services.bog.payment_url'),
        ];
    }

    /**
     * Create BOG payment
     */
    public function createPayment(array $data)
    {
        try {
            $config = $this->getBogConfig();
            
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'card',
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            // Try to create real BOG payment
            $bogResponse = $this->initiateBogPayment($payment, $config);
            
            if ($bogResponse['success']) {
                // Update payment with BOG transaction ID
                $payment->update([
                    'bog_transaction_id' => $bogResponse['transaction_id'],
                    'payment_details' => array_merge($payment->payment_details ?? [], [
                        'bog_response' => $bogResponse['response_data'],
                        'redirect_url' => $bogResponse['redirect_url'],
                    ]),
                ]);

                Log::info('BOG payment created successfully', [
                    'payment_id' => $payment->id,
                    'order_id' => $payment->order_id,
                    'amount' => $payment->amount,
                    'bog_transaction_id' => $bogResponse['transaction_id'],
                ]);

                return [
                    'success' => true,
                    'payment' => $payment,
                    'redirect_url' => $bogResponse['redirect_url'],
                    'bog_transaction_id' => $bogResponse['transaction_id'],
                ];
            } else {
                // If BOG API fails, return error
                Log::error('BOG payment creation failed', [
                    'payment_id' => $payment->id,
                    'error' => $bogResponse['error'],
                ]);

                return [
                    'success' => false,
                    'error' => $bogResponse['error'],
                ];
            }

        } catch (\Exception $e) {
            Log::error('Failed to create BOG payment: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate BOG payment via API
     */
    protected function initiateBogPayment($payment, $config)
    {
        try {
            // Prepare BOG API request
            $requestData = [
                'merchant_id' => $config['merchant_id'],
                'order_id' => $payment->order_id,
                'amount' => (int)($payment->amount * 100), // Convert to tetri (cents)
                'currency' => $payment->currency,
                'description' => $payment->payment_details['description'] ?? 'Payment via MyKids',
                'return_url' => url('/bog-payment/success'),
                'cancel_url' => url('/bog-payment/cancel'),
                'callback_url' => url('/api/bog-payment/callback'),
            ];

            // Log the API request
            Log::info('Making BOG API request', [
                'url' => $config['base_url'] . '/paymentgateway/initiate',
                'merchant_id' => $config['merchant_id'],
                'order_id' => $payment->order_id,
                'amount' => $requestData['amount'],
            ]);

            // Make API call to BOG
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $config['api_key'],
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ])
                ->post($config['base_url'] . '/paymentgateway/initiate', $requestData);

            Log::info('BOG API response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'transaction_id' => $responseData['transaction_id'] ?? $responseData['id'] ?? $responseData['order_id'],
                    'redirect_url' => $responseData['redirect_url'] ?? $responseData['payment_url'] ?? $responseData['url'],
                    'response_data' => $responseData,
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'BOG API error: ' . $response->status() . ' - ' . $response->body(),
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'BOG API exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Handle payment callback
     */
    public function handleCallback(array $callbackData)
    {
        try {
            $payment = BogPayment::where('order_id', $callbackData['order_id'])->first();

            if (!$payment) {
                Log::error('Payment not found for callback', $callbackData);
                return false;
            }

            // Update payment status based on callback
            $status = $this->mapBogStatus($callbackData['status']);
            
            $payment->update([
                'status' => $status,
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'callback_data' => $callbackData,
                    'callback_received_at' => now(),
                ]),
            ]);

            if ($status === 'completed') {
                $payment->update(['paid_at' => now()]);
            }

            Log::info('BOG payment callback handled successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status' => $status,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to handle BOG payment callback: ' . $e->getMessage(), [
                'callback_data' => $callbackData,
            ]);

            return false;
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $orderId)
    {
        $payment = BogPayment::where('order_id', $orderId)->first();
        
        if (!$payment) {
            return null;
        }

        return [
            'order_id' => $payment->order_id,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at,
            'bog_transaction_id' => $payment->bog_transaction_id,
        ];
    }

    /**
     * Generate unique order ID
     */
    protected function generateOrderId(): string
    {
        do {
            $orderId = 'BOG_' . strtoupper(Str::random(8)) . '_' . time();
        } while (BogPayment::where('order_id', $orderId)->exists());

        return $orderId;
    }

    /**
     * Map BOG status to internal status
     */
    protected function mapBogStatus(string $bogStatus): string
    {
        $statusMap = [
            'success' => 'completed',
            'failed' => 'failed',
            'cancelled' => 'cancelled',
            'pending' => 'pending',
            'processing' => 'processing',
        ];

        return $statusMap[$bogStatus] ?? 'pending';
    }
}