<?php
// admin/servers.php
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';
$error = '';

// Handle actions: add, edit, delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $host = trim($_POST['host'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $m3u_url = trim($_POST['m3u_url'] ?? '');

        if (!empty($name) && !empty($host) && !empty($username) && !empty($password)) {
            $stmt = $db->prepare("INSERT INTO servers (user_id, name, host, username, password, m3u_url) VALUES (NULL, ?, ?, ?, ?, ?)");
            $stmt->execute([$name, $host, $username, $password, $m3u_url]);
            $msg = admin_t('msg_server_added');
        } else {
            $error = 'Server Name, Host, Username and Password are required.';
        }
    } elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $host = trim($_POST['host'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if ($id > 0 && !empty($name) && !empty($host) && !empty($username) && !empty($password)) {
            $stmt = $db->prepare("UPDATE servers SET name = ?, host = ?, username = ?, password = ? WHERE id = ? AND user_id IS NULL");
            $stmt->execute([$name, $host, $username, $password, $id]);
            $msg = 'Global Xtream server updated successfully!';
        } else {
            $error = 'All fields are required.';
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $db->prepare("DELETE FROM servers WHERE id = ? AND user_id IS NULL");
            $stmt->execute([$id]);
            $msg = 'Server deleted successfully.';
        }
    }
}

$stmtServers = $db->query("SELECT * FROM servers WHERE user_id IS NULL ORDER BY id ASC");
$servers = $stmtServers->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-hdd-network me-2"></i><?= admin_t('servers_title') ?></h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServerModal">
        <i class="bi bi-plus-circle me-1"></i><?= admin_t('btn_add_server') ?>
    </button>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($error) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
                <thead>
                    <tr>
                        <th><?= admin_t('tbl_id') ?></th>
                        <th><?= admin_t('tbl_name') ?></th>
                        <th><?= admin_t('tbl_host') ?></th>
                        <th><?= admin_t('tbl_username') ?></th>
                        <th><?= admin_t('tbl_m3u_url') ?></th>
                        <th>Created At</th>
                        <th class="text-end"><?= admin_t('tbl_actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servers)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">No global servers added yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($servers as $s): ?>
                            <tr>
                                <td><span class="badge bg-primary">#<?= $s['id'] ?></span></td>
                                <td><?= htmlspecialchars($s['name']) ?></td>
                                <td><code><?= htmlspecialchars($s['host']) ?></code></td>
                                <td><?= htmlspecialchars($s['username']) ?></td>
                                <td><code><?= !empty($s['m3u_url']) ? htmlspecialchars($s['m3u_url']) : '<span class="text-muted">-</span>' ?></code></td>
                                <td><?= htmlspecialchars($s['created_at']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-warning me-1" 
                                            onclick="editServer(<?= htmlspecialchars(json_encode($s)) ?>)">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <form method="POST" style="display:inline-block;" onsubmit="return confirm('Are you sure you want to delete this server?');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-trash"></i>
                                        </button>
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

<!-- Add Server Modal -->
<div class="modal fade" id="addServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i><?= admin_t('modal_add_server') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_server_name') ?></label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. Premium GOTV" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_host_url') ?></label>
                        <input type="text" name="host" class="form-control" placeholder="e.g. http://gotv.ghost.co:80" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_username') ?></label>
                        <input type="text" name="username" class="form-control" placeholder="e.g. user123" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_password') ?></label>
                        <input type="text" name="password" class="form-control" placeholder="e.g. pass123" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_m3u_url') ?></label>
                        <input type="url" name="m3u_url" class="form-control" placeholder="http://example.com/live.m3u8">
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

<!-- Edit Server Modal -->
<div class="modal fade" id="editServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                <div class="modal-header border-bottom border-dark">
                    <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><?= admin_t('modal_add_server') ?></h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_server_name') ?></label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_host_url') ?></label>
                        <input type="text" name="host" id="edit_host" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_username') ?></label>
                        <input type="text" name="username" id="edit_username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_password') ?></label>
                        <input type="text" name="password" id="edit_password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= admin_t('lbl_m3u_url') ?></label>
                        <input type="url" name="m3u_url" id="edit_m3u_url" class="form-control">
                    </div>
                </div>
                <div class="modal-footer border-top border-dark">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal"><?= admin_t('btn_cancel') ?></button>
                    <button type="submit" class="btn btn-warning"><?= admin_t('btn_save') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function editServer(server) {
    document.getElementById('edit_id').value = server.id;
    document.getElementById('edit_name').value = server.name;
    document.getElementById('edit_host').value = server.host;
    document.getElementById('edit_username').value = server.username;
    document.getElementById('edit_password').value = server.password;
    document.getElementById('edit_m3u_url').value = server.m3u_url || '';
    
    var editModal = new bootstrap.Modal(document.getElementById('editServerModal'));
    editModal.show();
}
</script>
</body>
</html>
