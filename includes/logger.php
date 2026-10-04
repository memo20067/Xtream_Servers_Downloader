<?php
// includes/logger.php - Centralized logger for application errors

require_once __DIR__ . '/../config/init.php';

class Logger {
    public static function log($message, $level = 'ERROR', $action = null, $serverId = null, $details = null) {
        try {
            $pdo = getDBConnection();
            $stmt = $pdo->prepare("INSERT INTO error_logs (level, action, server_id, message, details) VALUES (?, ?, ?, ?, ?)");
            $detailsStr = is_array($details) || is_object($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)$details;
            $stmt->execute([
                strtoupper($level),
                $action,
                $serverId !== null ? (string)$serverId : null,
                (string)$message,
                $detailsStr
            ]);
        } catch (Exception $e) {
            error_log("Logger DB Write Error: " . $e->getMessage() . " | Original Message: " . $message);
        }
    }

    public static function getLogs($limit = 100, $levelFilter = null, $search = null) {
        try {
            $pdo = getDBConnection();
            $sql = "SELECT * FROM error_logs WHERE 1=1";
            $params = [];

            if ($levelFilter) {
                $sql .= " AND level = ?";
                $params[] = strtoupper($levelFilter);
            }

            if ($search) {
                $sql .= " AND (message LIKE ? OR action LIKE ? OR server_id LIKE ? OR details LIKE ?)";
                $term = "%{$search}%";
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
                $params[] = $term;
            }

            $sql .= " ORDER BY created_at DESC LIMIT " . (int)$limit;

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public static function clearLogs() {
        try {
            $pdo = getDBConnection();
            $pdo->exec("DELETE FROM error_logs");
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
