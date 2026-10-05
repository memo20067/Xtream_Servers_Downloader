<?php
// includes/cache_helper.php - Image & Metadata Local Caching Helper

class CacheHelper {
    public static function ensureServerFolders($serverFolder) {
        if (empty($serverFolder)) return;
        $cleanFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $serverFolder);
        $base = __DIR__ . '/../cache/images/' . $cleanFolder . '/';

        foreach (['live', 'movies', 'series'] as $sub) {
            $path = $base . $sub . '/';
            if (!is_dir($path)) {
                @mkdir($path, 0777, true);
            }
        }
    }

    public static function getCacheDir($serverFolder = '', $mediaType = '') {
        $base = __DIR__ . '/../cache/images/';
        if (!empty($serverFolder)) {
            // Sanitize folder name
            $cleanFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $serverFolder);
            $base .= $cleanFolder . '/';
            self::ensureServerFolders($cleanFolder);
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
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamIPTV/1.0'
        ]);

        $imgData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && !empty($imgData)) {
            @file_put_contents($localPath, $imgData);
            return $publicUrl;
        }

        return $imageUrl; // Fallback to remote URL if download fails
    }

    public static function getPlaylistCachePath($serverId, $type): string {
        $dir = __DIR__ . '/../cache/playlists/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        $cleanServer = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$serverId);
        $cleanType   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', (string)$type);
        return $dir . 'cache_' . $cleanServer . '_' . $cleanType . '.gz';
    }

    public static function getCachedPlaylist($serverId, $type, int $ttl = 21600) {
        // 1. Try compressed disk cache first (lightning fast, immune to MySQL packet limits)
        $cacheFile = self::getPlaylistCachePath($serverId, $type);
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $ttl) {
            $gzContent = @file_get_contents($cacheFile);
            if (!empty($gzContent)) {
                $rawJson = @gzdecode($gzContent);
                if ($rawJson !== false) {
                    $decoded = json_decode($rawJson, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        return [
                            'data' => $decoded,
                            'last_updated' => date('Y-m-d H:i:s', filemtime($cacheFile))
                        ];
                    }
                }
            }
        }

        // 2. Fallback to DB cache
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("SELECT data, last_updated FROM playlist_cache WHERE server_id = ? AND type = ? AND item_id IS NULL");
            $stmt->execute([$serverId, $type]);
            $row = $stmt->fetch();

            if ($row && !empty($row['data'])) {
                $decoded = json_decode($row['data'], true);
                if (is_array($decoded)) {
                    self::writeDiskCache($cacheFile, $decoded);
                    return [
                        'data' => $decoded,
                        'last_updated' => $row['last_updated']
                    ];
                }
            }
        } catch (\Throwable $e) {
            // ignore DB cache read errors
        }

        return null;
    }

    public static function setCachedPlaylist($serverId, $type, $data) {
        if (empty($data) || isset($data['error'])) {
            return;
        }

        $now = date('Y-m-d H:i:s');
        $cacheFile = self::getPlaylistCachePath($serverId, $type);

        // 1. Save compressed disk cache (always succeeds, zero MySQL packet limit issues)
        self::writeDiskCache($cacheFile, $data);

        // 2. Try DB cache only if payload size is safe (< 500KB) and DB is available
        try {
            $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($jsonData !== false && strlen($jsonData) < 500000) {
                $pdo = getDBConnection();
                $stmtDel = $pdo->prepare("DELETE FROM playlist_cache WHERE server_id = ? AND type = ? AND item_id IS NULL");
                $stmtDel->execute([$serverId, $type]);

                $stmtIns = $pdo->prepare("INSERT INTO playlist_cache (server_id, type, item_id, data, last_updated) VALUES (?, ?, NULL, ?, ?)");
                $stmtIns->execute([$serverId, $type, $jsonData, $now]);
            }
        } catch (\Throwable $e) {
            // DB insert failed (e.g. packet limit or duplicate), disk cache already safe!
        }
    }

    private static function writeDiskCache(string $file, array $data): bool {
        try {
            $json = json_encode($data, JSON_UNESCAPED_UNICODE);
            if ($json !== false) {
                $gz = gzencode($json, 5);
                if ($gz !== false) {
                    return @file_put_contents($file, $gz, LOCK_EX) !== false;
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return false;
    }

    public static function updateItemEpg($serverId, $type, $itemId, $epgData) {
        try {
            $pdo = getDBConnection();
            $jsonEpg = is_string($epgData) ? $epgData : json_encode($epgData, JSON_UNESCAPED_UNICODE);
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("UPDATE playlist_cache SET epg_data = ?, last_updated = ? WHERE server_id = ? AND type = ? AND item_id = ?");
            $stmt->execute([$jsonEpg, $now, $serverId, $type, $itemId]);
        } catch (\Throwable $e) {
            // ignore EPG cache update errors
        }
    }
}
