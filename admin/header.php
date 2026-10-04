<?php
// admin/header.php
require_once __DIR__ . '/../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    header("Location: ../login.php");
    exit;
}

// Language handler for Admin
if (isset($_GET['lang']) && in_array($_GET['lang'], ['en', 'ar'])) {
    $_SESSION['admin_lang'] = $_GET['lang'];
}
$adminLang = $_SESSION['admin_lang'] ?? 'ar'; // Default to Arabic if requested or default
$isRtl = ($adminLang === 'ar');

$currentUser = getCurrentUser();

// Arabic & English translations
$t = [
    'ar' => [
        'title' => 'لوحة تحكم المدير - Xtream IPTV',
        'brand' => 'لوحة تحكم المدير',
        'servers' => 'السيرفرات العامة',
        'users' => 'إدارة المستخدمين',
        'plans' => 'باقات الاشتراك',
        'news' => 'شريط الأخبار',
        'settings' => 'إعدادات الموقع',
        'main_dashboard' => 'لوحة العرض الرئيسية',
        'logout' => 'تسجيل الخروج'
    ],
    'en' => [
        'title' => 'Admin Dashboard - Xtream IPTV',
        'brand' => 'Admin Dashboard',
        'servers' => 'Global Servers',
        'users' => 'User Management',
        'plans' => 'Subscription Plans',
        'news' => 'News Ticker',
        'settings' => 'Site Settings',
        'main_dashboard' => 'Main Player Dashboard',
        'logout' => 'Logout'
    ]
][$adminLang];
?>
<!DOCTYPE html>
<html lang="<?= $adminLang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $t['title'] ?></title>
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
    <a class="navbar-brand fw-bold me-3 ms-2" href="index.php"><i class="bi bi-shield-lock-fill me-2"></i><?= $t['brand'] ?></a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNavbar">
        <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNavbar">
        <ul class="navbar-nav me-auto mb-2 mb-lg-0">
            <li class="nav-item">
                <a class="nav-link" href="servers.php"><i class="bi bi-hdd-network me-1"></i><?= $t['servers'] ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="users.php"><i class="bi bi-people me-1"></i><?= $t['users'] ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="plans.php"><i class="bi bi-gem me-1"></i><?= $t['plans'] ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="news.php"><i class="bi bi-megaphone me-1"></i><?= $t['news'] ?></a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="settings.php"><i class="bi bi-gear me-1"></i><?= $t['settings'] ?></a>
            </li>
        </ul>
        <div class="d-flex align-items-center gap-2">
            <!-- Language Switcher -->
            <div class="btn-group btn-group-sm me-2" role="group">
                <a href="?lang=ar" class="btn btn-outline-light <?= $adminLang === 'ar' ? 'active' : '' ?>">العربية</a>
                <a href="?lang=en" class="btn btn-outline-light <?= $adminLang === 'en' ? 'active' : '' ?>">EN</a>
            </div>
            <a href="../index.php" class="btn btn-outline-light btn-sm"><i class="bi bi-play-circle me-1"></i><?= $t['main_dashboard'] ?></a>
            <a href="../logout.php" class="btn btn-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i><?= $t['logout'] ?></a>
        </div>
    </div>
</nav>
<div class="container py-4">
