<?php
// config/init.php - Application initialization and database table setup

require_once __DIR__ . '/db.php';

function isInstalled() {
    return getSetting('installed', '0') === '1';
}

function initializeDatabase() {
    $db = getDBConnection();
    $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'sqlite') {
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            );

            CREATE TABLE IF NOT EXISTS subscription_plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                features TEXT,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                phone VARCHAR(30) NOT NULL DEFAULT '',
                password VARCHAR(255) NOT NULL,
                avatar VARCHAR(255) DEFAULT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                has_paid_subscription TINYINT(1) NOT NULL DEFAULT 0,
                subscription_plan_id INTEGER DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS servers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                host VARCHAR(255) NOT NULL,
                username VARCHAR(100) NOT NULL,
                password VARCHAR(100) NOT NULL,
                m3u_url TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS m3u_playlists (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                url TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS playlist_cache (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                server_id VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL,
                item_id VARCHAR(100) DEFAULT NULL,
                category_id VARCHAR(50) DEFAULT NULL,
                name VARCHAR(255) DEFAULT NULL,
                image_url TEXT DEFAULT NULL,
                epg_data TEXT DEFAULT NULL,
                data TEXT DEFAULT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS news_ticker (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER DEFAULT NULL,
                message TEXT NOT NULL,
                severity VARCHAR(20) NOT NULL DEFAULT 'info',
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS password_resets (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email VARCHAR(100) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS error_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                level VARCHAR(20) NOT NULL DEFAULT 'ERROR',
                action VARCHAR(100) DEFAULT NULL,
                server_id VARCHAR(100) DEFAULT NULL,
                message TEXT NOT NULL,
                details TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS xtream_cache (
                cache_key VARCHAR(191) PRIMARY KEY,
                server_host VARCHAR(255) NOT NULL,
                action VARCHAR(100) NOT NULL,
                data TEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } else {
        $db->exec("
            CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(50) PRIMARY KEY,
                setting_value TEXT
            );

            CREATE TABLE IF NOT EXISTS subscription_plans (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                currency VARCHAR(10) NOT NULL DEFAULT 'USD',
                features TEXT,
                is_visible TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) NOT NULL UNIQUE,
                email VARCHAR(100) NOT NULL UNIQUE,
                phone VARCHAR(30) NOT NULL DEFAULT '',
                password VARCHAR(255) NOT NULL,
                avatar VARCHAR(255) DEFAULT NULL,
                role VARCHAR(20) NOT NULL DEFAULT 'user',
                has_paid_subscription TINYINT(1) NOT NULL DEFAULT 0,
                subscription_plan_id INT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (subscription_plan_id) REFERENCES subscription_plans(id) ON DELETE SET NULL
            );

            CREATE TABLE IF NOT EXISTS servers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                host VARCHAR(255) NOT NULL,
                username VARCHAR(100) NOT NULL,
                password VARCHAR(100) NOT NULL,
                m3u_url TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS m3u_playlists (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                name VARCHAR(100) NOT NULL,
                url TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS playlist_cache (
                id INT AUTO_INCREMENT PRIMARY KEY,
                server_id VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL,
                item_id VARCHAR(100) DEFAULT NULL,
                category_id VARCHAR(50) DEFAULT NULL,
                name VARCHAR(255) DEFAULT NULL,
                image_url TEXT DEFAULT NULL,
                epg_data LONGTEXT DEFAULT NULL,
                data LONGTEXT DEFAULT NULL,
                last_updated DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS news_ticker (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT DEFAULT NULL,
                message TEXT NOT NULL,
                severity ENUM('info', 'warning', 'alert') NOT NULL DEFAULT 'info',
                expires_at DATETIME DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(100) NOT NULL,
                code VARCHAR(10) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS error_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                level VARCHAR(20) NOT NULL DEFAULT 'ERROR',
                action VARCHAR(100) DEFAULT NULL,
                server_id VARCHAR(100) DEFAULT NULL,
                message TEXT NOT NULL,
                details LONGTEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS xtream_cache (
                cache_key VARCHAR(191) PRIMARY KEY,
                server_host VARCHAR(255) NOT NULL,
                action VARCHAR(100) NOT NULL,
                data LONGTEXT NOT NULL,
                expires_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            );
        ");
    }

    // Ensure default 3 subscription plans exist if table is empty
    $stmt = $db->query("SELECT COUNT(*) as cnt FROM subscription_plans");
    $count = $stmt->fetch()['cnt'];
    if ($count == 0) {
        $defaultPlans = [
            ['Basic Pass', 9.99, 'USD', "Access to standard live TV channels\nHD Quality streaming\nPersonal server integration"],
            ['Standard Pro', 19.99, 'USD', "Full Live TV & Movie Library\nFHD & 4K Quality streaming\nUnlimited Movie & Series Downloads\nPriority Support"],
            ['VIP Ultra', 29.99, 'USD', "Full VIP Access to Live, VOD & Series\nMulti-device streaming support\nUnlimited Movie & Series Downloads\n24/7 dedicated support & IPTV server backup"]
        ];
        $stmtInsert = $db->prepare("INSERT INTO subscription_plans (name, price, currency, features) VALUES (?, ?, ?, ?)");
        foreach ($defaultPlans as $plan) {
            $stmtInsert->execute($plan);
        }
    }

    // Ensure a default global M3U playlist exists if table is empty
    $stmtM3u = $db->query("SELECT COUNT(*) as cnt FROM m3u_playlists");
    $countM3u = $stmtM3u->fetch()['cnt'];
    if ($countM3u == 0) {
        $stmtInsM3u = $db->prepare("INSERT INTO m3u_playlists (user_id, name, url) VALUES (NULL, ?, ?)");
        $stmtInsM3u->execute(['Global Free IPTV Playlist', 'https://iptv-org.github.io/iptv/index.m3u']);
    }
}

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

initializeDatabase();

// Redirect to installer if not installed, not CLI mode, and not currently in install script
if (php_sapi_name() !== 'cli') {
    $currentScript = $_SERVER['SCRIPT_NAME'] ?? '';
    if (!isInstalled() && strpos($currentScript, 'install/index.php') === false && strpos($currentScript, 'cron/sync_playlist.php') === false) {
        header("Location: install/index.php");
        exit;
    }
}
