<?php
// cron/refresh_cache.php - 24-Hour Automated Cache Refresh
// This script should be called via web cron or system cron every 24 hours
// to ensure all cached content is fresh and up-to-date.

require_once __DIR__ . '/../config/init.php';
require_once __DIR__ . '/../includes/xtream.php';
require_once __DIR__ . '/../includes/cache_helper.php';
require_once __DIR__ . '/../includes/logger.php';
require_once __DIR__ . '/../includes/m3u_parser.php';

// Allow CLI or authenticated web requests
$isCli = (php_sapi_name() === 'cli');
$isWebCron = (!$isCli && isset($_GET['cron_secret']) && $_GET['cron_secret'] === getSetting('cron_secret', ''));

if (!$isCli && !$isWebCron) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized.']);
    exit;
}

$db = getDBConnection();
$startTime = microtime(true);
$results = [
    'servers_processed' => 0,
    'm3u_processed' => 0,
    'cache_refreshed' => 0,
    'errors' => []
];

// 1. Refresh all global and user Xtream server caches
try {
    $stmt = $db->query("SELECT id, host, username, password, m3u_url FROM servers");
    $servers = $stmt->fetchAll();

    foreach ($servers as $server) {
        $results['servers_processed']++;

        try {
            $api = new XtreamAPI($server['host'], $server['username'], $server['password'], $server['m3u_url'] ?? null, $db, 86400);

            // Refresh live categories
            $liveCats = $api->getLiveCategories();
            if (is_array($liveCats) && !empty($liveCats) && !isset($liveCats['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'live_categories', $liveCats);
                $results['cache_refreshed']++;
            }

            // Refresh VOD categories
            $vodCats = $api->getVodCategories();
            if (is_array($vodCats) && !empty($vodCats) && !isset($vodCats['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'vod_categories', $vodCats);
                $results['cache_refreshed']++;
            }

            // Refresh series categories
            $seriesCats = $api->getSeriesCategories();
            if (is_array($seriesCats) && !empty($seriesCats) && !isset($seriesCats['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'series_categories', $seriesCats);
                $results['cache_refreshed']++;
            }

            // Refresh live streams
            $liveStreams = $api->getLiveStreams();
            if (is_array($liveStreams) && !empty($liveStreams) && !isset($liveStreams['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'live_streams', $liveStreams);
                $results['cache_refreshed']++;
            }

            // Refresh VOD streams
            $vodStreams = $api->getVodStreams();
            if (is_array($vodStreams) && !empty($vodStreams) && !isset($vodStreams['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'vod_streams', $vodStreams);
                $results['cache_refreshed']++;
            }

            // Refresh series
            $seriesList = $api->getSeries();
            if (is_array($seriesList) && !empty($seriesList) && !isset($seriesList['error'])) {
                CacheHelper::setCachedPlaylist($server['id'], 'series', $seriesList);
                $results['cache_refreshed']++;
            }

        } catch (Throwable $e) {
            $results['errors'][] = "Server #{$server['id']}: " . $e->getMessage();
            Logger::log("Cache refresh failed for server #{$server['id']}", 'ERROR', 'cache_refresh', $server['host'], [
                'error' => $e->getMessage()
            ]);
        }
    }
} catch (Throwable $e) {
    $results['errors'][] = "Database error: " . $e->getMessage();
}

// 2. Refresh all M3U playlist caches
try {
    $stmt = $db->query("SELECT id, name, url FROM m3u_playlists");
    $m3uPlaylists = $stmt->fetchAll();

    foreach ($m3uPlaylists as $playlist) {
        $results['m3u_processed']++;

        try {
            $parsed = M3UParser::parseUrl($playlist['url'], false, $playlist['name'] ?? '');
            if (!isset($parsed['error'])) {
                CacheHelper::setCachedPlaylist('m3u_' . $playlist['id'], 'm3u_content', $parsed);
                $results['cache_refreshed']++;
            }
        } catch (Throwable $e) {
            $results['errors'][] = "M3U #{$playlist['id']}: " . $e->getMessage();
            Logger::log("Cache refresh failed for M3U #{$playlist['id']}", 'ERROR', 'cache_refresh', $playlist['url'], [
                'error' => $e->getMessage()
            ]);
        }
    }
} catch (Throwable $e) {
    $results['errors'][] = "M3U Database error: " . $e->getMessage();
}

// 3. Clean expired caches older than 48 hours
try {
    $db->exec("DELETE FROM xtream_cache WHERE expires_at < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
    $db->exec("DELETE FROM playlist_cache WHERE last_updated < DATE_SUB(NOW(), INTERVAL 48 HOUR)");
} catch (Throwable $e) {
    // ignore cleanup errors
}

$results['duration_seconds'] = round(microtime(true) - $startTime, 2);
$results['timestamp'] = date('Y-m-d H:i:s');

// Log cache refresh summary
Logger::log("24h cache refresh completed: {$results['cache_refreshed']} items refreshed across {$results['servers_processed']} servers and {$results['m3u_processed']} M3U playlists.", 'INFO', 'cache_refresh', null, $results);

if (!$isCli) {
    header('Content-Type: application/json');
    echo json_encode($results);
}
