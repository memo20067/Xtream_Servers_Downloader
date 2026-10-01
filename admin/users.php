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
            $planId = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;

            $stmt = $db->prepare("UPDATE users SET has_paid_subscription = ?, subscription_plan_id = ? WHERE id = ?");
            $stmt->execute([$status, $planId, $userId]);
            $msg = 'User subscription updated successfully.';
        } elseif ($action === 'toggle_role') {
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $userId]);
            $msg = 'User role updated successfully.';
        }
    }
}

$stmtUsers = $db->query("SELECT u.*, p.name as plan_name FROM users u LEFT JOIN subscription_plans p ON u.subscription_plan_id = p.id ORDER BY u.id ASC");
$users = $stmtUsers->fetchAll();

$stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
$plans = $stmtPlans->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people me-2"></i>User Accounts & Subscription Profiles</h3>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Mobile Phone</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Subscription Status</th>
                        <th>Assigned Plan</th>
                        <th>Registered At</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $avatarSrc = !empty($u['avatar']) ? '../' . htmlspecialchars($u['avatar']) : 'https://via.placeholder.com/40?text=U'; ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <img src="<?= $avatarSrc ?>" class="rounded-circle" style="width:36px; height:36px; object-fit:cover;">
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($u['username']) ?></div>
                                        <small class="text-white-50">ID #<?= $u['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><span class="badge bg-dark border border-secondary"><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></span></td>
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
                            <td>
                                <?= htmlspecialchars($u['plan_name'] ?: 'None') ?>
                            </td>
                            <td><?= htmlspecialchars($u['created_at']) ?></td>
                            <td class="text-end">
                                <button class="btn btn-sm <?= $u['has_paid_subscription'] ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1"
                                        data-bs-toggle="modal" data-bs-target="#subModal<?= $u['id'] ?>">
                                    <?= $u['has_paid_subscription'] ? 'Edit Sub' : 'Grant Sub' ?>
                                </button>
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="action" value="toggle_role">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="role" value="<?= $u['role'] === 'admin' ? 'user' : 'admin' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-info">
                                        <?= $u['role'] === 'admin' ? 'Make User' : 'Make Admin' ?>
                                    </button>
                                </form>

                                <!-- Subscription Modal for user -->
                                <div class="modal fade" id="subModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content bg-secondary text-white">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="toggle_subscription">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header border-bottom border-dark">
                                                    <h5 class="modal-title">Manage Subscription - <?= htmlspecialchars($u['username']) ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label">Subscription Payment Access</label>
                                                        <select name="status" class="form-select bg-dark text-white border-secondary">
                                                            <option value="1" <?= $u['has_paid_subscription'] ? 'selected' : '' ?>>Active Paid Access</option>
                                                            <option value="0" <?= !$u['has_paid_subscription'] ? 'selected' : '' ?>>Inactive / Free User</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Assign Subscription Tier Plan</label>
                                                        <select name="plan_id" class="form-select bg-dark text-white border-secondary">
                                                            <option value="">No Assigned Plan</option>
                                                            <?php foreach ($plans as $p): ?>
                                                                <option value="<?= $p['id'] ?>" <?= $u['subscription_plan_id'] == $p['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['currency'] ?> $<?= $p['price'] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top border-dark">
                                                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-success">Save Subscription</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
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
