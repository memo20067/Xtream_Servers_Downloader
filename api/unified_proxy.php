<?php
// api/unified_proxy.php - Unified Xtream Server Gateway
// All Xtream server traffic routes through here to ensure:
// 1. Single device identity per server (consistent User-Agent)
// 2. No credential exposure to clients
// 3. Independent concurrent playback per user

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xtream.php';
require_once __DIR__ . '/../includes/m3u_parser.php';
require_once __DIR__ . '/../includes/cache_helper.php';
require_once __DIR__ . '/../includes/logger.php';

$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/api/unified_proxy.php');
$appRoot = dirname(dirname($scriptName));
$appRoot = ($appRoot === '.' || $appRoot === '/') ? '' : '/' . trim($appRoot, '/');
$proxyEndpointPath = $appRoot . '/api/unified_proxy.php';

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

// Strict access guard: reject any server_id not explicitly added by authorized user
$serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;

if (!$serverId) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Server ID is required.']);
    exit;
}

if (!canAccessServer($serverId)) {
    header('Content-Type: application/json');
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this server.']);
    exit;
}

$server = getServerById($serverId);
if (!$server) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Server not found.']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Stream Proxy: all stream traffic routes through here
if ($action === 'stream_proxy') {
    $streamUrl = $_GET['url'] ?? '';
    if (empty($streamUrl)) {
        header('HTTP/1.1 400 Bad Request');
        echo 'Missing stream url';
        exit;
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL            => $streamUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamGateway/1.0',
        CURLOPT_ENCODING       => '',
        CURLOPT_HEADER         => false,
    ]);
    $response     = curl_exec($ch);
    $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $streamUrl;
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        http_response_code($httpCode ?: 502);
        echo "Failed to fetch remote stream.";
        exit;
    }

    // Check if response is M3U/M3U8 playlist text
    $isM3u = (stripos($contentType, 'mpegurl') !== false || stripos($streamUrl, '.m3u') !== false || strpos($response, '#EXTM3U') === 0);

    if ($isM3u) {
        header("Content-Type: application/x-mpegURL");
        $lines = explode("\n", $response);
        $output = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && strpos($trimmed, '#') !== 0) {
                $resolvedUrl = M3UParser::resolveRelativeUrl($effectiveUrl, $trimmed);
                $output[] = $proxyEndpointPath . '?action=stream_proxy&server_id=' . urlencode($serverId) . '&url=' . rawurlencode($resolvedUrl);
            } else {
                $output[] = $line;
            }
        }
        echo implode("\n", $output);
        exit;
    }

    // Binary media chunk
    header("Content-Type: " . ($contentType ?: 'video/mp2t'));
    header("Content-Length: " . strlen($response));
    echo $response;
    exit;
}

// All other actions require logged in user
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// Initialize Xtream API with single device identity
$api = new XtreamAPI($server['host'], $server['username'], $server['password'], $server['m3u_url'] ?? null, getDBConnection(), 86400);

$categoryId = $_GET['category_id'] ?? null;
$seriesId   = $_GET['series_id']   ?? null;
$streamId   = $_GET['stream_id']   ?? null;
$ext        = $_GET['container_extension'] ?? $_GET['ext'] ?? 'mp4';

switch ($action) {
    case 'get_live_categories':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'live_categories');
        if ($cached && !empty($cached['data'])) {
            echo json_encode($cached['data']);
        } else {
            $res = $api->getLiveCategories();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'live_categories', $res);
            }
            echo json_encode($res);
        }
        break;

    case 'get_live_streams':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'live_streams');
        if ($cached && !empty($cached['data'])) {
            $data = $cached['data'];
            if ($categoryId !== null && $categoryId !== '') {
                $data = array_values(array_filter($data, function($item) use ($categoryId) {
                    return (string)($item['category_id'] ?? '') === (string)$categoryId;
                }));
            }
            echo json_encode($data);
        } else {
            $res = $api->getLiveStreams();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'live_streams', $res);
                if ($categoryId !== null && $categoryId !== '') {
                    $res = array_values(array_filter($res, function($item) use ($categoryId) {
                        return (string)($item['category_id'] ?? '') === (string)$categoryId;
                    }));
                }
            }
            echo json_encode($res);
        }
        break;

    case 'get_vod_categories':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'vod_categories');
        if ($cached && !empty($cached['data'])) {
            echo json_encode($cached['data']);
        } else {
            $res = $api->getVodCategories();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'vod_categories', $res);
            }
            echo json_encode($res);
        }
        break;

    case 'get_vod_streams':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'vod_streams');
        if ($cached && !empty($cached['data'])) {
            $data = $cached['data'];
            if ($categoryId !== null && $categoryId !== '') {
                $data = array_values(array_filter($data, function($item) use ($categoryId) {
                    return (string)($item['category_id'] ?? '') === (string)$categoryId;
                }));
            }
            echo json_encode($data);
        } else {
            $res = $api->getVodStreams();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'vod_streams', $res);
                if ($categoryId !== null && $categoryId !== '') {
                    $res = array_values(array_filter($res, function($item) use ($categoryId) {
                        return (string)($item['category_id'] ?? '') === (string)$categoryId;
                    }));
                }
            }
            echo json_encode($res);
        }
        break;

    case 'get_series_categories':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'series_categories');
        if ($cached && !empty($cached['data'])) {
            echo json_encode($cached['data']);
        } else {
            $res = $api->getSeriesCategories();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'series_categories', $res);
            }
            echo json_encode($res);
        }
        break;

    case 'get_series':
        header('Content-Type: application/json');
        $cached = CacheHelper::getCachedPlaylist($serverId, 'series');
        if ($cached && !empty($cached['data'])) {
            $data = $cached['data'];
            if ($categoryId !== null && $categoryId !== '') {
                $data = array_values(array_filter($data, function($item) use ($categoryId) {
                    return (string)($item['category_id'] ?? '') === (string)$categoryId;
                }));
            }
            echo json_encode($data);
        } else {
            $res = $api->getSeries();
            if (is_array($res) && !empty($res) && !isset($res['error'])) {
                CacheHelper::setCachedPlaylist($serverId, 'series', $res);
                if ($categoryId !== null && $categoryId !== '') {
                    $res = array_values(array_filter($res, function($item) use ($categoryId) {
                        return (string)($item['category_id'] ?? '') === (string)$categoryId;
                    }));
                }
            }
            echo json_encode($res);
        }
        break;

    case 'get_series_info':
        header('Content-Type: application/json');
        if (!$seriesId) {
            echo json_encode(['error' => 'Series ID required.']);
            exit;
        }
        echo json_encode($api->getSeriesInfo($seriesId));
        break;

    case 'get_epg':
        header('Content-Type: application/json');
        $type = $_GET['type'] ?? 'live';
        $targetId = $seriesId ?: $streamId;

        if ($type === 'live' && $streamId) {
            $epgData = $api->getShortEpg($streamId);
            if (!empty($epgData)) {
                CacheHelper::updateItemEpg($serverId, 'live', $streamId, $epgData);
            }
            echo json_encode($epgData);
        } elseif (($type === 'movie' || $type === 'vod') && $streamId) {
            $vodInfo = $api->getVodInfo($streamId);
            if (!empty($vodInfo)) {
                CacheHelper::updateItemEpg($serverId, 'movie', $streamId, $vodInfo);
            }
            echo json_encode($vodInfo);
        } elseif ($type === 'series' && ($seriesId || $streamId)) {
            $targetSeriesId = $seriesId ?: $streamId;
            $seriesInfo = $api->getSeriesInfo($targetSeriesId);
            if (!empty($seriesInfo)) {
                CacheHelper::updateItemEpg($serverId, 'series', $targetSeriesId, $seriesInfo);
            }
            echo json_encode($seriesInfo);
        } else {
            echo json_encode(['epg_listings' => []]);
        }
        break;

    case 'get_stream_url':
        header('Content-Type: application/json');
        $type = $_GET['type'] ?? 'live';
        if (!$streamId) {
            echo json_encode(['error' => 'Stream ID required.']);
            exit;
        }

        // Return a same-origin proxy path instead of exposing the source URL.
        $streamUrl = '';

        if ($type === 'live') {
            $streamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        } elseif ($type === 'movie' || $type === 'vod') {
            $streamUrl = $api->getVodStreamUrl($streamId, $ext);
        } elseif ($type === 'series') {
            $streamUrl = $api->getSeriesStreamUrl($streamId, $ext);
        } else {
            $streamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        }

        // Route through unified proxy so credentials are never exposed
        echo json_encode([
            'stream_url' => $proxyEndpointPath . '?action=stream_proxy&server_id=' . urlencode($serverId) . '&url=' . rawurlencode($streamUrl)
        ]);
        break;

    case 'download_stream':
        header('Content-Type: application/json; charset=utf-8');
        if (!hasPaidSubscription()) {
            http_response_code(403);
            echo json_encode(['error' => 'A paid subscription is required to download this file.']);
            exit;
        }

        $type = $_GET['type'] ?? 'movie';
        $title = trim((string)($_GET['title'] ?? ($type === 'series' ? 'Episode' : 'Movie')));
        $resolution = $_GET['resolution'] ?? 'source';
        $fileExt = $_GET['container_extension'] ?? 'mp4';
        $fileExt = preg_match('/^[a-z0-9]{1,8}$/i', $fileExt) ? strtolower($fileExt) : 'mp4';

        if (!in_array($type, ['movie', 'vod', 'series'], true)) {
            http_response_code(400);
            echo json_encode(['error' => 'Only movies and series episodes can be downloaded.']);
            exit;
        }
        if ($resolution !== 'source') {
            http_response_code(422);
            echo json_encode(['error' => 'This source does not provide transcoding. Only the source file quality can be downloaded.']);
            exit;
        }
        if ($streamId === null || $streamId === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Stream ID is required.']);
            exit;
        }

        $safeTitle = preg_replace('/[^\pL\pN._ -]+/u', '', $title);
        $safeTitle = trim((string)$safeTitle, " ._-\t\n\r\0\x0B");
        if ($safeTitle === '') $safeTitle = 'video';
        $safeTitle = substr($safeTitle, 0, 150);
        $filename = $safeTitle . '.' . $fileExt;

        $rawUrl = ($type === 'series')
            ? $api->getSeriesStreamUrl($streamId, $fileExt)
            : $api->getVodStreamUrl($streamId, $fileExt);
        if ($rawUrl === '') {
            http_response_code(502);
            echo json_encode(['error' => 'The source did not provide a download URL.']);
            exit;
        }

        // Return an authorized local route. The source credentials are rebuilt server-side.
        $downloadQuery = http_build_query([
            'action' => 'download_proxy',
            'server_id' => $serverId,
            'type' => $type,
            'stream_id' => $streamId,
            'container_extension' => $fileExt,
            'filename' => $filename
        ]);
        $downloadPath = $proxyEndpointPath . '?' . $downloadQuery;

        if (isset($_GET['prepare']) && $_GET['prepare'] === '1') {
            echo json_encode(['download_url' => $downloadPath, 'filename' => $filename], JSON_UNESCAPED_UNICODE);
            exit;
        }

        header('Location: ' . $downloadPath, true, 302);
        exit;

    case 'download_proxy':
        if (!hasPaidSubscription()) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'A paid subscription is required to download this file.';
            exit;
        }

        // Never accept a caller-provided remote URL here: that would expose credentials and enable SSRF.
        $downloadType = $_GET['type'] ?? '';
        $downloadId = trim((string)($_GET['stream_id'] ?? ''));
        $downloadExt = $_GET['container_extension'] ?? 'mp4';
        $downloadExt = preg_match('/^[a-z0-9]{1,8}$/i', $downloadExt) ? strtolower($downloadExt) : 'mp4';
        $filename = basename((string)($_GET['filename'] ?? 'download.' . $downloadExt));
        if (!in_array($downloadType, ['movie', 'vod', 'series'], true) || $downloadId === '') {
            http_response_code(400);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'A supported media item is required.';
            exit;
        }

        $downloadUrl = $downloadType === 'series'
            ? $api->getSeriesStreamUrl($downloadId, $downloadExt)
            : $api->getVodStreamUrl($downloadId, $downloadExt);
        $tempFile = tempnam(sys_get_temp_dir(), 'xtream-download-');
        if ($tempFile === false) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Could not prepare the download.';
            exit;
        }

        $fileHandle = fopen($tempFile, 'wb');
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $downloadUrl,
            CURLOPT_FILE => $fileHandle,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 XtreamGateway/1.0',
            CURLOPT_ENCODING => ''
        ]);
        $success = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: 'application/octet-stream';
        $curlError = curl_error($ch);
        curl_close($ch);
        fclose($fileHandle);

        if ($success !== true || $httpCode < 200 || $httpCode >= 300 || !is_file($tempFile) || filesize($tempFile) === 0) {
            @unlink($tempFile);
            http_response_code($httpCode >= 400 ? $httpCode : 502);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'The source could not provide this file. ' . ($curlError ? 'Please try again later.' : '');
            exit;
        }

        $asciiFilename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        header('Content-Type: ' . $contentType);
        header('Content-Length: ' . filesize($tempFile));
        header('Content-Disposition: attachment; filename="' . $asciiFilename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('X-Content-Type-Options: nosniff');
        readfile($tempFile);
        @unlink($tempFile);
        exit;

    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action.']);
        break;
}
