<?php
// includes/logger.php - Categorized Multi-Logging System

require_once __DIR__ . '/../config/init.php';

class Logger {
    const CATEGORY_AUTH = 'AUTH';
    const CATEGORY_API = 'API';
    const CATEGORY_PAYMENT = 'PAYMENT';
    const CATEGORY_SYSTEM = 'SYSTEM';
    const CATEGORY_ERROR = 'ERROR';
    const CATEGORY_STREAM = 'STREAM';
    const CATEGORY_ADMIN = 'ADMIN';

    public static function log($message, $level = 'ERROR', $action = null, $serverId = null, $details = null, $category = null) {
        try {
            $pdo = getDBConnection();

            // Auto-detect category if not provided
            if ($category === null) {
                $category = self::CATEGORY_ERROR;
                $actionLower = strtolower((string)$action);
                if (strpos($actionLower, 'login') !== false || strpos($actionLower, 'auth') !== false || strpos($actionLower, 'register') !== false) {
                    $category = self::CATEGORY_AUTH;
                } elseif (strpos($actionLower, 'payment') !== false || strpos($actionLower, 'receipt') !== false || strpos($actionLower, 'plan') !== false) {
                    $category = self::CATEGORY_PAYMENT;
                } elseif (strpos($actionLower, 'stream') !== false || strpos($actionLower, 'play') !== false) {
                    $category = self::CATEGORY_STREAM;
                } elseif (strpos($actionLower, 'admin') !== false || strpos($actionLower, 'setting') !== false) {
                    $category = self::CATEGORY_ADMIN;
                } elseif (strpos($actionLower, 'api') !== false || strpos($actionLower, 'proxy') !== false) {
                    $category = self::CATEGORY_API;
                }
            }

            $detailsStr = is_array($details) || is_object($details) ? json_encode($details, JSON_UNESCAPED_UNICODE) : (string)$details;
            $stmt = $pdo->prepare("INSERT INTO error_logs (level, action, server_id, message, details) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                strtoupper($level),
                $action,
                $serverId !== null ? (string)$serverId : null,
                (string)$message,
                $detailsStr
            ]);

            // Also log to system log file for reliability
            $logLine = sprintf("[%s] [%s] [%s] %s: %s\n", date('Y-m-d H:i:s'), $category, strtoupper($level), $action ?: 'N/A', $message);
            @file_put_contents(__DIR__ . '/../logs/app.log', $logLine, FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            error_log("Logger DB Write Error: " . $e->getMessage() . " | Original Message: " . $message);
        }
    }

    public static function getLogs($limit = 100, $levelFilter = null, $search = null, $categoryFilter = null) {
        try {
            $pdo = getDBConnection();
            $sql = "SELECT * FROM error_logs WHERE 1=1";
            $params = [];

            if ($levelFilter) {
                $sql .= " AND level = ?";
                $params[] = strtoupper($levelFilter);
            }

            if ($categoryFilter) {
                // Category is encoded in action field prefix or details
                $sql .= " AND (action LIKE ? OR details LIKE ?)";
                $term = "%" . $categoryFilter . "%";
                $params[] = $term;
                $params[] = $term;
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

    public static function logAuth($message, $level = 'INFO', $userId = null, $details = null) {
        $details = is_array($details) ? $details : [];
        $details['user_id'] = $userId;
        $details['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        self::log($message, $level, 'auth_' . strtolower($level), null, $details, self::CATEGORY_AUTH);
    }

    public static function logPayment($message, $level = 'INFO', $userId = null, $details = null) {
        $details = is_array($details) ? $details : [];
        $details['user_id'] = $userId;
        self::log($message, $level, 'payment_' . strtolower($level), null, $details, self::CATEGORY_PAYMENT);
    }

    public static function logStream($message, $level = 'INFO', $serverId = null, $streamId = null, $details = null) {
        $details = is_array($details) ? $details : [];
        $details['stream_id'] = $streamId;
        self::log($message, $level, 'stream_access', $serverId, $details, self::CATEGORY_STREAM);
    }

    public static function logAdmin($message, $level = 'INFO', $adminId = null, $details = null) {
        $details = is_array($details) ? $details : [];
        $details['admin_id'] = $adminId;
        self::log($message, $level, 'admin_action', null, $details, self::CATEGORY_ADMIN);
    }

    public static function logSystem($message, $level = 'INFO', $details = null) {
        self::log($message, $level, 'system_event', null, $details, self::CATEGORY_SYSTEM);
    }
}
