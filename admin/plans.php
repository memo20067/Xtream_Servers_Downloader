<?php
// admin/plans.php - Admin Subscription Plan Management (CRUD & Visibility)
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';
$error = '';

// Add column defensively if not present
try {
    $db->exec("ALTER TABLE subscription_plans ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1");
} catch (Exception $e) {
    // Column already exists
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_plan') {
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0.00);
        $currency = trim($_POST['currency'] ?? 'USD');
        $features = trim($_POST['features'] ?? '');
        $isVisible = isset($_POST['is_visible']) ? 1 : 0;

        if (!empty($name) && !empty($currency)) {
            $stmt = $db->prepare("INSERT INTO subscription_plans (name, price, currency, features, is_visible) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $price, $currency, $features, $isVisible]);
            $msg = admin_t('msg_plan_added');
        } else {
            $error = 'Plan name and currency are required.';
        }
    } elseif ($action === 'edit_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0.00);
        $currency = trim($_POST['currency'] ?? 'USD');
        $features = trim($_POST['features'] ?? '');
        $isVisible = isset($_POST['is_visible']) ? 1 : 0;

        if ($id > 0 && !empty($name) && !empty($currency)) {
            $stmt = $db->prepare("UPDATE subscription_plans SET name = ?, price = ?, currency = ?, features = ?, is_visible = ? WHERE id = ?");
            $stmt->execute([$name, $price, $currency, $features, $isVisible, $id]);
            $msg = admin_t('msg_plan_updated');
        } else {
            $error = 'Plan name and currency are required.';
        }
    } elseif ($action === 'toggle_visibility') {
        $id = (int)($_POST['id'] ?? 0);
        $isVisible = (int)($_POST['is_visible'] ?? 1);
        if ($id > 0) {
            $stmt = $db->prepare("UPDATE subscription_plans SET is_visible = ? WHERE id = ?");
            $stmt->execute([$isVisible, $id]);
            $msg = admin_t('msg_plan_updated');
        }
    } elseif ($action === 'delete_plan') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM subscription_plans WHERE id = ?");
            $stmt->execute([$id]);
            $msg = admin_t('msg_plan_deleted');
        }
    }
}

$stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
$plans = $stmtPlans->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gem me-2"></i><?= admin_t('plans_title') ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPlanModal">
        <i class="bi bi-plus-lg me-1"></i><?= admin_t('btn_add_plan') ?>
    </button>
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

<div class="row g-4">
    <?php foreach ($plans as $p): ?>
        <?php $pVisible = isset($p['is_visible']) ? (int)$p['is_visible'] : 1; ?>
        <div class="col-md-4">
            <div class="card bg-secondary text-white border-0 shadow-sm h-100">
                <div class="card-header border-bottom border-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($p['name']) ?></h5>
                    <div>
                        <?php if ($pVisible): ?>
                            <span class="badge bg-success me-1"><i class="bi bi-eye-fill me-1"></i>Visible</span>
                        <?php else: ?>
                            <span class="badge bg-danger me-1"><i class="bi bi-eye-slash-fill me-1"></i>Hidden</span>
                        <?php endif; ?>
                        <span class="badge bg-dark">#<?= $p['id'] ?></span>
                    </div>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_plan">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label"><?= admin_t('lbl_plan_name') ?></label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($p['name']) ?>" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label"><?= admin_t('lbl_price') ?></label>
                                <input type="number" step="0.01" name="price" class="form-control bg-dark text-white border-secondary" value="<?= $p['price'] ?>" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label"><?= admin_t('lbl_currency') ?></label>
                                <input type="text" name="currency" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($p['currency']) ?>" required placeholder="USD">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label"><?= admin_t('lbl_features') ?></label>
                            <textarea name="features" class="form-control bg-dark text-white border-secondary" rows="4" required><?= htmlspecialchars($p['features']) ?></textarea>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="vis_check_<?= $p['id'] ?>" <?= $pVisible ? 'checked' : '' ?>>
                            <label class="form-check-label" for="vis_check_<?= $p['id'] ?>"><?= admin_t('lbl_is_visible') ?></label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary flex-fill"><i class="bi bi-save me-1"></i><?= admin_t('btn_save') ?></button>
                    </form>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to delete this subscription plan?');" class="d-inline">
                        <input type="hidden" name="action" value="delete_plan">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                        </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Modal: Add Subscription Plan -->
<div class="modal fade" id="addPlanModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="create_plan">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title"><i class="bi bi-plus-lg me-2"></i><?= admin_t('modal_add_plan') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_plan_name') ?></label>
                        <input type="text" name="name" class="form-control bg-dark text-white border-secondary" placeholder="e.g. Premium VIP" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label"><?= admin_t('lbl_price') ?></label>
                            <input type="number" step="0.01" name="price" class="form-control bg-dark text-white border-secondary" placeholder="19.99" required>
                        </div>
                        <div class="col-5">
                            <label class="form-label"><?= admin_t('lbl_currency') ?></label>
                            <input type="text" name="currency" class="form-control bg-dark text-white border-secondary" value="USD" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_features') ?></label>
                        <textarea name="features" class="form-control bg-dark text-white border-secondary" rows="4" placeholder="Access to Live Channels&#10;Unlimited Movies & Series&#10;4K Ultra HD Quality" required></textarea>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="add_vis_check" checked>
                        <label class="form-check-label" for="add_vis_check"><?= admin_t('lbl_is_visible') ?></label>
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

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
