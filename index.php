<?php
// index.php - Source-scoped IPTV catalog
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();
$accessibleServers = getAccessibleServers();
$db = getDBConnection();
$stmtM3u = $db->prepare('SELECT id, name, url, user_id FROM m3u_playlists WHERE user_id = ? OR user_id IS NULL ORDER BY name ASC');
$stmtM3u->execute([$_SESSION['user_id']]);
$accessibleM3u = $stmtM3u->fetchAll();

$serverError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_personal_server') {
    $name = trim($_POST['name'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($name !== '' && $host !== '' && $username !== '' && $password !== '') {
        $stmt = $db->prepare('INSERT INTO servers (user_id, name, host, username, password) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $name, $host, $username, $password]);
        $returnQuery = http_build_query(array_intersect_key($_GET, array_flip(['source', 'tab', 'q', 'category'])));
        header('Location: index.php' . ($returnQuery ? '?' . $returnQuery : ''));
        exit;
    }
    $serverError = 'All server fields are required.';
}

$sourceOptions = [];
foreach ($accessibleServers as $server) {
    $sourceOptions['xtream_' . $server['id']] = $server['name'];
}
foreach ($accessibleM3u as $playlist) {
    $sourceOptions['m3u_' . $playlist['id']] = $playlist['name'];
}
$requestedSource = isset($_GET['source']) && is_string($_GET['source']) ? $_GET['source'] : '';
$selectedSource = isset($sourceOptions[$requestedSource]) ? $requestedSource : (string)array_key_first($sourceOptions);
$initialQuery = isset($_GET['q']) && is_scalar($_GET['q']) ? (string)$_GET['q'] : '';
$initialCategory = isset($_GET['category']) && is_scalar($_GET['category']) ? (string)$_GET['category'] : '';
$initialTab = isset($_GET['tab']) && is_string($_GET['tab']) ? $_GET['tab'] : 'live';
$lang = $_COOKIE['app_lang'] ?? ($_SESSION['lang'] ?? 'ar');
$lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
$isRtl = $lang === 'ar';
$userAvatar = !empty($currentUser['avatar']) ? htmlspecialchars($currentUser['avatar'], ENT_QUOTES, 'UTF-8') : null;
$appName = getSetting('app_name', 'Xtream IPTV Player');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="app_title"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= $isRtl ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="catalog-page" data-paid="<?= hasPaidSubscription() ? '1' : '0' ?>">
<nav class="catalog-topbar navbar navbar-expand-lg navbar-dark px-3 px-lg-5">
    <div class="container-fluid gap-3">
        <a class="navbar-brand d-inline-flex align-items-center gap-2 fw-bold text-white" href="index.php" aria-label="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
            <span class="auth-brand-mark">X</span>
            <span><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
        </a>
        <div class="d-flex align-items-center gap-2 ms-auto">
            <label for="server-select" class="visually-hidden" data-i18n="select_server">Select source</label>
            <select id="server-select" class="form-select form-select-sm" aria-label="Select IPTV source" <?= empty($sourceOptions) ? 'disabled' : '' ?>>
                <?php if (empty($sourceOptions)): ?><option value=""><?= $isRtl ? 'لا يوجد مصدر بعد' : 'No sources yet' ?></option><?php endif; ?>
                <?php foreach ($sourceOptions as $sourceKey => $sourceName): ?>
                    <option value="<?= htmlspecialchars($sourceKey, ENT_QUOTES, 'UTF-8') ?>" <?= $sourceKey === $selectedSource ? 'selected' : '' ?>><?= htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8') ?> · <?= strpos($sourceKey, 'm3u_') === 0 ? 'M3U' : 'Xtream' ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-outline-secondary btn-sm text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addPersonalServerModal" title="Add Xtream source">
                <i class="bi bi-plus-lg" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1" data-i18n="add_server">Add source</span>
            </button>
            <button class="btn btn-outline-secondary btn-sm text-nowrap" type="button" data-bs-toggle="modal" data-bs-target="#addM3uModal" title="Add M3U source">
                <i class="bi bi-link-45deg" aria-hidden="true"></i><span class="d-none d-sm-inline ms-1">M3U</span>
            </button>
            <div class="btn-group btn-group-sm ms-1" role="group" aria-label="Language">
                <button type="button" class="btn btn-outline-secondary lang-btn" data-lang="en" onclick="setLanguage('en', true)">EN</button>
                <button type="button" class="btn btn-outline-secondary lang-btn" data-lang="ar" onclick="setLanguage('ar', true)">عربي</button>
            </div>
            <div class="dropdown">
                <button class="btn btn-outline-secondary btn-sm dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($userAvatar): ?><img src="<?= $userAvatar ?>" alt="" class="rounded-circle" width="24" height="24" style="object-fit:cover"><?php else: ?><i class="bi bi-person-circle" aria-hidden="true"></i><?php endif; ?>
                    <span class="d-none d-md-inline"><?= htmlspecialchars($currentUser['username'], ENT_QUOTES, 'UTF-8') ?></span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2" aria-hidden="true"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="subscriptions.php"><i class="bi bi-gem me-2" aria-hidden="true"></i>Subscription Plans</a></li>
                    <?php if (isAdmin()): ?><li><hr class="dropdown-divider"><a class="dropdown-item" href="admin/index.php"><i class="bi bi-speedometer2 me-2" aria-hidden="true"></i><span data-i18n="admin_panel">Admin Panel</span></a></li><?php endif; ?>
                    <li><hr class="dropdown-divider"><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i><span data-i18n="logout">Logout</span></a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<main class="catalog-main">
    <div class="catalog-intro">
        <div>
            <h1 class="fw-bold mb-2" data-i18n="catalog_title">Search your library</h1>
            <p class="text-secondary mb-0" data-i18n="catalog_subtitle">Find a channel or title in the selected source.</p>
        </div>
        <div class="catalog-scope" aria-live="polite">
            <i class="bi bi-broadcast-pin text-primary" aria-hidden="true"></i>
            <span data-i18n="search_scope">Search scope:</span>
            <strong id="search-scope-name"><?= htmlspecialchars($sourceOptions[$selectedSource] ?? 'No source', ENT_QUOTES, 'UTF-8') ?></strong>
        </div>
    </div>

    <section class="catalog-search-area" aria-label="Source and search">
        <div class="d-flex flex-wrap align-items-end justify-content-between gap-4 mb-4">
            <div>
                <div class="small fw-semibold text-light mb-2" data-i18n="current_source">Current source</div>
                <div class="catalog-source-tabs" role="group" aria-label="Source type">
                    <button class="btn active" type="button" data-source-type="xtream" aria-pressed="true"><i class="bi bi-hdd-network me-1" aria-hidden="true"></i>Xtream</button>
                    <button class="btn" type="button" data-source-type="m3u" aria-pressed="false"><i class="bi bi-link-45deg me-1" aria-hidden="true"></i>M3U</button>
                </div>
            </div>
            <div class="small text-muted" data-i18n="source_scope_note">Results change when you switch source.</div>
        </div>
        <label for="search-input" class="form-label fw-semibold small mb-2" data-i18n="search_in_source">Search in selected source</label>
        <div class="d-flex flex-column flex-sm-row gap-3">
            <div class="input-group catalog-search-field flex-grow-1">
                <span class="input-group-text" aria-hidden="true"><i class="bi bi-search"></i></span>
                <input id="search-input" type="search" class="form-control" placeholder="Search channels or titles..." data-i18n="search_placeholder" autocomplete="off">
                <button id="clear-search" class="btn btn-outline-secondary" type="button" aria-label="Clear search"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
            </div>
            <select id="category-select" class="form-select" aria-label="Filter by category" style="max-width:280px">
                <option value="" data-i18n="all_categories">All categories</option>
            </select>
        </div>
        <p class="small text-muted mt-2 mb-0" data-i18n="source_search_note">Search results are limited to the selected source and content type.</p>
    </section>

    <nav class="catalog-tabs nav" id="horizontal-nav-tabs" aria-label="Content type">
        <a href="#" class="nav-link tab-link active" data-tab="live"><i class="bi bi-broadcast me-2" aria-hidden="true"></i><span data-i18n="live_tv">Live TV</span></a>
        <a href="#" class="nav-link tab-link tab-xtream-only" data-tab="movies"><i class="bi bi-film me-2" aria-hidden="true"></i><span data-i18n="movies">Movies</span></a>
        <a href="#" class="nav-link tab-link tab-xtream-only" data-tab="series"><i class="bi bi-collection-play me-2" aria-hidden="true"></i><span data-i18n="series">Series</span></a>
    </nav>

    <section id="series-detail-view" class="d-none mt-4" aria-live="polite">
        <div class="d-flex align-items-center gap-3 mb-4">
            <button id="btn-back-series" class="btn btn-outline-secondary" type="button"><i class="bi bi-arrow-right me-2" aria-hidden="true"></i><span data-i18n="back_to_series">Back to series</span></button>
            <h2 id="series-title" class="h4 fw-bold mb-0"></h2>
        </div>
        <div id="series-seasons-container" class="row g-4"></div>
    </section>

    <section class="mt-4" aria-labelledby="results-title">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-3">
            <div>
                <h2 id="results-title" class="h5 fw-bold mb-1" data-i18n="catalog_results">Results</h2>
                <p id="results-context" class="small text-muted mb-0" aria-live="polite"></p>
            </div>
            <span id="results-count" class="small text-muted" aria-live="polite"></span>
        </div>
        <div class="catalog-results-header" aria-hidden="true"><span data-i18n="artwork">Artwork</span><span data-i18n="content_item">Content</span><span class="text-end" data-i18n="action">Action</span></div>
        <div id="content-grid" class="catalog-results" aria-live="polite" aria-busy="true">
            <div class="catalog-state"><span class="catalog-state-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span><span data-i18n="loading">Loading content...</span></div>
        </div>
        <div id="results-sentinel" aria-hidden="true"></div>
    </section>
</main>

<div class="modal fade" id="downloadResolutionModal" tabindex="-1" aria-labelledby="downloadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title h5 fw-bold mb-1" id="downloadModalTitle"><i class="bi bi-download me-2 text-primary" aria-hidden="true"></i><span data-i18n="download_file">Download file</span></h2><p class="small text-secondary mb-0" id="downloadItemName"></p></div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex align-items-start gap-3 p-3 border border-secondary rounded">
                    <i class="bi bi-info-circle text-secondary mt-1" aria-hidden="true"></i>
                    <p class="small text-secondary mb-0" data-i18n="source_quality_note">The file downloads in the quality provided by your source. This app does not transcode it into another resolution.</p>
                </div>
                <div id="download-error" class="alert alert-danger d-none mt-3" role="alert"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-i18n="cancel">Cancel</button>
                <button type="button" class="btn btn-primary download-source-option"><i class="bi bi-download me-2" aria-hidden="true"></i><span data-i18n="download_source_quality">Download source quality</span></button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addM3uModal" tabindex="-1" aria-labelledby="addM3uTitle" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content">
        <form id="addM3uForm">
            <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="addM3uTitle"><i class="bi bi-link-45deg me-2 text-primary" aria-hidden="true"></i>Add M3U playlist</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <div id="m3uAlertContainer" aria-live="polite"></div>
                <div class="mb-3"><label class="form-label" for="m3uNameInput">Playlist name</label><input type="text" id="m3uNameInput" class="form-control" placeholder="My sports playlist" required></div>
                <div class="mb-3"><label class="form-label" for="m3uUrlInput">M3U / M3U8 URL</label><input type="url" id="m3uUrlInput" class="form-control" placeholder="https://example.com/playlist.m3u8" required><div class="form-text">Direct HTTP/HTTPS URL to the playlist.</div></div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-primary">Save playlist</button></div>
        </form>
    </div></div>
</div>

<div class="modal fade" id="addPersonalServerModal" tabindex="-1" aria-labelledby="addServerTitle" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form method="POST">
        <input type="hidden" name="action" value="add_personal_server">
        <div class="modal-header"><h2 class="modal-title h5 fw-bold" id="addServerTitle" data-i18n="add_personal_server">Add personal Xtream server</h2><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <?php if ($serverError): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($serverError, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
            <div class="mb-3"><label class="form-label" for="personal-server-name" data-i18n="server_name">Server name</label><input id="personal-server-name" type="text" name="name" class="form-control" required></div>
            <div class="mb-3"><label class="form-label" for="personal-server-host" data-i18n="host_url">Host / URL</label><input id="personal-server-host" type="url" name="host" class="form-control" placeholder="https://server.example" required></div>
            <div class="mb-3"><label class="form-label" for="personal-server-username" data-i18n="username">Username</label><input id="personal-server-username" type="text" name="username" class="form-control" required></div>
            <div class="mb-3"><label class="form-label" for="personal-server-password" data-i18n="password">Password</label><input id="personal-server-password" type="password" name="password" class="form-control" required></div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" data-i18n="close">Close</button><button type="submit" class="btn btn-primary" data-i18n="save">Save server</button></div>
    </form></div></div>
</div>

<?php
$wa = getSetting('whatsapp', '');
$tg = getSetting('telegram', '');
$fb = getSetting('facebook', '');
$ig = getSetting('instagram', '');
$hasSocialFooter = !empty($wa) || !empty($tg) || !empty($fb) || !empty($ig);
if ($hasSocialFooter):
?>
<footer class="fixed-social-footer"><div class="container d-flex flex-wrap justify-content-center align-items-center gap-2">
    <small class="fw-bold me-2"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?> Support:</small>
    <?php if ($wa): ?><a href="<?= htmlspecialchars(strpos($wa, 'http') === 0 ? $wa : 'https://wa.me/' . preg_replace('/[^0-9+]/', '', $wa), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-whatsapp me-1" aria-hidden="true"></i>WhatsApp</a><?php endif; ?>
    <?php if ($tg): ?><a href="<?= htmlspecialchars(strpos($tg, 'http') === 0 ? $tg : 'https://t.me/' . ltrim($tg, '@'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-telegram me-1" aria-hidden="true"></i>Telegram</a><?php endif; ?>
    <?php if ($fb): ?><a href="<?= htmlspecialchars(strpos($fb, 'http') === 0 ? $fb : 'https://facebook.com/' . $fb, ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-facebook me-1" aria-hidden="true"></i>Facebook</a><?php endif; ?>
    <?php if ($ig): ?><a href="<?= htmlspecialchars(strpos($ig, 'http') === 0 ? $ig : 'https://instagram.com/' . ltrim($ig, '@'), ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener" class="btn btn-outline-secondary btn-sm"><i class="bi bi-instagram me-1" aria-hidden="true"></i>Instagram</a><?php endif; ?>
</div></footer>
<?php endif; ?>

<?php
ob_start();
require_once __DIR__ . '/includes/news_ticker.php';
render_news_ticker();
echo ob_get_clean();
?>
<script>
window.CATALOG_CONFIG = <?= json_encode(['source' => $selectedSource, 'hasPaid' => hasPaidSubscription(), 'query' => $initialQuery, 'category' => $initialCategory, 'tab' => $initialTab], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/i18n.js"></script>
<script src="assets/js/app.js"></script>
<script src="assets/js/download.js"></script>
</body>
</html>
