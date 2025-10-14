<?php

// Test Full Notification Flow
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;
use App\Models\Card;
use App\Services\ExpoNotificationService;
use App\Services\FCMService;
use Illuminate\Support\Facades\Log;

echo "==================================\n";
echo "Testing Full Notification Flow\n";
echo "==================================\n\n";

// Get the most recent device
$device = Device::whereNotNull('expo_token')
    ->where('status', 'active')
    ->orderBy('updated_at', 'desc')
    ->first();

if (!$device) {
    echo "❌ No active device with expo_token found!\n";
    echo "\n💡 Make sure you've logged in to the app and registered a device.\n";
    exit;
}

echo "✅ Found Device:\n";
echo "   ID: {$device->id}\n";
echo "   Name: {$device->name}\n";
echo "   Platform: " . ($device->platform ?? 'unknown') . "\n";
echo "   Token (first 50 chars): " . substr($device->expo_token, 0, 50) . "...\n";
echo "   Token Type: " . (str_starts_with($device->expo_token, 'ExponentPushToken') ? '⚠️  EXPO (OLD)' : '✅ FCM (NEW)') . "\n";
echo "\n";

if (str_starts_with($device->expo_token, 'ExponentPushToken')) {
    echo "⚠️  WARNING: This device has an OLD Expo token!\n";
    echo "   The new app should be sending FCM tokens that start with 'f...' or 'c...'\n";
    echo "   This means either:\n";
    echo "   1. You're testing with an old build (not version 1.0.10)\n";
    echo "   2. The app didn't update the token properly\n";
    echo "\n";
    echo "🔧 Fix: Re-login to the app or reinstall to get new FCM token\n\n";
}

echo "Sending test notification...\n\n";

try {
    $title = "Test FCM Notification";
    $body = "This is a test notification at " . now()->format('H:i:s');
    $data = [
        'type' => 'test',
        'test' => true,
        'timestamp' => now()->toISOString(),
        'notification_image' => 'https://picsum.photos/400/300',
        'image_url' => 'https://picsum.photos/400/300',
    ];
    
    // Test using ExpoNotificationService (which should use FCMService internally)
    $expoService = new ExpoNotificationService();
    $result = $expoService->sendToDevice($device, $title, $body, $data);
    
    echo "Result: " . ($result ? "✅ SUCCESS" : "❌ FAILED") . "\n\n";
    
    // Check last notification in database
    $notification = \App\Models\Notification::where('device_id', $device->id)
        ->orderBy('created_at', 'desc')
        ->first();
    
    if ($notification) {
        echo "Last notification in DB:\n";
        echo "   ID: {$notification->id}\n";
        echo "   Title: {$notification->title}\n";
        echo "   Status: {$notification->status}\n";
        echo "   Created: {$notification->created_at}\n";
        echo "   Sent: " . ($notification->sent_at ?? 'Not sent') . "\n";
        
        if ($notification->status === 'failed') {
            echo "   Error: " . ($notification->error_message ?? 'Unknown') . "\n";
        }
    }
    
    echo "\n";
    echo "==================================\n";
    echo "Check your phone now!\n";
    echo "==================================\n";
    
    if (str_starts_with($device->expo_token, 'ExponentPushToken')) {
        echo "\n⚠️  IMPORTANT: Update your app to version 1.0.10 to get FCM token!\n";
    }
    
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "\nStack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

?>

