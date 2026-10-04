<?php
// includes/cache_helper.php - Image & Metadata Local Caching Helper

class CacheHelper {
    public static function getCacheDir($serverFolder = '', $mediaType = '') {
        $base = __DIR__ . '/../cache/images/';
        if (!empty($serverFolder)) {
            // Sanitize folder name
            $cleanFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $serverFolder);
            $base .= $cleanFolder . '/';
        }
        if (!empty($mediaType) && in_array($mediaType, ['live', 'movies', 'series'])) {
            $base .= $mediaType . '/';
        }

        if (!is_dir($base)) {
            @mkdir($base, 0777, true);
        }
        return $base;
    }

    public static function cacheImage($imageUrl, $serverFolder = 'global', $mediaType = 'live') {
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl;
        }

        $cleanServerFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $serverFolder);
        if (!in_array($mediaType, ['live', 'movies', 'series'])) {
            $mediaType = 'live';
        }

        $hash = md5($imageUrl);
        $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($ext) || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = 'jpg';
        }

        $filename = $hash . '.' . $ext;
        $targetDir = self::getCacheDir($cleanServerFolder, $mediaType);
        $localPath = $targetDir . $filename;
        $publicUrl = 'cache/images/' . $cleanServerFolder . '/' . $mediaType . '/' . $filename;

        // If cached file already exists, return local cached URL directly
        if (file_exists($localPath) && filesize($localPath) > 0) {
            return $publicUrl;
        }

        // Download and cache image locally
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $imageUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamIPTV/1.0'
        ]);

        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($imgData)) {
            file_put_contents($localPath, $imgData);
            return $publicUrl;
        }

        return $imageUrl; // Fallback to remote URL if download fails
    }

    public static function getCachedPlaylist($serverId, $type) {
        $pdo = getDBConnection();
        $stmt = $pdo->prepare("SELECT data, last_updated FROM playlist_cache WHERE server_id = ? AND type = ? AND item_id IS NULL");
        $stmt->execute([$serverId, $type]);
        $row = $stmt->fetch();

        if ($row && !empty($row['data'])) {
            return [
                'data' => json_decode($row['data'], true),
                'last_updated' => $row['last_updated']
            ];
        }
        return null;
    }

    public static function setCachedPlaylist($serverId, $type, $data) {
        $pdo = getDBConnection();
        $jsonData = json_encode($data);
        $now = date('Y-m-d H:i:s');

        // Delete existing full list cache entry
        $stmtDel = $pdo->prepare("DELETE FROM playlist_cache WHERE server_id = ? AND type = ? AND item_id IS NULL");
        $stmtDel->execute([$serverId, $type]);

        $stmtIns = $pdo->prepare("INSERT INTO playlist_cache (server_id, type, item_id, data, last_updated) VALUES (?, ?, NULL, ?, ?)");
        $stmtIns->execute([$serverId, $type, $jsonData, $now]);
    }

    public static function updateItemEpg($serverId, $type, $itemId, $epgData) {
        $pdo = getDBConnection();
        $jsonEpg = is_string($epgData) ? $epgData : json_encode($epgData);
        $now = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("UPDATE playlist_cache SET epg_data = ?, last_updated = ? WHERE server_id = ? AND type = ? AND item_id = ?");
        $stmt->execute([$jsonEpg, $now, $serverId, $type, $itemId]);
    }
}
