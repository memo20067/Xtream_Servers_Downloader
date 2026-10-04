<?php
// includes/m3u_parser.php - M3U / M3U8 Playlist Parser Helper

class M3UParser {
    public static function parseUrl($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamIPTV/1.0'
        ]);

        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || empty($content)) {
            return ['categories' => [], 'channels' => []];
        }

        return self::parseContent($content);
    }

    public static function parseContent($content) {
        $lines = explode("\n", str_replace("\r", "", $content));
        $channels = [];
        $categoriesMap = [];
        $currentChannel = null;
        $idCounter = 1;

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            if (strpos($line, '#EXTINF:') === 0) {
                $currentChannel = [
                    'stream_id' => $idCounter++,
                    'name' => 'Untitled Channel',
                    'category_id' => 'general',
                    'category_name' => 'General',
                    'stream_icon' => '',
                    'url' => ''
                ];

                // Extract group-title (category)
                if (preg_match('/group-title="([^"]+)"/i', $line, $matches)) {
                    $group = trim($matches[1]);
                    if (!empty($group)) {
                        $catId = strtolower(preg_replace('/[^a-zA-Z0-9]/', '_', $group));
                        $currentChannel['category_id'] = $catId;
                        $currentChannel['category_name'] = $group;
                        $categoriesMap[$catId] = $group;
                    }
                }

                // Extract tvg-logo (icon)
                if (preg_match('/tvg-logo="([^"]+)"/i', $line, $matches)) {
                    $currentChannel['stream_icon'] = trim($matches[1]);
                }

                // Extract channel name from the last comma in #EXTINF line
                $lastCommaPos = strrpos($line, ',');
                if ($lastCommaPos !== false) {
                    $channelName = trim(substr($line, $lastCommaPos + 1));
                    if (!empty($channelName)) {
                        $currentChannel['name'] = $channelName;
                    }
                }

                if (!isset($categoriesMap[$currentChannel['category_id']])) {
                    $categoriesMap[$currentChannel['category_id']] = $currentChannel['category_name'];
                }

            } elseif (strpos($line, '#') !== 0 && $currentChannel !== null) {
                $currentChannel['url'] = $line;
                $channels[] = $currentChannel;
                $currentChannel = null;
            }
        }

        $categories = [];
        foreach ($categoriesMap as $catId => $catName) {
            $categories[] = [
                'category_id' => $catId,
                'category_name' => $catName
            ];
        }

        return [
            'categories' => $categories,
            'channels' => $channels
        ];
    }
}
