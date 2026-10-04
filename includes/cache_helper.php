<?php
// includes/cache_helper.php - Image & Metadata Local Caching Helper

class CacheHelper {
    public static function getCacheDir() {
        $dir = __DIR__ . '/../cache/images/';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    public static function cacheImage($imageUrl) {
        if (empty($imageUrl) || !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            return $imageUrl;
        }

        $hash = md5($imageUrl);
        $ext = strtolower(pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION));
        if (empty($ext) || !in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $ext = 'jpg';
        }

        $filename = $hash . '.' . $ext;
        $localPath = self::getCacheDir() . $filename;
        $publicUrl = 'cache/images/' . $filename;

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
        $stmt = $pdo->prepare("SELECT data, last_updated FROM playlist_cache WHERE server_id = ? AND type = ?");
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

        // Delete existing cache for server and type
        $stmtDel = $pdo->prepare("DELETE FROM playlist_cache WHERE server_id = ? AND type = ?");
        $stmtDel->execute([$serverId, $type]);

        $stmtIns = $pdo->prepare("INSERT INTO playlist_cache (server_id, type, data, last_updated) VALUES (?, ?, ?, ?)");
        $stmtIns->execute([$serverId, $type, $jsonData, $now]);
    }
}
