<?php
// Shared RTL-aware admin shell.
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_i18n.php';

if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit;
}

$adminLang = get_admin_lang();
$isRtl = $adminLang === 'ar';
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'] ?? 'index.php');
$navItems = [
    ['index.php', 'nav_dashboard', 'bi-grid-1x2'],
    ['servers.php', 'nav_servers', 'bi-hdd-network'],
    ['users.php', 'nav_users', 'bi-people'],
    ['plans.php', 'nav_plans', 'bi-gem'],
    ['payments.php', 'nav_payments', 'bi-receipt'],
    ['news.php', 'nav_news', 'bi-megaphone'],
    ['pages.php', 'nav_pages', 'bi-file-earmark-code'],
    ['widgets.php', 'nav_widgets', 'bi-puzzle'],
    ['logs.php', 'nav_logs', 'bi-journal-text'],
    ['settings.php', 'nav_settings', 'bi-gear']
];
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($adminLang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(admin_t('admin_title'), ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= $isRtl ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="admin-body text-light">
<nav class="admin-sidebar" id="adminSidebar" aria-label="<?= $isRtl ? 'التنقل الإداري' : 'Admin navigation' ?>" aria-hidden="true">
    <div class="sidebar-brand">
        <a href="index.php" class="d-flex align-items-center gap-3 text-white text-decoration-none">
            <span class="auth-brand-mark mb-0"><i class="bi bi-play-fill" aria-hidden="true"></i></span>
            <span class="d-flex flex-column"><strong class="auth-brand">XTREAM</strong><small class="text-secondary mt-1"><?= htmlspecialchars(admin_t('admin_title'), ENT_QUOTES, 'UTF-8') ?></small></span>
        </a>
    </div>
    <ul class="nav flex-column">
        <?php foreach ($navItems as [$path, $label, $icon]): ?>
            <li class="nav-item">
                <a class="nav-link <?= $currentPage === $path ? 'active' : '' ?>" href="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>" <?= $currentPage === $path ? 'aria-current="page"' : '' ?>>
                    <i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i><span><?= htmlspecialchars(admin_t($label), ENT_QUOTES, 'UTF-8') ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <div class="mt-auto px-3 mt-4">
        <div class="d-grid gap-2">
            <a href="../index.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-play-circle me-1" aria-hidden="true"></i><?= htmlspecialchars(admin_t('nav_player'), ENT_QUOTES, 'UTF-8') ?></a>
            <a href="../logout.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i><?= htmlspecialchars(admin_t('nav_logout'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>
</nav>
<button class="admin-sidebar-backdrop" id="adminSidebarBackdrop" type="button" aria-label="<?= $isRtl ? 'إغلاق القائمة' : 'Close menu' ?>" tabindex="-1"></button>

<div class="admin-main">
    <header class="admin-topbar d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-3">
            <button class="btn btn-outline-secondary d-lg-none" id="sidebar-toggle" type="button" aria-label="<?= $isRtl ? 'فتح القائمة' : 'Open menu' ?>" aria-controls="adminSidebar" aria-expanded="false"><i class="bi bi-list fs-5" aria-hidden="true"></i></button>
            <span class="small text-secondary d-none d-sm-inline"><?= htmlspecialchars(admin_t('admin_title'), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="small text-secondary"><?= htmlspecialchars($currentUser['username'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
            <span class="auth-brand-mark mb-0" aria-hidden="true"><i class="bi bi-person-fill"></i></span>
            <div class="btn-group btn-group-sm" role="group" aria-label="Language">
                <a class="btn btn-outline-secondary <?= $adminLang === 'en' ? 'active' : '' ?>" href="?lang=en" lang="en">EN</a>
                <a class="btn btn-outline-secondary <?= $adminLang === 'ar' ? 'active' : '' ?>" href="?lang=ar" lang="ar">عربي</a>
            </div>
        </div>
    </header>
    <div class="admin-content">
<script>
(() => {
    const body = document.body;
    const sidebar = document.getElementById('adminSidebar');
    const toggle = document.getElementById('sidebar-toggle');
    const backdrop = document.getElementById('adminSidebarBackdrop');
    if (!sidebar || !toggle || !backdrop) return;
    const setOpen = (open) => {
        body.classList.toggle('sidebar-open', open);
        sidebar.classList.toggle('open', open);
        sidebar.setAttribute('aria-hidden', String(!open && window.matchMedia('(max-width: 991.98px)').matches));
        toggle.setAttribute('aria-expanded', String(open));
    };
    toggle.addEventListener('click', () => setOpen(!body.classList.contains('sidebar-open')));
    backdrop.addEventListener('click', () => setOpen(false));
    sidebar.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setOpen(false)));
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape') setOpen(false); });
    window.matchMedia('(min-width: 992px)').addEventListener('change', () => setOpen(false));
    setOpen(false);
})();
</script>
