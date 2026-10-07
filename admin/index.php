<?php
// Operational admin overview using the application's real records.
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$globalServersCount = (int)$db->query('SELECT COUNT(*) AS cnt FROM servers WHERE user_id IS NULL')->fetch()['cnt'];
$totalUsersCount = (int)$db->query('SELECT COUNT(*) AS cnt FROM users')->fetch()['cnt'];
$paidUsersCount = (int)$db->query('SELECT COUNT(*) AS cnt FROM users WHERE has_paid_subscription = 1')->fetch()['cnt'];
$pendingReceiptsCount = (int)$db->query("SELECT COUNT(*) AS cnt FROM payment_receipts WHERE status = 'pending'")->fetch()['cnt'];
?>
<section class="mb-5 pb-4 border-bottom border-secondary">
    <h1 class="h2 fw-bold mb-2"><?= htmlspecialchars(admin_t('dash_welcome'), ENT_QUOTES, 'UTF-8') ?></h1>
    <p class="text-secondary mb-0"><?= htmlspecialchars(admin_t('dash_sub'), ENT_QUOTES, 'UTF-8') ?></p>
</section>

<section class="admin-summary mb-5 pb-4 border-bottom border-secondary" aria-label="<?= $isRtl ? 'ملخص المنصة' : 'Platform summary' ?>">
    <div class="d-flex flex-wrap gap-4 gap-lg-5">
        <a class="admin-summary-item text-decoration-none" href="servers.php"><span><?= htmlspecialchars(admin_t('stat_servers'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($globalServersCount) ?></strong></a>
        <a class="admin-summary-item text-decoration-none" href="users.php"><span><?= htmlspecialchars(admin_t('stat_users'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($totalUsersCount) ?></strong></a>
        <a class="admin-summary-item text-decoration-none" href="users.php"><span><?= htmlspecialchars(admin_t('stat_paid'), ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format($paidUsersCount) ?></strong></a>
    </div>
</section>

<section class="mb-5" aria-labelledby="pending-receipts-title">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
            <h2 class="h4 fw-semibold mb-1" id="pending-receipts-title"><?= $isRtl ? 'مراجعة الإيصالات المعلّقة' : 'Review pending receipts' ?></h2>
            <p class="small text-secondary mb-0"><?= $isRtl ? 'تظهر هنا الدفعات التي تحتاج إلى مراجعة.' : 'Receipts awaiting review are listed here.' ?></p>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="payments.php"><?= $isRtl ? 'سجل الدفعات' : 'Payment records' ?><i class="bi bi-arrow-up-left ms-2" aria-hidden="true"></i></a>
    </div>
    <?php if ($pendingReceiptsCount === 0): ?>
        <div class="admin-empty-state border-top border-bottom border-secondary py-5 px-3 text-center">
            <i class="bi bi-receipt text-secondary fs-3" aria-hidden="true"></i>
            <p class="fw-semibold mt-3 mb-1"><?= $isRtl ? 'لا توجد إيصالات بانتظار المراجعة' : 'No receipts are waiting for review' ?></p>
            <p class="small text-secondary mb-0"><?= $isRtl ? 'ستظهر الإيصالات الجديدة في هذا القسم.' : 'Newly submitted receipts will appear here.' ?></p>
        </div>
    <?php else: ?>
        <a class="admin-work-row d-flex align-items-center justify-content-between gap-3 py-3 border-top border-bottom border-secondary text-decoration-none" href="payments.php">
            <span class="d-flex align-items-center gap-3"><i class="bi bi-receipt text-primary" aria-hidden="true"></i><span class="text-white"><?= $isRtl ? 'دفعات بانتظار المراجعة' : 'Receipts awaiting review' ?></span></span>
            <span class="badge text-bg-primary"><?= number_format($pendingReceiptsCount) ?></span>
        </a>
    <?php endif; ?>
</section>

<section aria-labelledby="admin-shortcuts-title">
    <h2 class="h5 fw-semibold mb-3" id="admin-shortcuts-title"><?= $isRtl ? 'متابعة الإدارة' : 'Continue managing' ?></h2>
    <div class="admin-work-list border-top border-bottom border-secondary">
        <a class="admin-work-row d-flex align-items-center justify-content-between gap-3 py-3 border-bottom border-secondary text-decoration-none" href="servers.php"><span class="d-flex align-items-center gap-3"><i class="bi bi-hdd-network text-secondary" aria-hidden="true"></i><span class="text-white"><?= htmlspecialchars(admin_t('nav_servers'), ENT_QUOTES, 'UTF-8') ?></span></span><span class="small text-secondary"><?= $isRtl ? 'مصادر البث' : 'Broadcast sources' ?><i class="bi bi-arrow-up-left ms-2" aria-hidden="true"></i></span></a>
        <a class="admin-work-row d-flex align-items-center justify-content-between gap-3 py-3 border-bottom border-secondary text-decoration-none" href="users.php"><span class="d-flex align-items-center gap-3"><i class="bi bi-people text-secondary" aria-hidden="true"></i><span class="text-white"><?= htmlspecialchars(admin_t('nav_users'), ENT_QUOTES, 'UTF-8') ?></span></span><span class="small text-secondary"><?= $isRtl ? 'الحسابات والاشتراكات' : 'Accounts and subscriptions' ?><i class="bi bi-arrow-up-left ms-2" aria-hidden="true"></i></span></a>
        <a class="admin-work-row d-flex align-items-center justify-content-between gap-3 py-3 text-decoration-none" href="plans.php"><span class="d-flex align-items-center gap-3"><i class="bi bi-gem text-secondary" aria-hidden="true"></i><span class="text-white"><?= htmlspecialchars(admin_t('nav_plans'), ENT_QUOTES, 'UTF-8') ?></span></span><span class="small text-secondary"><?= $isRtl ? 'الأسعار والمزايا' : 'Pricing and benefits' ?><i class="bi bi-arrow-up-left ms-2" aria-hidden="true"></i></span></a>
    </div>
</section>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
