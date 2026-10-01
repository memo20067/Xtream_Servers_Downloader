<?php
// includes/xtream.php - Xtream Codes API Client helper

class XtreamAPI {
    private $host;
    private $username;
    private $password;

    public function __construct($host, $username, $password) {
        $this->host = rtrim($host, '/');
        $this->username = $username;
        $this->password = $password;
    }

    private function buildUrl($action, $params = []) {
        $query = array_merge([
            'username' => $this->username,
            'password' => $this->password,
            'action'   => $action
        ], $params);
        return $this->host . '/player_api.php?' . http_build_query($query);
    }

    private function request($action, $params = []) {
        $url = $this->buildUrl($action, $params);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) XtreamIPTV/1.0'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || $response === false) {
            return [];
        }

        $data = json_decode($response, true);
        return is_array($data) ? $data : [];
    }

    public function getLiveCategories() {
        return $this->request('get_live_categories');
    }

    public function getLiveStreams($categoryId = null) {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_live_streams', $params);
    }

    public function getVodCategories() {
        return $this->request('get_vod_categories');
    }

    public function getVodStreams($categoryId = null) {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_vod_streams', $params);
    }

    public function getSeriesCategories() {
        return $this->request('get_series_categories');
    }

    public function getSeries($categoryId = null) {
        $params = [];
        if ($categoryId !== null && $categoryId !== '') {
            $params['category_id'] = $categoryId;
        }
        return $this->request('get_series', $params);
    }

    public function getSeriesInfo($seriesId) {
        return $this->request('get_series_info', ['series_id' => $seriesId]);
    }

    public function getLiveStreamUrl($streamId, $extension = 'm3u8') {
        // e.g. http://host:port/live/username/password/stream_id.m3u8
        return $this->host . '/live/' . rawurlencode($this->username) . '/' . rawurlencode($this->password) . '/' . rawurlencode($streamId) . '.' . $extension;
    }

    public function getVodStreamUrl($streamId, $extension = 'mp4') {
        // e.g. http://host:port/movie/username/password/stream_id.mp4
        return $this->host . '/movie/' . rawurlencode($this->username) . '/' . rawurlencode($this->password) . '/' . rawurlencode($streamId) . '.' . $extension;
    }

    public function getSeriesStreamUrl($streamId, $extension = 'mp4') {
        // e.g. http://host:port/series/username/password/stream_id.mp4
        return $this->host . '/series/' . rawurlencode($this->username) . '/' . rawurlencode($this->password) . '/' . rawurlencode($streamId) . '.' . $extension;
    }
}
