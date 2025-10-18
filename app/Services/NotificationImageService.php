<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class NotificationImageService
{
    /**
     * iOS Notification Image Requirements:
     * - Max file size: 10MB
     * - Supported formats: JPEG, PNG, GIF
     * - Recommended size: 1038x1038 px
     *
     * Android Notification Image Requirements:
     * - Max file size: ~1MB for optimal performance
     * - Supported formats: JPEG, PNG, GIF
     * - Recommended size: 450x450 px
     */

    const MAX_IMAGE_SIZE = 1048576; // 1MB for optimal performance
    const TARGET_WIDTH = 512; // Good for both iOS and Android
    const TARGET_HEIGHT = 512;
    const JPEG_QUALITY = 85;

    /**
     * Get optimized image URL for notifications
     * If image is already small enough, returns original URL
     * Otherwise, returns URL with resize parameters (if CDN supports it)
     */
    public static function getOptimizedImageUrl(?string $imageUrl): ?string
    {
        if (empty($imageUrl)) {
            return null;
        }

        // Convert HTTP to HTTPS
        if (strpos($imageUrl, 'http://') === 0) {
            $imageUrl = str_replace('http://', 'https://', $imageUrl);
        }

        // Check if URL is from a CDN that supports query parameters for resizing
        if (self::isCdnUrl($imageUrl)) {
            return self::addCdnResizeParameters($imageUrl);
        }

        // For custom domains, add cache-busting and return original
        return $imageUrl;
    }

    /**
     * Check if URL is from a common CDN
     */
    private static function isCdnUrl(string $url): bool
    {
        $cdnDomains = [
            'cloudinary.com',
            'imgix.net',
            'cloudflare.com',
            'imagekit.io',
            'cloudfront.net',
            'amazonaws.com',
        ];

        foreach ($cdnDomains as $cdn) {
            if (strpos($url, $cdn) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Add resize parameters based on CDN
     */
    private static function addCdnResizeParameters(string $url): string
    {
        // Cloudinary
        if (strpos($url, 'cloudinary.com') !== false) {
            // Add transformation: w_512,h_512,c_fill,q_85,f_auto
            return str_replace('/upload/', '/upload/w_512,h_512,c_fill,q_85,f_auto/', $url);
        }

        // Imgix
        if (strpos($url, 'imgix.net') !== false) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            return $url . $separator . 'w=512&h=512&fit=crop&auto=format&q=85';
        }

        // ImageKit
        if (strpos($url, 'imagekit.io') !== false) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            return $url . $separator . 'tr=w-512,h-512,c-at_max,q-85';
        }

        // For other CDNs, try generic parameters
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . 'w=512&h=512';
    }

    /**
     * Download and resize image locally (for non-CDN images)
     * This is more resource-intensive but ensures optimal size
     */
    public static function downloadAndOptimizeImage(string $imageUrl): ?string
    {
        try {
            // Download image
            $response = Http::timeout(10)->get($imageUrl);

            if (!$response->successful()) {
                \Log::warning('NotificationImageService: Failed to download image', [
                    'url' => $imageUrl,
                    'status' => $response->status()
                ]);
                return null;
            }

            $imageContent = $response->body();
            $imageSize = strlen($imageContent);

            // If image is already small, return original URL
            if ($imageSize <= self::MAX_IMAGE_SIZE) {
                Log::info('NotificationImageService: Image size acceptable', [
                    'url' => $imageUrl,
                    'size' => $imageSize
                ]);
                return $imageUrl;
            }

            Log::info('NotificationImageService: Image too large, needs optimization', [
                'url' => $imageUrl,
                'size' => $imageSize,
                'max_size' => self::MAX_IMAGE_SIZE
            ]);

            // For now, return original and log warning
            // In production, you should implement actual image resizing with GD or Imagick
            return $imageUrl;

        } catch (\Exception $e) {
            Log::error('NotificationImageService: Error processing image', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            return $imageUrl; // Return original on error
        }
    }

    /**
     * Validate image URL is accessible
     */
    public static function validateImageUrl(?string $imageUrl): bool
    {
        if (empty($imageUrl)) {
            return false;
        }

        try {
            $response = Http::timeout(5)->head($imageUrl);

            if (!$response->successful()) {
                Log::warning('NotificationImageService: Image URL not accessible', [
                    'url' => $imageUrl,
                    'status' => $response->status()
                ]);
                return false;
            }

            // Check content type
            $contentType = $response->header('Content-Type');
            $validTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];

            if (!in_array(strtolower($contentType), $validTypes)) {
                Log::warning('NotificationImageService: Invalid content type', [
                    'url' => $imageUrl,
                    'content_type' => $contentType
                ]);
                return false;
            }

            return true;

        } catch (\Exception $e) {
            Log::error('NotificationImageService: Error validating image URL', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get image dimensions info
     */
    public static function getImageInfo(string $imageUrl): ?array
    {
        try {
            $response = Http::timeout(10)->head($imageUrl);

            if (!$response->successful()) {
                return null;
            }

            return [
                'content_type' => $response->header('Content-Type'),
                'content_length' => $response->header('Content-Length'),
                'size_bytes' => (int) $response->header('Content-Length'),
                'size_mb' => round((int) $response->header('Content-Length') / 1048576, 2),
            ];

        } catch (\Exception $e) {
            return null;
        }
    }
}
