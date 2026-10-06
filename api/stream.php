<?php
// api/stream.php - Centralized HLS & Media Proxy Script
// Proxies all user stream requests through this server so the upstream IPTV provider sees only 1 connection/IP.

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xtream.php';

if (!isLoggedIn()) {
    http_response_code(403);
    echo "Unauthorized access.";
    exit;
}

$chunkUrl = $_GET['chunk_url'] ?? null;
$rawUrl   = $_GET['url']       ?? null;
$serverId = $_GET['server_id'] ?? null;
$type     = $_GET['type']      ?? 'live';
$streamId = $_GET['stream_id'] ?? null;
$ext      = $_GET['ext']       ?? 'mp4';

// 1. Direct chunk/segment proxy
if (!empty($chunkUrl)) {
    $targetUrl = base64_decode($chunkUrl);
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo "Invalid chunk URL.";
        exit;
    }

    proxyDirectStream($targetUrl);
    exit;
}

// 2. Encoded full URL proxy
if (!empty($rawUrl)) {
    $targetUrl = base64_decode($rawUrl);
    if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
        http_response_code(400);
        echo "Invalid target URL.";
        exit;
    }

    if (strpos($targetUrl, '.m3u8') !== false) {
        proxyHlsPlaylist($targetUrl, $serverId);
    } else {
        proxyDirectStream($targetUrl);
    }
    exit;
}

// 3. Xtream Server stream proxy via server_id & stream_id
if (!empty($serverId) && !empty($streamId)) {
    if (!canAccessServer($serverId)) {
        http_response_code(403);
        echo "Access denied to server.";
        exit;
    }

    $server = getServerById($serverId);
    if (!$server) {
        http_response_code(404);
        echo "Server not found.";
        exit;
    }

    $api = new XtreamAPI($server['host'], $server['username'], $server['password'], $server['m3u_url'] ?? null);

    if ($type === 'live') {
        $upstreamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        proxyHlsPlaylist($upstreamUrl, $serverId);
    } elseif ($type === 'movie' || $type === 'vod') {
        $upstreamUrl = $api->getVodStreamUrl($streamId, $ext);
        proxyDirectStream($upstreamUrl);
    } elseif ($type === 'series') {
        $upstreamUrl = $api->getSeriesStreamUrl($streamId, $ext);
        proxyDirectStream($upstreamUrl);
    } else {
        $upstreamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        proxyHlsPlaylist($upstreamUrl, $serverId);
    }
    exit;
}

http_response_code(400);
echo "Missing stream parameters.";
exit;

/**
 * Download HLS .m3u8 playlist from upstream and rewrite internal segment URLs
 * to route back through api/stream.php?chunk_url=...
 */
function proxyHlsPlaylist($m3u8Url, $serverId) {
    header('Content-Type: application/x-mpegURL');
    header('Access-Control-Allow-Origin: *');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $m3u8Url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'IPTVSmartersPlayer/3.0.0'
    ]);

    $content  = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($content)) {
        http_response_code(502);
        echo "#EXTM3U\n#EXT-X-ERROR: Unable to fetch upstream HLS playlist.";
        exit;
    }

    $baseUrl = substr($m3u8Url, 0, strrpos($m3u8Url, '/') + 1);
    $lines = explode("\n", str_replace("\r", "", $content));
    $rewritten = [];

    foreach ($lines as $line) {
        $trimmed = trim($line);
        if (empty($trimmed)) continue;

        if (strpos($trimmed, '#') === 0) {
            // Check for URI inside tags like #EXT-X-KEY:METHOD=AES-128,URI="http..."
            if (preg_match('/URI="([^"]+)"/i', $trimmed, $matches)) {
                $segmentUrl = $matches[1];
                if (strpos($segmentUrl, 'http://') !== 0 && strpos($segmentUrl, 'https://') !== 0) {
                    $segmentUrl = $baseUrl . ltrim($segmentUrl, '/');
                }
                $proxyChunk = 'api/stream.php?chunk_url=' . urlencode(base64_encode($segmentUrl));
                $trimmed = str_replace($matches[1], $proxyChunk, $trimmed);
            }
            $rewritten[] = $trimmed;
        } else {
            // Segment URL (.ts or nested .m3u8)
            $segmentUrl = $trimmed;
            if (strpos($segmentUrl, 'http://') !== 0 && strpos($segmentUrl, 'https://') !== 0) {
                $segmentUrl = $baseUrl . ltrim($segmentUrl, '/');
            }
            $proxyChunk = 'api/stream.php?chunk_url=' . urlencode(base64_encode($segmentUrl));
            $rewritten[] = $proxyChunk;
        }
    }

    echo implode("\n", $rewritten);
}

/**
 * Stream binary media bytes (.ts / .mp4) directly from upstream to browser.
 */
function proxyDirectStream($targetUrl) {
    header('Access-Control-Allow-Origin: *');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $targetUrl,
        CURLOPT_RETURNTRANSFER => false, // Stream response directly to output buffer
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 0, // No timeout for continuous streaming
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'IPTVSmartersPlayer/3.0.0',
        CURLOPT_HEADERFUNCTION => function($curl, $header) {
            $len = strlen($header);
            $headerParts = explode(':', $header, 2);
            if (count($headerParts) == 2) {
                $name = strtolower(trim($headerParts[0]));
                if (in_array($name, ['content-type', 'content-length', 'accept-ranges', 'content-range'])) {
                    header($header, true);
                }
            }
            return $len;
        }
    ]);

    curl_exec($ch);
    curl_close($ch);
}
