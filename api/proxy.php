<?php
// api/proxy.php - AJAX Proxy endpoint for Xtream API calls with authorization checks

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xtream.php';
require_once __DIR__ . '/../includes/m3u_parser.php';
require_once __DIR__ . '/../includes/cache_helper.php';
require_once __DIR__ . '/../includes/logger.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;
$m3uId    = $_GET['m3u_id']    ?? $_POST['m3u_id']    ?? null;
$action   = $_GET['action']    ?? $_POST['action']    ?? '';

// Handle M3U playlist endpoints
if ($action === 'add_m3u_playlist') {
    header('Content-Type: application/json');
    $name = trim($_POST['name'] ?? '');
    $url  = trim($_POST['url'] ?? '');

    if (empty($name) || empty($url)) {
        echo json_encode(['error' => 'Playlist name and M3U/M3U8 URL are required.']);
        exit;
    }

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO m3u_playlists (user_id, name, url) VALUES (?, ?, ?)");
    $stmt->execute([$_SESSION['user_id'], $name, $url]);
    $newId = $pdo->lastInsertId();

    echo json_encode(['success' => true, 'm3u_id' => $newId, 'name' => $name]);
    exit;
}

if ($action === 'get_m3u_playlists') {
    header('Content-Type: application/json');
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, name, url FROM m3u_playlists WHERE user_id = ? OR user_id IS NULL ORDER BY name ASC");
    $stmt->execute([$_SESSION['user_id']]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    exit;
}

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

    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT url FROM m3u_playlists WHERE id = ? AND (user_id = ? OR user_id IS NULL)");
    $stmt->execute([$m3uId, $_SESSION['user_id']]);
    $playlist = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$playlist) {
        echo json_encode(['error' => 'M3U Playlist not found.']);
        exit;
    }

    $parsed = M3UParser::parseUrl($playlist['url']);

    CacheHelper::setCachedPlaylist('m3u_' . $m3uId, 'm3u_content', $parsed);
    echo json_encode($parsed);
    exit;
}

if (!$serverId && !$m3uId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server ID or M3U ID is required.']);
    exit;
}

if (!canAccessServer($serverId)) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Access denied to this server. Paid subscription required for global servers.']);
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
        
        // Return local proxy URL pointing to api/stream.php so the upstream server sees only 1 server IP
        $localProxyUrl = "api/stream.php?server_id=" . urlencode($serverId) . "&type=" . urlencode($type) . "&stream_id=" . urlencode($streamId) . "&ext=" . urlencode($ext);

        echo json_encode(['stream_url' => $localProxyUrl]);
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
        $resolution = $_GET['resolution'] ?? '1080p';
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

        // Always enforce mp4 container extension for downloads
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
