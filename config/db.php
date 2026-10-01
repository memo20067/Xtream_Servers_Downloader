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

    try {
        if ($db_type === 'mysql') {
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
        // Fallback to SQLite if MySQL fails
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
