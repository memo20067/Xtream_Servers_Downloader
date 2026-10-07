<?php
// page.php - Custom Page Renderer
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$slug = $_GET['slug'] ?? '';
if (empty($slug)) {
    header("Location: index.php");
    exit;
}

$db = getDBConnection();
$stmt = $db->prepare("SELECT * FROM custom_pages WHERE slug = ? AND is_visible = 1 LIMIT 1");
$stmt->execute([$slug]);
$page = $stmt->fetch();

if (!$page) {
    header("HTTP/1.0 404 Not Found");
    echo "Page not found.";
    exit;
}

$currentUser = getCurrentUser();
$lang = $_SESSION['lang'] ?? 'ar';
$isRtl = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page['title']) ?> - <?= htmlspecialchars(getSetting('app_name', 'Xtream IPTV Player')) ?></title>
    <?php if ($isRtl): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i><?= $isRtl ? 'العودة للقائمة الرئيسية' : 'Back to Dashboard' ?>
        </a>
        <span class="navbar-text text-white fw-bold"><?= htmlspecialchars($page['title']) ?></span>
    </div>
</nav>

<div class="container-fluid px-4 py-4">
    <div class="row">
        <div class="col-12">
            <div class="glass-panel p-4">
                <?= $page['content'] ?>
            </div>
        </div>
    </div>
</div>

<?php if (!empty(getSetting('whatsapp', '')) || !empty(getSetting('telegram', '')) || !empty(getSetting('facebook', '')) || !empty(getSetting('instagram', ''))): ?>
<footer class="fixed-social-footer text-center">
    <div class="container d-flex justify-content-center align-items-center gap-3">
        <?php
        $wa = getSetting('whatsapp', '');
        $tg = getSetting('telegram', '');
        $fb = getSetting('facebook', '');
        $ig = getSetting('instagram', '');
        $appName = getSetting('app_name', 'Xtream IPTV Player');
        ?>
        <small class="fw-bold me-2 text-white"><?= htmlspecialchars($appName) ?> Support:</small>
        <?php if (!empty($wa)): ?>
            <a href="<?= htmlspecialchars(strpos($wa, 'http') === 0 ? $wa : 'https://wa.me/' . preg_replace('/[^0-9+]/', '', $wa)) ?>" target="_blank" class="btn btn-outline-success btn-sm"><i class="bi bi-whatsapp me-1"></i>WhatsApp</a>
        <?php endif; ?>
        <?php if (!empty($tg)): ?>
            <a href="<?= htmlspecialchars(strpos($tg, 'http') === 0 ? $tg : 'https://t.me/' . ltrim($tg, '@')) ?>" target="_blank" class="btn btn-outline-info btn-sm"><i class="bi bi-telegram me-1"></i>Telegram</a>
        <?php endif; ?>
        <?php if (!empty($fb)): ?>
            <a href="<?= htmlspecialchars(strpos($fb, 'http') === 0 ? $fb : 'https://facebook.com/' . $fb) ?>" target="_blank" class="btn btn-outline-primary btn-sm"><i class="bi bi-facebook me-1"></i>Facebook</a>
        <?php endif; ?>
        <?php if (!empty($ig)): ?>
            <a href="<?= htmlspecialchars(strpos($ig, 'http') === 0 ? $ig : 'https://instagram.com/' . ltrim($ig, '@')) ?>" target="_blank" class="btn btn-outline-warning btn-sm"><i class="bi bi-instagram me-1"></i>Instagram</a>
        <?php endif; ?>
    </div>
</footer>
<?php endif; ?>

<?php
ob_start();
require_once __DIR__ . '/includes/news_ticker.php';
render_news_ticker();
$tickerHtml = ob_get_clean();
echo $tickerHtml;
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var hasTicker = <?= !empty($tickerHtml) ? 'true' : 'false' ?>;
        var hasFooter = <?= (!empty(getSetting('whatsapp', '')) || !empty(getSetting('telegram', '')) || !empty(getSetting('facebook', '')) || !empty(getSetting('instagram', ''))) ? 'true' : 'false' ?>;
        if (hasTicker) document.body.classList.add('has-ticker');
        if (hasFooter) document.body.classList.add('has-footer');
    });
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
