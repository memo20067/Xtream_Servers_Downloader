<?php
// admin/plans.php - Admin Subscription Plan Management
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'edit_plan') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float)($_POST['price'] ?? 0.00);
        $currency = trim($_POST['currency'] ?? 'USD');
        $features = trim($_POST['features'] ?? '');

        if ($id > 0 && !empty($name) && !empty($currency)) {
            $stmt = $db->prepare("UPDATE subscription_plans SET name = ?, price = ?, currency = ?, features = ? WHERE id = ?");
            $stmt->execute([$name, $price, $currency, $features, $id]);
            $msg = 'Subscription plan updated successfully!';
        } else {
            $error = 'Plan name and currency are required.';
        }
    }
}

$stmtPlans = $db->query("SELECT * FROM subscription_plans ORDER BY id ASC");
$plans = $stmtPlans->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gem me-2"></i>Subscription Plans Management</h3>
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
        <div class="col-md-4">
            <div class="card bg-secondary text-white border-0 shadow-sm h-100">
                <div class="card-header border-bottom border-dark d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary fw-bold"><?= htmlspecialchars($p['name']) ?></h5>
                    <span class="badge bg-dark">#<?= $p['id'] ?></span>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="edit_plan">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">

                        <div class="mb-3">
                            <label class="form-label">Plan Name</label>
                            <input type="text" name="name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($p['name']) ?>" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-7">
                                <label class="form-label">Price</label>
                                <input type="number" step="0.01" name="price" class="form-control bg-dark text-white border-secondary" value="<?= $p['price'] ?>" required>
                            </div>
                            <div class="col-5">
                                <label class="form-label">Currency</label>
                                <input type="text" name="currency" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($p['currency']) ?>" required placeholder="USD">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Features List (1 per line)</label>
                            <textarea name="features" class="form-control bg-dark text-white border-secondary" rows="5" required><?= htmlspecialchars($p['features']) ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-1"></i>Save Plan Changes</button>
                    </form>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
