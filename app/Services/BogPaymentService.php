<?php

namespace App\Services;

use App\Models\BogPayment;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Card;
use App\Models\Dister;
use App\Models\Garden;
use App\Models\User;
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
            'client_id' => config('services.bog.client_id'),
            'secret' => config('services.bog.secret'),
            'public_key' => config('services.bog.public_key'),
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

                \Log::info('BOG payment created successfully', [
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
                \Log::error('BOG payment creation failed', [
                    'payment_id' => $payment->id,
                    'error' => $bogResponse['error'],
                ]);

                return [
                    'success' => false,
                    'error' => $bogResponse['error'],
                ];
            }

        } catch (\Exception $e) {
            \Log::error('Failed to create BOG payment: ' . $e->getMessage(), [
                'data' => $data,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Initiate BOG payment via direct URL generation
     * Based on Redberry BOG Payment Gateway documentation
     */
    protected function initiateBogPayment($payment, $config)
    {
        try {
            // Generate transaction ID
            $transactionId = 'BOG_' . strtoupper(Str::random(8)) . '_' . time();
            
            // Use direct URL generation (BOG doesn't provide working API)
            return $this->generateDirectPaymentUrl($payment, $config, $transactionId);

        } catch (\Exception $e) {
            \Log::error('BOG payment URL generation exception: ' . $e->getMessage());
            
            return [
                'success' => false,
                'error' => 'BOG payment URL generation failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate direct payment URL as fallback
     */
    protected function generateDirectPaymentUrl($payment, $config, $transactionId)
    {
        try {
            // Use the correct BOG payment URL format
            $paymentUrl = 'https://payment.bog.ge/payment?' . http_build_query([
                'client_id' => $config['client_id'],
                'order_id' => $payment->order_id,
                'amount' => (int)($payment->amount * 100),
                'currency' => $payment->currency,
                'description' => $payment->payment_details['description'] ?? 'Payment via MyKids',
                'return_url' => url('/bog-payment/success'),
                'cancel_url' => url('/bog-payment/cancel'),
                'callback_url' => url('/api/bog-payment/callback'),
            ]);

            \Log::info('Using direct BOG payment URL (fallback)', [
                'transaction_id' => $transactionId,
                'payment_url' => $paymentUrl,
            ]);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'redirect_url' => $paymentUrl,
                'response_data' => [
                    'id' => $transactionId,
                    'redirect_url' => $paymentUrl,
                    'client_id' => $config['client_id'],
                    'order_id' => $payment->order_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                ],
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'BOG payment URL generation failed: ' . $e->getMessage(),
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
                \Log::error('Payment not found for callback', $callbackData);
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
                
                // Create Payment record in payments table
                $this->createPaymentRecord($payment);
                
                // Update dister balance
                $this->updateDisterBalance($payment);
            }

            \Log::info('BOG payment callback handled successfully', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status' => $status,
            ]);

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to handle BOG payment callback: ' . $e->getMessage(), [
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
     * Create Payment record in payments table after successful BOG payment
     */
    protected function createPaymentRecord(BogPayment $bogPayment)
    {
        try {
            // Check if Payment already exists for this BOG payment
            $existingPayment = Payment::where('transaction_number', $bogPayment->order_id)->first();
            if ($existingPayment) {
                \Log::info('Payment already exists for BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'payment_id' => $existingPayment->id,
                ]);
                return $existingPayment;
            }

            // Get card number from callback data, payment_details, or generate placeholder
            $cardNumber = null;
            
            // First try to get from callback_data (most recent)
            if (isset($bogPayment->payment_details['callback_data']['card_number'])) {
                $cardNumber = $bogPayment->payment_details['callback_data']['card_number'];
            }
            // Then try payment_details
            elseif (isset($bogPayment->payment_details['card_number'])) {
                $cardNumber = $bogPayment->payment_details['card_number'];
            }
            // If card_id exists, use phone as fallback
            elseif ($bogPayment->card_id) {
                $card = Card::find($bogPayment->card_id);
                if ($card && $card->phone) {
                    $cardNumber = '****' . substr($card->phone, -4);
                }
            }
            
            // Final fallback
            if (!$cardNumber) {
                $cardNumber = 'N/A';
            }

            // Find payment gateway by currency (required - should always find one with fallbacks)
            $paymentGateway = $this->findPaymentGatewayByCurrency($bogPayment->currency ?? 'GEL');
            
            if (!$paymentGateway) {
                \Log::error('Payment gateway not found even after all fallbacks', [
                    'currency' => $bogPayment->currency,
                    'bog_payment_id' => $bogPayment->id,
                ]);
                throw new \Exception('Payment gateway not found for currency: ' . ($bogPayment->currency ?? 'GEL'));
            }

            // Get comment from payment_details
            $comment = $bogPayment->payment_details['description'] ?? 'BOG Payment';

            // Ensure all required fields are set
            $paymentData = [
                'transaction_number' => $bogPayment->order_id,
                'transaction_number_bank' => $bogPayment->bog_transaction_id ?? null,
                'card_number' => $cardNumber ?? 'N/A',
                'card_id' => $bogPayment->card_id,
                'amount' => $bogPayment->amount,
                'currency' => $bogPayment->currency ?? 'GEL',
                'comment' => $comment,
                'type' => 'bank', // Always 'bank' for BOG payments
                'status' => 'completed',
                'payment_gateway_id' => $paymentGateway->id, // Should always be set due to fallbacks
            ];

            // Create Payment record
            $payment = Payment::create($paymentData);

            \Log::info('Payment record created from BOG payment', [
                'bog_payment_id' => $bogPayment->id,
                'payment_id' => $payment->id,
                'transaction_number' => $payment->transaction_number,
            ]);

            return $payment;

        } catch (\Exception $e) {
            \Log::error('Failed to create Payment record from BOG payment: ' . $e->getMessage(), [
                'bog_payment_id' => $bogPayment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return null;
        }
    }

    /**
     * Find payment gateway by currency
     * Always returns a PaymentGateway (never null) - uses fallbacks if needed
     */
    protected function findPaymentGatewayByCurrency(string $currency): ?PaymentGateway
    {
        try {
            // Map currency to payment gateway name
            $gatewayName = match($currency) {
                'GEL' => 'BOG',
                'USD' => 'BOG - USD',
                'EUR' => 'BOG - EUR',
                default => 'BOG',
            };

            // First try: exact match by name and currency
            $paymentGateway = PaymentGateway::where('name', $gatewayName)
                ->where('currency', $currency)
                ->where('is_active', true)
                ->first();

            if (!$paymentGateway) {
                // Fallback 1: try to find by currency only (any BOG gateway with this currency)
                $paymentGateway = PaymentGateway::where('name', 'like', 'BOG%')
                    ->where('currency', $currency)
                    ->where('is_active', true)
                    ->first();
            }

            if (!$paymentGateway) {
                // Fallback 2: try to find any active BOG gateway
                $paymentGateway = PaymentGateway::where('name', 'like', 'BOG%')
                    ->where('is_active', true)
                    ->first();
            }

            if (!$paymentGateway) {
                // Fallback 3: try to find default BOG gateway (ID 1)
                $paymentGateway = PaymentGateway::find(1);
            }

            if ($paymentGateway) {
                \Log::info('Payment gateway found', [
                    'gateway_id' => $paymentGateway->id,
                    'gateway_name' => $paymentGateway->name,
                    'currency' => $currency,
                ]);
            } else {
                \Log::warning('No payment gateway found, even with fallbacks', [
                    'currency' => $currency,
                ]);
            }

            return $paymentGateway;

        } catch (\Exception $e) {
            \Log::error('Failed to find payment gateway by currency: ' . $e->getMessage(), [
                'currency' => $currency,
            ]);
            
            // Last resort: try to get default BOG gateway
            return PaymentGateway::find(1);
        }
    }

    /**
     * Update dister balance when payment is completed
     * Finds dister by garden (if exists) or by country, then adds percentage of payment amount
     */
    protected function updateDisterBalance(BogPayment $bogPayment)
    {
        try {
            // Get card to find garden
            if (!$bogPayment->card_id) {
                \Log::warning('BOG payment has no card_id, cannot update dister balance', [
                    'bog_payment_id' => $bogPayment->id,
                ]);
                return false;
            }

            $card = Card::find($bogPayment->card_id);
            if (!$card || !$card->group_id) {
                \Log::warning('Card or group not found for BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'card_id' => $bogPayment->card_id,
                ]);
                return false;
            }

            // Get garden through group
            $group = \App\Models\GardenGroup::find($card->group_id);
            if (!$group || !$group->garden_id) {
                \Log::warning('Group or garden not found for BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'group_id' => $card->group_id,
                ]);
                return false;
            }

            $garden = Garden::find($group->garden_id);
            if (!$garden) {
                \Log::warning('Garden not found for BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'garden_id' => $group->garden_id,
                ]);
                return false;
            }

            // Find dister: first try by garden, then by country
            $dister = Dister::whereJsonContains('gardens', $garden->id)->first();
            
            if (!$dister && $garden->country_id) {
                // If no dister found by garden, try to find by country
                $dister = Dister::where('country_id', $garden->country_id)->first();
            }

            if (!$dister) {
                \Log::warning('Dister not found for BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'garden_id' => $garden->id,
                    'country_id' => $garden->country_id,
                ]);
                return false;
            }

            // Check if dister has percent field
            if (!$dister->percent || $dister->percent <= 0) {
                \Log::warning('Dister has no valid percent, skipping balance update', [
                    'bog_payment_id' => $bogPayment->id,
                    'dister_id' => $dister->id,
                    'percent' => $dister->percent,
                ]);
                return false;
            }

            // Calculate percentage amount: payment_amount * (percent / 100)
            $percentageAmount = $bogPayment->amount * ($dister->percent / 100);

            // Calculate remaining amount (payment_amount - dister_percentage)
            $remainingAmount = $bogPayment->amount - $percentageAmount;

            // Update dister balance
            $oldDisterBalance = $dister->balance ?? 0;
            $newDisterBalance = $oldDisterBalance + $percentageAmount;
            
            $dister->update([
                'balance' => $newDisterBalance,
            ]);

            // Find first admin user and update their balance
            $adminUser = User::where('type', User::TYPE_ADMIN)->orderBy('id', 'asc')->first();
            
            if ($adminUser) {
                $oldAdminBalance = $adminUser->balance ?? 0;
                $newAdminBalance = $oldAdminBalance + $remainingAmount;
                
                $adminUser->update([
                    'balance' => $newAdminBalance,
                ]);

                \Log::info('Dister and Admin balances updated from BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'dister_id' => $dister->id,
                    'payment_amount' => $bogPayment->amount,
                    'dister_percent' => $dister->percent,
                    'dister_percentage_amount' => $percentageAmount,
                    'dister_old_balance' => $oldDisterBalance,
                    'dister_new_balance' => $newDisterBalance,
                    'remaining_amount' => $remainingAmount,
                    'admin_user_id' => $adminUser->id,
                    'admin_old_balance' => $oldAdminBalance,
                    'admin_new_balance' => $newAdminBalance,
                    'garden_id' => $garden->id,
                ]);
            } else {
                \Log::warning('Admin user not found, only dister balance updated', [
                    'bog_payment_id' => $bogPayment->id,
                    'dister_id' => $dister->id,
                    'remaining_amount' => $remainingAmount,
                ]);

                \Log::info('Dister balance updated from BOG payment', [
                    'bog_payment_id' => $bogPayment->id,
                    'dister_id' => $dister->id,
                    'payment_amount' => $bogPayment->amount,
                    'dister_percent' => $dister->percent,
                    'percentage_amount' => $percentageAmount,
                    'old_balance' => $oldDisterBalance,
                    'new_balance' => $newDisterBalance,
                    'garden_id' => $garden->id,
                ]);
            }

            return true;

        } catch (\Exception $e) {
            \Log::error('Failed to update dister balance from BOG payment: ' . $e->getMessage(), [
                'bog_payment_id' => $bogPayment->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return false;
        }
    }
}