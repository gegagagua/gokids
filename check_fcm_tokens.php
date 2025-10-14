<?php

// Check FCM Tokens in Database
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

echo "==================================\n";
echo "Checking FCM Tokens in Database\n";
echo "==================================\n\n";

try {
    // Check devices table
    $devices = DB::table('devices')
        ->select('id', 'name', 'expo_token', 'platform', 'status', 'created_at')
        ->whereNotNull('expo_token')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent Devices with tokens:\n";
    echo "----------------------------\n";
    
    if ($devices->count() > 0) {
        foreach ($devices as $device) {
            echo "ID: {$device->id}\n";
            echo "Name: {$device->name}\n";
            echo "Platform: " . ($device->platform ?? 'unknown') . "\n";
            echo "Status: {$device->status}\n";
            echo "Token (first 40 chars): " . substr($device->expo_token, 0, 40) . "...\n";
            echo "Token type: " . (str_starts_with($device->expo_token, 'ExponentPushToken') ? 'EXPO TOKEN ❌' : 'FCM TOKEN ✅') . "\n";
            echo "Created: {$device->created_at}\n";
            echo "----------------------------\n";
        }
    } else {
        echo "❌ No devices with tokens found!\n";
    }
    
    echo "\n";
    
    // Check cards table
    $cards = DB::table('cards')
        ->select('id', 'phone', 'expo_token', 'child_first_name', 'child_last_name', 'created_at')
        ->whereNotNull('expo_token')
        ->orderBy('created_at', 'desc')
        ->limit(5)
        ->get();
    
    echo "Recent Cards with tokens:\n";
    echo "----------------------------\n";
    
    if ($cards->count() > 0) {
        foreach ($cards as $card) {
            echo "ID: {$card->id}\n";
            echo "Phone: {$card->phone}\n";
            echo "Child: {$card->child_first_name} {$card->child_last_name}\n";
            echo "Token (first 40 chars): " . substr($card->expo_token, 0, 40) . "...\n";
            echo "Token type: " . (str_starts_with($card->expo_token, 'ExponentPushToken') ? 'EXPO TOKEN ❌' : 'FCM TOKEN ✅') . "\n";
            echo "Created: {$card->created_at}\n";
            echo "----------------------------\n";
        }
    } else {
        echo "❌ No cards with tokens found!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Database Error: " . $e->getMessage() . "\n";
    echo "\n💡 Make sure XAMPP MySQL is running!\n";
}

echo "\n==================================\n";
echo "Check complete!\n";
echo "==================================\n";

?>

