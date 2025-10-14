<?php

// Direct FCM Test Script
// Usage: php test_fcm_direct.php

$fcmServerKey = 'AIzaSyAaAutvOV-XIclZ3WqK-B_ZdKe-lJDZauw';
$fcmUrl = 'https://fcm.googleapis.com/fcm/send';

// Replace with your actual FCM token from the app
$fcmToken = 'PASTE_YOUR_FCM_TOKEN_HERE';

// Test notification payload
$payload = [
    'to' => $fcmToken,
    'priority' => 'high',
    'notification' => [
        'title' => 'Test FCM Notification',
        'body' => 'This is a test from PHP script',
        'sound' => 'default',
        'image' => 'https://picsum.photos/400/300',
    ],
    'data' => [
        'test' => 'true',
        'notification_image' => 'https://picsum.photos/400/300',
        'type' => 'test',
    ],
    'android' => [
        'priority' => 'high',
        'notification' => [
            'image' => 'https://picsum.photos/400/300',
            'channel_id' => 'default',
        ],
    ],
];

echo "==================================\n";
echo "FCM Direct Test Script\n";
echo "==================================\n";
echo "FCM Server Key: " . substr($fcmServerKey, 0, 20) . "...\n";
echo "FCM Token: " . substr($fcmToken, 0, 30) . "...\n";
echo "==================================\n\n";

echo "Sending notification...\n\n";

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, $fcmUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: key=' . $fcmServerKey,
    'Content-Type: application/json',
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    echo "❌ CURL Error: " . curl_error($ch) . "\n";
} else {
    echo "HTTP Status Code: " . $httpCode . "\n";
    echo "Response:\n";
    echo json_encode(json_decode($response), JSON_PRETTY_PRINT) . "\n";
    
    $responseData = json_decode($response, true);
    
    if ($httpCode == 200 && isset($responseData['success']) && $responseData['success'] > 0) {
        echo "\n✅ SUCCESS! Notification sent successfully!\n";
    } else {
        echo "\n❌ FAILED! Check the response above for errors.\n";
        
        if (isset($responseData['results'][0]['error'])) {
            echo "Error: " . $responseData['results'][0]['error'] . "\n";
            
            // Common errors
            if ($responseData['results'][0]['error'] === 'InvalidRegistration') {
                echo "\n💡 Fix: Your FCM token is invalid. Get a new token from the app.\n";
            } elseif ($responseData['results'][0]['error'] === 'NotRegistered') {
                echo "\n💡 Fix: The FCM token is not registered or expired. Get a new token.\n";
            } elseif ($responseData['results'][0]['error'] === 'MismatchSenderId') {
                echo "\n💡 Fix: The FCM Server Key doesn't match the project. Check your google-services.json.\n";
            }
        }
    }
}

curl_close($ch);

echo "\n==================================\n";
echo "Test complete!\n";
echo "==================================\n";

?>

