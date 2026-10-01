<?php
// index.php - Main IPTV Dashboard & Video Player Page
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();
$accessibleServers = getAccessibleServers();

// Handle adding personal server
$serverMsg = '';
$serverErr = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_personal_server') {
    $name = trim($_POST['name'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($name) && !empty($host) && !empty($username) && !empty($password)) {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO servers (user_id, name, host, username, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $name, $host, $username, $password]);
        header("Location: index.php");
        exit;
    } else {
        $serverErr = "All server fields are required.";
    }
}

$userAvatar = !empty($currentUser['avatar']) ? htmlspecialchars($currentUser['avatar']) : null;
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title data-i18n="app_title">Xtream IPTV Player</title>
    
    <!-- Bootstrap CSS & Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.3.0/video-js.css" rel="stylesheet" />
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<!-- Top Navigation Bar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-3 shadow-sm sticky-top">
    <div class="container-fluid">
        <div class="d-flex align-items-center">
            <button id="sidebar-toggle-btn" class="btn btn-outline-light btn-sm me-3 sidebar-toggle-btn">
                <i class="bi bi-arrow-left"></i>
            </button>
            <a class="navbar-brand fw-bold text-primary me-3" href="index.php">
                <i class="bi bi-tv-fill me-2"></i><span data-i18n="app_title">Xtream IPTV Player</span>
            </a>
        </div>

        <!-- Server Selector Dropdown & Add Server -->
        <div class="d-flex align-items-center me-auto my-2 my-lg-0" style="max-width: 400px; width: 100%;">
            <select id="server-select" class="form-select form-select-sm bg-dark text-white border-secondary me-2">
                <?php if (empty($accessibleServers)): ?>
                    <option value="" disabled selected>No Xtream Servers Available</option>
                <?php else: ?>
                    <?php foreach ($accessibleServers as $idx => $srv): ?>
                        <option value="<?= $srv['id'] ?>" <?= $idx === 0 ? 'selected' : '' ?>>
                            <?= htmlspecialchars($srv['name']) ?> <?= $srv['user_id'] ? '(Personal)' : '(Global)' ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <button class="btn btn-sm btn-outline-info text-nowrap" data-bs-toggle="modal" data-bs-target="#addPersonalServerModal">
                <i class="bi bi-plus-circle me-1"></i><span data-i18n="add_server">Add Server</span>
            </button>
        </div>

        <!-- Right Side: Lang Switcher, Subscription status, Profile/Admin -->
        <div class="d-flex align-items-center">
            <!-- Language Switcher -->
            <div class="btn-group btn-group-sm me-3" role="group">
                <button type="button" class="btn btn-outline-secondary lang-btn" data-lang="en" onclick="setLanguage('en')">EN</button>
                <button type="button" class="btn btn-outline-secondary lang-btn" data-lang="ar" onclick="setLanguage('ar')">العربية</button>
            </div>

            <!-- User Info & Subscription -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-light dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                    <?php if ($userAvatar): ?>
                        <img src="<?= $userAvatar ?>" class="rounded-circle" style="width:24px; height:24px; object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-circle"></i>
                    <?php endif; ?>
                    <span><?= htmlspecialchars($currentUser['username']) ?></span>
                    <?php if (hasPaidSubscription()): ?>
                        <span class="badge bg-success" data-i18n="paid_user">Paid</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark" data-i18n="free_user">Free</span>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark">
                    <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person-gear me-2"></i>My Profile</a></li>
                    <li><a class="dropdown-item" href="subscriptions.php"><i class="bi bi-gem me-2"></i>Subscription Plans</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <?php if (isAdmin()): ?>
                        <li><a class="dropdown-menu-item text-warning fw-bold dropdown-item" href="admin/index.php"><i class="bi bi-speedometer2 me-2"></i><span data-i18n="admin_panel">Admin Panel</span></a></li>
                        <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item text-danger" href="logout.php"><i class="bi bi-box-arrow-right me-2"></i><span data-i18n="logout">Logout</span></a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Pass user subscription status to JS -->
<script>
    window.HAS_PAID_SUBSCRIPTION = <?= hasPaidSubscription() ? 'true' : 'false' ?>;
</script>

<!-- Main App Layout Container -->
<div id="app-container">
    <!-- Collapsible Sidebar -->
    <aside id="sidebar" class="py-3">
        <ul class="nav nav-pills flex-column mb-auto">
            <li class="nav-item">
                <a href="#" class="nav-link active tab-link" data-tab="live">
                    <i class="bi bi-broadcast me-2"></i>
                    <span class="nav-text" data-i18n="live_tv">Live TV</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link tab-link" data-tab="movies">
                    <i class="bi bi-film me-2"></i>
                    <span class="nav-text" data-i18n="movies">Movies</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="#" class="nav-link tab-link" data-tab="series">
                    <i class="bi bi-tv me-2"></i>
                    <span class="nav-text" data-i18n="series">Series</span>
                </a>
            </li>
        </ul>
    </aside>

    <!-- Main Content Area -->
    <main id="main-content">
        <!-- Filter & Search Bar -->
        <div class="row g-3 mb-4 align-items-center">
            <div class="col-md-4">
                <select id="category-select" class="form-select bg-secondary text-white border-0">
                    <option value="" data-i18n="all_categories">All Categories</option>
                </select>
            </div>
            <div class="col-md-8">
                <div class="input-group">
                    <span class="input-group-text bg-secondary text-white border-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="search-input" class="form-control bg-secondary text-white border-0" placeholder="Search channels or titles..." data-i18n="search_placeholder">
                </div>
            </div>
        </div>

        <!-- Series Detail Drawer / View (Initially Hidden) -->
        <div id="series-detail-view" class="d-none mb-4">
            <div class="d-flex align-items-center mb-3">
                <button id="btn-back-series" class="btn btn-outline-light btn-sm me-3">
                    <i class="bi bi-arrow-left me-1"></i><span data-i18n="back_to_series">Back to Series List</span>
                </button>
                <h4 id="series-title" class="mb-0 text-primary fw-bold"></h4>
            </div>
            <div id="series-seasons-container" class="row"></div>
        </div>

        <!-- Content Grid Display -->
        <div id="content-grid" class="row g-3">
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted" data-i18n="loading">Loading content...</p>
            </div>
        </div>
    </main>
</div>

<!-- Player Modal -->
<div class="modal fade" id="playerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content bg-dark text-white border-secondary">
            <div class="modal-header border-secondary py-2">
                <h5 class="modal-title" id="playerModalLabel">Video Player</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="ratio ratio-16x9">
                    <video id="iptv-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="100%" height="100%">
                    </video>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add Personal Server Modal -->
<div class="modal fade" id="addPersonalServerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content bg-secondary text-white">
            <form method="POST">
                <input type="hidden" name="action" value="add_personal_server">
                <div class="modal-header border-dark">
                    <h5 class="modal-title" data-i18n="add_personal_server">Add Personal Xtream Server</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if ($serverErr): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($serverErr) ?></div>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="server_name">Server Name</label>
                        <input type="text" name="name" class="form-control" placeholder="e.g. My Personal IPTV" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="host_url">Host / URL</label>
                        <input type="text" name="host" class="form-control" placeholder="e.g. http://gotv.ghost.co:80" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="username">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" data-i18n="password">Password</label>
                        <input type="text" name="password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-dark">
                    <button type="button" class="btn btn-dark" data-bs-dismiss="modal" data-i18n="close">Close</button>
                    <button type="submit" class="btn btn-primary" data-i18n="save">Save Server</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://vjs.zencdn.net/8.3.0/video.min.js"></script>
<script src="assets/js/i18n.js"></script>
<script src="assets/js/app.js"></script>

</body>
</html>
