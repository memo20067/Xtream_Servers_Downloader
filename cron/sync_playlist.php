<?php
// cron/sync_playlist.php - Daily IPTV Playlist Caching & Sync Script

if (php_sapi_name() !== 'cli' && !defined('CRON_SYNC_EXECUTING')) {
    // Allow admin manual trigger or CLI cron execution
    require_once __DIR__ . '/../includes/auth.php';
    if (!isLoggedIn() || !isAdmin()) {
        die("Access denied. CLI or Admin session required.");
    }
} else {
    require_once __DIR__ . '/../config/init.php';
    require_once __DIR__ . '/../includes/xtream.php';
}

echo "[" . date('Y-m-d H:i:s') . "] Starting IPTV Playlist Caching & Sync...\n";

$db = getDBConnection();
$stmtServers = $db->query("SELECT * FROM servers ORDER BY id ASC");
$servers = $stmtServers->fetchAll();

if (empty($servers)) {
    echo "No IPTV servers configured for playlist sync.\n";
    exit(0);
}

foreach ($servers as $server) {
    echo "Syncing Server ID: {$server['id']} ({$server['name']})...\n";
    $api = new XtreamAPI($server['host'], $server['username'], $server['password']);

    // 1. Live Categories & Streams
    try {
        $liveCats = $api->getLiveCategories();
        $liveStreams = $api->getLiveStreams();

        savePlaylistCache($db, $server['id'], 'live_categories', null, $liveCats);
        savePlaylistCache($db, $server['id'], 'live_streams', null, $liveStreams);
        echo " - Cached " . count($liveCats) . " Live Categories & " . count($liveStreams) . " Live Streams.\n";
    } catch (Exception $e) {
        echo " - Error syncing Live content: " . $e->getMessage() . "\n";
    }

    // 2. VOD (Movies) Categories & Streams
    try {
        $vodCats = $api->getVodCategories();
        $vodStreams = $api->getVodStreams();

        savePlaylistCache($db, $server['id'], 'vod_categories', null, $vodCats);
        savePlaylistCache($db, $server['id'], 'vod_streams', null, $vodStreams);
        echo " - Cached " . count($vodCats) . " Movie Categories & " . count($vodStreams) . " Movie Streams.\n";
    } catch (Exception $e) {
        echo " - Error syncing VOD content: " . $e->getMessage() . "\n";
    }

    // 3. Series Categories & Streams
    try {
        $seriesCats = $api->getSeriesCategories();
        $seriesList = $api->getSeries();

        savePlaylistCache($db, $server['id'], 'series_categories', null, $seriesCats);
        savePlaylistCache($db, $server['id'], 'series', null, $seriesList);
        echo " - Cached " . count($seriesCats) . " Series Categories & " . count($seriesList) . " Series.\n";
    } catch (Exception $e) {
        echo " - Error syncing Series content: " . $e->getMessage() . "\n";
    }
}

setSetting('last_playlist_sync', date('Y-m-d H:i:s'));
echo "[" . date('Y-m-d H:i:s') . "] Playlist caching and sync process completed.\n";

function savePlaylistCache($db, $serverId, $type, $categoryId, $data) {
    $jsonData = json_encode($data);
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Delete previous cached entry if exists for server and type
    $stmtDel = $db->prepare("DELETE FROM playlist_cache WHERE server_id = ? AND type = ?");
    $stmtDel->execute([$serverId, $type]);

    $stmtIns = $db->prepare("INSERT INTO playlist_cache (server_id, type, category_id, data, last_updated) VALUES (?, ?, ?, ?, ?)");
    $stmtIns->execute([$serverId, $type, $categoryId, $jsonData, date('Y-m-d H:i:s')]);
}
