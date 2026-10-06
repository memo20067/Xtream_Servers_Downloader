<?php
// payment.php - User-facing payment / subscription checkout page
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$db = getDBConnection();
$msg = '';
$error = '';

// ---- Handle screenshot upload & payment request submission ----
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'submit_payment') {
    $planId = (int)($_POST['plan_id'] ?? 0);

    // Validate plan exists
    $stmtPlan = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_visible = 1");
    $stmtPlan->execute([$planId]);
    $selectedPlan = $stmtPlan->fetch();

    if (!$selectedPlan) {
        $error = 'Invalid plan selected.';
    } elseif (empty($_FILES['screenshot']['tmp_name'])) {
        $error = 'Please upload a screenshot / proof of payment.';
    } else {
        // File upload validation
        $file      = $_FILES['screenshot'];
        $allowedMime = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $mime      = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, $allowedMime)) {
            $error = 'Only JPG, PNG, GIF, or WebP images are accepted.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = 'Image must be smaller than 5 MB.';
        } else {
            // Check if user already has a pending request for same plan
            $stmtChk = $db->prepare("SELECT id FROM payment_requests WHERE user_id = ? AND plan_id = ? AND status = 'pending'");
            $stmtChk->execute([$_SESSION['user_id'], $planId]);
            if ($stmtChk->fetch()) {
                $error = 'You already have a pending payment request for this plan. Please wait for admin review.';
            } else {
                // Save file
                $uploadDir = __DIR__ . '/uploads/payment_proofs/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0755, true);
                }
                $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'pay_' . $_SESSION['user_id'] . '_' . $planId . '_' . time() . '.' . strtolower($ext);
                $savePath = $uploadDir . $filename;

                if (!move_uploaded_file($file['tmp_name'], $savePath)) {
                    $error = 'Failed to save uploaded file. Please try again.';
                } else {
                    $relPath = 'uploads/payment_proofs/' . $filename;
                    $stmtIns = $db->prepare("INSERT INTO payment_requests (user_id, plan_id, amount, screenshot_path, status) VALUES (?, ?, ?, ?, 'pending')");
                    $stmtIns->execute([$_SESSION['user_id'], $planId, $selectedPlan['price'], $relPath]);
                    $msg = 'Payment proof submitted successfully! Our team will review and activate your subscription soon.';
                }
            }
        }
    }
}

// ---- Load plans ----
try {
    $stmtPlans = $db->query("SELECT * FROM subscription_plans WHERE is_visible = 1 ORDER BY price ASC");
    $plans = $stmtPlans->fetchAll();
} catch (Exception $e) {
    $plans = [];
}

// ---- Load payment methods from settings ----
$payMethods = [
    'wallet'   => getSetting('pay_wallet',     ''),
    'bank'     => getSetting('pay_bank',       ''),
    'paypal'   => getSetting('pay_paypal',     ''),
    'instapay' => getSetting('pay_instapay',   ''),
    'btc'      => getSetting('pay_crypto_btc', ''),
    'eth'      => getSetting('pay_crypto_eth', ''),
    'usdt'     => getSetting('pay_crypto_usdt',''),
    'currency' => getSetting('pay_currency',   'USD'),
    'notes'    => getSetting('pay_instructions',''),
];

// ---- Load user's existing payment request ----
$stmtMyReq = $db->prepare("
    SELECT pr.*, p.name as plan_name
    FROM payment_requests pr
    JOIN subscription_plans p ON pr.plan_id = p.id
    WHERE pr.user_id = ?
    ORDER BY pr.submitted_at DESC LIMIT 1
");
$stmtMyReq->execute([$_SESSION['user_id']]);
$myRequest = $stmtMyReq->fetch();

$appName = getSetting('app_name', 'Xtream IPTV');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الاشتراك والدفع – <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .plan-card { cursor: pointer; transition: all .2s; border: 2px solid transparent; }
        .plan-card:hover, .plan-card.selected { border-color: #0d6efd !important; transform: translateY(-4px); }
        .plan-card.selected .plan-radio { display: block; }
        .plan-radio { display: none; }
        .copy-btn { font-size: .75rem; }
        .proof-preview { max-height: 200px; display: none; border-radius: 8px; }
    </style>
</head>
<body class="bg-dark text-light">

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-play-circle-fill me-2"></i><?= htmlspecialchars($appName) ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="text-white-50 small">مرحباً، <?= htmlspecialchars($currentUser['username']) ?></span>
            <a href="logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i>خروج</a>
        </div>
    </div>
</nav>

<div class="container py-5">

    <!-- Page Header -->
    <div class="text-center mb-5">
        <h2 class="fw-bold text-primary"><i class="bi bi-gem me-2"></i>الاشتراك في الخدمة</h2>
        <p class="text-white-50">اختر الباقة المناسبة، ثم قم بالدفع وأرفق صورة التأكيد</p>
    </div>

    <?php if ($msg): ?>
        <div class="alert alert-success alert-dismissible fade show text-end" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show text-end" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Existing request status banner -->
    <?php if ($myRequest && !$msg): ?>
        <div class="alert alert-<?= $myRequest['status'] === 'approved' ? 'success' : ($myRequest['status'] === 'rejected' ? 'danger' : 'warning') ?> mb-4 text-end">
            <strong>حالة طلبك الأخير:</strong>
            <span class="ms-2">
                <?php if ($myRequest['status'] === 'pending'): ?>
                    <i class="bi bi-hourglass-split me-1"></i>قيد المراجعة — باقة: <?= htmlspecialchars($myRequest['plan_name']) ?>
                <?php elseif ($myRequest['status'] === 'approved'): ?>
                    <i class="bi bi-check-circle-fill me-1"></i>تم التفعيل! باقة: <?= htmlspecialchars($myRequest['plan_name']) ?>
                <?php else: ?>
                    <i class="bi bi-x-circle-fill me-1"></i>مرفوض — <?= htmlspecialchars($myRequest['admin_notes'] ?: 'تواصل مع الدعم') ?>
                <?php endif; ?>
            </span>
        </div>
    <?php endif; ?>

    <!-- Plan Selection -->
    <h5 class="mb-3 fw-bold"><i class="bi bi-ui-checks me-2 text-info"></i>اختر الباقة</h5>
    <div class="row g-3 mb-4" id="plansRow">
        <?php foreach ($plans as $i => $plan):
            $isPopular = ($i === 1);
        ?>
        <div class="col-md-4">
            <div class="card plan-card bg-secondary text-white border-0 shadow h-100 p-3"
                 data-plan-id="<?= $plan['id'] ?>"
                 data-plan-name="<?= htmlspecialchars($plan['name']) ?>"
                 data-plan-price="<?= $plan['price'] ?>"
                 onclick="selectPlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['name'], ENT_QUOTES) ?>', <?= $plan['price'] ?>, '<?= htmlspecialchars($payMethods['currency'], ENT_QUOTES) ?>')">
                <?php if ($isPopular): ?>
                    <div class="badge bg-primary mb-2 w-auto d-inline-block">الأكثر شيوعاً</div>
                <?php endif; ?>
                <h5 class="fw-bold text-center"><?= htmlspecialchars($plan['name']) ?></h5>
                <div class="text-center my-2">
                    <span class="display-6 fw-bold text-info"><?= htmlspecialchars($payMethods['currency']) ?> <?= number_format($plan['price'], 2) ?></span>
                    <span class="text-white-50"> / شهر</span>
                </div>
                <hr class="border-dark">
                <ul class="list-unstyled small">
                    <?php foreach (explode("\n", $plan['features'] ?? '') as $f): if (!trim($f)) continue; ?>
                        <li class="mb-1"><i class="bi bi-check-circle-fill text-success me-2"></i><?= htmlspecialchars(trim($f)) ?></li>
                    <?php endforeach; ?>
                </ul>
                <div class="plan-radio mt-2 text-center">
                    <span class="badge bg-primary px-3 py-2"><i class="bi bi-check2-circle me-1"></i>محدد</span>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Payment methods + form -->
    <div id="paymentSection" style="display:none">
        <div class="row g-4">
            <!-- Payment details -->
            <div class="col-lg-5">
                <div class="card bg-secondary text-white border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="text-warning fw-bold mb-3">
                            <i class="bi bi-credit-card me-2"></i>تفاصيل الدفع
                        </h5>
                        <div class="alert alert-info text-end mb-3 p-2 small">
                            <strong>الباقة المختارة:</strong> <span id="selectedPlanName">—</span><br>
                            <strong>المبلغ المطلوب:</strong> <span id="selectedPlanPrice" class="fw-bold text-warning">—</span>
                        </div>

                        <p class="text-white-50 small mb-2">يمكنك الدفع عبر أي من الطرق التالية:</p>

                        <?php if ($payMethods['wallet']): ?>
                        <div class="mb-3 p-3 bg-dark rounded">
                            <div class="fw-bold mb-1"><i class="bi bi-wallet2 text-success me-2"></i>محفظة / فودافون كاش</div>
                            <div class="d-flex align-items-center gap-2">
                                <code class="text-info flex-grow-1"><?= htmlspecialchars($payMethods['wallet']) ?></code>
                                <button class="btn btn-outline-light btn-sm copy-btn" onclick="copyText('<?= htmlspecialchars($payMethods['wallet'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($payMethods['bank']): ?>
                        <div class="mb-3 p-3 bg-dark rounded">
                            <div class="fw-bold mb-1"><i class="bi bi-bank text-info me-2"></i>حساب بنكي / IBAN</div>
                            <div class="d-flex align-items-center gap-2">
                                <code class="text-info flex-grow-1" style="word-break:break-all"><?= htmlspecialchars($payMethods['bank']) ?></code>
                                <button class="btn btn-outline-light btn-sm copy-btn" onclick="copyText('<?= htmlspecialchars($payMethods['bank'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($payMethods['paypal']): ?>
                        <div class="mb-3 p-3 bg-dark rounded">
                            <div class="fw-bold mb-1"><i class="bi bi-paypal text-primary me-2"></i>PayPal</div>
                            <div class="d-flex align-items-center gap-2">
                                <code class="text-info flex-grow-1"><?= htmlspecialchars($payMethods['paypal']) ?></code>
                                <button class="btn btn-outline-light btn-sm copy-btn" onclick="copyText('<?= htmlspecialchars($payMethods['paypal'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($payMethods['instapay']): ?>
                        <div class="mb-3 p-3 bg-dark rounded">
                            <div class="fw-bold mb-1"><i class="bi bi-phone text-warning me-2"></i>InstaPay / فوري</div>
                            <div class="d-flex align-items-center gap-2">
                                <code class="text-info flex-grow-1"><?= htmlspecialchars($payMethods['instapay']) ?></code>
                                <button class="btn btn-outline-light btn-sm copy-btn" onclick="copyText('<?= htmlspecialchars($payMethods['instapay'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($payMethods['btc'] || $payMethods['eth'] || $payMethods['usdt']): ?>
                        <div class="mb-3 p-3 bg-dark rounded">
                            <div class="fw-bold mb-2"><i class="bi bi-currency-bitcoin text-warning me-2"></i>عملات مشفرة</div>
                            <?php if ($payMethods['btc']): ?>
                                <div class="mb-1 small"><span class="text-warning">BTC:</span>
                                    <code class="text-info" style="word-break:break-all"><?= htmlspecialchars($payMethods['btc']) ?></code>
                                    <button class="btn btn-outline-light btn-sm copy-btn ms-1" onclick="copyText('<?= htmlspecialchars($payMethods['btc'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($payMethods['eth']): ?>
                                <div class="mb-1 small"><span class="text-warning">ETH:</span>
                                    <code class="text-info" style="word-break:break-all"><?= htmlspecialchars($payMethods['eth']) ?></code>
                                    <button class="btn btn-outline-light btn-sm copy-btn ms-1" onclick="copyText('<?= htmlspecialchars($payMethods['eth'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                                </div>
                            <?php endif; ?>
                            <?php if ($payMethods['usdt']): ?>
                                <div class="small"><span class="text-warning">USDT:</span>
                                    <code class="text-info" style="word-break:break-all"><?= htmlspecialchars($payMethods['usdt']) ?></code>
                                    <button class="btn btn-outline-light btn-sm copy-btn ms-1" onclick="copyText('<?= htmlspecialchars($payMethods['usdt'], ENT_QUOTES) ?>')"><i class="bi bi-copy"></i></button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($payMethods['notes']): ?>
                        <div class="alert alert-warning mt-2 text-end p-2 small">
                            <i class="bi bi-info-circle me-1"></i><?= nl2br(htmlspecialchars($payMethods['notes'])) ?>
                        </div>
                        <?php endif; ?>

                        <?php if (!array_filter([$payMethods['wallet'], $payMethods['bank'], $payMethods['paypal'], $payMethods['instapay'], $payMethods['btc'], $payMethods['eth'], $payMethods['usdt']])): ?>
                        <div class="alert alert-warning text-end">
                            <i class="bi bi-exclamation-triangle me-1"></i>لم يتم إعداد طرق الدفع بعد. يرجى التواصل مع الدعم.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Upload form -->
            <div class="col-lg-7">
                <div class="card bg-secondary text-white border-0 shadow-sm">
                    <div class="card-body p-4">
                        <h5 class="text-success fw-bold mb-3">
                            <i class="bi bi-upload me-2"></i>رفع إثبات الدفع
                        </h5>
                        <p class="text-white-50 small mb-3">بعد إتمام الدفع، قم برفع صورة من عملية الدفع (لقطة شاشة) للتأكيد.</p>

                        <form method="POST" enctype="multipart/form-data" id="paymentForm">
                            <input type="hidden" name="action" value="submit_payment">
                            <input type="hidden" name="plan_id" id="planIdInput" value="">

                            <div class="mb-3 p-3 bg-dark rounded text-center">
                                <label for="screenshotInput" class="form-label fw-bold text-info">
                                    <i class="bi bi-image me-2"></i>صورة إثبات الدفع (JPG / PNG / WebP)
                                </label>
                                <input type="file" name="screenshot" id="screenshotInput"
                                       class="form-control bg-secondary text-white border-secondary"
                                       accept="image/*" required onchange="previewImage(this)">
                                <img id="proofPreview" class="proof-preview mt-3 mx-auto d-block" alt="Preview">
                            </div>

                            <div class="alert alert-info text-end p-2 small mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                سيتم مراجعة الطلب وتفعيل اشتراكك خلال مدة لا تتجاوز 24 ساعة.
                            </div>

                            <button type="submit" class="btn btn-success w-100 fw-bold btn-lg" id="submitBtn">
                                <i class="bi bi-send-check me-2"></i>إرسال طلب الدفع
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- No plan selected yet -->
    <div id="noPlanMsg" class="text-center mt-4">
        <p class="text-white-50"><i class="bi bi-arrow-up me-2"></i>اختر باقة أعلاه لعرض تفاصيل الدفع</p>
    </div>

    <div class="text-center mt-4">
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-right me-1"></i>العودة للرئيسية
        </a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function selectPlan(id, name, price, currency) {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    const card = document.querySelector(`[data-plan-id="${id}"]`);
    if (card) card.classList.add('selected');

    document.getElementById('planIdInput').value = id;
    document.getElementById('selectedPlanName').textContent = name;
    document.getElementById('selectedPlanPrice').textContent = currency + ' ' + parseFloat(price).toFixed(2);

    document.getElementById('paymentSection').style.display = 'block';
    document.getElementById('noPlanMsg').style.display = 'none';

    // Scroll to payment section
    setTimeout(() => document.getElementById('paymentSection').scrollIntoView({behavior:'smooth', block:'start'}), 100);
}

function copyText(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Brief visual feedback
        event.target.closest('button').innerHTML = '<i class="bi bi-check-lg"></i>';
        setTimeout(() => event.target.closest('button').innerHTML = '<i class="bi bi-copy"></i>', 1500);
    }).catch(() => {
        prompt('انسخ النص التالي:', text);
    });
}

function previewImage(input) {
    const preview = document.getElementById('proofPreview');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            preview.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>

