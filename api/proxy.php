<?php
// api/proxy.php - AJAX Proxy endpoint for Xtream API calls with authorization checks

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xtream.php';
require_once __DIR__ . '/../includes/m3u_parser.php';
require_once __DIR__ . '/../includes/cache_helper.php';
require_once __DIR__ . '/../includes/logger.php';

// Enable CORS for AJAX and player requests
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Stream Proxy Endpoint (Can proxy streams / HLS with CORS headers for in-browser playback)
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
        CURLOPT_TIMEOUT        => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    ]);
    $response     = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType  = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $streamUrl;
    curl_close($ch);

    if ($httpCode !== 200 || $response === false) {
        http_response_code($httpCode ?: 502);
        echo "Failed to fetch remote stream.";
        exit;
    }

    // Check if response is M3U / M3U8 playlist text
    $isM3u = (stripos($contentType, 'mpegurl') !== false || stripos($streamUrl, '.m3u') !== false || strpos($response, '#EXTM3U') === 0);

    if ($isM3u) {
        header("Content-Type: application/x-mpegURL");
        $lines = explode("\n", $response);
        $output = [];
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed) && strpos($trimmed, '#') !== 0) {
                // Resolve relative chunk URL
                $resolvedUrl = M3UParser::resolveRelativeUrl($effectiveUrl, $trimmed);
                // Route through proxy to ensure CORS compliance for chunks
                $output[] = 'api/proxy.php?action=stream_proxy&url=' . rawurlencode($resolvedUrl);
            } else {
                $output[] = $line;
            }
        }
        echo implode("\n", $output);
        exit;
    }

    // Binary media chunk (.ts, .aac, .mp4)
    header("Content-Type: " . ($contentType ?: 'video/mp2t'));
    header("Content-Length: " . strlen($response));
    echo $response;
    exit;
}

// All other API endpoints require logged in user
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// Handle M3U playlist endpoints
if ($action === 'add_m3u_playlist') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $url  = trim($_POST['url'] ?? '');

    if (empty($name) || empty($url)) {
        echo json_encode(['error' => 'Playlist name and M3U/M3U8 URL are required.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("INSERT INTO m3u_playlists (user_id, name, url) VALUES (?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $url]);
        $newId = $pdo->lastInsertId();

        echo json_encode(['success' => true, 'm3u_id' => $newId, 'name' => $name]);
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'delete_m3u_playlist') {
    header('Content-Type: application/json');
    $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['error' => 'Invalid Playlist ID.']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        if (isAdmin()) {
            $stmt = $pdo->prepare("DELETE FROM m3u_playlists WHERE id = ?");
            $stmt->execute([$id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM m3u_playlists WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $_SESSION['user_id']]);
        }

        // Delete cache file if exists
        $cacheFile = CacheHelper::getPlaylistCachePath('m3u_' . $id, 'm3u_content');
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }

        echo json_encode(['success' => true]);
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if ($action === 'get_m3u_playlists') {
    header('Content-Type: application/json');
    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name, url FROM m3u_playlists WHERE user_id = ? OR user_id IS NULL ORDER BY name ASC");
        $stmt->execute([$_SESSION['user_id']]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (\Throwable $e) {
        echo json_encode([]);
    }
    exit;
}

$m3uId = $_GET['m3u_id'] ?? $_POST['m3u_id'] ?? null;

if ($action === 'get_m3u_content') {
    header('Content-Type: application/json');
    if (!$m3uId) {
        echo json_encode(['error' => 'M3U Playlist ID is required.']);
        exit;
    }

    // Check playlist_cache first
    $cached = CacheHelper::getCachedPlaylist('m3u_' . $m3uId, 'm3u_content');
    if ($cached && !empty($cached['data'])) {
        echo json_encode($cached['data']);
        exit;
    }

    try {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT id, name, url FROM m3u_playlists WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
        $stmt->execute([$m3uId, $_SESSION['user_id']]);
        $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$playlist) {
            echo json_encode(['error' => 'M3U Playlist not found.']);
            exit;
        }

        $parsed = M3UParser::parseUrl($playlist['url'], false, $playlist['name'] ?? '');

        if (!isset($parsed['error'])) {
            CacheHelper::setCachedPlaylist('m3u_' . $m3uId, 'm3u_content', $parsed);
        }
        echo json_encode($parsed);
    } catch (\Throwable $e) {
        echo json_encode(['error' => 'Failed to parse M3U content: ' . $e->getMessage()]);
    }
    exit;
}

$serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;

if (!$serverId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server ID or M3U ID is required.']);
    exit;
}

if (!canAccessServer($serverId)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied to this server.']);
    exit;
}

$server = getServerById($serverId);
if (!$server) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server not found.']);
    exit;
}

$api = new XtreamAPI($server['host'], $server['username'], $server['password'], $server['m3u_url'] ?? null);
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
        
        if ($type === 'live') {
            $url = $api->getLiveStreamUrl($streamId, 'm3u8');
        } elseif ($type === 'movie' || $type === 'vod') {
            $url = $api->getVodStreamUrl($streamId, $ext);
        } elseif ($type === 'series') {
            $url = $api->getSeriesStreamUrl($streamId, $ext);
        } else {
            $url = $api->getLiveStreamUrl($streamId, 'm3u8');
        }

        echo json_encode(['stream_url' => $url]);
        break;

    case 'download_stream':
        // Enforce subscription check for downloading streams
        if (!hasPaidSubscription()) {
            header('Content-Type: application/json');
            http_response_code(403);
            echo json_encode(['error' => 'Download permission denied. Active paid subscription required.']);
            exit;
        }

        $type = $_GET['type'] ?? 'movie';
        $title = $_GET['title'] ?? ($type === 'series' ? 'Episode' : 'Movie');

        // Sanitize filename for download
        $cleanTitle = preg_replace('/[^A-Za-z0-9_\-\. ]/', '', $title);
        if (empty($cleanTitle)) {
            $cleanTitle = 'video';
        }
        $filename = $cleanTitle . '.mp4';

        if (!$streamId) {
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Stream ID required.']);
            exit;
        }

        $ext = 'mp4';

        if ($type === 'movie' || $type === 'vod') {
            $url = $api->getVodStreamUrl($streamId, $ext);
        } elseif ($type === 'series') {
            $url = $api->getSeriesStreamUrl($streamId, $ext);
        } else {
            $url = $api->getLiveStreamUrl($streamId, 'mp4');
        }

        // Redirect directly to the full IPTV stream URL for direct full-file downloading
        header("Location: " . $url);
        exit;

    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action.']);
        break;
}
