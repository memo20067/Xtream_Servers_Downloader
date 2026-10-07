<?php
// payment.php - Payment Receipt Upload & Processing
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$db = getDBConnection();
$msg = '';
$error = '';
$selectedPlanId = (int)($_GET['plan_id'] ?? ($currentUser['subscription_plan_id'] ?? 0));

// Handle payment receipt upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_receipt') {
    $planId = (int)($_POST['plan_id'] ?? 0);
    $selectedPlanId = $planId;
    $paymentMethod = trim($_POST['payment_method'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $transactionId = trim($_POST['transaction_id'] ?? '');

    if ($planId <= 0 || empty($paymentMethod)) {
        $error = 'Please select a plan and payment method.';
    } else {
        // Verify plan exists
        $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ? AND is_visible = 1");
        $stmt->execute([$planId]);
        $plan = $stmt->fetch();

        if (!$plan) {
            $error = 'Invalid subscription plan selected.';
        } else {
            // Handle file upload
            $receiptPath = null;
            if (isset($_FILES['receipt']) && $_FILES['receipt']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/uploads/receipts/';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $fileExt = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
                $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];

                if (!in_array($fileExt, $allowedExts)) {
                    $error = 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP, PDF.';
                } else {
                    $filename = 'receipt_' . $currentUser['id'] . '_' . time() . '.' . $fileExt;
                    $receiptPath = 'uploads/receipts/' . $filename;

                    if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $uploadDir . $filename)) {
                        $error = 'Failed to upload receipt file.';
                    }
                }
            } else {
                $error = 'Please upload a payment receipt/screenshot.';
            }

            if (!$error) {
                // Store payment record
                $stmt = $db->prepare("
                    INSERT INTO payment_receipts (user_id, plan_id, payment_method, amount, transaction_id, receipt_path, status, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$currentUser['id'], $planId, $paymentMethod, $amount, $transactionId, $receiptPath]);
                $msg = 'Payment receipt uploaded successfully! Waiting for admin verification.';

                // Log payment
                require_once __DIR__ . '/includes/logger.php';
                Logger::logPayment('Payment receipt uploaded by user', 'INFO', $currentUser['id'], [
                    'plan_id' => $planId,
                    'payment_method' => $paymentMethod,
                    'amount' => $amount,
                    'transaction_id' => $transactionId
                ]);
            }
        }
    }
}

// Fetch available plans
$stmtPlans = $db->prepare("SELECT * FROM subscription_plans WHERE is_visible = 1 ORDER BY price ASC");
$stmtPlans->execute();
$plans = $stmtPlans->fetchAll();

if ($selectedPlanId <= 0 || !array_filter($plans, static fn($plan) => (int)$plan['id'] === $selectedPlanId)) {
    $selectedPlanId = (int)($plans[0]['id'] ?? 0);
}
$selectedPlan = null;
foreach ($plans as $availablePlan) {
    if ((int)$availablePlan['id'] === $selectedPlanId) {
        $selectedPlan = $availablePlan;
        break;
    }
}

// Fetch payment settings
$payWallet = getSetting('pay_wallet', '');
$payBank = getSetting('pay_bank', '');
$payPaypal = getSetting('pay_paypal', '');
$payInstapay = getSetting('pay_instapay', '');
$payCryptoBtc = getSetting('pay_crypto_btc', '');
$payCryptoEth = getSetting('pay_crypto_eth', '');
$payCryptoUsdt = getSetting('pay_crypto_usdt', '');
$payCurrency = getSetting('pay_currency', 'USD');
$payInstructions = getSetting('pay_instructions', '');

$lang = $_COOKIE['app_lang'] ?? ($_SESSION['lang'] ?? 'ar');
$lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
$isRtl = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $isRtl ? 'الدفع والاشتراك' : 'Payment & Subscription' ?> - <?= htmlspecialchars(getSetting('app_name', 'Xtream IPTV Player')) ?></title>
    <?php if ($isRtl): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i><?= $isRtl ? 'العودة للقائمة الرئيسية' : 'Back to Dashboard' ?>
        </a>
        <span class="navbar-text text-white fw-bold"><?= $isRtl ? 'الدفع والاشتراك' : 'Payment & Subscription' ?></span>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="glass-panel p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-gem me-2 text-warning"></i><?= $isRtl ? 'باقات الاشتراك' : 'Subscription Plans' ?></h4>
                <div class="list-group">
                    <?php foreach ($plans as $plan): ?>
                        <div class="list-group-item bg-transparent text-white border-secondary">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="fw-bold mb-1"><?= htmlspecialchars($plan['name']) ?></h6>
                                    <small class="text-white-50"><?= $plan['currency'] ?> $<?= number_format($plan['price'], 2) ?></small>
                                </div>
                                <span class="badge bg-<?= $currentUser['has_paid_subscription'] && $currentUser['subscription_plan_id'] == $plan['id'] ? 'success' : 'secondary' ?>">
                                    <?= $currentUser['has_paid_subscription'] && $currentUser['subscription_plan_id'] == $plan['id'] ? ($isRtl ? 'فعّال' : 'Active') : '' ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="glass-panel p-4">
                <h4 class="fw-bold mb-3"><i class="bi bi-credit-card me-2 text-info"></i><?= $isRtl ? 'طرق الدفع' : 'Payment Methods' ?></h4>
                <?php if ($selectedPlan): ?>
                    <div class="selected-plan-summary d-flex flex-wrap justify-content-between align-items-center gap-3 border-bottom border-secondary pb-3 mb-4">
                        <div><div class="small text-secondary"><?= $isRtl ? 'الباقة المختارة' : 'Selected plan' ?></div><strong><?= htmlspecialchars($selectedPlan['name'], ENT_QUOTES, 'UTF-8') ?></strong></div>
                        <strong dir="ltr"><?= htmlspecialchars($selectedPlan['currency'], ENT_QUOTES, 'UTF-8') ?> $<?= number_format((float)$selectedPlan['price'], 2) ?></strong>
                    </div>
                <?php endif; ?>

                <?php if ($msg): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($payInstructions)): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i><?= nl2br(htmlspecialchars($payInstructions)) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload_receipt">

                    <div class="mb-3">
                        <label class="form-label"><?= $isRtl ? 'اختر الباقة' : 'Select Plan' ?></label>
                        <select name="plan_id" class="form-select bg-dark text-white border-secondary" required>
                            <?php foreach ($plans as $plan): ?>
                                <option value="<?= (int)$plan['id'] ?>" <?= $selectedPlanId === (int)$plan['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plan['name']) ?> - <?= $plan['currency'] ?> $<?= number_format($plan['price'], 2) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= $isRtl ? 'طريقة الدفع' : 'Payment Method' ?></label>
                        <select name="payment_method" class="form-select bg-dark text-white border-secondary" required <?= empty($payWallet) && empty($payBank) && empty($payPaypal) && empty($payInstapay) && empty($payCryptoBtc) && empty($payCryptoEth) && empty($payCryptoUsdt) ? 'disabled' : '' ?>>
                            <option value=""><?= $isRtl ? '-- اختر طريقة الدفع --' : '-- Select Payment Method --' ?></option>
                            <?php if (!empty($payWallet)): ?>
                                <option value="wallet"><?= $isRtl ? 'محفظة إلكترونية / فودافون كاش' : 'Mobile Wallet / Vodafone Cash' ?></option>
                            <?php endif; ?>
                            <?php if (!empty($payBank)): ?>
                                <option value="bank"><?= $isRtl ? 'تحويل بنكي' : 'Bank Transfer' ?></option>
                            <?php endif; ?>
                            <?php if (!empty($payPaypal)): ?>
                                <option value="paypal">PayPal</option>
                            <?php endif; ?>
                            <?php if (!empty($payInstapay)): ?>
                                <option value="instapay">InstaPay / Fawry</option>
                            <?php endif; ?>
                            <?php if (!empty($payCryptoBtc)): ?>
                                <option value="btc">Bitcoin (BTC)</option>
                            <?php endif; ?>
                            <?php if (!empty($payCryptoEth)): ?>
                                <option value="eth">Ethereum (ETH)</option>
                            <?php endif; ?>
                            <?php if (!empty($payCryptoUsdt)): ?>
                                <option value="usdt">USDT (TRC20/ERC20)</option>
                            <?php endif; ?>
                        </select>
                        <?php if (empty($payWallet) && empty($payBank) && empty($payPaypal) && empty($payInstapay) && empty($payCryptoBtc) && empty($payCryptoEth) && empty($payCryptoUsdt)): ?>
                            <div class="form-text text-warning"><?= $isRtl ? 'لم تُعدّ الإدارة طرق دفع بعد.' : 'The administrator has not configured payment methods yet.' ?></div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= $isRtl ? 'المبلغ المدفوع' : 'Amount Paid' ?></label>
                        <input type="text" name="amount" class="form-control bg-dark text-white border-secondary" placeholder="<?= htmlspecialchars($payCurrency) ?> 0.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= $isRtl ? 'رقم المعاملة / العملية' : 'Transaction ID' ?></label>
                        <input type="text" name="transaction_id" class="form-control bg-dark text-white border-secondary" placeholder="<?= $isRtl ? 'رقم العملية إن وجد' : 'Transaction reference if available' ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= $isRtl ? 'صورة إيصال الدفع' : 'Payment Receipt / Screenshot' ?> *</label>
                        <input type="file" name="receipt" class="form-control bg-dark text-white border-secondary" accept="image/*,.pdf" required>
                        <div class="form-text text-muted"><?= $isRtl ? 'يُرجى رفع صورة واضحة لإيصال الدفع أو لقطة شاشة' : 'Upload a clear image or screenshot of your payment receipt' ?></div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100" <?= empty($plans) || (empty($payWallet) && empty($payBank) && empty($payPaypal) && empty($payInstapay) && empty($payCryptoBtc) && empty($payCryptoEth) && empty($payCryptoUsdt)) ? 'disabled' : '' ?>>
                        <i class="bi bi-upload me-2"></i><?= $isRtl ? 'رفع إيصال الدفع' : 'Upload Payment Receipt' ?>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
