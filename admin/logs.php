<?php
// admin/logs.php - Categorized Multi-Log System
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    $stmt = $db->prepare("DELETE FROM error_logs");
    $stmt->execute();
    $msg = admin_t('msg_logs_cleared');

    require_once __DIR__ . '/../includes/logger.php';
    Logger::logSystem('All logs cleared by admin', 'INFO');
}

$categoryFilter = $_GET['category'] ?? '';
$levelFilter = $_GET['level'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 50;
$offset = ($page - 1) * $limit;

$sql = "SELECT * FROM error_logs WHERE 1=1";
$params = [];

if ($levelFilter) {
    $sql .= " AND level = ?";
    $params[] = strtoupper($levelFilter);
}

if ($categoryFilter) {
    $sql .= " AND action LIKE ?";
    $params[] = "%{$categoryFilter}%";
}

if ($search) {
    $sql .= " AND (message LIKE ? OR action LIKE ? OR server_id LIKE ? OR details LIKE ?)";
    $term = "%{$search}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$countSql = str_replace("SELECT *", "SELECT COUNT(*) as cnt", $sql);
$stmtCount = $db->prepare($countSql);
$stmtCount->execute($params);
$totalLogs = $stmtCount->fetch()['cnt'];
$totalPages = max(1, (int)ceil($totalLogs / $limit));

$sql .= " ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$categories = [
    'AUTH' => 'Authentication',
    'API' => 'API Requests',
    'PAYMENT' => 'Payments',
    'SYSTEM' => 'System',
    'ERROR' => 'Errors',
    'STREAM' => 'Streaming',
    'ADMIN' => 'Admin Actions'
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-journal-code me-2"></i><?= admin_t('logs_title') ?></h3>
    <form method="POST" onsubmit="return confirm('Clear all logs?')" class="d-inline">
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash me-1"></i><?= admin_t('btn_clear_logs') ?>
        </button>
    </form>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <select name="category" class="form-select bg-dark text-white border-secondary">
                    <option value=""><?= $isRtl ? 'كل الفئات' : 'All Categories' ?></option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $categoryFilter === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select name="level" class="form-select bg-dark text-white border-secondary">
                    <option value=""><?= $isRtl ? 'كل المستويات' : 'All Levels' ?></option>
                    <option value="INFO" <?= $levelFilter === 'INFO' ? 'selected' : '' ?>>INFO</option>
                    <option value="WARNING" <?= $levelFilter === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                    <option value="ERROR" <?= $levelFilter === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                    <option value="CRITICAL" <?= $levelFilter === 'CRITICAL' ? 'selected' : '' ?>>CRITICAL</option>
                </select>
            </div>
            <div class="col-md-4">
                <input type="text" name="search" class="form-control bg-dark text-white border-secondary" placeholder="<?= $isRtl ? 'بحث...' : 'Search...' ?>" value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100"><?= $isRtl ? 'تصفية' : 'Filter' ?></button>
            </div>
        </form>
    </div>
</div>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th><?= admin_t('tbl_timestamp') ?></th>
                        <th><?= $isRtl ? 'الفئة' : 'Category' ?></th>
                        <th><?= admin_t('tbl_level') ?></th>
                        <th><?= admin_t('tbl_action') ?></th>
                        <th><?= $isRtl ? 'الرسالة' : 'Message' ?></th>
                        <th><?= admin_t('tbl_details') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted"><?= $isRtl ? 'لا توجد سجلات' : 'No logs found.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><small><?= htmlspecialchars($log['created_at']) ?></small></td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($log['action'] ?? 'N/A') ?></span></td>
                                <td>
                                    <?php
                                    $levelClass = 'secondary';
                                    if ($log['level'] === 'ERROR' || $log['level'] === 'CRITICAL') $levelClass = 'danger';
                                    elseif ($log['level'] === 'WARNING') $levelClass = 'warning';
                                    elseif ($log['level'] === 'INFO') $levelClass = 'info';
                                    ?>
                                    <span class="badge bg-<?= $levelClass ?>"><?= htmlspecialchars($log['level']) ?></span>
                                </td>
                                <td><code><?= htmlspecialchars($log['action'] ?? '-') ?></code></td>
                                <td><?= htmlspecialchars($log['message']) ?></td>
                                <td><small class="text-white-50"><?= htmlspecialchars(substr($log['details'] ?? '', 0, 100)) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<nav class="mt-4">
    <ul class="pagination justify-content-center">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link bg-dark text-white border-secondary" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
            </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
