<?php
// includes/auth.php - Authentication & Authorization functions

require_once __DIR__ . '/../config/init.php';

function registerUser($username, $email, $phone, $password) {
    $db = getDBConnection();

    $username = trim($username);
    $email = strtolower(trim(filter_var($email, FILTER_SANITIZE_EMAIL)));
    $phone = trim($phone);

    if (empty($username) || empty($email) || empty($phone) || empty($password)) {
        return ['success' => false, 'error' => 'All fields including Mobile Phone Number are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'Invalid email format.'];
    }

    // Check if user or email already exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
    $stmt->execute([$username, $email]);
    if ($stmt->fetch()) {
        return ['success' => false, 'error' => 'Username or email already exists.'];
    }

    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $db->prepare("INSERT INTO users (username, email, phone, password, role, has_paid_subscription) VALUES (?, ?, ?, ?, 'user', 0)");
    $stmt->execute([$username, $email, $phone, $hashedPassword]);

    $userId = $db->lastInsertId();

    // Regenerate session ID upon registration to prevent session fixation
    session_regenerate_id(true);

    // Auto log in after registration
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = 'user';
    $_SESSION['has_paid_subscription'] = 0;

    return ['success' => true, 'user_id' => $userId];
}

function loginUser($usernameOrEmail, $password) {
    $db = getDBConnection();
    $input = trim($usernameOrEmail);

    if (empty($input) || empty($password)) {
        return ['success' => false, 'error' => 'Username/Email and Password are required.'];
    }

    $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(username) = LOWER(?) OR LOWER(email) = LOWER(?) LIMIT 1");
    $stmt->execute([$input, $input]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Invalid credentials.'];
    }

    // Regenerate session ID upon login to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['has_paid_subscription'] = (int)$user['has_paid_subscription'];

    return ['success' => true, 'user' => $user];
}

function logoutUser() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT id, username, email, phone, avatar, role, has_paid_subscription, subscription_plan_id, created_at FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['has_paid_subscription'] = (int)$user['has_paid_subscription'];
        $_SESSION['role'] = $user['role'];
    }

    return $user;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function isAdmin() {
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function hasPaidSubscription() {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true; // Admins have full access
    
    // Refresh subscription status from DB
    $user = getCurrentUser();
    return $user && (int)$user['has_paid_subscription'] === 1;
}

function getAccessibleServers() {
    if (!isLoggedIn()) {
        return [];
    }

    $db = getDBConnection();
    $userId = $_SESSION['user_id'];

    // All logged in users can access global servers (user_id IS NULL) and their personal servers (user_id = $userId)
    $stmt = $db->prepare("SELECT * FROM servers WHERE user_id IS NULL OR user_id = ? ORDER BY id ASC");
    $stmt->execute([$userId]);

    return $stmt->fetchAll();
}

function canAccessServer($serverId) {
    if (!isLoggedIn()) return false;
    if (isAdmin()) return true;
    $servers = getAccessibleServers();
    foreach ($servers as $server) {
        if ((int)$server['id'] === (int)$serverId) {
            return true;
        }
    }
    return false;
}

function getServerById($serverId) {
    if (!canAccessServer($serverId)) {
        return null;
    }
    $db = getDBConnection();
    $stmt = $db->prepare("SELECT * FROM servers WHERE id = ?");
    $stmt->execute([$serverId]);
    return $stmt->fetch() ?: null;
}
