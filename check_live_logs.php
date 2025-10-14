<?php

// Check Live Server Logs via SSH
// This checks recent Laravel logs from live server

$liveServerPath = '/var/www/html'; // Adjust if needed
$logFile = $liveServerPath . '/storage/logs/laravel.log';

echo "==================================\n";
echo "Checking Live Server Logs\n";
echo "==================================\n\n";

echo "📝 To check live server logs, run this command:\n\n";
echo "tail -100 $logFile | grep -E 'FCMService|ExpoNotificationService|Sending notification'\n\n";
echo "==================================\n\n";

echo "OR use Laravel Tinker to check recent notifications:\n\n";
echo "php artisan tinker\n";
echo ">>> \\App\\Models\\Notification::orderBy('created_at', 'desc')->limit(5)->get(['id', 'title', 'status', 'expo_token', 'created_at']);\n";
echo ">>> exit\n\n";
echo "==================================\n\n";

echo "OR check database directly:\n\n";
echo "SELECT id, title, body, status, LEFT(expo_token, 40) as token, created_at \n";
echo "FROM notifications \n";
echo "ORDER BY created_at DESC \n";
echo "LIMIT 10;\n\n";
echo "==================================\n";

?>

