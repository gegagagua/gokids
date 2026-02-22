<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SmsToVerifyService
{
    private string $clientId = 'uwAcL2HidKRJS1lo';
    private string $secret   = 'd1n1inbVBFCXzfEFNJicRmLLvQF2nghm';
    private string $authUrl  = 'https://auth.sms.to/oauth/token';
    private string $appGuid  = '486379-1771399-4a22-f532-905760d3-f6';
    private string $createUrl = 'https://verifyapi.sms.to/api/v1/verifications/create';
    private string $verifyUrl = 'https://verifyapi.sms.to/api/v1/verifications/confirm';

    public function sendOtp(string $phone): array
    {
        $phone = str_replace("\0", '', $phone);
        $phone = trim($phone);
        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $phone);
        $cleanPhone = str_replace('+', '', $cleanPhone);

        Log::info('SmsToVerifyService: sendOtp', ['phone' => $phone, 'cleanPhone' => $cleanPhone]);

        if ($cleanPhone === '995597887736' || $cleanPhone === '597887736') {
            return ['success' => true, 'response' => 'SMS skipped for blocked number', 'http_code' => 200, 'gateway' => 'blocked'];
        }

        $token = $this->getToken();
        if (!$token) {
            return ['success' => false, 'response' => 'Failed to obtain sms.to OAuth token', 'http_code' => 0, 'gateway' => 'smsto'];
        }

        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . $cleanPhone;
        }

        $payload = [
            'guid'      => $this->appGuid,
            'recipient' => $cleanPhone,
            'reference' => Str::uuid()->toString(),
        ];

        try {
            $response = Http::withToken($token)
                ->timeout(15)
                ->post($this->createUrl, $payload);

            Log::info('SmsToVerifyService create response', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'phone'  => $cleanPhone,
            ]);

            $trackingId = $response->json('data.trackingId');

            return [
                'success'         => $response->successful(),
                'response'        => $response->body(),
                'http_code'       => $response->status(),
                'gateway'         => 'smsto',
                'verification_id' => $trackingId,
            ];
        } catch (\Exception $e) {
            Log::error('SmsToVerifyService create error', ['error' => $e->getMessage()]);
            return ['success' => false, 'response' => $e->getMessage(), 'http_code' => 0, 'gateway' => 'smsto'];
        }
    }

    public function verifyCode(string $verificationId, string $code): array
    {
        $token = $this->getToken();
        if (!$token) {
            return ['success' => false, 'response' => 'Failed to obtain sms.to OAuth token', 'http_code' => 0];
        }

        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->post($this->verifyUrl, [
                    'verification_id' => $verificationId,
                    'password'        => $code,
                ]);

            Log::info('SmsToVerifyService verifyCode response', [
                'status' => $response->status(),
                'body'   => $response->body(),
                'verification_id' => $verificationId,
            ]);

            $data = $response->json();
            $isVerified = ($data['success'] ?? false) === true;

            return [
                'success'   => $isVerified,
                'response'  => $response->body(),
                'http_code' => $response->status(),
            ];
        } catch (\Exception $e) {
            Log::error('SmsToVerifyService verifyCode error', ['error' => $e->getMessage()]);
            return ['success' => false, 'response' => $e->getMessage(), 'http_code' => 0];
        }
    }

    protected function getToken(): ?string
    {
        $cached = Cache::get('smsto_bearer_token');
        if ($cached) {
            return $cached;
        }

        try {
            $response = Http::post($this->authUrl, [
                'client_id'  => $this->clientId,
                'secret'     => $this->secret,
                'grant_type' => 'client_credentials',
            ]);

            if ($response->successful()) {
                $token = $response->json('jwt') ?? $response->json('access_token');
                if ($token) {
                    Cache::put('smsto_bearer_token', $token, 3000);
                    Log::info('SmsToVerifyService: OAuth token obtained');
                    return $token;
                }
            }

            Log::error('SmsToVerifyService: OAuth failed', ['status' => $response->status(), 'body' => $response->body()]);
            return null;
        } catch (\Exception $e) {
            Log::error('SmsToVerifyService: OAuth exception', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
