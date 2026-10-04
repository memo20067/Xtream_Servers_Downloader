<?php
// config/db.php - PDO Database connection helper

function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $db_type = getenv('DB_TYPE') ?: 'sqlite'; // Default to sqlite for portable local execution, or mysql
    $db_host = getenv('DB_HOST') ?: '127.0.0.1';
    $db_name = getenv('DB_NAME') ?: 'xtream_iptv';
    $db_user = getenv('DB_USER') ?: 'root';
    $db_pass = getenv('DB_PASS') ?: '';
    $db_port = getenv('DB_PORT') ?: '3306';

    // If custom db config exists from installer, load it
    $customConfigFile = __DIR__ . '/db_config.php';
    if (file_exists($customConfigFile)) {
        $customConfig = include $customConfigFile;
        if (is_array($customConfig)) {
            $db_type = $customConfig['db_type'] ?? $db_type;
            $db_host = $customConfig['db_host'] ?? $db_host;
            $db_name = $customConfig['db_name'] ?? $db_name;
            $db_user = $customConfig['db_user'] ?? $db_user;
            $db_pass = $customConfig['db_pass'] ?? $db_pass;
            $db_port = $customConfig['db_port'] ?? $db_port;
        }
    }

    try {
        if ($db_type === 'mysql') {
            // Ensure target database exists
            $dsnHost = "mysql:host={$db_host};port={$db_port};charset=utf8mb4";
            $pdoHost = new PDO($dsnHost, $db_user, $db_pass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdoHost->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $db_name) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

            $dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";
            $pdo = new PDO($dsn, $db_user, $db_pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // Default to SQLite
            $db_dir = __DIR__ . '/../data';
            if (!is_dir($db_dir)) {
                mkdir($db_dir, 0777, true);
            }
            $sqlite_file = $db_dir . '/database.sqlite';
            $pdo = new PDO("sqlite:" . $sqlite_file);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $pdo->exec("PRAGMA foreign_keys = ON;");
        }
    } catch (PDOException $e) {
        if (file_exists($customConfigFile)) {
            throw $e; // Re-throw MySQL exceptions when explicit config is set
        }
        // Fallback to SQLite only for unconfigured default local mode
        $db_dir = __DIR__ . '/../data';
        if (!is_dir($db_dir)) {
            mkdir($db_dir, 0777, true);
        }
        $sqlite_file = $db_dir . '/database.sqlite';
        $pdo = new PDO("sqlite:" . $sqlite_file);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec("PRAGMA foreign_keys = ON;");
    }

    return $pdo;
}

function getSetting($key, $default = '') {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        return $row ? $row['setting_value'] : $default;
    } catch (Exception $e) {
        return $default;
    }
}

function setSetting($key, $value) {
    try {
        $db = getDBConnection();
        $driver = $db->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value");
        } else {
            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        }
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}
