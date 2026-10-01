<?php
// admin/users.php
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        if ($action === 'toggle_subscription') {
            $status = (int)($_POST['status'] ?? 0);
            $stmt = $db->prepare("UPDATE users SET has_paid_subscription = ? WHERE id = ?");
            $stmt->execute([$status, $userId]);
            $msg = 'User subscription updated successfully.';
        } elseif ($action === 'toggle_role') {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);
            $msg = 'User role updated successfully.';
        }
    }
}

$stmtUsers = $db->query("SELECT * FROM users ORDER BY id ASC");
$users = $stmtUsers->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people me-2"></i>User Accounts & Subscriptions</h3>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Paid Subscription</th>
                        <th>Registered At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><span class="badge bg-secondary">#<?= $u['id'] ?></span></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><?= htmlspecialchars($u['email']) ?></td>
                            <td>
                                <?php if ($u['role'] === 'admin'): ?>
                                    <span class="badge bg-danger"><i class="bi bi-shield-check me-1"></i>Admin</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">User</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($u['has_paid_subscription']): ?>
                                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Active Paid</span>
                                <?php else: ?>
                                    <span class="badge bg-warning text-dark"><i class="bi bi-x-circle me-1"></i>Free User</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($u['created_at']) ?></td>
                            <td class="text-end">
                                <form method="POST" style="display:inline-block;" class="me-1">
                                    <input type="hidden" name="action" value="toggle_subscription">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $u['has_paid_subscription'] ? 0 : 1 ?>">
                                    <button type="submit" class="btn btn-sm <?= $u['has_paid_subscription'] ? 'btn-outline-warning' : 'btn-outline-success' ?>">
                                        <?= $u['has_paid_subscription'] ? '<i class="bi bi-x-circle me-1"></i>Revoke Subscription' : '<i class="bi bi-star me-1"></i>Grant Subscription' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info">
                                        <?= $u['role'] === 'admin' ? 'Make Regular User' : 'Make Admin' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
