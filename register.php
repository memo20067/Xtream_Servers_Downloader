<?php
// register.php
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $res = registerUser($username, $email, $password);
    if ($res['success']) {
        header("Location: index.php");
        exit;
    } else {
        $error = $res['error'];
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Xtream IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">
    <div class="container d-flex justify-content-center align-items-center vh-100">
        <div class="card bg-secondary text-white p-4 style-auth-card" style="width: 100%; max-width: 400px;">
            <h3 class="text-center mb-4" id="register-title">Sign Up</h3>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST" action="register.php">
                <div class="mb-3">
                    <label for="username" class="form-label" id="lbl-username">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="email" class="form-label" id="lbl-email">Email</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label" id="lbl-password">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary w-100" id="btn-register">Sign Up</button>
            </form>
            <div class="mt-3 text-center">
                <span id="txt-has-account">Already have an account?</span> <a href="login.php" class="text-info" id="link-login">Sign In</a>
            </div>
        </div>
    </div>
</body>
</html>
