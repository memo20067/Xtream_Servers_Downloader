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

function createXtreamStreamToken(string $targetUrl): string {
    if (session_status() !== PHP_SESSION_ACTIVE) return '';
    $now = time();
    if (!isset($_SESSION['xtream_stream_proxy']) || !is_array($_SESSION['xtream_stream_proxy'])) {
        $_SESSION['xtream_stream_proxy'] = [];
    }
    foreach ($_SESSION['xtream_stream_proxy'] as $existingToken => $record) {
        if (!is_array($record) || (int)($record['expires_at'] ?? 0) < $now) unset($_SESSION['xtream_stream_proxy'][$existingToken]);
    }
    $token = bin2hex(random_bytes(24));
    $_SESSION['xtream_stream_proxy'][$token] = ['url' => $targetUrl, 'expires_at' => $now + 43200];
    return $token;
}

function getXtreamStreamTarget(string $token): string {
    if (session_status() !== PHP_SESSION_ACTIVE || !preg_match('/^[a-f0-9]{48}$/', $token)) return '';
    $record = $_SESSION['xtream_stream_proxy'][$token] ?? null;
    if (!is_array($record) || (int)($record['expires_at'] ?? 0) < time()) {
        unset($_SESSION['xtream_stream_proxy'][$token]);
        return '';
    }
    return is_string($record['url'] ?? null) ? $record['url'] : '';
}

// Stream live media incrementally; only HLS playlists are buffered and rewritten.
if ($action === 'stream_proxy') {
    $streamUrl = getXtreamStreamTarget((string)($_GET['token'] ?? ''));
    $remoteParts = parse_url($streamUrl);
    if ($streamUrl === '' || !is_array($remoteParts) || empty($remoteParts['host']) || !in_array(strtolower((string)($remoteParts['scheme'] ?? '')), ['http', 'https'], true)) {
        http_response_code($streamUrl === '' ? 403 : 400);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'The stream token is invalid or the stream URL is unavailable.';
        exit;
    }

    @set_time_limit(0);
    @ini_set('zlib.output_compression', '0');
    $httpCode = 0;
    $contentType = '';
    $effectiveUrl = $streamUrl;
    $playlistBody = '';
    $probeBody = '';
    $isPlaylist = preg_match('/\\.m3u8?(?:$|[?#])/i', $streamUrl) === 1;
    $playlistTooLarge = false;
    $headersPrepared = false;
    $contentLength = '';
    $contentRange = '';
    $acceptRanges = '';
    $rangeHeader = (string)($_SERVER['HTTP_RANGE'] ?? '');
    $remoteRange = preg_match('/^bytes=(\\d*-\\d*)$/i', $rangeHeader, $rangeMatch) ? $rangeMatch[1] : '';

    $sendStreamHeaders = static function () use (&$headersPrepared, &$httpCode, &$contentType, &$contentLength, &$contentRange, &$acceptRanges): void {
        if ($headersPrepared || headers_sent()) return;
        $headersPrepared = true;
        http_response_code($httpCode >= 200 && $httpCode < 300 ? $httpCode : 502);
        header('Content-Type: ' . ($contentType !== '' ? $contentType : 'application/octet-stream'));
        if ($contentLength !== '' && ctype_digit($contentLength)) header('Content-Length: ' . $contentLength);
        if ($contentRange !== '' && preg_match('/^bytes \\d+-\\d+\\/(?:\\d+|\\*)$/i', $contentRange)) header('Content-Range: ' . $contentRange);
        if (strtolower($acceptRanges) === 'bytes') header('Accept-Ranges: bytes');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        header('X-Accel-Buffering: no');
    };
    $flushStream = static function (): void {
        while (ob_get_level() > 0) @ob_end_flush();
        flush();
    };

    $ch = curl_init($streamUrl);
    $curlOptions = [
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_TIMEOUT        => 0,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamGateway/1.0',
        CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$httpCode, &$contentType, &$isPlaylist, &$contentLength, &$contentRange, &$acceptRanges): int {
            if (preg_match('/^HTTP\\/\\S+\\s+(\\d{3})/i', $line, $matches)) {
                $httpCode = (int)$matches[1];
                $contentType = '';
                $contentLength = '';
                $contentRange = '';
                $acceptRanges = '';
                return strlen($line);
            }
            if (stripos($line, 'Content-Type:') === 0) {
                $contentType = trim(substr($line, strlen('Content-Type:')));
                if (stripos($contentType, 'mpegurl') !== false) $isPlaylist = true;
            } elseif (stripos($line, 'Content-Length:') === 0) {
                $candidateLength = trim(substr($line, strlen('Content-Length:')));
                if (ctype_digit($candidateLength)) $contentLength = $candidateLength;
            } elseif (stripos($line, 'Content-Range:') === 0) {
                $contentRange = trim(substr($line, strlen('Content-Range:')));
            } elseif (stripos($line, 'Accept-Ranges:') === 0) {
                $acceptRanges = trim(substr($line, strlen('Accept-Ranges:')));
            }
            return strlen($line);
        },
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$httpCode, &$contentType, &$isPlaylist, &$playlistBody, &$probeBody, &$playlistTooLarge, $sendStreamHeaders, $flushStream): int {
            $length = strlen($chunk);
            if ($httpCode < 200 || $httpCode >= 300) return $length;

            if ($isPlaylist) {
                $playlistBody .= $chunk;
                if (strlen($playlistBody) > 4194304) {
                    $playlistTooLarge = true;
                    return 0;
                }
                return $length;
            }

            if ($probeBody !== '') {
                $probeBody .= $chunk;
            } else {
                $probeBody = $chunk;
            }
            $prefix = ltrim($probeBody, "\xEF\xBB\xBF \t\r\n");
            if (strncmp($prefix, '#EXTM3U', 7) === 0) {
                $isPlaylist = true;
                $playlistBody = $probeBody;
                $probeBody = '';
                if (strlen($playlistBody) > 4194304) {
                    $playlistTooLarge = true;
                    return 0;
                }
                return $length;
            }

            if (strlen($probeBody) < 16) return $length;
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
            $sendStreamHeaders();
            echo $probeBody;
            $probeBody = '';
            $flushStream();
            return $length;
        },
    ];
    if ($remoteRange !== '') $curlOptions[CURLOPT_RANGE] = $remoteRange;
    curl_setopt_array($ch, $curlOptions);

    $transferResult = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: $contentType);
    $effectiveUrl = (string)(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $streamUrl);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($httpCode < 200 || $httpCode >= 300 || $transferResult === false || $playlistTooLarge) {
        if (!headers_sent()) {
            http_response_code($playlistTooLarge ? 502 : ($httpCode >= 400 ? $httpCode : 502));
            header('Content-Type: text/plain; charset=utf-8');
            echo $playlistTooLarge ? 'The source playlist is too large to process.' : 'The source could not provide this stream.';
        }
        exit;
    }

    if ($isPlaylist) {
        $output = [];
        foreach (preg_split('/\\r?\\n/', $playlistBody) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                $output[] = $line;
                continue;
            }
            if ($trimmed[0] === '#') {
                $output[] = preg_replace_callback('/URI="([^"]+)"/i', static function (array $match) use ($effectiveUrl, $serverId, $proxyEndpointPath): string {
                    $uri = trim($match[1]);
                    if ($uri === '' || preg_match('/^(?:data:|skd:)/i', $uri)) return $match[0];
                    $resolved = M3UParser::resolveRelativeUrl($effectiveUrl, $uri);
                    $token = createXtreamStreamToken($resolved);
                    if ($token === '') return $match[0];
                    $proxied = $proxyEndpointPath . '?action=stream_proxy&server_id=' . rawurlencode((string)$serverId) . '&token=' . rawurlencode($token);
                    return 'URI="' . $proxied . '"';
                }, $line) ?? $line;
                continue;
            }
            $resolvedUrl = M3UParser::resolveRelativeUrl($effectiveUrl, $trimmed);
            $token = createXtreamStreamToken($resolvedUrl);
            $output[] = $token === '' ? '' : $proxyEndpointPath . '?action=stream_proxy&server_id=' . rawurlencode((string)$serverId) . '&token=' . rawurlencode($token);
        }
        if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: application/x-mpegURL; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo implode("\n", $output);
        exit;
    }

    if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
    if (!headers_sent()) {
        $sendStreamHeaders();
        if ($probeBody !== '') echo $probeBody;
    }
    $flushStream();
    exit;
}

// All other actions require logged in user
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized access.']);
    exit;
}

// Metadata endpoints may take a long time on some Xtream servers. Release PHP's
// per-session lock after authorization so their response cannot block stream_proxy.
if ($action !== 'get_stream_url' && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
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
        $rawStreamUrl = '';

        if ($type === 'live') {
            $rawStreamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        } elseif ($type === 'movie' || $type === 'vod') {
            $rawStreamUrl = $api->getVodStreamUrl($streamId, $ext);
        } elseif ($type === 'series') {
            $rawStreamUrl = $api->getSeriesStreamUrl($streamId, $ext);
        } else {
            $rawStreamUrl = $api->getLiveStreamUrl($streamId, 'm3u8');
        }

        $streamToken = $rawStreamUrl !== '' ? createXtreamStreamToken($rawStreamUrl) : '';
        if ($streamToken === '') {
            http_response_code(502);
            echo json_encode(['error' => 'The source did not provide a playback URL.']);
            exit;
        }
        echo json_encode([
            'stream_url' => $proxyEndpointPath . '?action=stream_proxy&server_id=' . rawurlencode((string)$serverId) . '&token=' . rawurlencode($streamToken)
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
