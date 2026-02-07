<?php

namespace App\Services;

use App\Models\ProCreditPayment;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Card;
use App\Models\Dister;
use App\Models\Garden;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProCreditPaymentService
{
    protected ProCreditEcommerceClient $client;

    public function __construct(?ProCreditEcommerceClient $client = null)
    {
        $this->client = $client ?? new ProCreditEcommerceClient();
    }

    /**
     * Create ProCredit payment via E-commerce PG: Create Order → redirect user to HPP.
     */
    public function createPayment(array $data)
    {
        try {
            $internalOrderId = $this->generateOrderId();

            $payment = ProCreditPayment::create([
                'order_id' => $internalOrderId,
                'amount' => $data['amount'],
                'currency' => $data['currency'] ?? 'GEL',
                'status' => 'pending',
                'user_id' => $data['user_id'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'garden_id' => $data['garden_id'] ?? null,
                'payment_method' => 'card',
                'payment_details' => $data['payment_details'] ?? [],
            ]);

            $hppRedirectUrl = url('/procredit-payment/success?order_id=' . urlencode($internalOrderId));

            $createParams = [
                'amount' => number_format((float) $payment->amount, 2, '.', ''),
                'currency' => $payment->currency,
                'description' => $data['payment_details']['description'] ?? ('Payment ' . $internalOrderId),
                'language' => 'en',
                'hppRedirectUrl' => $hppRedirectUrl,
                'initiationEnvKind' => 'Browser',
            ];

            $result = $this->client->createOrder($createParams);

            if (!$result['success']) {
                Log::error('ProCredit E-commerce Create Order failed', [
                    'payment_id' => $payment->id,
                    'order_id' => $internalOrderId,
                    'error' => $result['error'] ?? 'Unknown',
                ]);
                return [
                    'success' => false,
                    'error' => $result['error'] ?? 'Failed to create order at payment gateway',
                ];
            }

            $order = $result['order'];
            $bankId = $order['id'] ?? null;
            $password = $order['password'] ?? null;
            $hppUrl = $order['hppUrl'] ?? '';

            if (!$bankId || !$password || !$hppUrl) {
                Log::error('ProCredit E-commerce Create Order missing id/password/hppUrl', ['order' => $order]);
                return [
                    'success' => false,
                    'error' => 'Invalid response from payment gateway',
                ];
            }

            $redirectUrl = rtrim($hppUrl, '/') . (str_contains($hppUrl, '?') ? '&' : '?')
                . http_build_query(['id' => $bankId, 'password' => $password]);

            $payment->update([
                'bank_order_id' => (string) $bankId,
                'bank_order_password' => $password,
                'bog_transaction_id' => (string) $bankId,
                'payment_details' => array_merge($payment->payment_details ?? [], [
                    'procredit_create_order_response' => $order,
                    'redirect_url' => $redirectUrl,
                ]),
            ]);

            Log::info('ProCredit E-commerce payment created', [
                'payment_id' => $payment->id,
                'order_id' => $internalOrderId,
                'bank_order_id' => $bankId,
            ]);

            return [
                'success' => true,
                'payment' => $payment,
                'redirect_url' => $redirectUrl,
                'bog_transaction_id' => (string) $bankId,
                'order_id' => $internalOrderId,
            ];
        } catch (\Throwable $e) {
            Log::error('ProCredit createPayment exception: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Handle callback: from bank (bank_order_id + status) or from our success page (order_id → Get Order Details).
     */
    public function handleCallback(array $callbackData)
    {
        try {
            $payment = null;

            if (!empty($callbackData['bank_order_id'])) {
                $payment = ProCreditPayment::where('bank_order_id', $callbackData['bank_order_id'])->first();
            }
            if (!$payment && !empty($callbackData['order_id'])) {
                $payment = ProCreditPayment::where('order_id', $callbackData['order_id'])->first();
            }

            if (!$payment) {
                Log::error('ProCredit callback: payment not found', $callbackData);
                return false;
            }

            $status = null;
            if (isset($callbackData['status'])) {
                $status = $this->mapPgStatus($callbackData['status']);
            }
            if ($status === null && $payment->bank_order_id && $payment->bank_order_password) {
                $details = $this->client->getOrderDetails($payment->bank_order_id, $payment->bank_order_password);
                if ($details['success'] && isset($details['order']['status'])) {
                    $status = $this->mapPgStatus($details['order']['status']);
                }
            }

            if ($status !== null) {
                $payment->update([
                    'status' => $status,
                    'payment_details' => array_merge($payment->payment_details ?? [], [
                        'callback_data' => $callbackData,
                        'callback_received_at' => now()->toIso8601String(),
                    ]),
                ]);

                if ($status === 'completed') {
                    $payment->update(['paid_at' => now()]);
                    $this->createPaymentRecord($payment);
                    $this->updateDisterBalance($payment);
                    $this->activateCardLicense($payment);
                }
            }

            Log::info('ProCredit payment callback handled', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'status' => $status ?? $payment->status,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('ProCredit handleCallback exception: ' . $e->getMessage(), [
                'callback_data' => $callbackData,
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Get payment status; if pending and we have bank order, sync via Get Order Details.
     * $hppStatus: optional STATUS from HPP redirect URL (e.g. "FullyPaid").
     */
    public function getPaymentStatus(string $orderId, ?string $hppStatus = null): ?array
    {
        $payment = ProCreditPayment::where('order_id', $orderId)->first();
        if (!$payment) {
            return null;
        }

        if ($payment->status === 'pending') {
            $resolvedStatus = null;

            // 1) Try Get Order Details from bank API
            if ($payment->bank_order_id && $payment->bank_order_password) {
                $details = $this->client->getOrderDetails($payment->bank_order_id, $payment->bank_order_password);
                Log::info('ProCredit getPaymentStatus: bank API response', [
                    'order_id' => $orderId,
                    'bank_order_id' => $payment->bank_order_id,
                    'api_success' => $details['success'],
                    'api_order_status' => $details['order']['status'] ?? null,
                    'api_order' => $details['order'] ?? null,
                ]);
                if ($details['success'] && isset($details['order']['status'])) {
                    $resolvedStatus = $this->mapPgStatus($details['order']['status']);
                }
            }

            // 2) If bank API didn't resolve, use HPP redirect STATUS as fallback
            if (($resolvedStatus === null || $resolvedStatus === 'pending') && $hppStatus) {
                $hppMapped = $this->mapPgStatus($hppStatus);
                Log::info('ProCredit getPaymentStatus: using HPP redirect STATUS', [
                    'order_id' => $orderId,
                    'hpp_status_raw' => $hppStatus,
                    'hpp_status_mapped' => $hppMapped,
                ]);
                if ($hppMapped !== 'pending') {
                    $resolvedStatus = $hppMapped;
                }
            }

            if ($resolvedStatus && $resolvedStatus !== 'pending') {
                $payment->update(['status' => $resolvedStatus]);
                if ($resolvedStatus === 'completed') {
                    $payment->update(['paid_at' => now()]);
                    $this->createPaymentRecord($payment);
                    $this->updateDisterBalance($payment);
                    $this->activateCardLicense($payment);
                }
            }
        }

        $card = $payment->card_id ? Card::find($payment->card_id) : null;

        return [
            'order_id' => $payment->order_id,
            'status' => $payment->status,
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'paid_at' => $payment->paid_at?->toIso8601String(),
            'bog_transaction_id' => $payment->bog_transaction_id,
            'card_id' => $payment->card_id,
            'card_license' => $card ? $card->license : null,
        ];
    }

    protected function generateOrderId(): string
    {
        do {
            $orderId = 'PC_' . strtoupper(Str::random(8)) . '_' . time();
        } while (ProCreditPayment::where('order_id', $orderId)->exists());

        return $orderId;
    }

    protected function mapPgStatus(string $pgStatus): string
    {
        $s = strtolower(trim($pgStatus));
        $map = [
            'preparing' => 'pending',
            'pending' => 'pending',
            'approved' => 'completed',
            'completed' => 'completed',
            'fullypaid' => 'completed',
            'fully_paid' => 'completed',
            'paid' => 'completed',
            'success' => 'completed',
            'failed' => 'failed',
            'declined' => 'failed',
            'error' => 'failed',
            'cancelled' => 'cancelled',
            'canceled' => 'cancelled',
        ];
        return $map[$s] ?? 'pending';
    }

    protected function createPaymentRecord(ProCreditPayment $proCreditPayment)
    {
        try {
            if (Payment::where('transaction_number', $proCreditPayment->order_id)->exists()) {
                return Payment::where('transaction_number', $proCreditPayment->order_id)->first();
            }

            $cardNumber = 'N/A';
            if (!empty($proCreditPayment->payment_details['callback_data']['card_number'])) {
                $cardNumber = $proCreditPayment->payment_details['callback_data']['card_number'];
            } elseif (!empty($proCreditPayment->payment_details['card_number'])) {
                $cardNumber = $proCreditPayment->payment_details['card_number'];
            } elseif ($proCreditPayment->card_id) {
                $card = Card::find($proCreditPayment->card_id);
                if ($card && $card->phone) {
                    $cardNumber = '****' . substr($card->phone, -4);
                }
            }

            $paymentGateway = $this->findPaymentGatewayByCurrency($proCreditPayment->currency ?? 'GEL');
            if (!$paymentGateway) {
                throw new \Exception('Payment gateway not found for currency: ' . ($proCreditPayment->currency ?? 'GEL'));
            }

            $comment = $proCreditPayment->payment_details['description'] ?? 'ProCredit Payment';

            return Payment::create([
                'transaction_number' => $proCreditPayment->order_id,
                'transaction_number_bank' => $proCreditPayment->bog_transaction_id,
                'card_number' => $cardNumber,
                'card_id' => $proCreditPayment->card_id,
                'amount' => $proCreditPayment->amount,
                'currency' => $proCreditPayment->currency ?? 'GEL',
                'comment' => $comment,
                'type' => 'bank',
                'status' => 'completed',
                'payment_gateway_id' => $paymentGateway->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('ProCredit createPaymentRecord exception: ' . $e->getMessage(), [
                'procredit_payment_id' => $proCreditPayment->id,
            ]);
            return null;
        }
    }

    /** ProCredit first, then BOG for backward compatibility. */
    protected function findPaymentGatewayByCurrency(string $currency): ?PaymentGateway
    {
        $names = ['ProCredit', 'ProCredit - USD', 'ProCredit - EUR', 'BOG', 'BOG - USD', 'BOG - EUR'];
        $byCurrency = match ($currency) {
            'GEL' => ['ProCredit', 'BOG'],
            'USD' => ['ProCredit - USD', 'BOG - USD'],
            'EUR' => ['ProCredit - EUR', 'BOG - EUR'],
            default => ['ProCredit', 'BOG'],
        };

        foreach ($byCurrency as $name) {
            $gw = PaymentGateway::where('name', $name)->where('currency', $currency)->where('is_active', true)->first();
            if ($gw) {
                return $gw;
            }
        }
        $gw = PaymentGateway::where('name', 'like', 'ProCredit%')->where('is_active', true)->first();
        if ($gw) {
            return $gw;
        }
        $gw = PaymentGateway::where('name', 'like', 'BOG%')->where('is_active', true)->first();
        if ($gw) {
            return $gw;
        }
        return PaymentGateway::find(1);
    }

    protected function updateDisterBalance(ProCreditPayment $proCreditPayment)
    {
        try {
            if (!$proCreditPayment->card_id) {
                return false;
            }
            $card = Card::find($proCreditPayment->card_id);
            if (!$card || !$card->group_id) {
                return false;
            }
            $group = \App\Models\GardenGroup::find($card->group_id);
            if (!$group || !$group->garden_id) {
                return false;
            }
            $garden = Garden::find($group->garden_id);
            if (!$garden) {
                return false;
            }

            $dister = Dister::whereJsonContains('gardens', $garden->id)->first();
            if (!$dister && $garden->country_id) {
                $dister = Dister::where('country_id', $garden->country_id)->first();
            }
            if (!$dister || !$dister->percent || $dister->percent <= 0) {
                return false;
            }

            $percentageAmount = $proCreditPayment->amount * ($dister->percent / 100);
            $remainingAmount = $proCreditPayment->amount - $percentageAmount;

            $dister->update([
                'balance' => ($dister->balance ?? 0) + $percentageAmount,
            ]);

            $adminUser = User::where('type', User::TYPE_ADMIN)->orderBy('id', 'asc')->first();
            if ($adminUser) {
                $adminUser->update([
                    'balance' => ($adminUser->balance ?? 0) + $remainingAmount,
                ]);
            }

            return true;
        } catch (\Throwable $e) {
            Log::error('ProCredit updateDisterBalance exception: ' . $e->getMessage(), [
                'procredit_payment_id' => $proCreditPayment->id,
            ]);
            return false;
        }
    }

    /**
     * Activate card license after successful payment.
     * Sets license to date type with expiry = 1 year from now.
     */
    protected function activateCardLicense(ProCreditPayment $proCreditPayment)
    {
        try {
            if (!$proCreditPayment->card_id) {
                Log::warning('ProCredit activateCardLicense: no card_id', [
                    'procredit_payment_id' => $proCreditPayment->id,
                ]);
                return false;
            }

            $card = Card::find($proCreditPayment->card_id);
            if (!$card) {
                Log::warning('ProCredit activateCardLicense: card not found', [
                    'card_id' => $proCreditPayment->card_id,
                ]);
                return false;
            }

            $oldLicense = $card->license;
            $expiryDate = Carbon::now()->addYear()->toDateString(); // 1 year from now

            $card->setLicenseDate($expiryDate);
            $card->save();

            Log::info('ProCredit: card license activated after payment', [
                'procredit_payment_id' => $proCreditPayment->id,
                'card_id' => $card->id,
                'old_license' => $oldLicense,
                'new_license' => $card->license,
                'expiry_date' => $expiryDate,
                'amount' => $proCreditPayment->amount,
                'currency' => $proCreditPayment->currency,
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::error('ProCredit activateCardLicense exception: ' . $e->getMessage(), [
                'procredit_payment_id' => $proCreditPayment->id,
                'card_id' => $proCreditPayment->card_id,
            ]);
            return false;
        }
    }
}
