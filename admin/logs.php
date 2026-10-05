<?php
// admin/logs.php - Admin Error Logs Viewer
require_once __DIR__ . '/header.php';
require_once __DIR__ . '/../includes/logger.php';

$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'clear_logs') {
    Logger::clearLogs();
    $msg = admin_t('msg_logs_cleared');
}

$levelFilter = $_GET['level'] ?? null;
$search = $_GET['search'] ?? null;

$logs = Logger::getLogs(150, $levelFilter, $search);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold mb-0"><i class="bi bi-journal-code text-warning me-2"></i><?= admin_t('logs_title') ?></h3>
    <form method="POST" onsubmit="return confirm('Are you sure you want to clear all error logs?');">
        <input type="hidden" name="action" value="clear_logs">
        <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash me-1"></i><?= admin_t('btn_clear_logs') ?></button>
    </form>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Filter & Search Bar -->
<div class="glass-panel p-3 mb-4">
    <form method="GET" class="row g-2 align-items-center">
        <div class="col-md-3">
            <select name="level" class="form-select glass-input border-0" onchange="this.form.submit()">
                <option value="">All Levels</option>
                <option value="ERROR" <?= $levelFilter === 'ERROR' ? 'selected' : '' ?>>ERROR</option>
                <option value="WARNING" <?= $levelFilter === 'WARNING' ? 'selected' : '' ?>>WARNING</option>
                <option value="INFO" <?= $levelFilter === 'INFO' ? 'selected' : '' ?>>INFO</option>
            </select>
        </div>
        <div class="col-md-7">
            <input type="text" name="search" class="form-control glass-input border-0" placeholder="Search error messages or details..." value="<?= htmlspecialchars($search ?? '') ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
        </div>
    </form>
</div>

<div class="glass-panel p-3">
    <div class="table-responsive">
        <table class="table table-dark table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th style="width: 180px;"><?= admin_t('tbl_timestamp') ?></th>
                    <th style="width: 100px;"><?= admin_t('tbl_level') ?></th>
                    <th style="width: 140px;"><?= admin_t('tbl_action') ?></th>
                    <th style="width: 160px;"><?= admin_t('tbl_host') ?></th>
                    <th><?= admin_t('tbl_details') ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)): ?>
                    <tr>
                        <td colspan="5" class="text-center py-4 text-muted">No error logs recorded. System operating normally.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="small text-secondary"><?= htmlspecialchars($log['created_at']) ?></td>
                            <td>
                                <?php if ($log['level'] === 'ERROR'): ?>
                                    <span class="badge bg-danger">ERROR</span>
                                <?php elseif ($log['level'] === 'WARNING'): ?>
                                    <span class="badge bg-warning text-dark">WARNING</span>
                                <?php else: ?>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($log['level']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="fw-bold text-info"><?= htmlspecialchars($log['action'] ?? 'N/A') ?></td>
                            <td class="small text-truncate" style="max-width: 160px;" title="<?= htmlspecialchars($log['server_id'] ?? '') ?>">
                                <?= htmlspecialchars($log['server_id'] ?? '-') ?>
                            </td>
                            <td>
                                <div class="fw-bold mb-1"><?= htmlspecialchars($log['message']) ?></div>
                                <?php if (!empty($log['details'])): ?>
                                    <pre class="bg-black text-light p-2 rounded small mb-0" style="max-height: 120px; overflow-y: auto;"><?= htmlspecialchars($log['details']) ?></pre>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
