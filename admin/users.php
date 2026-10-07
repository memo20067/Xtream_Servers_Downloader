<?php
// admin/users.php
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $userId = (int)($_POST['user_id'] ?? 0);

    if ($userId > 0) {
        if ($action === 'edit_user') {
            $username = trim($_POST['username'] ?? '');
            $email = strtolower(trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL)));
            $phone = trim($_POST['phone'] ?? '');
            $role = $_POST['role'] === 'admin' ? 'admin' : 'user';
            $status = (int)($_POST['status'] ?? 0);
            $planId = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;
            $newPass = trim($_POST['new_password'] ?? '');

            if (empty($username) || empty($email) || empty($phone)) {
                $error = 'Username, Email, and Phone Number are required.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Invalid email address format.';
            } else {
                // Check unique username/email excluding current user
                $stmtCheck = $db->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
                $stmtCheck->execute([$username, $email, $userId]);
                if ($stmtCheck->fetch()) {
                    $error = 'Username or email address is already taken by another account.';
                } else {
                    $sql = "UPDATE users SET username = ?, email = ?, phone = ?, role = ?, has_paid_subscription = ?, subscription_plan_id = ? WHERE id = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$username, $email, $phone, $role, $status, $planId, $userId]);

                    if (!empty($newPass)) {
                        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
                        $stmtPass = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmtPass->execute([$hashed, $userId]);
                    }

                    $msg = admin_t('msg_user_updated');
                }
            }
        } elseif ($action === 'toggle_subscription') {
            $status = (int)($_POST['status'] ?? 0);
            $planId = isset($_POST['plan_id']) && $_POST['plan_id'] !== '' ? (int)$_POST['plan_id'] : null;

            $stmt = $db->prepare("UPDATE users SET has_paid_subscription = ?, subscription_plan_id = ? WHERE id = ?");
            $stmt->execute([$status, $planId, $userId]);
            $msg = admin_t('msg_user_updated');
        }
    }
}

$stmtUsers = $db->query("SELECT u.*, p.name as plan_name FROM users u LEFT JOIN subscription_plans p ON u.subscription_plan_id = p.id ORDER BY u.id ASC");
$users = $stmtUsers->fetchAll();

$stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
$plans = $stmtPlans->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people me-2"></i><?= admin_t('users_title') ?></h3>
</div>

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

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th><?= admin_t('tbl_user') ?></th>
                        <th><?= admin_t('tbl_contact') ?></th>
                        <th><?= admin_t('tbl_role') ?></th>
                        <th><?= admin_t('tbl_status') ?></th>
                        <th><?= admin_t('tbl_plan') ?></th>
                        <th>Registered At</th>
                        <th class="text-end"><?= admin_t('tbl_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <?php $avatarSrc = !empty($u['avatar']) ? '../' . htmlspecialchars($u['avatar'], ENT_QUOTES, 'UTF-8') : ''; ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if ($avatarSrc): ?>
                                        <img src="<?= $avatarSrc ?>" alt="" class="rounded-circle" width="36" height="36" style="object-fit:cover;">
                                    <?php else: ?>
                                        <span class="admin-avatar-fallback rounded-circle d-inline-flex justify-content-center align-items-center" aria-hidden="true"><i class="bi bi-person"></i></span>
                                    <?php endif; ?>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars($u['username']) ?></div>
                                        <small class="text-white-50">ID #<?= $u['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div><i class="bi bi-telephone text-info me-1"></i><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></div>
                                <small class="text-white-50"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($u['email']) ?></small>
                            </td>
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
                                <button class="btn btn-sm btn-outline-info me-1" data-bs-toggle="modal" data-bs-target="#editUserModal<?= $u['id'] ?>">
                                    <i class="bi bi-pencil-square me-1"></i><?= admin_t('btn_edit_user') ?>
                                </button>

                                <!-- Edit User Profile Modal -->
                                <div class="modal fade" id="editUserModal<?= $u['id'] ?>" tabindex="-1" aria-hidden="true">
                                    <div class="modal-dialog modal-dialog-centered text-start">
                                        <div class="modal-content bg-secondary text-white">
                                            <form method="POST">
                                                <input type="hidden" name="action" value="edit_user">
                                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                <div class="modal-header border-bottom border-dark">
                                                    <h5 class="modal-title"><i class="bi bi-person-gear me-2"></i><?= admin_t('modal_edit_user') ?> - <?= htmlspecialchars($u['username']) ?></h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('tbl_user') ?></label>
                                                        <input type="text" name="username" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($u['username']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_email') ?></label>
                                                        <input type="email" name="email" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($u['email']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_phone') ?></label>
                                                        <input type="tel" name="phone" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($u['phone']) ?>" required>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_role') ?></label>
                                                        <select name="role" class="form-select bg-dark text-white border-secondary">
                                                            <option value="user" <?= $u['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                                            <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_paid_status') ?></label>
                                                        <select name="status" class="form-select bg-dark text-white border-secondary">
                                                            <option value="1" <?= $u['has_paid_subscription'] ? 'selected' : '' ?>>Active Paid Access</option>
                                                            <option value="0" <?= !$u['has_paid_subscription'] ? 'selected' : '' ?>>Inactive / Free User</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_sub_plan') ?></label>
                                                        <select name="plan_id" class="form-select bg-dark text-white border-secondary">
                                                            <option value="">No Assigned Plan</option>
                                                            <?php foreach ($plans as $p): ?>
                                                                <option value="<?= $p['id'] ?>" <?= $u['subscription_plan_id'] == $p['id'] ? 'selected' : '' ?>>
                                                                    <?= htmlspecialchars($p['name']) ?> (<?= $p['currency'] ?> $<?= $p['price'] ?>)
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label"><?= admin_t('lbl_new_password') ?></label>
                                                        <input type="password" name="new_password" class="form-control bg-dark text-white border-secondary" placeholder="••••••••">
                                                    </div>
                                                </div>
                                                <div class="modal-footer border-top border-dark">
                                                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= admin_t('btn_cancel') ?></button>
                                                    <button type="submit" class="btn btn-primary"><?= admin_t('btn_save') ?></button>
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
