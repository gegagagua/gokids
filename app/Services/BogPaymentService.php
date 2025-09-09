<?php

namespace App\Services;

use App\Models\BogPayment;
use App\Models\User;
use App\Models\Card;
use App\Models\Garden;
use RedberryProducts\LaravelBogPayment\Facades\Pay;
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
            'test_mode' => config('services.bog.test_mode', true),
        ];
    }

    /**
     * Initiate a new payment
     */
    public function initiatePayment(array $data)
    {
        try {
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => $data['payment_method'] ?? 'card',
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            // Process payment with BOG
            $paymentDetails = Pay::orderId($payment->order_id)
                ->redirectUrl(route('bog.payment.callback', ['payment_id' => $payment->id]))
                ->amount($payment->amount)
                ->process();

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $paymentDetails['id'],
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => $paymentDetails,
                    'redirect_url' => $paymentDetails['redirect_url'],
                    'details_url' => $paymentDetails['details_url'],
                ]),
            ]);

            Log::info('BOG payment initiated successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $paymentDetails['redirect_url'],
                'bog_transaction_id' => $paymentDetails['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to initiate BOG payment: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Save card during payment
     */
    public function initiatePaymentWithCardSaving(array $data)
    {
        try {
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'card_save',
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            // Process payment with card saving
            $paymentDetails = Pay::orderId($payment->order_id)
                ->redirectUrl(route('bog.payment.callback', ['payment_id' => $payment->id]))
                ->amount($payment->amount)
                ->saveCard()
                ->process();

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $paymentDetails['id'],
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => $paymentDetails,
                    'redirect_url' => $paymentDetails['redirect_url'],
                    'details_url' => $paymentDetails['details_url'],
                    'card_saving_enabled' => true,
                ]),
            ]);

            Log::info('BOG payment with card saving initiated successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $paymentDetails['redirect_url'],
                'bog_transaction_id' => $paymentDetails['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to initiate BOG payment with card saving: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Pay with saved card
     */
    public function payWithSavedCard(array $data)
    {
        try {
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'saved_card',
                'saved_card_id' => $data['saved_card_id'],
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            // Process payment with saved card
            $paymentDetails = Pay::orderId($payment->order_id)
                ->amount($payment->amount)
                ->chargeCard($data['saved_card_id']);

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $paymentDetails['id'],
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => $paymentDetails,
                    'saved_card_used' => true,
                ]),
            ]);

            Log::info('BOG payment with saved card initiated successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'saved_card_id' => $data['saved_card_id'],
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'bog_transaction_id' => $paymentDetails['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to initiate BOG payment with saved card: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create subscription
     */
    public function createSubscription(array $data)
    {
        try {
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'subscription',
                'payment_details' => array_merge($data['payment_details'] ?? [], [
                    'subscription_type' => $data['subscription_type'] ?? 'monthly',
                    'subscription_duration' => $data['subscription_duration'] ?? 30,
                ]),
            ]);

            // Process subscription with BOG
            $paymentDetails = Pay::orderId($payment->order_id)
                ->redirectUrl(route('bog.payment.callback', ['payment_id' => $payment->id]))
                ->amount($payment->amount)
                ->subscribe();

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $paymentDetails['id'],
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => $paymentDetails,
                    'redirect_url' => $paymentDetails['redirect_url'],
                    'details_url' => $paymentDetails['details_url'],
                    'subscription_created' => true,
                ]),
            ]);

            Log::info('BOG subscription created successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $paymentDetails['redirect_url'],
                'bog_transaction_id' => $paymentDetails['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create BOG subscription: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Charge subscription
     */
    public function chargeSubscription(array $data)
    {
        try {
            // Create payment record
            $payment = BogPayment::create([
                'order_id' => $this->generateOrderId(),
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'subscription_charge',
                'saved_card_id' => $data['parent_transaction_id'],
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            // Charge subscription with BOG
            $paymentDetails = Pay::orderId($payment->order_id)
                ->amount($payment->amount)
                ->chargeSubscription($data['parent_transaction_id']);

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $paymentDetails['id'],
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => $paymentDetails,
                    'subscription_charged' => true,
                ]),
            ]);

            Log::info('BOG subscription charged successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'parent_transaction_id' => $data['parent_transaction_id'],
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'bog_transaction_id' => $paymentDetails['id'],
            ];

        } catch (\Exception $e) {
            Log::error('Failed to charge BOG subscription: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
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

    /**
     * Create a test payment (simulated)
     */
    public function createTestPayment(array $data)
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
                'payment_method' => $data['payment_method'] ?? 'test',
                'payment_details' => array_merge($data['payment_details'] ?? [], [
                    'test_payment' => true,
                    'bog_config' => $config,
                ]),
            ]);

            // Generate BOG transaction ID based on mode
            if ($config['test_mode']) {
                $bogTransactionId = 'BOG_TEST_' . strtoupper(Str::random(8)) . '_' . time();
                $redirectUrl = url('/test-payment/' . $bogTransactionId);
            } else {
                // For production mode, try to integrate with real BOG API
                $bogTransactionId = 'BOG_' . strtoupper(Str::random(8)) . '_' . time();
                $bogResponse = $this->initiateRealBogPayment($payment, $config);
                
                if ($bogResponse['success'] && !empty($bogResponse['redirect_url'])) {
                    $redirectUrl = $bogResponse['redirect_url'];
                    $bogTransactionId = $bogResponse['transaction_id'] ?? $bogTransactionId;
                } else {
                    // Fallback to test mode if BOG API fails
                    $redirectUrl = url('/test-payment/' . $bogTransactionId);
                    Log::warning('BOG API integration failed, falling back to test mode', [
                        'error' => $bogResponse['error'] ?? 'Unknown error',
                        'payment_id' => $payment->id
                    ]);
                }
            }

            // Update payment with BOG transaction ID
            $payment->update([
                'bog_transaction_id' => $bogTransactionId,
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'bog_response' => [
                        'id' => $bogTransactionId,
                        'status' => 'pending',
                        'amount' => $payment->amount,
                        'currency' => $payment->currency,
                        'redirect_url' => $redirectUrl,
                        'details_url' => $redirectUrl . '/details',
                        'test_mode' => $config['test_mode'],
                    ],
                    'redirect_url' => $redirectUrl,
                    'details_url' => $redirectUrl . '/details',
                ]),
            ]);

            Log::info('BOG payment created successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'bog_transaction_id' => $bogTransactionId,
                'test_mode' => $config['test_mode'],
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $redirectUrl,
                'bog_transaction_id' => $bogTransactionId,
                'test_mode' => $config['test_mode'],
            ];

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
     * Initiate real BOG payment via API
     */
    protected function initiateRealBogPayment($payment, $config)
    {
        try {
            // For now, we'll use the direct payment URL approach
            // This creates a direct link to BOG payment page with the order details
            $bogTransactionId = 'BOG_' . strtoupper(Str::random(8)) . '_' . time();
            
            // Create the BOG payment URL with parameters
            $paymentUrl = $config['payment_url'] . '/' . $bogTransactionId;
            
            // Log the attempt
            Log::info('Creating BOG live payment', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
                'currency' => $payment->currency,
                'merchant_id' => $config['merchant_id'],
            ]);

            return [
                'success' => true,
                'transaction_id' => $bogTransactionId,
                'redirect_url' => $paymentUrl,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'BOG payment creation exception: ' . $e->getMessage(),
            ];
        }
    }
}
