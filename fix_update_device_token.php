<?php

// Force update device token from FCM format to test
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Device;

echo "==================================\n";
echo "Update Device Token Manually\n";
echo "==================================\n\n";

echo "Enter Device ID: ";
$deviceId = trim(fgets(STDIN));

echo "Enter new FCM Token: ";
$fcmToken = trim(fgets(STDIN));

if (empty($deviceId) || empty($fcmToken)) {
    echo "❌ Device ID and FCM Token are required!\n";
    exit;
}

$device = Device::find($deviceId);

if (!$device) {
    echo "❌ Device not found!\n";
    exit;
}

echo "\nCurrent token: " . substr($device->expo_token, 0, 50) . "...\n";
echo "New token: " . substr($fcmToken, 0, 50) . "...\n\n";

echo "Update? (yes/no): ";
$confirm = trim(fgets(STDIN));

if (strtolower($confirm) !== 'yes') {
    echo "❌ Cancelled.\n";
    exit;
}

$device->expo_token = $fcmToken;
$device->platform = str_starts_with($fcmToken, 'ExponentPushToken') ? 'unknown' : 'android';
$device->save();

echo "\n✅ Device token updated successfully!\n";
echo "   Device ID: {$device->id}\n";
echo "   New Token: " . substr($device->expo_token, 0, 50) . "...\n";
echo "   Platform: {$device->platform}\n";

?>

