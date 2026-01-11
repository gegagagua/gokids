<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class BrevoMailService
{
    private $apiKey;
    private $baseUrl = 'https://api.brevo.com/v3';

    public function __construct()
    {
        // Get API key from environment variable (.env file)
        $this->apiKey = config('services.brevo.api_key');
        
        if (empty($this->apiKey)) {
            Log::error('Brevo API key is not configured. Please set BREVO_API_KEY in .env file.');
        }
    }

    /**
     * Send transactional email via Brevo API using cURL
     */
    public function sendEmail($to, $subject, $htmlContent, $fromEmail = null, $fromName = null)
    {
        try {

            $fromEmail = $fromEmail ?? config('mail.from.address');
            $fromName = $fromName ?? config('mail.from.name');

            // Validate required fields
            if (empty($fromEmail)) {
                Log::error('MAIL_FROM_ADDRESS is not configured');
                return [
                    'success' => false,
                    'message' => 'MAIL_FROM_ADDRESS is not configured. Please set it in .env file.',
                ];
            }

            $requestData = [
                'sender' => [
                    'name' => $fromName ?? 'MyKids Garden System',
                    'email' => $fromEmail,
                ],
                'to' => [
                    [
                        'email' => $to,
                    ]
                ],
                'subject' => $subject,
                'htmlContent' => $htmlContent,
            ];

            Log::info("Attempting to send Brevo email via cURL", [
                'to' => $to,
                'from' => $fromEmail,
                'subject' => $subject,
            ]);

            // Use cURL to send request
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "{$this->baseUrl}/smtp/email");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'accept: application/json',
                'api-key: ' . $this->apiKey,
                'content-type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($curlError) {
                Log::error("cURL error when sending Brevo email to {$to}: " . $curlError);
                return [
                    'success' => false,
                    'message' => 'cURL error: ' . $curlError,
                ];
            }

            $responseData = json_decode($response, true);

            if ($httpCode >= 200 && $httpCode < 300) {
                $messageId = $responseData['messageId'] ?? null;
                
                Log::info("Brevo email sent successfully via cURL to: {$to}", [
                    'response' => $responseData,
                    'message_id' => $messageId,
                    'from' => $fromEmail,
                ]);

                return [
                    'success' => true,
                    'message' => 'Email sent successfully',
                    'response' => $responseData,
                    'message_id' => $messageId,
                ];
            } else {
                $errorMessage = 'Failed to send email';
                if (isset($responseData['message'])) {
                    $errorMessage = $responseData['message'];
                } elseif (isset($responseData['error'])) {
                    $errorMessage = is_array($responseData['error']) ? json_encode($responseData['error']) : $responseData['error'];
                } else {
                    $errorMessage = $response;
                }

                Log::error("Failed to send Brevo email via cURL to {$to}", [
                    'status' => $httpCode,
                    'response' => $responseData ?? $response,
                    'request_data' => [
                        'to' => $to,
                        'from' => $fromEmail,
                        'subject' => $subject,
                    ],
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                    'status' => $httpCode,
                    'response' => $responseData ?? $response,
                ];
            }
        } catch (\Exception $e) {
            Log::error("Exception when sending Brevo email via cURL to {$to}: " . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Exception: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Send OTP code to email via Brevo using cURL
     */
    public function sendOtp($email, $otp)
    {
        $htmlContent = view('emails.garden-otp', [
            'otp' => $otp,
            'email' => $email,
        ])->render();

        $subject = 'Garden Registration OTP Code';

        return $this->sendEmail($email, $subject, $htmlContent);
    }
}
