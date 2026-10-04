<?php
// api/cache_image.php - On-Demand Categorized Image Caching Endpoint
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cache_helper.php';

$url          = $_GET['url'] ?? '';
$serverFolder = $_GET['server'] ?? 'global';
$mediaType    = $_GET['type'] ?? 'live';

if (empty($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
    header("Location: https://via.placeholder.com/300x400?text=No+Image");
    exit;
}

$cachedUrl = CacheHelper::cacheImage($url, $serverFolder, $mediaType);

if ($cachedUrl !== $url && file_exists(__DIR__ . '/../' . $cachedUrl)) {
    $mime = mime_content_type(__DIR__ . '/../' . $cachedUrl) ?: 'image/jpeg';
    header("Content-Type: " . $mime);
    header("Cache-Control: public, max-age=864000"); // Cache in browser 10 days
    readfile(__DIR__ . '/../' . $cachedUrl);
    exit;
}

// Redirect to original URL if caching fails
header("Location: " . $url);
exit;
