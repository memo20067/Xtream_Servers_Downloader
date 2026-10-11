<?php
// Secure first-run installer. It must stay public so the admin account can be created.
require_once __DIR__ . '/../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$lockFile = __DIR__ . '/install.lock';
$configFile = __DIR__ . '/../config/db_config.php';
$installLocked = is_file($lockFile);
$storedConfig = [];
if (is_file($configFile)) {
    try {
        $loadedConfig = include $configFile;
        if (is_array($loadedConfig)) $storedConfig = $loadedConfig;
    } catch (Throwable $e) {
        $storedConfig = [];
    }
}

function installerConnect(array $config, bool $createDatabase = false): PDO {
    $type = $config['db_type'] ?? 'mysql';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ];

    if ($type === 'sqlite') {
        $directory = __DIR__ . '/../data';
        $databasePath = $directory . '/database.sqlite';
        if ($createDatabase && !is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('The SQLite data folder could not be created.');
        }
        if (!$createDatabase && !is_file($databasePath)) {
            throw new RuntimeException('The SQLite database does not exist yet.');
        }
        $pdo = new PDO('sqlite:' . $databasePath, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        return $pdo;
    }

    if ($type !== 'mysql') throw new InvalidArgumentException('Choose MySQL or SQLite.');

    $host = trim((string)($config['db_host'] ?? '127.0.0.1'));
    $port = (int)($config['db_port'] ?? 3306);
    $name = trim((string)($config['db_name'] ?? 'xtream_iptv'));
    $user = (string)($config['db_user'] ?? 'root');
    $password = (string)($config['db_pass'] ?? '');
    if ($host === '' || $port < 1 || $port > 65535 || !preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
        throw new InvalidArgumentException('Check the MySQL host, port, and database name.');
    }

    if ($createDatabase) {
        $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $password, $options);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . $name . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    return new PDO("mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4", $user, $password, $options);
}

function installerTableExists(PDO $pdo, string $table): bool {
    if (!in_array($table, ['settings', 'users'], true)) return false;
    if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
        $statement = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ? LIMIT 1");
        $statement->execute([$table]);
        return (bool)$statement->fetchColumn();
    }
    $statement = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
    $statement->execute([$table]);
    return (bool)$statement->fetchColumn();
}

$databaseConnected = false;
$installedSetting = false;
$hasUsers = false;
try {
    $existingPdo = installerConnect($storedConfig);
    $databaseConnected = true;
    if (installerTableExists($existingPdo, 'settings')) {
        $statement = $existingPdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $statement->execute(['installed']);
        $installedSetting = $statement->fetchColumn() === '1';
    }
    if (installerTableExists($existingPdo, 'users')) {
        $hasUsers = (bool)$existingPdo->query('SELECT 1 FROM users LIMIT 1')->fetchColumn();
    }
} catch (Throwable $e) {
    // A fresh database may not exist yet; the first wizard step tests and creates it.
}

// An installed flag always locks this route. A committed/old lock file only locks
// when a real account exists, so a fresh empty database can still be initialized.
$shouldLock = $installedSetting || ($installLocked && ($hasUsers || !$databaseConnected));
if ($shouldLock) {
    http_response_code(200);
    ?>
    <!doctype html>
    <html lang="ar" dir="rtl">
    <head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
        <title>التطبيق مثبت بالفعل</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <link rel="stylesheet" href="../assets/css/style.css">
    </head>
    <body class="auth-page">
        <main class="style-auth-card installer-card text-center">
            <span class="auth-brand-mark mb-3"><i class="bi bi-shield-lock" aria-hidden="true"></i></span>
            <h1 class="h3 fw-bold mb-2">التطبيق مثبت بالفعل</h1>
            <p class="text-secondary mb-4">تم قفل معالج التثبيت لحماية قاعدة البيانات والحسابات الحالية.</p>
            <a href="../login.php" class="btn btn-primary">الانتقال إلى تسجيل الدخول</a>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty($_SESSION['installer_csrf'])) $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
if (!isset($_SESSION['installer_step'])) $_SESSION['installer_step'] = 1;

$step = isset($_GET['step']) ? (int)$_GET['step'] : (int)$_SESSION['installer_step'];
$step = max(1, min(5, $step, (int)$_SESSION['installer_step']));
$error = '';

if ($step > 1) {
    try {
        require_once __DIR__ . '/../config/init.php';
    } catch (Throwable $e) {
        $error = 'تعذر فتح قاعدة البيانات الحالية. أعد اختبار إعداد الاتصال في الخطوة الأولى.';
        $step = 1;
        $_SESSION['installer_step'] = 1;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)$_SESSION['installer_csrf'], $token)) {
        $error = 'انتهت صلاحية النموذج. أعد تحميل الصفحة ثم حاول مرة أخرى.';
    } else {
        $action = (string)($_POST['action'] ?? '');
        try {
            if ($action === 'step1_db' && $step === 1) {
                $type = (string)($_POST['db_type'] ?? 'mysql');
                $candidate = [
                    'db_type' => $type,
                    'db_host' => trim((string)($_POST['db_host'] ?? '127.0.0.1')),
                    'db_port' => trim((string)($_POST['db_port'] ?? '3306')),
                    'db_name' => trim((string)($_POST['db_name'] ?? 'xtream_iptv')),
                    'db_user' => trim((string)($_POST['db_user'] ?? 'root')),
                    'db_pass' => (string)($_POST['db_pass'] ?? ''),
                    'db_prefix' => ''
                ];
                if (!in_array($type, ['mysql', 'sqlite'], true)) throw new InvalidArgumentException('اختر نوع قاعدة بيانات صالحًا.');
                installerConnect($candidate, true);

                $configContent = "<?php\nreturn " . var_export($candidate, true) . ";\n";
                if (file_put_contents($configFile, $configContent, LOCK_EX) === false) {
                    throw new RuntimeException('تعذر حفظ إعدادات قاعدة البيانات. تحقق من صلاحيات مجلد config.');
                }
                @chmod($configFile, 0640);
                require_once __DIR__ . '/../config/init.php';
                initializeDatabase();
                $_SESSION['installer_step'] = 2;
                header('Location: index.php?step=2');
                exit;
            }

            if ($action === 'step2_site' && $step === 2) {
                $appName = trim((string)($_POST['app_name'] ?? ''));
                if ($appName === '') throw new InvalidArgumentException('أدخل اسمًا للتطبيق.');
                foreach ([
                    'app_name' => $appName,
                    'site_title' => trim((string)($_POST['site_title'] ?? '')),
                    'whatsapp' => trim((string)($_POST['whatsapp'] ?? '')),
                    'telegram' => trim((string)($_POST['telegram'] ?? '')),
                    'facebook' => trim((string)($_POST['facebook'] ?? '')),
                    'instagram' => trim((string)($_POST['instagram'] ?? ''))
                ] as $key => $value) {
                    if (!setSetting($key, $value)) throw new RuntimeException('تعذر حفظ إعدادات الموقع.');
                }
                $_SESSION['installer_step'] = 3;
                header('Location: index.php?step=3');
                exit;
            }

            if ($action === 'step3_cache' && $step === 3) {
                foreach ([__DIR__ . '/../cache/images', __DIR__ . '/../uploads/avatars'] as $directory) {
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new RuntimeException('تعذر إنشاء مجلدات التخزين المطلوبة.');
                    }
                    if (!is_writable($directory)) throw new RuntimeException('مجلد التخزين موجود لكنه غير قابل للكتابة.');
                }
                $_SESSION['installer_step'] = 4;
                header('Location: index.php?step=4');
                exit;
            }

            if ($action === 'step4_admin' && $step === 4) {
                $username = trim((string)($_POST['admin_user'] ?? ''));
                $email = strtolower(trim((string)($_POST['admin_email'] ?? '')));
                $phone = trim((string)($_POST['admin_phone'] ?? ''));
                $password = (string)($_POST['admin_pass'] ?? '');
                if ($username === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10) {
                    throw new InvalidArgumentException('أدخل اسم مستخدم وبريدًا صالحًا وكلمة مرور لا تقل عن 10 أحرف.');
                }
                $pdo = getDBConnection();
                $statement = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1');
                $statement->execute([$username, $email]);
                if ($statement->fetch()) throw new InvalidArgumentException('اسم المستخدم أو البريد مستخدم بالفعل.');
                $statement = $pdo->prepare("INSERT INTO users (username, email, phone, password, role, has_paid_subscription) VALUES (?, ?, ?, ?, 'admin', 1)");
                $statement->execute([$username, $email, $phone, password_hash($password, PASSWORD_DEFAULT)]);
                $_SESSION['installer_step'] = 5;
                header('Location: index.php?step=5');
                exit;
            }

            if ($action === 'step5_finish' && $step === 5) {
                $pdo = getDBConnection();
                $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
                if ($adminCount < 1) throw new RuntimeException('أنشئ حساب المدير قبل إكمال التثبيت.');
                if (!setSetting('installed', '1')) throw new RuntimeException('تعذر تسجيل حالة التثبيت في قاعدة البيانات.');
                $lockWritten = file_put_contents($lockFile, date('c'), LOCK_EX);
                if ($lockWritten === false) {
                    setSetting('installed', '0');
                    throw new RuntimeException('تعذر إنشاء ملف حماية التثبيت. تحقق من صلاحيات مجلد install.');
                }
                session_regenerate_id(true);
                unset($_SESSION['installer_step'], $_SESSION['installer_csrf']);
                header('Location: ../login.php?installed=1');
                exit;
            }

            throw new InvalidArgumentException('هذه الخطوة غير متاحة بعد. أكمل الخطوات بالترتيب.');
        } catch (Throwable $e) {
            $error = $e instanceof InvalidArgumentException || $e instanceof RuntimeException
                ? $e->getMessage()
                : 'تعذر إكمال هذه الخطوة. تحقق من بيانات الاتصال وصلاحيات الملفات.';
        }
    }
}

$defaults = array_merge([
    'db_type' => 'mysql', 'db_host' => '127.0.0.1', 'db_port' => '3306',
    'db_name' => 'xtream_iptv', 'db_user' => 'root'
], $storedConfig);
$stepTitles = ['قاعدة البيانات', 'إعدادات الموقع', 'مجلدات التخزين', 'حساب المدير', 'إكمال التثبيت'];
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>إعداد مشغل Xtream IPTV</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body class="auth-page installer-page">
<main class="installer-shell">
    <header class="installer-header">
        <span class="auth-brand-mark"><i class="bi bi-tv" aria-hidden="true"></i></span>
        <div><h1 class="h3 fw-bold mb-1">إعداد مشغل Xtream IPTV</h1><p class="small text-secondary mb-0">معالج التثبيت الأولي</p></div>
    </header>
    <nav class="installer-steps" aria-label="خطوات التثبيت">
        <?php foreach ($stepTitles as $index => $title): $number = $index + 1; ?>
            <span class="installer-step <?= $number === $step ? 'is-current' : ($number < $step ? 'is-complete' : '') ?>" <?= $number === $step ? 'aria-current="step"' : '' ?>>
                <span class="installer-step-number"><?= $number < $step ? '<i class="bi bi-check2" aria-hidden="true"></i>' : $number ?></span>
                <span class="installer-step-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></span>
            </span>
        <?php endforeach; ?>
    </nav>

    <section class="installer-body">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($step === 1): ?>
            <h2 class="h5 fw-bold mb-2">الاتصال بقاعدة البيانات</h2>
            <p class="text-secondary small mb-4">أدخل بيانات MySQL أو اختر SQLite. سيختبر المعالج الاتصال قبل المتابعة.</p>
            <form method="post" action="index.php?step=1">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="step1_db">
                <div class="mb-3">
                    <label class="form-label" for="db-type">نوع قاعدة البيانات</label>
                    <select id="db-type" name="db_type" class="form-select">
                        <option value="mysql" <?= ($defaults['db_type'] ?? 'mysql') === 'mysql' ? 'selected' : '' ?>>MySQL / MariaDB</option>
                        <option value="sqlite" <?= ($defaults['db_type'] ?? '') === 'sqlite' ? 'selected' : '' ?>>SQLite</option>
                    </select>
                </div>
                <div id="mysql-fields" class="row g-3">
                    <div class="col-md-8"><label class="form-label" for="db-host">المضيف</label><input id="db-host" name="db_host" class="form-control" value="<?= htmlspecialchars((string)$defaults['db_host'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="url"></div>
                    <div class="col-md-4"><label class="form-label" for="db-port">المنفذ</label><input id="db-port" name="db_port" class="form-control" inputmode="numeric" value="<?= htmlspecialchars((string)$defaults['db_port'], ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="col-md-6"><label class="form-label" for="db-name">اسم قاعدة البيانات</label><input id="db-name" name="db_name" class="form-control" value="<?= htmlspecialchars((string)$defaults['db_name'], ENT_QUOTES, 'UTF-8') ?>" required></div>
                    <div class="col-md-6"><label class="form-label" for="db-user">اسم المستخدم</label><input id="db-user" name="db_user" class="form-control" value="<?= htmlspecialchars((string)$defaults['db_user'], ENT_QUOTES, 'UTF-8') ?>" autocomplete="username"></div>
                    <div class="col-12"><label class="form-label" for="db-pass">كلمة مرور قاعدة البيانات</label><input id="db-pass" name="db_pass" type="password" class="form-control" autocomplete="current-password"><div class="form-text">تُحفظ بيانات الاتصال في ملف إعدادات الخادم.</div></div>
                </div>
                <button type="submit" class="btn btn-primary mt-4"><i class="bi bi-plug me-2" aria-hidden="true"></i>اختبار الاتصال والمتابعة</button>
            </form>
        <?php elseif ($step === 2): ?>
            <h2 class="h5 fw-bold mb-2">إعدادات الموقع</h2><p class="text-secondary small mb-4">أدخل اسم التطبيق وبيانات الدعم الاختيارية.</p>
            <form method="post" action="index.php?step=2">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="step2_site">
                <div class="mb-3"><label class="form-label" for="app-name">اسم التطبيق</label><input id="app-name" name="app_name" class="form-control" value="Xtream IPTV Player" required></div>
                <div class="mb-3"><label class="form-label" for="site-title">عنوان الموقع</label><input id="site-title" name="site_title" class="form-control" value="Xtream Media Portal"></div>
                <div class="row g-3">
                    <?php foreach (['whatsapp' => 'واتساب', 'telegram' => 'تيليجرام', 'facebook' => 'فيسبوك', 'instagram' => 'إنستغرام'] as $name => $label): ?>
                        <div class="col-md-6"><label class="form-label" for="contact-<?= $name ?>"><?= $label ?> <span class="text-secondary">(اختياري)</span></label><input id="contact-<?= $name ?>" name="<?= $name ?>" class="form-control" autocomplete="url"></div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-primary mt-4">حفظ ومتابعة<i class="bi bi-arrow-left me-2" aria-hidden="true"></i></button>
            </form>
        <?php elseif ($step === 3): ?>
            <h2 class="h5 fw-bold mb-2">التحقق من مجلدات التخزين</h2><p class="text-secondary small mb-4">سننشئ مجلدي تخزين صور الكاش والصور الشخصية ونتأكد من قابليتهما للكتابة.</p>
            <div class="installer-folder-list mb-4"><div><i class="bi bi-folder2-open" aria-hidden="true"></i><code>cache/images/</code></div><div><i class="bi bi-folder2-open" aria-hidden="true"></i><code>uploads/avatars/</code></div></div>
            <form method="post" action="index.php?step=3"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="step3_cache"><button type="submit" class="btn btn-primary">إنشاء المجلدات والتحقق</button></form>
        <?php elseif ($step === 4): ?>
            <h2 class="h5 fw-bold mb-2">إنشاء حساب المدير</h2><p class="text-secondary small mb-4">استخدم كلمة مرور قوية؛ لا تُحذف الحسابات الموجودة أثناء هذه الخطوة.</p>
            <form method="post" action="index.php?step=4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="step4_admin">
                <div class="mb-3"><label class="form-label" for="admin-user">اسم المستخدم</label><input id="admin-user" name="admin_user" class="form-control" value="admin" autocomplete="username" required></div>
                <div class="mb-3"><label class="form-label" for="admin-email">البريد الإلكتروني</label><input id="admin-email" name="admin_email" type="email" class="form-control" autocomplete="email" required></div>
                <div class="mb-3"><label class="form-label" for="admin-phone">رقم الهاتف <span class="text-secondary">(اختياري)</span></label><input id="admin-phone" name="admin_phone" type="tel" class="form-control" autocomplete="tel"></div>
                <div class="mb-3"><label class="form-label" for="admin-pass">كلمة المرور</label><input id="admin-pass" name="admin_pass" type="password" class="form-control" minlength="10" autocomplete="new-password" required><div class="form-text">10 أحرف على الأقل.</div></div>
                <button type="submit" class="btn btn-primary">إنشاء الحساب والمتابعة</button>
            </form>
        <?php else: ?>
            <h2 class="h5 fw-bold mb-2">اكتمل الإعداد</h2><p class="text-secondary small mb-4">حساب المدير جاهز. يمكنك الآن إكمال التثبيت والانتقال إلى تسجيل الدخول.</p>
            <div class="installer-folder-list mb-4"><div><i class="bi bi-check-circle" aria-hidden="true"></i><span>قاعدة البيانات وحساب المدير جاهزان</span></div><div><i class="bi bi-clock-history" aria-hidden="true"></i><span>يمكن تهيئة مزامنة الكاش اليومية لاحقًا عبر مهمة Cron.</span></div></div>
            <form method="post" action="index.php?step=5"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['installer_csrf'], ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="step5_finish"><button type="submit" class="btn btn-primary"><i class="bi bi-check2-circle me-2" aria-hidden="true"></i>إكمال التثبيت</button></form>
        <?php endif; ?>
    </section>
    <footer class="installer-footer"><a href="../login.php" class="link-secondary text-decoration-none">العودة إلى تسجيل الدخول</a></footer>
</main>
<script>
const databaseType = document.getElementById('db-type');
const mysqlFields = document.getElementById('mysql-fields');
function syncDatabaseFields() {
    if (!databaseType || !mysqlFields) return;
    const isMysql = databaseType.value === 'mysql';
    mysqlFields.classList.toggle('d-none', !isMysql);
    mysqlFields.querySelectorAll('input').forEach((input) => {
        input.disabled = !isMysql;
        input.required = isMysql && ['db_host', 'db_port', 'db_name', 'db_user'].includes(input.name);
    });
}
databaseType?.addEventListener('change', syncDatabaseFields);
syncDatabaseFields();
</script>
</body>
</html>
