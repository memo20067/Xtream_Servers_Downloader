<?php
// subscriptions.php - Select a plan and continue to receipt submission.
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();
$db = getDBConnection();
$msg = '';
$error = '';

try {
    $plans = $db->query('SELECT * FROM subscription_plans WHERE is_visible = 1 ORDER BY price ASC')->fetchAll();
} catch (Throwable $e) {
    $plans = [];
    $error = 'Subscription plans could not be loaded.';
}

$selectedPlanId = (int)($_GET['plan_id'] ?? ($currentUser['subscription_plan_id'] ?? 0));
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'select_plan') {
    $selectedPlanId = (int)($_POST['plan_id'] ?? 0);
    $validPlan = false;
    foreach ($plans as $plan) {
        if ((int)$plan['id'] === $selectedPlanId) {
            $validPlan = true;
            break;
        }
    }
    if ($validPlan) {
        header('Location: payment.php?plan_id=' . rawurlencode((string)$selectedPlanId));
        exit;
    }
    $error = 'Select an available subscription plan to continue.';
}

if (!$selectedPlanId && $plans) $selectedPlanId = (int)$plans[0]['id'];
if ($selectedPlanId && !array_filter($plans, static fn($plan) => (int)$plan['id'] === $selectedPlanId)) {
    $selectedPlanId = $plans ? (int)$plans[0]['id'] : 0;
}

$lang = $_COOKIE['app_lang'] ?? ($_SESSION['lang'] ?? 'ar');
$lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
$isRtl = $lang === 'ar';
$appName = getSetting('app_name', 'Xtream IPTV Player');
$allFeatures = [];
foreach ($plans as $plan) {
    foreach (preg_split('/\r\n|\r|\n/', (string)($plan['features'] ?? '')) as $feature) {
        $feature = trim($feature);
        if ($feature !== '' && !in_array($feature, $allFeatures, true)) $allFeatures[] = $feature;
    }
}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isRtl ? 'باقات الاشتراك' : 'Subscription plans' ?> · <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= $isRtl ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<nav class="catalog-topbar navbar navbar-dark px-3 px-lg-5">
    <div class="container-fluid">
        <a class="navbar-brand auth-brand d-flex align-items-center" href="index.php"><span class="auth-brand-mark">X</span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></a>
        <a href="index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-right me-2" aria-hidden="true"></i><?= $isRtl ? 'العودة إلى المكتبة' : 'Back to library' ?></a>
    </div>
</nav>
<main class="page-shell container py-5" style="max-width:1240px">
    <header class="mb-4">
        <h1 class="h2 fw-bold mb-2"><?= $isRtl ? 'اختر باقتك' : 'Choose a plan' ?></h1>
        <p class="text-secondary mb-0"><?= $isRtl ? 'راجع السعر والمزايا المسجلة لكل باقة قبل رفع إيصال الدفع.' : 'Review the listed price and benefits before submitting a payment receipt.' ?></p>
    </header>

    <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

    <?php if (!$plans): ?>
        <div class="border-top border-bottom border-secondary py-5 text-center"><i class="bi bi-gem fs-3 text-secondary" aria-hidden="true"></i><p class="mt-3 mb-1 fw-semibold"><?= $isRtl ? 'لا توجد باقات متاحة حاليًا' : 'No plans are currently available' ?></p><p class="small text-secondary mb-0"><?= $isRtl ? 'تواصل مع الإدارة لمزيد من المعلومات.' : 'Contact the administrator for more information.' ?></p></div>
    <?php else: ?>
        <form method="POST" action="subscriptions.php" id="plan-selection-form">
            <input type="hidden" name="action" value="select_plan">
            <div class="table-responsive plan-comparison-wrap">
                <table class="table table-dark align-middle plan-comparison mb-0">
                    <thead><tr>
                        <th scope="col" class="text-secondary"><?= $isRtl ? 'الميزة المسجلة' : 'Listed benefit' ?></th>
                        <?php foreach ($plans as $plan): ?>
                            <th scope="col" class="plan-header-cell <?= (int)$plan['id'] === $selectedPlanId ? 'is-selected' : '' ?>" data-plan-id="<?= (int)$plan['id'] ?>">
                                <label class="plan-radio-label" for="plan-<?= (int)$plan['id'] ?>">
                                    <input id="plan-<?= (int)$plan['id'] ?>" type="radio" name="plan_id" value="<?= (int)$plan['id'] ?>" <?= (int)$plan['id'] === $selectedPlanId ? 'checked' : '' ?> required>
                                    <span class="plan-name"><?= htmlspecialchars($plan['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                </label>
                                <strong class="plan-price" dir="ltr"><?= htmlspecialchars($plan['currency'], ENT_QUOTES, 'UTF-8') ?> $<?= number_format((float)$plan['price'], 2) ?></strong>
                            </th>
                        <?php endforeach; ?>
                    </tr></thead>
                    <tbody>
                    <?php if ($allFeatures): ?>
                        <?php foreach ($allFeatures as $feature): ?>
                            <tr>
                                <th scope="row" class="feature-label"><?= htmlspecialchars($feature, ENT_QUOTES, 'UTF-8') ?></th>
                                <?php foreach ($plans as $plan): ?>
                                    <?php $planFeatures = array_map('trim', preg_split('/\r\n|\r|\n/', (string)($plan['features'] ?? ''))); ?>
                                    <td class="plan-cell <?= (int)$plan['id'] === $selectedPlanId ? 'is-selected' : '' ?>" data-plan-id="<?= (int)$plan['id'] ?>">
                                        <?php if (in_array($feature, $planFeatures, true)): ?><span class="plan-includes"><i class="bi bi-check2" aria-hidden="true"></i><span class="visually-hidden"><?= $isRtl ? 'مدرجة' : 'Listed' ?></span></span><?php else: ?><span class="not-listed"><?= $isRtl ? 'غير مذكورة' : 'Not listed' ?></span><?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="<?= count($plans) + 1 ?>" class="text-secondary py-4"><?= $isRtl ? 'لم تُسجّل مزايا لهذه الباقات بعد.' : 'No benefits are listed for these plans yet.' ?></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <p class="small text-secondary mt-3"><i class="bi bi-info-circle me-2" aria-hidden="true"></i><?= $isRtl ? 'تعرض المقارنة المزايا الموجودة في بيانات كل باقة. عدم ذكر ميزة لا يعني بالضرورة أنها غير متاحة.' : 'The comparison reflects benefits recorded for each plan. An unlisted benefit is not necessarily unavailable.' ?></p>

            <section class="plan-next-step d-flex flex-wrap align-items-center justify-content-between gap-3 mt-5 pt-4 border-top border-secondary" aria-labelledby="next-step-title">
                <div><h2 class="h5 fw-semibold mb-1" id="next-step-title"><?= $isRtl ? 'بعد اختيار الباقة' : 'After selecting a plan' ?></h2><p class="small text-secondary mb-0"><?= $isRtl ? 'ستتابع إلى صفحة الدفع لرفع الإيصال للمراجعة.' : 'Continue to the payment page to submit your receipt for review.' ?></p></div>
                <button type="submit" class="btn btn-primary px-4 py-2"><?= $isRtl ? 'متابعة إلى رفع الإيصال' : 'Continue to receipt upload' ?><i class="bi bi-arrow-left ms-2" aria-hidden="true"></i></button>
            </section>
        </form>
    <?php endif; ?>
</main>
<script>
document.querySelectorAll('input[name="plan_id"]').forEach((input) => input.addEventListener('change', () => {
    document.querySelectorAll('.plan-header-cell, .plan-cell').forEach((cell) => cell.classList.toggle('is-selected', cell.dataset.planId === input.value));
}));
</script>
</body>
</html>
