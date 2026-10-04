<?php
// forgot_password.php - Forgot & Reset Password Page
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$pdo = getDBConnection();

// Create password_resets table defensively
$driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
if ($driver === 'sqlite') {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} else {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(100) NOT NULL,
        code VARCHAR(10) NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
}

$step = 1; // 1: Send Code, 2: Verify Code & Reset Password
$message = '';
$error = '';
$sent_email = $_GET['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_code') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            $error = "يرجى إدخال البريد الإلكتروني الخاص بك.";
        } else {
            // Check if email exists
            $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                // Generate 6-digit verification code
                $code = sprintf("%06d", mt_rand(100000, 999999));
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));

                // Save code in database
                $stmtDel = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmtDel->execute([$email]);

                $stmtIns = $pdo->prepare("INSERT INTO password_resets (email, code, expires_at) VALUES (?, ?, ?)");
                $stmtIns->execute([$email, $code, $expires]);

                // Simulate email sending or send via mail()
                $subject = "كود استعادة كلمة المرور - Password Reset Code";
                $body = "مرحباً " . htmlspecialchars($user['username']) . ",\n\nكود استعادة كلمة المرور الخاص بك هو: " . $code . "\nينتهي كود التحقق بعد 15 دقيقة.\n\nشكراً لك.";
                $headers = "From: noreply@" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\r\nContent-Type: text/plain; charset=UTF-8";

                @mail($email, $subject, $body, $headers);

                $success = "تم إرسال كود التحقق بنجاح إلى البريد الإلكتروني (" . htmlspecialchars($email) . "). يرجى التحقق من صندوق الوارد أو البريد العشوائي.";
                $step = 2;
                $sent_email = $email;
            } else {
                $error = "لم نتمكن من العثور على حساب مرتبط بهذا البريد الإلكتروني.";
            }
        }
    } elseif ($action === 'reset_password') {
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $new_pass = trim($_POST['new_password'] ?? '');
        $confirm_pass = trim($_POST['confirm_password'] ?? '');

        $sent_email = $email;
        $step = 2;

        if (empty($email) || empty($code) || empty($new_pass) || empty($confirm_pass)) {
            $error = "جميع الحقول مطلوبة.";
        } elseif ($new_pass !== $confirm_pass) {
            $error = "كلمتا المرور غير متطابقتين.";
        } elseif (strlen($new_pass) < 6) {
            $error = "يجب أن تكون كلمة المرور 6 أحرف على الأقل.";
        } else {
            // Verify code
            $now = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE email = ? AND code = ? AND expires_at > ?");
            $stmt->execute([$email, $code, $now]);
            $reset = $stmt->fetch();

            if ($reset) {
                // Update password
                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
                $stmtUp = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                $stmtUp->execute([$hashed, $email]);

                // Clean up code
                $stmtClean = $pdo->prepare("DELETE FROM password_resets WHERE email = ?");
                $stmtClean->execute([$email]);

                header("Location: login.php?reset=success");
                exit;
            } else {
                $error = "كود التحقق غير صحيح أو انتهت صلاحيته. يرجى إعادة المحاولة.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>استعادة كلمة المرور - Forgot Password</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100 py-5">

<div class="container" style="max-width: 480px;">
    <div class="glass-panel p-4 p-sm-5 text-white style-auth-card shadow-lg">
        <div class="text-center mb-4">
            <i class="bi bi-key-fill text-warning fs-1 mb-2"></i>
            <h3 class="fw-bold">استعادة كلمة المرور</h3>
            <p class="text-secondary small">Reset Your Password</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-circle-fill me-2"></i><?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <!-- Step 1 Form: Request Verification Code -->
            <form method="POST">
                <input type="hidden" name="action" value="send_code">
                <div class="mb-4">
                    <label class="form-label text-light">البريد الإلكتروني المسجل</label>
                    <div class="input-group">
                        <span class="input-group-text glass-input text-secondary border-end-0"><i class="bi bi-envelope-at"></i></span>
                        <input type="email" name="email" class="form-control glass-input border-start-0" placeholder="your@email.com" value="<?= htmlspecialchars($sent_email) ?>" required>
                    </div>
                    <div class="form-text text-secondary small mt-1">سيتم إرسال كود مكوّن من 6 أرقام إلى إيميلك المسجل.</div>
                </div>

                <button type="submit" class="btn btn-neon-blue w-100 py-2 mb-3">
                    <i class="bi bi-send-check me-2"></i>إرسال كود التحقق
                </button>
            </form>
        <?php else: ?>
            <!-- Step 2 Form: Enter Code and New Password -->
            <form method="POST">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="email" value="<?= htmlspecialchars($sent_email) ?>">

                <div class="mb-3">
                    <label class="form-label text-light">كود التحقق المرسل للإيميل</label>
                    <div class="input-group">
                        <span class="input-group-text glass-input text-secondary border-end-0"><i class="bi bi-shield-lock"></i></span>
                        <input type="text" name="code" class="form-control glass-input border-start-0 text-center fw-bold fs-5 tracking-widest" placeholder="123456" maxlength="6" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label text-light">كلمة المرور الجديدة</label>
                    <div class="input-group">
                        <span class="input-group-text glass-input text-secondary border-end-0"><i class="bi bi-lock"></i></span>
                        <input type="password" name="new_password" class="form-control glass-input border-start-0" placeholder="••••••••" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-light">تأكيد كلمة المرور الجديدة</label>
                    <div class="input-group">
                        <span class="input-group-text glass-input text-secondary border-end-0"><i class="bi bi-lock-fill"></i></span>
                        <input type="password" name="confirm_password" class="form-control glass-input border-start-0" placeholder="••••••••" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-success w-100 py-2 mb-3 fw-bold">
                    <i class="bi bi-check2-circle me-2"></i>حفظ كلمة المرور الجديدة
                </button>
            </form>
        <?php endif; ?>

        <div class="text-center mt-3 pt-3 border-top border-secondary">
            <a href="login.php" class="text-info text-decoration-none small">
                <i class="bi bi-arrow-right me-1"></i>العودة لتسجيل الدخول (Back to Login)
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
