<?php

namespace App\Services;

use App\Mail\GardenOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class GardenMailService
{
    /**
     * Send OTP code to garden email
     */
    public function sendOtp($email, $otp)
    {
        try {
            Mail::to($email)->send(new GardenOtpMail($otp, $email));
            
            \Log::info("Garden OTP sent successfully to: {$email}");
            
            return [
                'success' => true,
                'message' => 'OTP sent to email successfully'
            ];
        } catch (\Exception $e) {
            \Log::error("Failed to send garden OTP to {$email}: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send OTP email: ' . $e->getMessage()
            ];
        }
    }
}
