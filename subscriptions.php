<?php
// subscriptions.php - Subscription Tier System & Plan Selection Page

require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$db = getDBConnection();

$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'select_plan') {
        $planId = (int)($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            $stmt = $db->prepare("UPDATE users SET has_paid_subscription = 1, subscription_plan_id = ? WHERE id = ?");
            $stmt->execute([$planId, $_SESSION['user_id']]);
            header("Location: index.php");
            exit;
        }
    }
}

$stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
$plans = $stmtPlans->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscription Plans - Xtream IPTV</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i>Dashboard
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="navbar-text text-white fw-bold">
                <?= htmlspecialchars($currentUser['username']) ?>
            </span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
        </div>
    </div>
</nav>

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold text-primary"><i class="bi bi-gem me-2"></i>Choose Your Subscription Plan</h2>
        <p class="text-white-50">Select a paid subscription plan for full global IPTV server access & movie/series downloads, OR add your personal server below.</p>
    </div>

    <!-- Subscription Plans Grid -->
    <div class="row g-4 justify-content-center mb-5">
        <?php foreach ($plans as $index => $plan): ?>
            <?php
                $isPopular = ($index === 1);
                $isCurrent = ($currentUser['subscription_plan_id'] == $plan['id'] && hasPaidSubscription());
            ?>
            <div class="col-md-4">
                <div class="card h-100 bg-secondary text-white border-<?= $isPopular ? 'primary' : 'secondary' ?> shadow-lg style-plan-card position-relative">
                    <?php if ($isPopular): ?>
                        <div class="position-absolute top-0 end-0 bg-primary text-white text-uppercase px-3 py-1 rounded-bl fw-bold small">Most Popular</div>
                    <?php endif; ?>
                    <div class="card-body p-4 d-flex flex-column">
                        <h4 class="card-title fw-bold text-center mb-3"><?= htmlspecialchars($plan['name']) ?></h4>
                        <div class="text-center mb-4">
                            <span class="display-5 fw-bold text-info"><?= htmlspecialchars($plan['currency']) ?> $<?= number_format($plan['price'], 2) ?></span>
                            <span class="text-white-50">/ month</span>
                        </div>
                        <hr class="border-secondary mb-4">
                        <ul class="list-unstyled mb-4 flex-grow-1">
                            <?php
                                $features = explode("\n", $plan['features'] ?? '');
                                foreach ($features as $f):
                                    if (!trim($f)) continue;
                            ?>
                                <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i><?= htmlspecialchars(trim($f)) ?></li>
                            <?php endforeach; ?>
                        </ul>

                        <form method="POST" class="mt-auto">
                            <input type="hidden" name="action" value="select_plan">
                            <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                            <button type="submit" class="btn btn-<?= $isPopular ? 'primary' : 'outline-light' ?> w-100 fw-bold btn-lg">
                                <?= $isCurrent ? '<i class="bi bi-check-lg me-1"></i>Current Plan' : 'Subscribe Now' ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Mandatory Bottom Option for New Users / Personal Server Access -->
    <div class="card bg-dark text-white border-info text-center p-4 mx-auto shadow" style="max-width: 700px;">
        <h5 class="fw-bold mb-2"><i class="bi bi-hdd-network-fill me-2 text-info"></i>Already Have Your Own Xtream Line?</h5>
        <p class="text-white-50 mb-3">You can bypass subscription plans and connect directly using your personal Xtream Codes server credentials.</p>
        <div>
            <a href="index.php" class="btn btn-info btn-lg px-4 fw-bold">
                <i class="bi bi-plus-circle-fill me-2"></i>Add Your Personal Xtream Server
            </a>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
