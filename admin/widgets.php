<?php
// admin/widgets.php - Custom Widget System
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $content = $_POST['content'] ?? '';
        $placement = $_POST['placement'] ?? 'global';
        $isVisible = isset($_POST['is_visible']) ? 1 : 0;

        if (empty($title)) {
            $msg = 'Widget title is required.';
        } else {
            if ($action === 'create') {
                $stmt = $db->prepare("
                    INSERT INTO custom_widgets (title, content, placement, is_visible, created_by)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $content, $placement, $isVisible, $_SESSION['user_id']]);
                $msg = 'Widget created successfully!';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE custom_widgets SET title = ?, content = ?, placement = ?, is_visible = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $content, $placement, $isVisible, $id]);
                $msg = 'Widget updated successfully!';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM custom_widgets WHERE id = ?");
            $stmt->execute([$id]);
            $msg = 'Widget deleted successfully!';
        }
    }
}

$stmt = $db->query("SELECT * FROM custom_widgets ORDER BY position ASC, created_at DESC");
$widgets = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-puzzle me-2"></i><?= $isRtl ? 'إدارة الودجات' : 'Widget Management' ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addWidgetModal">
        <i class="bi bi-plus-lg me-1"></i><?= $isRtl ? 'إنشاء ودجت' : 'Create Widget' ?>
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php if (empty($widgets)): ?>
        <div class="col-12 text-center py-5 text-muted"><?= $isRtl ? 'لا توجد ودجات بعد.' : 'No widgets yet. Create your first widget above.' ?></div>
    <?php else: ?>
        <?php foreach ($widgets as $w): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card bg-secondary text-white border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= htmlspecialchars($w['title']) ?></h5>
                        <span class="badge bg-<?= $w['is_visible'] ? 'success' : 'danger' ?>">
                            <?= ucfirst($w['placement']) ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="bg-dark p-3 rounded mb-3" style="max-height: 200px; overflow-y: auto;">
                            <?= nl2br(htmlspecialchars($w['content'])) ?>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editWidgetModal<?= $w['id'] ?>">
                                <i class="bi bi-pencil me-1"></i><?= $isRtl ? 'تعديل' : 'Edit' ?>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this widget?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $w['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editWidgetModal<?= $w['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-secondary text-white">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $w['id'] ?>">
                            <div class="modal-header border-bottom border-dark">
                                <h5 class="modal-title"><?= $isRtl ? 'تعديل الودجت' : 'Edit Widget' ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($w['title']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Placement</label>
                                    <select name="placement" class="form-select bg-dark text-white border-secondary">
                                        <option value="global" <?= $w['placement'] == 'global' ? 'selected' : '' ?>><?= $isRtl ? 'عام (كل الصفحات)' : 'Global (All Pages)' ?></option>
                                        <option value="homepage" <?= $w['placement'] == 'homepage' ? 'selected' : '' ?>><?= $isRtl ? 'الصفحة الرئيسية فقط' : 'Homepage Only' ?></option>
                                        <option value="player" <?= $w['placement'] == 'player' ? 'selected' : '' ?>><?= $isRtl ? 'صفحة المشغل' : 'Player Page' ?></option>
                                        <option value="admin" <?= $w['placement'] == 'admin' ? 'selected' : '' ?>><?= $isRtl ? 'لوحة التحكم فقط' : 'Admin Only' ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Content (HTML)</label>
                                    <textarea name="content" class="form-control bg-dark text-white border-secondary" rows="6" required><?= htmlspecialchars($w['content']) ?></textarea>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="wvis_<?= $w['id'] ?>" <?= $w['is_visible'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="wvis_<?= $w['id'] ?>"><?= $isRtl ? 'ظاهر' : 'Visible' ?></label>
                                </div>
                            </div>
                            <div class="modal-footer border-top border-dark">
                                <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $isRtl ? 'إلغاء' : 'Cancel' ?></button>
                                <button type="submit" class="btn btn-primary"><?= $isRtl ? 'حفظ' : 'Save Changes' ?></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Add Widget Modal -->
<div class="modal fade" id="addWidgetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title"><?= $isRtl ? 'إنشاء ودجت' : 'Create Widget' ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Placement</label>
                        <select name="placement" class="form-select bg-dark text-white border-secondary">
                            <option value="global"><?= $isRtl ? 'عام (كل الصفحات)' : 'Global (All Pages)' ?></option>
                            <option value="homepage"><?= $isRtl ? 'الصفحة الرئيسية فقط' : 'Homepage Only' ?></option>
                            <option value="player"><?= $isRtl ? 'صفحة المشغل' : 'Player Page' ?></option>
                            <option value="admin"><?= $isRtl ? 'لوحة التحكم فقط' : 'Admin Only' ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content (HTML)</label>
                        <textarea name="content" class="form-control bg-dark text-white border-secondary" rows="6" required></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_visible" value="1" checked>
                        <label class="form-check-label"><?= $isRtl ? 'ظاهر' : 'Visible' ?></label>
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $isRtl ? 'إلغاء' : 'Cancel' ?></button>
                    <button type="submit" class="btn btn-primary"><?= $isRtl ? 'إنشاء' : 'Create Widget' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
