<?php
// admin/payments.php - Payment Receipt Verification & Auto-Activation
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $receiptId = (int)($_POST['receipt_id'] ?? 0);

    if ($receiptId > 0) {
        if ($action === 'verify') {
            $planId = (int)($_POST['plan_id'] ?? 0);
            $userId = (int)($_POST['user_id'] ?? 0);

            // Auto-activate plan
            $stmt = $db->prepare("
                UPDATE payment_receipts
                SET status = 'verified', verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $receiptId]);

            // Activate user subscription
            if ($planId > 0 && $userId > 0) {
                $stmt = $db->prepare("
                    UPDATE users
                    SET has_paid_subscription = 1, subscription_plan_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([$planId, $userId]);
            }

            $msg = 'Payment verified and subscription activated successfully!';

            require_once __DIR__ . '/../includes/logger.php';
            Logger::logPayment('Payment verified by admin', 'INFO', $userId, [
                'receipt_id' => $receiptId,
                'plan_id' => $planId
            ]);
        } elseif ($action === 'reject') {
            $stmt = $db->prepare("
                UPDATE payment_receipts
                SET status = 'rejected', verified_by = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $receiptId]);
            $msg = 'Payment receipt rejected.';

            require_once __DIR__ . '/../includes/logger.php';
            Logger::logPayment('Payment rejected by admin', 'WARNING', null, ['receipt_id' => $receiptId]);
        }
    }
}

// Fetch pending payments
$stmt = $db->query("
    SELECT pr.*, u.username, u.email, u.phone, p.name as plan_name, p.price, p.currency
    FROM payment_receipts pr
    JOIN users u ON pr.user_id = u.id
    JOIN subscription_plans p ON pr.plan_id = p.id
    ORDER BY pr.created_at DESC
");
$payments = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-credit-card me-2"></i><?= $isRtl ? 'إدارة الدفعات والاشتراكات' : 'Payment & Subscription Management' ?></h3>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-header border-bottom border-dark">
        <h5 class="mb-0"><i class="bi bi-receipt me-2"></i><?= $isRtl ? 'سجل الدفعات' : 'Payment Receipts' ?></h5>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?= $isRtl ? 'المستخدم' : 'User' ?></th>
                        <th><?= $isRtl ? 'الباقة' : 'Plan' ?></th>
                        <th><?= $isRtl ? 'طريقة الدفع' : 'Method' ?></th>
                        <th><?= $isRtl ? 'الإيصال' : 'Receipt' ?></th>
                        <th><?= $isRtl ? 'الحالة' : 'Status' ?></th>
                        <th><?= $isRtl ? 'التاريخ' : 'Date' ?></th>
                        <th class="text-end"><?= $isRtl ? 'إجراءات' : 'Actions' ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                        <tr><td colspan="8" class="text-center py-4 text-muted"><?= $isRtl ? 'لا توجد دفعات بعد' : 'No payment receipts yet.' ?></td></tr>
                    <?php else: ?>
                        <?php foreach ($payments as $p): ?>
                            <tr>
                                <td>#<?= $p['id'] ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($p['username']) ?></div>
                                    <small class="text-white-50"><?= htmlspecialchars($p['email']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($p['plan_name']) ?> (<?= $p['currency'] ?> $<?= number_format($p['price'], 2) ?>)</td>
                                <td><?= htmlspecialchars($p['payment_method']) ?></td>
                                <td>
                                    <a href="../<?= htmlspecialchars($p['receipt_path']) ?>" target="_blank" class="btn btn-sm btn-outline-info">
                                        <i class="bi bi-image me-1"></i><?= $isRtl ? 'عرض' : 'View' ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($p['status'] === 'verified'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?= $isRtl ? 'مُفعّل' : 'Verified' ?></span>
                                    <?php elseif ($p['status'] === 'rejected'): ?>
                                        <span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i><?= $isRtl ? 'مرفوض' : 'Rejected' ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="bi bi-clock me-1"></i><?= $isRtl ? 'قيد المراجعة' : 'Pending' ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($p['created_at']) ?></td>
                                <td class="text-end">
                                    <?php if ($p['status'] === 'pending'): ?>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="verify">
                                            <input type="hidden" name="receipt_id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="plan_id" value="<?= $p['plan_id'] ?>">
                                            <input type="hidden" name="user_id" value="<?= $p['user_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success me-1" onclick="return confirm('Verify and activate subscription?')">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                        </form>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Reject this payment?')">
                                            <input type="hidden" name="action" value="reject">
                                            <input type="hidden" name="receipt_id" value="<?= $p['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small"><?= $isRtl ? 'مكتمل' : 'Completed' ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
