<?php
// includes/m3u_parser.php - High-Performance M3U / M3U8 Playlist & Direct Stream Parser Helper

class M3UParser {
    /**
     * Fetch and parse an M3U or M3U8 playlist or stream from a remote URL.
     *
     * @param string $url Target M3U / M3U8 playlist or stream URL
     * @param bool $sslVerify Whether to verify SSL certificates (default false)
     * @param string $playlistName Optional friendly display name for single stream fallback
     * @return array Array containing parsed 'categories', 'channels', or 'error' message
     */
    public static function parseUrl(string $url, bool $sslVerify = false, string $playlistName = ''): array {
        $ch = curl_init();
        $headers = [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept: */*',
            'Connection: keep-alive'
        ];

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => $sslVerify,
            CURLOPT_SSL_VERIFYHOST => $sslVerify ? 2 : 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_ENCODING       => ''
        ]);

        $content      = curl_exec($ch);
        $httpCode     = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url;
        $curlErr      = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content) || !empty($curlErr)) {
            $logPath = __DIR__ . '/logger.php';
            $errMsg = !empty($curlErr)
                ? "cURL Error fetching M3U playlist: {$curlErr} (HTTP {$httpCode})"
                : "Failed to fetch M3U playlist URL (HTTP {$httpCode}).";

            if (file_exists($logPath)) {
                require_once $logPath;
                if (class_exists('Logger') && method_exists('Logger', 'log')) {
                    Logger::log($errMsg, "ERROR", "m3u_fetch", $url, [
                        'url' => $url,
                        'http_code' => $httpCode,
                        'curl_error' => $curlErr
                    ]);
                }
            }

            return ['error' => $errMsg, 'categories' => [], 'channels' => []];
        }

        return self::parseContent($content, $effectiveUrl, $playlistName);
    }

    /**
     * Check if content represents a single HLS media stream (chunks) or master playlist,
     * rather than a multi-channel IPTV playlist.
     */
    public static function isDirectHlsStream(string $content): bool {
        $hasHlsDirectives = (
            stripos($content, '#EXT-X-TARGETDURATION') !== false ||
            stripos($content, '#EXT-X-STREAM-INF') !== false ||
            stripos($content, '#EXT-X-MEDIA-SEQUENCE') !== false
        );

        $hasIptvChannels = (
            stripos($content, 'group-title=') !== false ||
            stripos($content, 'tvg-id=') !== false ||
            stripos($content, 'tvg-name=') !== false ||
            preg_match('/#EXTINF:\s*(-1|0)\b/i', $content)
        );

        return $hasHlsDirectives && !$hasIptvChannels;
    }

    /**
     * Resolve a relative URL against a base URL.
     */
    public static function resolveRelativeUrl(string $baseUrl, string $relUrl): string {
        $relUrl = trim($relUrl);
        if (empty($relUrl) || preg_match('#^https?://#i', $relUrl)) {
            return $relUrl;
        }

        $parsed = parse_url($baseUrl);
        $scheme = $parsed['scheme'] ?? 'http';
        $host   = $parsed['host'] ?? '';
        $port   = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $path   = $parsed['path'] ?? '/';

        if (strpos($relUrl, '/') === 0) {
            return $scheme . '://' . $host . $port . $relUrl;
        }

        $baseDir = rtrim(dirname($path), '/\\');
        return $scheme . '://' . $host . $port . ($baseDir ? $baseDir . '/' : '/') . $relUrl;
    }

    /**
     * High-performance M3U/M3U8 string parser powered by Regular Expressions.
     * Handles both full IPTV playlists and direct HLS stream links.
     *
     * @param string $content Raw M3U playlist content
     * @param string $baseUrl Base URL for resolving relative links
     * @param string $playlistName Friendly fallback name
     * @return array
     */
    public static function parseContent(string $content, string $baseUrl = '', string $playlistName = ''): array {
        $cleanContent = trim($content);
        $defaultName = !empty($playlistName) ? $playlistName : 'Live Stream';

        // 1. Direct HLS Stream check: if it's a media playlist with segments or a master playlist
        if (self::isDirectHlsStream($cleanContent)) {
            return [
                'categories' => [
                    ['category_id' => 'live', 'category_name' => 'Live Streams']
                ],
                'channels' => [
                    [
                        'stream_id'     => 1,
                        'name'          => $defaultName,
                        'category_id'   => 'live',
                        'category_name' => 'Live Streams',
                        'stream_icon'   => '',
                        'url'           => $baseUrl
                    ]
                ]
            ];
        }

        $channels      = [];
        $categoriesMap = [];
        $idCounter     = 1;

        // Split playlist content into blocks delimited by #EXTINF
        $blocks = preg_split('/#EXTINF:/i', $cleanContent);

        // Check if no #EXTINF was found but content has a URL (single link)
        if (count($blocks) <= 1 && !empty($baseUrl)) {
            return [
                'categories' => [
                    ['category_id' => 'live', 'category_name' => 'Live Streams']
                ],
                'channels' => [
                    [
                        'stream_id'     => 1,
                        'name'          => $defaultName,
                        'category_id'   => 'live',
                        'category_name' => 'Live Streams',
                        'stream_icon'   => '',
                        'url'           => $baseUrl
                    ]
                ]
            ];
        }

        foreach ($blocks as $block) {
            $block = trim($block);
            if (empty($block)) {
                continue;
            }

            // Extract the first line (#EXTINF attributes & channel title) and find stream URL
            $lines = explode("\n", str_replace("\r", "", $block));
            $extLine = trim($lines[0]);
            $streamUrl = '';
            $groupFromLine = '';

            // Find the stream URL and possible #EXTGRP from subsequent lines
            for ($i = 1; $i < count($lines); $i++) {
                $line = trim($lines[$i]);
                if (empty($line)) continue;

                if (stripos($line, '#EXTGRP:') === 0) {
                    $groupFromLine = trim(substr($line, 8));
                    continue;
                }

                if (strpos($line, '#') !== 0) {
                    $streamUrl = $line;
                    break;
                }
            }

            if (empty($streamUrl)) {
                continue;
            }

            // Resolve relative stream URLs if needed
            if (!empty($baseUrl)) {
                $streamUrl = self::resolveRelativeUrl($baseUrl, $streamUrl);
            }

            // Default channel metadata
            $channelName  = '';
            $categoryId   = 'general';
            $categoryName = 'General';
            $streamIcon   = '';

            // RegEx extraction for group-title
            if (preg_match('/group-title="([^"]+)"/i', $extLine, $matches)) {
                $group = trim($matches[1]);
                if (!empty($group)) {
                    $categoryName = $group;
                    $categoryId = strtolower(preg_replace('/[^a-zA-Z0-9_\x{0600}-\x{06FF}]/u', '_', $group));
                }
            } elseif (!empty($groupFromLine)) {
                $categoryName = $groupFromLine;
                $categoryId = strtolower(preg_replace('/[^a-zA-Z0-9_\x{0600}-\x{06FF}]/u', '_', $groupFromLine));
            }

            // RegEx extraction for tvg-logo
            if (preg_match('/tvg-logo="([^"]+)"/i', $extLine, $matches)) {
                $streamIcon = trim($matches[1]);
            }

            // Channel title from tvg-name if present
            if (preg_match('/tvg-name="([^"]+)"/i', $extLine, $matches)) {
                $channelName = trim($matches[1]);
            }

            // RegEx or substring extraction for channel title (text after the last comma)
            $lastCommaPos = strrpos($extLine, ',');
            if ($lastCommaPos !== false) {
                $parsedTitle = trim(substr($extLine, $lastCommaPos + 1));
                if (!empty($parsedTitle)) {
                    $channelName = $parsedTitle;
                }
            }

            if (empty($channelName)) {
                $channelName = $defaultName . ' #' . $idCounter;
            }

            $categoriesMap[$categoryId] = $categoryName;

            $channels[] = [
                'stream_id'     => $idCounter++,
                'name'          => $channelName,
                'category_id'   => $categoryId,
                'category_name' => $categoryName,
                'stream_icon'   => $streamIcon,
                'url'           => $streamUrl
            ];
        }

        // If channels were parsed, return them with category list
        if (!empty($channels)) {
            $categories = [];
            foreach ($categoriesMap as $catId => $catName) {
                $categories[] = [
                    'category_id'   => $catId,
                    'category_name' => $catName
                ];
            }

            return [
                'categories' => $categories,
                'channels'   => $channels
            ];
        }

        // Fallback: If no channels could be parsed from blocks but baseUrl exists
        if (!empty($baseUrl)) {
            return [
                'categories' => [
                    ['category_id' => 'live', 'category_name' => 'Live Streams']
                ],
                'channels' => [
                    [
                        'stream_id'     => 1,
                        'name'          => $defaultName,
                        'category_id'   => 'live',
                        'category_name' => 'Live Streams',
                        'stream_icon'   => '',
                        'url'           => $baseUrl
                    ]
                ]
            ];
        }

        return ['categories' => [], 'channels' => []];
    }
}
