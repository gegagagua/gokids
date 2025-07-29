<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Card;
use App\Models\CardOtp;
use App\Services\SmsService;

echo "Testing OTP functionality...\n\n";

// Test 1: Check if we have any cards in the database
echo "1. Checking for existing cards:\n";
$cards = Card::all();
if ($cards->count() > 0) {
    $testCard = $cards->first();
    echo "   Found card: {$testCard->child_first_name} {$testCard->child_last_name}\n";
    echo "   Phone: {$testCard->phone}\n";
    echo "   Parent Code: {$testCard->parent_code}\n\n";
} else {
    echo "   No cards found in database\n";
    exit;
}

// Test 2: Generate OTP
echo "2. Generating OTP for phone: {$testCard->phone}\n";
try {
    $otp = CardOtp::createOtp($testCard->phone);
    echo "   Generated OTP: {$otp->otp}\n";
    echo "   Expires at: {$otp->expires_at}\n\n";
} catch (Exception $e) {
    echo "   Error generating OTP: " . $e->getMessage() . "\n";
    exit;
}

// Test 3: Test SMS service (without actually sending)
echo "3. Testing SMS service (simulation):\n";
$smsService = new SmsService();
echo "   SMS service initialized\n";
echo "   Note: SMS sending is disabled in test mode\n\n";

// Test 4: Verify OTP
echo "4. Verifying OTP: {$otp->otp}\n";
try {
    $otpRecord = CardOtp::where('phone', $testCard->phone)
        ->where('otp', $otp->otp)
        ->where('used', false)
        ->where('expires_at', '>', now())
        ->first();
    
    if ($otpRecord) {
        echo "   OTP is valid\n";
        
        // Mark as used
        $otpRecord->update(['used' => true]);
        echo "   OTP marked as used\n";
        
        // Get card data
        $card = Card::with(['group', 'personType', 'parents', 'people'])
            ->where('phone', $testCard->phone)
            ->first();
        
        if ($card) {
            echo "   Card found: {$card->child_first_name} {$card->child_last_name}\n";
            echo "   Group: {$card->group->name}\n";
            echo "   Status: {$card->status}\n";
        }
    } else {
        echo "   OTP is invalid or expired\n";
    }
} catch (Exception $e) {
    echo "   Error verifying OTP: " . $e->getMessage() . "\n";
}

echo "\nTest completed!\n"; 