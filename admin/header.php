<?php
// admin/header.php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/admin_i18n.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

$adminLang = get_admin_lang();
$isRtl = ($adminLang === 'ar');
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= admin_t('admin_title') ?></title>
    <?php if ($isRtl): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="bg-dark text-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3">
    <a class="navbar-brand fw-bold me-3 ms-2" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i><?= admin_t('admin_title') ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link" href="servers.php"><i class="bi bi-hdd-network me-1"></i><?= admin_t('nav_servers') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php"><i class="bi bi-people me-1"></i><?= admin_t('nav_users') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="plans.php"><i class="bi bi-gem me-1"></i><?= admin_t('nav_plans') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="news.php"><i class="bi bi-megaphone me-1"></i><?= admin_t('nav_news') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-1"></i><?= admin_t('nav_settings') ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="logs.php"><i class="bi bi-journal-code me-1"></i><?= admin_t('nav_logs') ?></a>
            </li>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <!-- Language Switcher -->
            <div class="btn-group btn-group-sm me-2" role="group">
                <a href="?lang=ar" class="btn btn-outline-light <?= $adminLang === 'ar' ? 'active' : '' ?>">العربية</a>
                <a href="?lang=en" class="btn btn-outline-light <?= $adminLang === 'en' ? 'active' : '' ?>">EN</a>
            </div>
            <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-play-circle me-1"></i><?= admin_t('nav_player') ?></a>
            <a href="../logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i><?= admin_t('nav_logout') ?></a>
        </div>
    </div>
</nav>
<div class="container py-4">
