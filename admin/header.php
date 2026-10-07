<?php
// admin/header.php - Modern Sidebar Navigation Dashboard
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
    <style>
        .admin-sidebar {
            position: fixed;
            top: 0;
            left: 0;
            bottom: 0;
            width: 260px;
            background: rgba(22, 24, 34, 0.95);
            backdrop-filter: blur(16px);
            border-right: 1px solid var(--border-color);
            z-index: 1030;
            padding: 20px 0;
            overflow-y: auto;
            transition: transform 0.3s ease;
        }

        .admin-sidebar .sidebar-brand {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 10px;
        }

        .admin-sidebar .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: 0;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.2s ease;
            margin: 2px 10px;
            border-radius: 10px;
        }

        .admin-sidebar .nav-link:hover {
            color: var(--text-primary);
            background-color: rgba(255, 255, 255, 0.05);
        }

        .admin-sidebar .nav-link.active {
            color: #ffffff;
            background-color: var(--accent-blue);
            box-shadow: 0 4px 15px rgba(0, 136, 255, 0.3);
        }

        .admin-sidebar .nav-link i {
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }

        .admin-main {
            margin-left: 260px;
            min-height: 100vh;
        }

        .admin-topbar {
            position: sticky;
            top: 0;
            z-index: 1020;
            background: rgba(15, 17, 23, 0.9);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            padding: 15px 30px;
        }

        @media (max-width: 991px) {
            .admin-sidebar {
                transform: translateX(-100%);
            }
            .admin-sidebar.open {
                transform: translateX(0);
            }
            .admin-main {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-dark text-light">
<nav class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <a href="index.php" class="text-white text-decoration-none d-flex align-items-center gap-2">
            <i class="bi bi-shield-lock-fill text-primary fs-3"></i>
            <span class="fw-bold fs-5"><?= admin_t('admin_title') ?></span>
        </a>
    </div>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>" href="index.php">
                <i class="bi bi-speedometer2"></i><?= admin_t('nav_dashboard') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'servers.php' ? 'active' : '' ?>" href="servers.php">
                <i class="bi bi-hdd-network"></i><?= admin_t('nav_servers') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : '' ?>" href="users.php">
                <i class="bi bi-people"></i><?= admin_t('nav_users') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'plans.php' ? 'active' : '' ?>" href="plans.php">
                <i class="bi bi-gem"></i><?= admin_t('nav_plans') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'payments.php' ? 'active' : '' ?>" href="payments.php">
                <i class="bi bi-credit-card"></i><?= $isRtl ? 'الدفعات' : 'Payments' ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'news.php' ? 'active' : '' ?>" href="news.php">
                <i class="bi bi-megaphone"></i><?= admin_t('nav_news') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'pages.php' ? 'active' : '' ?>" href="pages.php">
                <i class="bi bi-file-earmark-code"></i><?= $isRtl ? 'الصفحات المخصصة' : 'Custom Pages' ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'widgets.php' ? 'active' : '' ?>" href="widgets.php">
                <i class="bi bi-puzzle"></i><?= $isRtl ? 'الودجات' : 'Widgets' ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'logs.php' ? 'active' : '' ?>" href="logs.php">
                <i class="bi bi-journal-code"></i><?= admin_t('nav_logs') ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : '' ?>" href="settings.php">
                <i class="bi bi-gear"></i><?= admin_t('nav_settings') ?>
            </a>
        </li>
    </ul>
    <div class="mt-auto px-3 mt-4">
        <div class="d-grid gap-2">
            <a href="../index.php" class="btn btn-outline-info btn-sm"><i class="bi bi-play-circle me-1"></i><?= admin_t('nav_player') ?></a>
            <a href="../logout.php" class="btn btn-outline-danger btn-sm"><i class="bi bi-box-arrow-right me-1"></i><?= admin_t('nav_logout') ?></a>
        </div>
    </div>
</nav>

<div class="admin-main">
    <div class="admin-topbar d-flex justify-content-between align-items-center">
        <button class="btn btn-outline-light btn-sm" id="sidebar-toggle"><i class="bi bi-list"></i></button>
        <div class="d-flex align-items-center gap-2">
            <span class="text-white-50 small"><?= htmlspecialchars($currentUser['username']) ?> (<?= admin_t('admin_title') ?>)</span>
        </div>
    </div>
    <div class="p-4">
