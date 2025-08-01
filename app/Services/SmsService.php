<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsService
{
    private $apiKey = '706ee5fb74ece6ddd994e0905c1141fc791bae17';
    private $baseUrl = 'https://api.ubill.dev/v1/sms/send';

    public function sendOtp($phone, $otp)
    {
        // Remove any non-digit characters from phone number
        $cleanPhone = preg_replace('/[^0-9]/', '', $phone);
        
        // Ensure it starts with 995
        if (!str_starts_with($cleanPhone, '995')) {
            $cleanPhone = '995' . $cleanPhone;
        }

        $data = [
            'brandID' => 1,
            'numbers' => [$cleanPhone],
            'text' => "OTP is {$otp}",
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post($this->baseUrl . '?key=' . $this->apiKey, $data);

            return [
                'success' => $response->successful(),
                'response' => $response->body(),
                'http_code' => $response->status()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'response' => $e->getMessage(),
                'http_code' => 0
            ];
        }
    }
} 