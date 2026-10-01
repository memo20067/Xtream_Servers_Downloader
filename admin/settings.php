<?php
// admin/settings.php - Admin Site & Branding Settings Management
require_once __DIR__ . '/header.php';

$db = getDBConnection();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $appName = trim($_POST['app_name'] ?? '');
    $siteTitle = trim($_POST['site_title'] ?? '');
    $whatsapp = trim($_POST['whatsapp'] ?? '');
    $telegram = trim($_POST['telegram'] ?? '');
    $facebook = trim($_POST['facebook'] ?? '');
    $instagram = trim($_POST['instagram'] ?? '');

    setSetting('app_name', $appName);
    setSetting('site_title', $siteTitle);
    setSetting('whatsapp', $whatsapp);
    setSetting('telegram', $telegram);
    setSetting('facebook', $facebook);
    setSetting('instagram', $instagram);

    $msg = 'Site settings updated successfully!';
}

$appName = getSetting('app_name', 'Xtream IPTV Player');
$siteTitle = getSetting('site_title', 'Xtream Media Hub');
$whatsapp = getSetting('whatsapp', '');
$telegram = getSetting('telegram', '');
$facebook = getSetting('facebook', '');
$instagram = getSetting('instagram', '');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gear me-2"></i>Global Application Settings</h3>
</div>

<?php if ($msg): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="card bg-secondary text-white border-0 shadow-sm style-settings-card" style="max-width: 800px;">
    <div class="card-body p-4">
        <form method="POST">
            <h5 class="text-info mb-3"><i class="bi bi-display me-2"></i>Brand Info</h5>
            <div class="mb-3">
                <label class="form-label">Application Name</label>
                <input type="text" name="app_name" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($appName) ?>" required>
            </div>
            <div class="mb-4">
                <label class="form-label">Site Title</label>
                <input type="text" name="site_title" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($siteTitle) ?>" required>
            </div>

            <h5 class="text-info mb-3"><i class="bi bi-chat-left-dots me-2"></i>Social Contacts & Links</h5>
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp Contact</label>
                    <input type="text" name="whatsapp" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($whatsapp) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-telegram me-1 text-info"></i>Telegram Handle</label>
                    <input type="text" name="telegram" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($telegram) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-facebook me-1 text-primary"></i>Facebook Link</label>
                    <input type="text" name="facebook" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($facebook) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label"><i class="bi bi-instagram me-1 text-warning"></i>Instagram Profile</label>
                    <input type="text" name="instagram" class="form-control bg-dark text-white border-secondary" value="<?= htmlspecialchars($instagram) ?>">
                </div>
            </div>

            <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Settings</button>
        </form>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
