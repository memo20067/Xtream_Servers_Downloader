<?php
// api/proxy.php - AJAX Proxy endpoint for Xtream API calls with authorization checks

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xtream.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

$serverId = $_GET['server_id'] ?? $_POST['server_id'] ?? null;
$action   = $_GET['action']    ?? $_POST['action']    ?? '';

if (!$serverId) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Server ID is required.']);
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

$api = new XtreamAPI($server['host'], $server['username'], $server['password']);
$categoryId = $_GET['category_id'] ?? null;
$seriesId   = $_GET['series_id']   ?? null;
$streamId   = $_GET['stream_id']   ?? null;
$ext        = $_GET['container_extension'] ?? $_GET['ext'] ?? 'mp4';

switch ($action) {
    case 'get_live_categories':
        header('Content-Type: application/json');
        echo json_encode($api->getLiveCategories());
        break;

    case 'get_live_streams':
        header('Content-Type: application/json');
        echo json_encode($api->getLiveStreams($categoryId));
        break;

    case 'get_vod_categories':
        header('Content-Type: application/json');
        echo json_encode($api->getVodCategories());
        break;

    case 'get_vod_streams':
        header('Content-Type: application/json');
        echo json_encode($api->getVodStreams($categoryId));
        break;

    case 'get_series_categories':
        header('Content-Type: application/json');
        echo json_encode($api->getSeriesCategories());
        break;

    case 'get_series':
        header('Content-Type: application/json');
        echo json_encode($api->getSeries($categoryId));
        break;

    case 'get_series_info':
        header('Content-Type: application/json');
        if (!$seriesId) {
            echo json_encode(['error' => 'Series ID required.']);
            exit;
        }
        echo json_encode($api->getSeriesInfo($seriesId));
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

        // Set response headers to force download in .mp4 format named with title
        header('Content-Description: File Transfer');
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        // Redirect or stream from target IPTV host URL
        header("Location: " . $url);
        exit;

    default:
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid action.']);
        break;
}
