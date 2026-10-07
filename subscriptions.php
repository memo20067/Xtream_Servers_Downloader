<?php
// subscriptions.php - Subscription Plans & Payment Page

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
            // Redirect to payment page instead of auto-activating
            header("Location: payment.php");
            exit;
        }
    }
}

try {
    $stmtPlans = $db->query("SELECT * FROM subscription_plans WHERE is_visible = 1 ORDER BY id ASC");
    $plans = $stmtPlans->fetchAll();
} catch (Exception $e) {
    $stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
    $plans = $stmtPlans->fetchAll();
}
$lang = $_SESSION['lang'] ?? 'ar';
$isRtl = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isRtl ? 'باقات الاشتراك' : 'Subscription Plans' ?> - <?= htmlspecialchars(getSetting('app_name', 'Xtream IPTV Player')) ?></title>
    <?php if ($isRtl): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i><?= $isRtl ? 'القائمة الرئيسية' : 'Dashboard' ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="navbar-text text-white fw-bold"><?= htmlspecialchars($currentUser['username']) ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i><?= $isRtl ? 'خروج' : 'Logout' ?></a>
        </div>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="text-center mb-5">
        <h2 class="fw-bold text-primary"><i class="bi bi-gem me-2"></i><?= $isRtl ? 'اختر باقة الاشتراك' : 'Choose Your Subscription Plan' ?></h2>
        <p class="text-white-50"><?= $isRtl ? 'اختر باقة مدفوعة للوصول الكامل لسيرفرات IPTV وتحميل الأفلام والمسلسلات' : 'Select a paid subscription plan for full IPTV access & downloads, OR connect your personal server below.' ?></p>
    </div>

    <div class="row g-4 justify-content-center mb-5">
        <?php foreach ($plans as $index => $plan): ?>
            <?php
                $isPopular = ($index === 1);
                $isCurrent = ($currentUser['subscription_plan_id'] == $plan['id'] && hasPaidSubscription());
            ?>
            <div class="col-md-4">
                <div class="card h-100 bg-secondary text-white border-<?= $isPopular ? 'primary' : 'secondary' ?> shadow-lg position-relative">
                    <?php if ($isPopular): ?>
                        <div class="position-absolute top-0 end-0 bg-primary text-white text-uppercase px-3 py-1 rounded-bl fw-bold small"><?= $isRtl ? 'الأكثر شعبية' : 'Most Popular' ?></div>
                    <?php endif; ?>
                    <div class="card-body p-4 d-flex flex-column">
                        <h4 class="card-title fw-bold text-center mb-3"><?= htmlspecialchars($plan['name']) ?></h4>
                        <div class="text-center mb-4">
                            <span class="display-5 fw-bold text-info"><?= htmlspecialchars($plan['currency']) ?> $<?= number_format($plan['price'], 2) ?></span>
                            <span class="text-white-50">/ <?= $isRtl ? 'شهر' : 'month' ?></span>
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
                                <?= $isCurrent ? '<i class="bi bi-check-lg me-1"></i>' . ($isRtl ? 'الباقة الحالية' : 'Current Plan') : ($isRtl ? 'اشترك الآن' : 'Subscribe Now') ?>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center">
        <a href="payment.php" class="btn btn-warning btn-lg px-5 fw-bold">
            <i class="bi bi-credit-card me-2"></i><?= $isRtl ? 'الذهاب إلى صفحة الدفع' : 'Go to Payment Page' ?>
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
