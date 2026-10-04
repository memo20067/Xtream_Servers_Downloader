<?php
// install/index.php - vBulletin-Style Dynamic Installation Wizard (Installer)

require_once __DIR__ . '/../config/db.php';

// If already installed and step is not reset, prevent running installer
if (getSetting('installed', '0') === '1') {
    die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Already Installed</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head><body class="bg-dark text-light d-flex align-items-center vh-100"><div class="container text-center"><div class="card bg-secondary text-white p-5 mx-auto" style="max-width:500px;"><h3>Application Already Installed!</h3><p class="mt-3">The application has already been set up. To reinstall, reset the database or update the settings table.</p><a href="../index.php" class="btn btn-primary mt-2">Go to Dashboard</a></div></div></body></html>');
}

session_start();
$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

// Step Processing Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1_db') {
        $dbHost   = trim($_POST['db_host'] ?? '127.0.0.1');
        $dbPort   = trim($_POST['db_port'] ?? '3306');
        $dbName   = trim($_POST['db_name'] ?? 'xtream_iptv');
        $dbUser   = trim($_POST['db_user'] ?? 'root');
        $dbPass   = trim($_POST['db_pass'] ?? '');
        $dbPrefix = trim($_POST['db_prefix'] ?? '');
        $dbType   = trim($_POST['db_type'] ?? 'mysql');

        // Test Connection and create DB if needed
        try {
            if ($dbType === 'mysql') {
                // Connect without dbname first to create database if it doesn't exist
                $dsnHost = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
                $testPdo = new PDO($dsnHost, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                $testPdo->exec("CREATE DATABASE IF NOT EXISTS `" . str_replace("`", "``", $dbName) . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

                // Connect to specific database
                $dsnDb = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
                $testPdo = new PDO($dsnDb, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            } else {
                $dbDir = __DIR__ . '/../data';
                if (!is_dir($dbDir)) mkdir($dbDir, 0777, true);
                $sqliteFile = $dbDir . '/database.sqlite';
                $testPdo = new PDO("sqlite:" . $sqliteFile);
            }

            // Save config file config/db_config.php
            $configContent = "<?php\nreturn " . var_export([
                'db_type'   => $dbType,
                'db_host'   => $dbHost,
                'db_port'   => $dbPort,
                'db_name'   => $dbName,
                'db_user'   => $dbUser,
                'db_pass'   => $dbPass,
                'db_prefix' => $dbPrefix
            ], true) . ";\n";

            file_put_contents(__DIR__ . '/../config/db_config.php', $configContent);

            // Initialize DB tables
            require_once __DIR__ . '/../config/init.php';
            initializeDatabase();

            header("Location: index.php?step=2");
            exit;
        } catch (Exception $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($action === 'step2_site') {
        $appName = trim($_POST['app_name'] ?? 'Xtream IPTV Player');
        $siteTitle = trim($_POST['site_title'] ?? 'Xtream Media Hub');
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

        header("Location: index.php?step=3");
        exit;
    } elseif ($action === 'step3_cache') {
        $cacheDir = __DIR__ . '/../cache/images/';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }
        @chmod($cacheDir, 0777);

        header("Location: index.php?step=4");
        exit;
    } elseif ($action === 'step4_admin') {
        $adminUser = trim($_POST['admin_user'] ?? '');
        $adminEmail = trim($_POST['admin_email'] ?? '');
        $adminPhone = trim($_POST['admin_phone'] ?? '');
        $adminPass = trim($_POST['admin_pass'] ?? '');

        if (empty($adminUser) || empty($adminEmail) || empty($adminPass)) {
            $error = "All mandatory fields (Username, Email, Password) must be filled.";
        } else {
            // Defensively ensure database tables exist before admin creation
            require_once __DIR__ . '/../config/init.php';
            initializeDatabase();

            $db = getDBConnection();
            $stmt = $db->prepare("DELETE FROM users WHERE role = 'admin'");
            $stmt->execute();

            $hashedPass = password_hash($adminPass, PASSWORD_BCRYPT);
            $stmtIns = $db->prepare("INSERT INTO users (username, email, phone, password, role, has_paid_subscription) VALUES (?, ?, ?, ?, 'admin', 1)");
            $stmtIns->execute([$adminUser, $adminEmail, $adminPhone, $hashedPass]);

            header("Location: index.php?step=5");
            exit;
        }
    } elseif ($action === 'step5_finish') {
        // Run initial sync check or set status
        setSetting('installed', '1');
        header("Location: ../login.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>vBulletin Setup Wizard - Xtream IPTV Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #121212; color: #e0e0e0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .vb-installer-box { max-width: 800px; margin: 40px auto; background: #1e1e1e; border: 1px solid #333; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.5); overflow: hidden; }
        .vb-header { background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%); padding: 20px 25px; color: #fff; border-bottom: 2px solid #084298; }
        .vb-steps { background: #252525; padding: 12px 25px; border-bottom: 1px solid #333; display: flex; justify-content: space-between; }
        .vb-step-item { font-size: 0.85rem; font-weight: 600; color: #888; text-transform: uppercase; }
        .vb-step-item.active { color: #0d6efd; text-decoration: underline; }
        .vb-step-item.done { color: #198754; }
        .vb-body { padding: 30px 25px; }
        .vb-footer { background: #252525; padding: 15px 25px; border-top: 1px solid #333; display: flex; justify-content: space-between; align-items: center; }
    </style>
</head>
<body>

<div class="vb-installer-box">
    <!-- Header -->
    <div class="vb-header">
        <div class="d-flex align-items-center">
            <i class="bi bi-gear-wide-connected fs-2 me-3"></i>
            <div>
                <h4 class="mb-0 fw-bold">vBulletin Dynamic Installation Wizard</h4>
                <small class="text-white-50">Xtream Codes IPTV Web Manager Setup</small>
            </div>
        </div>
    </div>

    <!-- Step Progress Indicator -->
    <div class="vb-steps">
        <span class="vb-step-item <?= $step === 1 ? 'active' : ($step > 1 ? 'done' : '') ?>">1. Database</span>
        <span class="vb-step-item <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : '') ?>">2. Site Settings</span>
        <span class="vb-step-item <?= $step === 3 ? 'active' : ($step > 3 ? 'done' : '') ?>">3. Cache Folders</span>
        <span class="vb-step-item <?= $step === 4 ? 'active' : ($step > 4 ? 'done' : '') ?>">4. Admin Account</span>
        <span class="vb-step-item <?= $step === 5 ? 'active' : ($step > 5 ? 'done' : '') ?>">5. Playlist & Sync</span>
    </div>

    <!-- Body -->
    <div class="vb-body">
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <h5 class="text-primary mb-3"><i class="bi bi-database-fill-gear me-2"></i>Step 1: Database Setup Configuration</h5>
            <p class="text-muted small">Please input your MySQL or SQLite database server connection details below.</p>
            <form method="POST">
                <input type="hidden" name="action" value="step1_db">
                <div class="mb-3">
                    <label class="form-label fw-bold">Database Type</label>
                    <select name="db_type" class="form-select bg-dark text-light border-secondary" id="db_type_select">
                        <option value="mysql" selected>MySQL / MariaDB</option>
                        <option value="sqlite">SQLite (Portable / Embedded)</option>
                    </select>
                </div>
                <div id="mysql_fields">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Database Host</label>
                            <input type="text" name="db_host" class="form-control bg-dark text-light border-secondary" value="127.0.0.1" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Port</label>
                            <input type="text" name="db_port" class="form-control bg-dark text-light border-secondary" value="3306" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">Database Name</label>
                            <input type="text" name="db_name" class="form-control bg-dark text-light border-secondary" value="xtream_iptv" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Table Prefix (Optional)</label>
                            <input type="text" name="db_prefix" class="form-control bg-dark text-light border-secondary" placeholder="e.g. vb_">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Database Username</label>
                            <input type="text" name="db_user" class="form-control bg-dark text-light border-secondary" value="root" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Database Password</label>
                            <input type="password" name="db_pass" class="form-control bg-dark text-light border-secondary" value="">
                        </div>
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1"></i>Test & Next Step</button>
                </div>
            </form>

        <?php elseif ($step === 2): ?>
            <h5 class="text-primary mb-3"><i class="bi bi-sliders me-2"></i>Step 2: Site & Social Media Settings</h5>
            <p class="text-muted small">Customize application brand details and optional customer contact links.</p>
            <form method="POST">
                <input type="hidden" name="action" value="step2_site">
                <div class="mb-3">
                    <label class="form-label fw-bold">Application Name</label>
                    <input type="text" name="app_name" class="form-control bg-dark text-light border-secondary" value="Xtream IPTV Player" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Site Title</label>
                    <input type="text" name="site_title" class="form-control bg-dark text-light border-secondary" value="Xtream Media Portal" required>
                </div>
                <h6 class="mt-4 mb-3 text-info"><i class="bi bi-chat-dots me-2"></i>Optional Social Contact Details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-whatsapp me-1 text-success"></i>WhatsApp Number / Link</label>
                        <input type="text" name="whatsapp" class="form-control bg-dark text-light border-secondary" placeholder="+1234567890">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-telegram me-1 text-info"></i>Telegram Handle / Link</label>
                        <input type="text" name="telegram" class="form-control bg-dark text-light border-secondary" placeholder="https://t.me/yourchannel">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-facebook me-1 text-primary"></i>Facebook Page</label>
                        <input type="text" name="facebook" class="form-control bg-dark text-light border-secondary" placeholder="https://facebook.com/yourpage">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label"><i class="bi bi-instagram me-1 text-warning"></i>Instagram Profile</label>
                        <input type="text" name="instagram" class="form-control bg-dark text-light border-secondary" placeholder="https://instagram.com/yourprofile">
                    </div>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1"></i>Save & Next Step</button>
                </div>
            </form>

        <?php elseif ($step === 3): ?>
            <h5 class="text-primary mb-3"><i class="bi bi-folder-check me-2"></i>Step 3: Cache Directory Setup</h5>
            <p class="text-muted small">Automatically creates and configures file permissions for local image caching (`/cache/images/`) to optimize page loading speed.</p>
            <div class="card bg-secondary text-white border-0 p-3 mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-folder-symlink fs-1 me-3 text-warning"></i>
                    <div>
                        <h6>Directory Target: <code>/cache/images/</code></h6>
                        <small class="text-light">Target Permissions: <code>0777 / Writable</code></small>
                    </div>
                </div>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="step3_cache">
                <div class="text-end">
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Create & Verify Permissions</button>
                </div>
            </form>

        <?php elseif ($step === 4): ?>
            <h5 class="text-primary mb-3"><i class="bi bi-person-badge-fill me-2"></i>Step 4: Super-Admin Account Creation</h5>
            <p class="text-muted small">Create the primary Super-Admin account credentials for full system administration.</p>
            <form method="POST">
                <input type="hidden" name="action" value="step4_admin">
                <div class="mb-3">
                    <label class="form-label fw-bold">Admin Username</label>
                    <input type="text" name="admin_user" class="form-control bg-dark text-light border-secondary" value="admin" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Admin Email Address</label>
                    <input type="email" name="admin_email" class="form-control bg-dark text-light border-secondary" value="admin@example.com" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Admin Mobile Phone Number</label>
                    <input type="text" name="admin_phone" class="form-control bg-dark text-light border-secondary" value="+123456789" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Admin Password</label>
                    <input type="password" name="admin_pass" class="form-control bg-dark text-light border-secondary" required>
                </div>
                <div class="text-end mt-4">
                    <button type="submit" class="btn btn-primary"><i class="bi bi-arrow-right-circle me-1"></i>Create Admin Account</button>
                </div>
            </form>

        <?php elseif ($step === 5): ?>
            <h5 class="text-primary mb-3"><i class="bi bi-arrow-repeat me-2"></i>Step 5: Playlist Caching & Daily Sync Setup</h5>
            <p class="text-muted small">IPTV playlists will be stored in MySQL / SQLite database cache. Setup the automated cron task below to refresh data automatically once every 24 hours.</p>

            <div class="card bg-secondary text-white border-0 p-3 mb-4">
                <h6><i class="bi bi-terminal me-2"></i>24-Hour Automated Cron Command</h6>
                <code class="p-2 bg-dark text-info rounded d-block mt-2">0 0 * * * php <?= realpath(__DIR__ . '/../cron/sync_playlist.php') ?> > /dev/null 2>&1</code>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="step5_finish">
                <div class="text-end">
                    <button type="submit" class="btn btn-success btn-lg"><i class="bi bi-rocket-takeoff-fill me-1"></i>Complete Installation & Launch</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<script>
const dbTypeSelect = document.getElementById('db_type_select');
if (dbTypeSelect) {
    dbTypeSelect.addEventListener('change', () => {
        const mysqlFields = document.getElementById('mysql_fields');
        if (dbTypeSelect.value === 'sqlite') {
            mysqlFields.style.display = 'none';
        } else {
            mysqlFields.style.display = 'block';
        }
    });
}
</script>
</body>
</html>
