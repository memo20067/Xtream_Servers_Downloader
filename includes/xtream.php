<?php
/**
 * Enterprise-Ready Xtream Codes API Client Helper
 *
 * Provides high-performance, resilient access to Xtream Codes IPTV servers
 * with anti-blocking request spoofing, PDO database caching, JSON validation,
 * configurable SSL verification, and graceful fallback to M3U playlists.
 *
 * PHP Version: 7.4+ / 8.x
 */

class XtreamAPI {
    private string $host;
    private string $username;
    private string $password;
    private ?string $m3uUrl;
    private ?PDO $pdo;
    private int $cacheTtl;
    private bool $sslVerify;

    /**
     * @param string $host Base host URL (e.g., "http://example.com:8080")
     * @param string $username Xtream username
     * @param string $password Xtream password
     * @param string|null $m3uUrl Direct custom M3U URL (optional)
     * @param PDO|null $pdo Database connection for caching (optional)
     * @param int $cacheTtl Cache time-to-live in seconds (default 12 hours = 43200s)
     * @param bool|null $sslVerify Enable SSL certificate verification (default read from settings or false)
     */
    public function __construct(
        string $host,
        string $username,
        string $password,
        ?string $m3uUrl = null,
        ?PDO $pdo = null,
        int $cacheTtl = 43200,
        ?bool $sslVerify = null
    ) {
        $this->host     = rtrim(trim($host), '/');
        $this->username = trim($username);
        $this->password = trim($password);
        $this->m3uUrl   = $m3uUrl ? trim($m3uUrl) : null;
        $this->cacheTtl = $cacheTtl;

        if ($pdo !== null) {
            $this->pdo = $pdo;
        } else {
            if (function_exists('getDBConnection')) {
                try {
                    $this->pdo = getDBConnection();
                } catch (Throwable $e) {
                    $this->pdo = null;
                }
            } else {
                $this->pdo = null;
            }
        }

        if ($sslVerify !== null) {
            $this->sslVerify = $sslVerify;
        } else {
            // Check setting from DB if available
            if (function_exists('getSetting')) {
                $this->sslVerify = (getSetting('ssl_verify', '0') === '1');
            } else {
                $this->sslVerify = false;
            }
        }
    }

    /**
     * Build the standard player_api.php request URL.
     */
    private function buildUrl(string $action, array $params = []): string {
        $query = array_merge([
            'username' => $this->username,
            'password' => $this->password,
            'action'   => $action
        ], $params);

        return $this->host . '/player_api.php?' . http_build_query($query);
    }

    /**
     * Get the fallback M3U playlist download URL.
     */
    private function getM3uFallbackUrl(): string {
        if (!empty($this->m3uUrl)) {
            return $this->m3uUrl;
        }

        return $this->host . '/get.php?username=' . rawurlencode($this->username)
            . '&password=' . rawurlencode($this->password)
            . '&type=m3u_plus&output=ts';
    }

    /**
     * Generate a unique cache key for a given action and parameters.
     */
    private function getCacheKey(string $action, array $params = []): string {
        $paramStr = !empty($params) ? '_' . md5(json_encode($params)) : '';
        return md5($this->host . '|' . $this->username) . '_' . $action . $paramStr;
    }

    /**
     * Check local PDO cache (`xtream_cache` table) for unexpired cached data.
     *
     * @return array|null Returns parsed data array on hit, or null on cache miss.
     */
    private function getFromCache(string $action, array $params = []): ?array {
        if (!$this->pdo) {
            return null;
        }

        try {
            $cacheKey = $this->getCacheKey($action, $params);
            $stmt = $this->pdo->prepare(
                "SELECT data FROM xtream_cache WHERE cache_key = ? AND expires_at > NOW() LIMIT 1"
            );
            $stmt->execute([$cacheKey]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && !empty($row['data'])) {
                $data = json_decode($row['data'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                    return $data;
                }
            }
        } catch (Throwable $e) {
            $this->logError("Cache read failed: " . $e->getMessage(), "WARNING", $action);
        }

        return null;
    }

    /**
     * Save/update dataset in PDO local cache (`xtream_cache` table).
     */
    private function saveToCache(string $action, array $params, array $data): bool {
        if (!$this->pdo || empty($data) || isset($data['error'])) {
            return false;
        }

        try {
            $cacheKey  = $this->getCacheKey($action, $params);
            $dataJson  = json_encode($data, JSON_UNESCAPED_UNICODE);
            $expiresAt = date('Y-m-d H:i:s', time() + $this->cacheTtl);
            $driver    = $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

            if ($driver === 'sqlite') {
                $stmt = $this->pdo->prepare("
                    INSERT INTO xtream_cache (cache_key, server_host, action, data, expires_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, datetime('now'))
                    ON CONFLICT(cache_key) DO UPDATE SET
                        data = excluded.data,
                        expires_at = excluded.expires_at,
                        updated_at = datetime('now')
                ");
                return $stmt->execute([$cacheKey, $this->host, $action, $dataJson, $expiresAt]);
            } else {
                $stmt = $this->pdo->prepare("
                    INSERT INTO xtream_cache (cache_key, server_host, action, data, expires_at)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        data = VALUES(data),
                        expires_at = VALUES(expires_at),
                        updated_at = NOW()
                ");
                return $stmt->execute([$cacheKey, $this->host, $action, $dataJson, $expiresAt]);
            }
        } catch (Throwable $e) {
            $this->logError("Cache write failed: " . $e->getMessage(), "WARNING", $action);
            return false;
        }
    }

    /**
     * Safely log messages using optional logger module if available.
     */
    private function logError(string $message, string $level = 'ERROR', ?string $action = null, ?array $details = null): void {
        $loggerPath = __DIR__ . '/logger.php';
        if (file_exists($loggerPath)) {
            require_once $loggerPath;
            if (class_exists('Logger') && method_exists('Logger', 'log')) {
                Logger::log($message, $level, $action, $this->host, $details);
            }
        }
    }

    /**
     * Execute cURL request with anti-blocking configuration, headers, and parsing safety.
     *
     * @param string $action Xtream API action name
     * @param array $params Query parameters
     * @param bool $useCache Whether to check and write to database cache
     * @return array Parsed API response or error array
     */
    private function request(string $action, array $params = [], bool $useCache = true): array {
        // 1. Check local DB Cache first
        if ($useCache) {
            $cachedData = $this->getFromCache($action, $params);
            if ($cachedData !== null) {
                return $cachedData;
            }
        }

        // 2. Build Request URL
        $url = $this->buildUrl($action, $params);

        // 3. Configure Anti-Blocking & Spoofed cURL Session
        $ch = curl_init();
        $headers = [
            'User-Agent: IPTVSmartersPro/3.1.5 (Linux; Android 11)',
            'Accept: application/json, text/plain, */*',
            'Accept-Language: en-US,en;q=0.9,ar;q=0.8',
            'Connection: keep-alive',
            'Cache-Control: no-cache'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => $this->sslVerify,
            CURLOPT_SSL_VERIFYHOST => $this->sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => ''
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        // 4. Validate HTTP response & JSON parsing
        if ($httpCode === 200 && $response !== false) {
            $data = json_decode($response, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data) && !empty($data)) {
                if ($useCache) {
                    $this->saveToCache($action, $params, $data);
                }
                return $data;
            }
        }

        $logMsg = !empty($curlErr)
            ? "Xtream API cURL Error: {$curlErr} (HTTP {$httpCode})"
            : "Xtream API call failed or returned invalid JSON (HTTP {$httpCode})";

        $this->logError($logMsg . ". Triggering M3U fallback.", "WARNING", $action, [
            'url'        => $url,
            'http_code'  => $httpCode,
            'curl_error' => $curlErr
        ]);

        // 5. Fallback: Parse via M3U download URL
        return $this->fallbackToM3u($action, $params, $logMsg, $useCache);
    }

    /**
     * Fallback execution using raw M3U playlist parser.
     */
    private function fallbackToM3u(string $action, array $params, string $logMsg, bool $useCache = true): array {
        $m3uParserPath = __DIR__ . '/m3u_parser.php';
        if (!file_exists($m3uParserPath)) {
            return ['error' => $logMsg . " | M3U Parser module not found."];
        }

        require_once $m3uParserPath;
        if (!class_exists('M3UParser') || !method_exists('M3UParser', 'parseUrl')) {
            return ['error' => $logMsg . " | M3UParser class or method missing."];
        }

        $m3uParsed = M3UParser::parseUrl($this->getM3uFallbackUrl(), $this->sslVerify);

        if (isset($m3uParsed['error']) && !empty($m3uParsed['error'])) {
            return ['error' => $logMsg . " | M3U Fallback Error: " . $m3uParsed['error']];
        }

        $result = [];
        if ($action === 'get_live_categories' || $action === 'get_vod_categories' || $action === 'get_series_categories') {
            $result = $m3uParsed['categories'] ?? [];
        } elseif ($action === 'get_live_streams' || $action === 'get_vod_streams' || $action === 'get_series') {
            $catId    = $params['category_id'] ?? null;
            $channels = $m3uParsed['channels'] ?? [];
            if ($catId !== null && $catId !== '') {
                $channels = array_values(array_filter($channels, function($c) use ($catId) {
                    return (string)($c['category_id'] ?? '') === (string)$catId;
                }));
            }
            $result = $channels;
        }

        if (!empty($result)) {
            if ($useCache) {
                $this->saveToCache($action, $params, $result);
            }
            return $result;
        }

        return ['error' => $logMsg];
    }

    public function getLiveCategories(): array {
        return $this->request('get_live_categories');
    }

    public function getLiveStreams($categoryId = null): array {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_live_streams', $params);
    }

    public function getVodCategories(): array {
        return $this->request('get_vod_categories');
    }

    public function getVodStreams($categoryId = null): array {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_vod_streams', $params);
    }

    public function getSeriesCategories(): array {
        return $this->request('get_series_categories');
    }

    public function getSeries($categoryId = null): array {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_series', $params);
    }

    public function getSeriesInfo($seriesId): array {
        return $this->request('get_series_info', ['series_id' => $seriesId]);
    }

    public function getShortEpg($streamId, int $limit = 4): array {
        return $this->request('get_short_epg', ['stream_id' => $streamId, 'limit' => $limit], false);
    }

    public function getVodInfo($streamId): array {
        return $this->request('get_vod_info', ['vod_id' => $streamId]);
    }

    public function getLiveStreamUrl($streamId, string $extension = 'm3u8'): string {
        return $this->host . '/live/' . rawurlencode($this->username)
            . '/' . rawurlencode($this->password)
            . '/' . rawurlencode($streamId) . '.' . $extension;
    }

    public function getVodStreamUrl($streamId, string $extension = 'mp4'): string {
        return $this->host . '/movie/' . rawurlencode($this->username)
            . '/' . rawurlencode($this->password)
            . '/' . rawurlencode($streamId) . '.' . $extension;
    }

    public function getSeriesStreamUrl($streamId, string $extension = 'mp4'): string {
        return $this->host . '/series/' . rawurlencode($this->username)
            . '/' . rawurlencode($this->password)
            . '/' . rawurlencode($streamId) . '.' . $extension;
    }
}
