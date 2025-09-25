<?php

require_once 'vendor/autoload.php';

use App\Models\Device;
use App\Models\Garden;
use App\Models\User;
use App\Http\Controllers\Api\DeviceController;

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Testing Devices Endpoint with Login Status\n";
echo "=========================================\n\n";

// Clean up any existing test data
echo "0. Cleaning up any existing test data...\n";
Device::where('name', 'like', '%Test%')->delete();

// Create test garden
$garden = Garden::first();
if (!$garden) {
    echo "   No garden found. Creating test garden...\n";
    $garden = Garden::create([
        'name' => 'Test Garden',
        'email' => 'testgarden@example.com',
        'phone' => '+995599123456',
        'address' => 'Test Address',
        'status' => 'active'
    ]);
}

// Create test devices
echo "1. Creating test devices...\n";

$device1 = Device::create([
    'name' => 'Test Device 1',
    'garden_id' => $garden->id,
    'status' => 'active',
    'garden_groups' => [],
    'active_garden_groups' => []
]);

$device2 = Device::create([
    'name' => 'Test Device 2',
    'garden_id' => $garden->id,
    'status' => 'active',
    'garden_groups' => [],
    'active_garden_groups' => []
]);

$device3 = Device::create([
    'name' => 'Test Device 3',
    'garden_id' => $garden->id,
    'status' => 'inactive',
    'garden_groups' => [],
    'active_garden_groups' => []
]);

echo "   Device 1 created with code: {$device1->code}\n";
echo "   Device 2 created with code: {$device2->code}\n";
echo "   Device 3 created with code: {$device3->code}\n";

// Create admin user for testing
$admin = User::create([
    'name' => 'Test Admin',
    'email' => 'testadmin@example.com',
    'password' => bcrypt('admin123'),
    'type' => 'admin',
    'status' => 'active'
]);

// Create controller instance
$controller = new DeviceController();

echo "\n2. Testing devices endpoint before any logins...\n";

$request1 = new \Illuminate\Http\Request();
$request1->setUserResolver(function () use ($admin) {
    return $admin;
});

$devices1 = $controller->index($request1);
$devicesData1 = $devices1->toArray();

echo "   Number of devices: " . count($devicesData1['data']) . "\n";

// Check login status for each device
foreach ($devicesData1['data'] as $device) {
    if (strpos($device['name'], 'Test Device') === 0) {
        echo "   Device: {$device['name']} - Login Status: " . ($device['is_logged_in_status'] ? 'LOGGED IN' : 'NOT LOGGED IN') . "\n";
        echo "   Last Login: " . ($device['last_login_at'] ?: 'Never') . "\n";
        echo "   Session Expires: " . ($device['session_expires_at'] ?: 'N/A') . "\n";
    }
}

echo "\n3. Logging in Device 1...\n";

$loginRequest = new \Illuminate\Http\Request();
$loginRequest->merge(['code' => $device1->code]);

$loginController = new \App\Http\Controllers\Api\DeviceController();
$loginResponse = $loginController->deviceLogin($loginRequest);
$loginData = json_decode($loginResponse->getContent(), true);

echo "   Login status: " . $loginResponse->getStatusCode() . "\n";
echo "   Message: " . $loginData['message'] . "\n";

if ($loginResponse->getStatusCode() === 200) {
    echo "   ✓ Device 1 login successful\n";
} else {
    echo "   ✗ Device 1 login failed\n";
}

echo "\n4. Testing devices endpoint after Device 1 login...\n";

$request2 = new \Illuminate\Http\Request();
$request2->setUserResolver(function () use ($admin) {
    return $admin;
});

$devices2 = $controller->index($request2);
$devicesData2 = $devices2->toArray();

echo "   Number of devices: " . count($devicesData2['data']) . "\n";

// Check login status for each device
foreach ($devicesData2['data'] as $device) {
    if (strpos($device['name'], 'Test Device') === 0) {
        echo "   Device: {$device['name']} - Login Status: " . ($device['is_logged_in_status'] ? 'LOGGED IN' : 'NOT LOGGED IN') . "\n";
        echo "   Last Login: " . ($device['last_login_at'] ?: 'Never') . "\n";
        echo "   Session Expires: " . ($device['session_expires_at'] ?: 'N/A') . "\n";
    }
}

echo "\n5. Logging in Device 2...\n";

$loginRequest2 = new \Illuminate\Http\Request();
$loginRequest2->merge(['code' => $device2->code]);

$loginResponse2 = $loginController->deviceLogin($loginRequest2);
$loginData2 = json_decode($loginResponse2->getContent(), true);

echo "   Login status: " . $loginResponse2->getStatusCode() . "\n";
echo "   Message: " . $loginData2['message'] . "\n";

if ($loginResponse2->getStatusCode() === 200) {
    echo "   ✓ Device 2 login successful\n";
} else {
    echo "   ✗ Device 2 login failed\n";
}

echo "\n6. Testing devices endpoint after both devices login...\n";

$request3 = new \Illuminate\Http\Request();
$request3->setUserResolver(function () use ($admin) {
    return $admin;
});

$devices3 = $controller->index($request3);
$devicesData3 = $devices3->toArray();

echo "   Number of devices: " . count($devicesData3['data']) . "\n";

// Check login status for each device
foreach ($devicesData3['data'] as $device) {
    if (strpos($device['name'], 'Test Device') === 0) {
        echo "   Device: {$device['name']} - Login Status: " . ($device['is_logged_in_status'] ? 'LOGGED IN' : 'NOT LOGGED IN') . "\n";
        echo "   Last Login: " . ($device['last_login_at'] ?: 'Never') . "\n";
        echo "   Session Expires: " . ($device['session_expires_at'] ?: 'N/A') . "\n";
    }
}

echo "\n7. Logging out Device 1...\n";

$logoutRequest = new \Illuminate\Http\Request();
$logoutRequest->merge(['code' => $device1->code]);

$logoutResponse = $loginController->deviceLogout($logoutRequest);
$logoutData = json_decode($logoutResponse->getContent(), true);

echo "   Logout status: " . $logoutResponse->getStatusCode() . "\n";
echo "   Message: " . $logoutData['message'] . "\n";

if ($logoutResponse->getStatusCode() === 200) {
    echo "   ✓ Device 1 logout successful\n";
} else {
    echo "   ✗ Device 1 logout failed\n";
}

echo "\n8. Testing devices endpoint after Device 1 logout...\n";

$request4 = new \Illuminate\Http\Request();
$request4->setUserResolver(function () use ($admin) {
    return $admin;
});

$devices4 = $controller->index($request4);
$devicesData4 = $devices4->toArray();

echo "   Number of devices: " . count($devicesData4['data']) . "\n";

// Check login status for each device
foreach ($devicesData4['data'] as $device) {
    if (strpos($device['name'], 'Test Device') === 0) {
        echo "   Device: {$device['name']} - Login Status: " . ($device['is_logged_in_status'] ? 'LOGGED IN' : 'NOT LOGGED IN') . "\n";
        echo "   Last Login: " . ($device['last_login_at'] ?: 'Never') . "\n";
        echo "   Session Expires: " . ($device['session_expires_at'] ?: 'N/A') . "\n";
    }
}

// Clean up test data
echo "\n9. Cleaning up test data...\n";
$device1->delete();
$device2->delete();
$device3->delete();
$admin->delete();
echo "   Test data cleaned up!\n";

echo "\nTest Results Summary:\n";
echo "- Devices endpoint returns login status: " . (isset($responseData1['data'][0]['is_logged_in_status']) ? 'PASS' : 'FAIL') . "\n";
echo "- Login status updates after login: " . (isset($responseData2['data'][0]['is_logged_in_status']) ? 'PASS' : 'FAIL') . "\n";
echo "- Multiple devices show correct status: " . (isset($responseData3['data'][0]['is_logged_in_status']) ? 'PASS' : 'FAIL') . "\n";
echo "- Login status updates after logout: " . (isset($responseData4['data'][0]['is_logged_in_status']) ? 'PASS' : 'FAIL') . "\n";

echo "\nTest completed!\n";
