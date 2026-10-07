<?php
// admin/pages.php - Custom Page Creator
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'edit') {
        $title = trim($_POST['title'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $content = $_POST['content'] ?? '';
        $placement = $_POST['placement'] ?? 'standalone';
        $isVisible = isset($_POST['is_visible']) ? 1 : 0;

        if (empty($title) || empty($slug)) {
            $msg = 'Title and slug are required.';
        } else {
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($slug));

            if ($action === 'create') {
                $stmt = $db->prepare("
                    INSERT INTO custom_pages (title, slug, content, placement, is_visible, created_by)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $slug, $content, $placement, $isVisible, $_SESSION['user_id']]);
                $msg = 'Custom page created successfully!';
            } else {
                $id = (int)($_POST['id'] ?? 0);
                $stmt = $db->prepare("
                    UPDATE custom_pages SET title = ?, slug = ?, content = ?, placement = ?, is_visible = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $slug, $content, $placement, $isVisible, $id]);
                $msg = 'Custom page updated successfully!';
            }
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM custom_pages WHERE id = ?");
            $stmt->execute([$id]);
            $msg = 'Custom page deleted successfully!';
        }
    }
}

$stmt = $db->query("SELECT * FROM custom_pages ORDER BY position ASC, created_at DESC");
$pages = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-file-earmark-code me-2"></i><?= $isRtl ? 'الصفحات المخصصة' : 'Custom Pages' ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPageModal">
        <i class="bi bi-plus-lg me-1"></i><?= $isRtl ? 'إنشاء صفحة' : 'Create Page' ?>
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row g-4">
    <?php if (empty($pages)): ?>
        <div class="col-12 text-center py-5 text-muted"><?= $isRtl ? 'لا توجد صفحات مخصصة بعد.' : 'No custom pages yet. Create your first custom page above.' ?></div>
    <?php else: ?>
        <?php foreach ($pages as $page): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card bg-secondary text-white border-0 shadow-sm h-100">
                    <div class="card-header border-bottom border-dark d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?= htmlspecialchars($page['title']) ?></h5>
                        <span class="badge bg-<?= $page['is_visible'] ? 'success' : 'danger' ?>">
                            <?= $page['is_visible'] ? ($isRtl ? 'ظاهر' : 'Visible') : ($isRtl ? 'مخفي' : 'Hidden') ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <p class="text-white-50 small mb-1"><strong>Slug:</strong> <?= htmlspecialchars($page['slug']) ?></p>
                        <p class="text-white-50 small mb-1"><strong>Placement:</strong> <?= ucfirst($page['placement']) ?></p>
                        <div class="d-flex gap-2 mt-3">
                            <a href="page.php?slug=<?= urlencode($page['slug']) ?>" class="btn btn-sm btn-outline-info" target="_blank">
                                <i class="bi bi-eye me-1"></i><?= $isRtl ? 'عرض' : 'View' ?>
                            </a>
                            <button class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editPageModal<?= $page['id'] ?>">
                                <i class="bi bi-pencil me-1"></i><?= $isRtl ? 'تعديل' : 'Edit' ?>
                            </button>
                            <form method="POST" onsubmit="return confirm('Delete this page?')">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= $page['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editPageModal<?= $page['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content bg-secondary text-white">
                        <form method="POST">
                            <input type="hidden" name="action" value="edit">
                            <input type="hidden" name="id" value="<?= $page['id'] ?>">
                            <div class="modal-header border-bottom border-dark">
                                <h5 class="modal-title"><?= $isRtl ? 'تعديل الصفحة' : 'Edit Custom Page' ?></h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($page['title']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Slug (URL)</label>
                                    <input type="text" name="slug" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($page['slug']) ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Placement</label>
                                    <select name="placement" class="form-select bg-dark text-white border-secondary">
                                        <option value="standalone" <?= $page['placement'] == 'standalone' ? 'selected' : '' ?>><?= $isRtl ? 'صفحة مستقلة' : 'Standalone Page' ?></option>
                                        <option value="tab" <?= $page['placement'] == 'tab' ? 'selected' : '' ?>><?= $isRtl ? 'تبويب في القائمة' : 'Navigation Tab' ?></option>
                                        <option value="button" <?= $page['placement'] == 'button' ? 'selected' : '' ?>><?= $isRtl ? 'زر في الصفحة' : 'Page Button' ?></option>
                                        <option value="homepage" <?= $page['placement'] == 'homepage' ? 'selected' : '' ?>><?= $isRtl ? 'الصفحة الرئيسية' : 'Homepage' ?></option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Content (HTML/PHP)</label>
                                    <textarea name="content" class="form-control bg-dark text-white border-secondary" rows="8" required><?= htmlspecialchars($page['content']) ?></textarea>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_visible" value="1" id="vis_<?= $page['id'] ?>" <?= $page['is_visible'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="vis_<?= $page['id'] ?>"><?= $isRtl ? 'ظاهر للمستخدمين' : 'Visible to users' ?></label>
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

<!-- Add Page Modal -->
<div class="modal fade" id="addPageModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="create">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title"><?= $isRtl ? 'إنشاء صفحة مخصصة' : 'Create Custom Page' ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" name="title" class="form-control bg-dark text-white border-secondary" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slug (URL)</label>
                        <input type="text" name="slug" class="form-control bg-dark text-white border-secondary" placeholder="my-custom-page" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Placement</label>
                        <select name="placement" class="form-select bg-dark text-white border-secondary">
                            <option value="standalone"><?= $isRtl ? 'صفحة مستقلة' : 'Standalone Page' ?></option>
                            <option value="tab"><?= $isRtl ? 'تبويب في القائمة' : 'Navigation Tab' ?></option>
                            <option value="button"><?= $isRtl ? 'زر في الصفحة' : 'Page Button' ?></option>
                            <option value="homepage"><?= $isRtl ? 'الصفحة الرئيسية' : 'Homepage' ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Content (HTML/PHP)</label>
                        <textarea name="content" class="form-control bg-dark text-white border-secondary" rows="8" required></textarea>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_visible" value="1" checked>
                        <label class="form-check-label"><?= $isRtl ? 'ظاهر للمستخدمين' : 'Visible to users' ?></label>
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= $isRtl ? 'إلغاء' : 'Cancel' ?></button>
                    <button type="submit" class="btn btn-primary"><?= $isRtl ? 'إنشاء' : 'Create Page' ?></button>
                </div>
            </form>
        </div>
    </div>
</div>
