<?php
// admin/news.php
require_once __DIR__ . '/header.php';
$pdo = getDBConnection();

// Create news table if not exists (defensive migration)
$pdo->exec("CREATE TABLE IF NOT EXISTS news_ticker (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    message TEXT NOT NULL,
    severity ENUM('info', 'warning', 'alert') NOT NULL DEFAULT 'info',
    expires_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

$message = '';
$error = '';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $msg_text = trim($_POST['message'] ?? '');
        $severity = $_POST['severity'] ?? 'info';
        $target_user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
        $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

        if (empty($msg_text)) {
            $error = "Announcement message cannot be empty.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO news_ticker (user_id, message, severity, expires_at) VALUES (?, ?, ?, ?)");
            $stmt->execute([$target_user_id, $msg_text, $severity, $expires_at]);
            $message = "News announcement posted successfully!";
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM news_ticker WHERE id = ?");
            $stmt->execute([$id]);
            $message = "Announcement deleted successfully.";
        }
    }
}

// Fetch users for target user dropdown
$users = $pdo->query("SELECT id, username, email FROM users ORDER BY username ASC")->fetchAll();

// Fetch news items
$news_items = $pdo->query("
    SELECT n.*, u.username
    FROM news_ticker n
    LEFT JOIN users u ON n.user_id = u.id
    ORDER BY n.created_at DESC
")->fetchAll();
?>

<div class="row mb-4">
    <div class="col-md-12 d-flex justify-content-between align-items-center">
        <h2><i class="bi bi-megaphone me-2"></i>News Ticker Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNewsModal">
            <i class="bi bi-plus-lg me-1"></i> Post Announcement
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white mb-4">
    <div class="card-header border-bottom border-dark">
        <h5 class="mb-0"><i class="bi bi-list-stars me-2"></i>Active & Past Announcements</h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-striped table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Severity</th>
                        <th>Target Audience</th>
                        <th>Message</th>
                        <th>Expiration</th>
                        <th>Posted At</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($news_items)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">No news announcements posted yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($news_items as $item): ?>
                            <tr>
                                <td><?= $item['id'] ?></td>
                                <td>
                                    <?php if ($item['severity'] === 'alert'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-exclamation-octagon me-1"></i>Alert</span>
                                    <?php elseif ($item['severity'] === 'warning'): ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-triangle me-1"></i>Warning</span>
                                    <?php else: ?>
                                        <span class="badge bg-info text-dark"><i class="bi bi-info-circle me-1"></i>Info</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= $item['user_id'] ? '<span class="badge bg-primary">User: ' . htmlspecialchars($item['username']) . '</span>' : '<span class="badge bg-secondary">Global (All Users)</span>' ?>
                                </td>
                                <td style="max-width: 350px;" class="text-truncate"><?= htmlspecialchars($item['message']) ?></td>
                                <td>
                                    <?php
                                    if ($item['expires_at']) {
                                        $exp = strtotime($item['expires_at']);
                                        echo $exp < time() ? '<span class="text-danger">' . htmlspecialchars($item['expires_at']) . ' (Expired)</span>' : htmlspecialchars($item['expires_at']);
                                    } else {
                                        echo '<span class="text-muted">Never</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= htmlspecialchars($item['created_at']) ?></td>
                                <td>
                                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this announcement?');" class="d-inline">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add News Item -->
<div class="modal fade" id="addNewsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark text-white border-secondary">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title"><i class="bi bi-megaphone me-2"></i>Post News Ticker Announcement</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Message / Announcement Text <span class="text-danger">*</span></label>
                        <textarea name="message" class="form-control bg-secondary text-white border-0" rows="3" required placeholder="Enter announcement text..."></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Severity Level</label>
                        <select name="severity" class="form-select bg-secondary text-white border-0">
                            <option value="info">Info (Blue)</option>
                            <option value="warning">Warning (Yellow)</option>
                            <option value="alert">Alert (Red)</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Audience</label>
                        <select name="user_id" class="form-select bg-secondary text-white border-0">
                            <option value="">Global (All Users)</option>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>">User: <?= htmlspecialchars($u['username']) ?> (<?= htmlspecialchars($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiration Date/Time (Optional)</label>
                        <input type="datetime-local" name="expires_at" class="form-control bg-secondary text-white border-0">
                        <div class="form-text text-muted">Leave blank for non-expiring announcement.</div>
                    </div>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Post Announcement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
