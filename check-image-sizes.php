<?php

/**
 * Script to check card image sizes and formats
 * Run with: php check-image-sizes.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Card;
use Illuminate\Support\Facades\Http;

echo "\n📸 Checking Card Image Sizes...\n";
echo str_repeat("=", 80) . "\n\n";

$cards = Card::whereNotNull('image_url')
    ->where('image_url', '!=', '')
    ->limit(20)
    ->get();

if ($cards->isEmpty()) {
    echo "❌ No cards with images found!\n";
    exit;
}

echo "Found " . $cards->count() . " cards with images\n\n";

$issues = [];
$goodImages = 0;

foreach ($cards as $card) {
    echo "Card #{$card->id} - {$card->child_first_name} {$card->child_last_name}\n";
    echo "  URL: " . substr($card->image_url, 0, 60) . "...\n";

    try {
        $response = Http::timeout(10)->head($card->image_url);

        if (!$response->successful()) {
            echo "  ❌ ERROR: Image not accessible (Status: {$response->status()})\n";
            $issues[] = [
                'card_id' => $card->id,
                'issue' => 'Not accessible',
                'url' => $card->image_url
            ];
            echo "\n";
            continue;
        }

        $contentType = $response->header('Content-Type');
        $contentLength = $response->header('Content-Length');
        $sizeBytes = (int) $contentLength;
        $sizeMB = round($sizeBytes / 1048576, 2);
        $sizeKB = round($sizeBytes / 1024, 1);

        echo "  📊 Type: {$contentType}\n";

        if ($sizeBytes > 0) {
            if ($sizeMB >= 1) {
                echo "  📏 Size: {$sizeMB}MB ({$sizeBytes} bytes)\n";
            } else {
                echo "  📏 Size: {$sizeKB}KB ({$sizeBytes} bytes)\n";
            }
        } else {
            echo "  ⚠️  Size: Unknown\n";
        }

        // Check for issues
        $hasIssue = false;

        // Check if HTTPS
        if (strpos($card->image_url, 'http://') === 0) {
            echo "  ⚠️  WARNING: Using HTTP (should be HTTPS)\n";
            $issues[] = [
                'card_id' => $card->id,
                'issue' => 'HTTP not HTTPS',
                'url' => $card->image_url
            ];
            $hasIssue = true;
        }

        // Check file size
        if ($sizeBytes > 10485760) { // 10MB
            echo "  ❌ ERROR: Image too large (>10MB) - Won't work on iOS!\n";
            $issues[] = [
                'card_id' => $card->id,
                'issue' => 'Too large (>10MB)',
                'url' => $card->image_url,
                'size' => $sizeMB . 'MB'
            ];
            $hasIssue = true;
        } elseif ($sizeBytes > 1048576) { // 1MB
            echo "  ⚠️  WARNING: Image larger than 1MB - May be slow to load\n";
            $issues[] = [
                'card_id' => $card->id,
                'issue' => 'Large (>1MB)',
                'url' => $card->image_url,
                'size' => $sizeMB . 'MB'
            ];
            $hasIssue = true;
        }

        // Check content type
        $validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array(strtolower($contentType), $validTypes)) {
            echo "  ❌ ERROR: Invalid content type\n";
            $issues[] = [
                'card_id' => $card->id,
                'issue' => 'Invalid type: ' . $contentType,
                'url' => $card->image_url
            ];
            $hasIssue = true;
        }

        if (!$hasIssue) {
            echo "  ✅ Image OK!\n";
            $goodImages++;
        }

    } catch (\Exception $e) {
        echo "  ❌ ERROR: " . $e->getMessage() . "\n";
        $issues[] = [
            'card_id' => $card->id,
            'issue' => $e->getMessage(),
            'url' => $card->image_url
        ];
    }

    echo "\n";
}

// Summary
echo str_repeat("=", 80) . "\n";
echo "\n📊 Summary\n\n";
echo "Total cards checked: " . $cards->count() . "\n";
echo "✅ Good images: {$goodImages}\n";
echo "⚠️  Issues found: " . count($issues) . "\n\n";

if (!empty($issues)) {
    echo "🔧 Issues to fix:\n\n";
    foreach ($issues as $issue) {
        echo "  Card #{$issue['card_id']}: {$issue['issue']}\n";
        if (isset($issue['size'])) {
            echo "    Size: {$issue['size']}\n";
        }
        echo "    URL: " . substr($issue['url'], 0, 60) . "...\n\n";
    }

    echo "\n💡 Recommended actions:\n\n";
    echo "1. For HTTP images:\n";
    echo "   UPDATE cards SET image_url = REPLACE(image_url, 'http://', 'https://') WHERE image_url LIKE 'http://%';\n\n";

    echo "2. For large images (>1MB):\n";
    echo "   - Use a CDN (Cloudinary, Imgix) - auto-optimizes\n";
    echo "   - Or resize images before upload\n";
    echo "   - Backend will add resize params if using supported CDN\n\n";

    echo "3. For very large images (>10MB):\n";
    echo "   - MUST resize - won't work on iOS\n";
    echo "   - Use ImageMagick: mogrify -resize 512x512^ -gravity center -extent 512x512 -quality 85 image.jpg\n\n";
} else {
    echo "🎉 All images look good!\n\n";
}

echo str_repeat("=", 80) . "\n\n";
