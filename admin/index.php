<?php
// admin/index.php
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$stmtServers = $db->query("SELECT COUNT(*) as cnt FROM servers WHERE user_id IS NULL");
$globalServersCount = $stmtServers->fetch()['cnt'];

$stmtUsers = $db->query("SELECT COUNT(*) as cnt FROM users");
$totalUsersCount = $stmtUsers->fetch()['cnt'];

$stmtPaid = $db->query("SELECT COUNT(*) as cnt FROM users WHERE has_paid_subscription = 1");
$paidUsersCount = $stmtPaid->fetch()['cnt'];
?>

<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card bg-secondary text-white shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="bi bi-hdd-network fs-1 text-info mb-2"></i>
                <h5 class="card-title"><?= admin_t('stat_servers') ?></h5>
                <h2 class="display-6 fw-bold mb-0"><?= $globalServersCount ?></h2>
                <a href="servers.php" class="btn btn-outline-info btn-sm mt-3"><?= admin_t('nav_servers') ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="bi bi-people fs-1 text-success mb-2"></i>
                <h5 class="card-title"><?= admin_t('stat_users') ?></h5>
                <h2 class="display-6 fw-bold mb-0"><?= $totalUsersCount ?></h2>
                <a href="users.php" class="btn btn-outline-success btn-sm mt-3"><?= admin_t('nav_users') ?></a>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-secondary text-white shadow-sm border-0">
            <div class="card-body text-center p-4">
                <i class="bi bi-star-fill fs-1 text-warning mb-2"></i>
                <h5 class="card-title"><?= admin_t('stat_paid') ?></h5>
                <h2 class="display-6 fw-bold mb-0"><?= $paidUsersCount ?></h2>
                <a href="users.php" class="btn btn-outline-warning btn-sm mt-3"><?= admin_t('nav_users') ?></a>
            </div>
        </div>
    </div>
</div>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-header border-bottom border-dark">
        <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i><?= admin_t('dash_welcome') ?></h5>
    </div>
    <div class="card-body">
        <p class="mb-2 fw-bold"><?= admin_t('dash_welcome') ?></p>
        <p class="mb-0 text-muted"><?= admin_t('dash_sub') ?></p>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
