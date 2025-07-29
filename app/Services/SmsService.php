<?php

namespace App\Services;

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

        $payload = json_encode($data);

        $ch = curl_init($this->baseUrl . '?key=' . $this->apiKey);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $httpCode === 200,
            'response' => $response,
            'http_code' => $httpCode
        ];
    }
} 