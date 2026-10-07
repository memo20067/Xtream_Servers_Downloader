<?php
// player.php - Dedicated player, item details, episode list, and source-quality download.
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$currentUser = getCurrentUser();
$queryString = static function (string $key, string $fallback = ''): string {
    $value = $_GET[$key] ?? $fallback;
    return is_scalar($value) ? trim((string)$value) : $fallback;
};
$allowedTypes = ['live', 'movie', 'vod', 'series', 'm3u_direct'];
$type = $queryString('type', 'live');
$type = in_array($type, $allowedTypes, true) ? $type : 'live';
$serverId = $queryString('server_id');
$streamId = $queryString('stream_id');
$seriesId = $queryString('series_id');
$title = $queryString('title', 'IPTV Stream');
$sourceName = $queryString('source_name', $serverId);
$extension = $queryString('ext', 'mp4');
$extension = preg_match('/^[a-z0-9]{1,8}$/i', $extension) ? $extension : 'mp4';
$directKey = $queryString('direct_key');
$directKey = preg_match('/^[a-z0-9-]{8,80}$/i', $directKey) ? $directKey : '';
$icon = $queryString('icon');
if (!preg_match('#^(https?://|/?(?:api/cache_image\.php|cache/images/))#i', $icon)) {
    $icon = '';
}

$lang = $_COOKIE['app_lang'] ?? ($_SESSION['lang'] ?? 'ar');
$lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';
$isRtl = $lang === 'ar';
$hasPaid = hasPaidSubscription();
$displayTitle = $title !== '' ? $title : ($isRtl ? 'عنوان من المصدر' : 'Title from source');
$typeLabels = [
    'live' => $isRtl ? 'قناة مباشرة' : 'Live channel',
    'movie' => $isRtl ? 'فيلم' : 'Movie',
    'vod' => $isRtl ? 'فيلم' : 'Movie',
    'series' => $isRtl ? 'مسلسل' : 'Series',
    'm3u_direct' => $isRtl ? 'بث مباشر' : 'Live stream'
];
$backParams = [];
foreach (['source', 'tab', 'q', 'category'] as $key) {
    if (isset($_GET['back_' . $key]) && is_scalar($_GET['back_' . $key])) {
        $backParams[$key] = (string)$_GET['back_' . $key];
    }
}
$backHref = 'index.php' . ($backParams ? '?' . http_build_query($backParams) : '');
$appName = getSetting('app_name', 'Xtream IPTV Player');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap<?= $isRtl ? '.rtl' : '' ?>.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://vjs.zencdn.net/8.3.0/video-js.css">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="player-page">
<header class="catalog-topbar">
    <div class="container-fluid d-flex align-items-center justify-content-between gap-3 px-3 px-lg-5 py-3">
        <div class="d-flex align-items-center gap-3 min-w-0">
            <a class="auth-brand text-decoration-none d-flex align-items-center" href="index.php" aria-label="<?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?>">
                <span class="auth-brand-mark">X</span><span class="d-none d-sm-inline"><?= htmlspecialchars($appName, ENT_QUOTES, 'UTF-8') ?></span>
            </a>
            <span class="text-muted small d-none d-md-inline" aria-hidden="true">/</span>
            <span class="small text-secondary text-truncate d-none d-md-inline"><?= $isRtl ? 'المشغّل' : 'Player' ?></span>
        </div>
        <a class="btn btn-outline-secondary btn-sm" href="<?= htmlspecialchars($backHref, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-arrow-right me-2" aria-hidden="true"></i><?= $isRtl ? 'العودة إلى نتائج المصدر' : 'Back to source results' ?></a>
    </div>
</header>

<main class="player-main">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
        <div class="d-flex align-items-center gap-3">
            <span class="d-inline-flex justify-content-center align-items-center border border-secondary rounded p-2"><i class="bi <?= $type === 'series' ? 'bi-collection-play' : ($type === 'movie' || $type === 'vod' ? 'bi-film' : 'bi-broadcast') ?>" aria-hidden="true"></i></span>
            <div><div class="fw-semibold"><?= htmlspecialchars($typeLabels[$type], ENT_QUOTES, 'UTF-8') ?></div><div class="small text-muted"><?= $isRtl ? 'المصدر المحدد' : 'Selected source' ?> · <?= htmlspecialchars($sourceName, ENT_QUOTES, 'UTF-8') ?></div></div>
        </div>
        <span id="player-status" class="small text-secondary" role="status" aria-live="polite"><?= $isRtl ? 'جارٍ تجهيز التشغيل…' : 'Preparing playback…' ?></span>
    </div>

    <section class="player-frame ratio ratio-16x9" aria-label="<?= $isRtl ? 'مشغل الفيديو' : 'Video player' ?>" dir="ltr">
        <video id="iptv-player" class="video-js vjs-default-skin vjs-big-play-centered" controls playsinline preload="auto" aria-label="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>"></video>
        <div id="player-feedback" class="d-none position-absolute z-2 top-50 start-50 translate-middle text-center p-3 bg-black bg-opacity-75 rounded" dir="rtl" role="alert">
            <i class="bi bi-exclamation-circle text-primary fs-2" aria-hidden="true"></i>
            <p id="player-feedback-message" class="text-white mt-2 mb-3"></p>
            <button id="retry-playback" type="button" class="btn btn-primary btn-sm"><i class="bi bi-arrow-clockwise me-1" aria-hidden="true"></i><?= $isRtl ? 'إعادة محاولة التشغيل' : 'Retry playback' ?></button>
        </div>
    </section>

    <div id="download-status" class="alert alert-secondary d-none mt-3" role="status" aria-live="polite"></div>

    <section class="player-info-section d-flex flex-wrap justify-content-between align-items-start gap-4">
        <div class="d-flex align-items-start gap-3 min-w-0">
            <?php if ($icon !== ''): ?><img src="<?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?>" alt="" class="rounded" width="64" height="80" style="object-fit:cover"><?php endif; ?>
            <div class="min-w-0">
                <h1 id="current-stream-title" class="h3 fw-bold mb-2 text-break"><?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?></h1>
                <div class="small text-secondary d-flex flex-wrap align-items-center gap-2"><span><?= htmlspecialchars($typeLabels[$type], ENT_QUOTES, 'UTF-8') ?></span><span aria-hidden="true">·</span><span><?= $isRtl ? 'المحتوى من المصدر الذي اخترته' : 'Content from the selected source' ?></span></div>
            </div>
        </div>
        <?php if (in_array($type, ['movie', 'vod'], true)): ?>
            <?php if ($hasPaid && $streamId !== '' && strpos($serverId, 'm3u_') !== 0): ?>
                <button type="button" class="btn btn-primary trigger-download-btn" data-id="<?= htmlspecialchars($streamId, ENT_QUOTES, 'UTF-8') ?>" data-type="movie" data-title="<?= htmlspecialchars($displayTitle, ENT_QUOTES, 'UTF-8') ?>" data-ext="<?= htmlspecialchars($extension, ENT_QUOTES, 'UTF-8') ?>" data-server-id="<?= htmlspecialchars($serverId, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-download me-2" aria-hidden="true"></i><?= $isRtl ? 'تنزيل الملف' : 'Download file' ?></button>
            <?php elseif (!$hasPaid): ?>
                <a class="btn btn-outline-secondary" href="subscriptions.php"><i class="bi bi-lock me-2" aria-hidden="true"></i><?= $isRtl ? 'يتطلب التنزيل اشتراكًا مدفوعًا' : 'Downloads require a paid plan' ?></a>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="player-detail-layout mt-4">
        <div class="min-w-0">
            <?php if (in_array($type, ['live', 'm3u_direct'], true)): ?>
                <section aria-labelledby="epg-title">
                    <div class="player-section-heading"><h2 id="epg-title" class="h5 fw-semibold mb-1"><?= $isRtl ? 'دليل البرامج' : 'Program guide' ?></h2><p class="small text-muted mb-0"><?= $isRtl ? 'معلومات الدليل تظهر عند توفرها ولا تمنع التشغيل.' : 'Guide information appears when available and does not block playback.' ?></p></div>
                    <div id="epg-container" class="py-2" aria-live="polite"><p class="small text-secondary mb-0"><?= $isRtl ? 'جارٍ جلب معلومات الدليل…' : 'Loading guide information…' ?></p></div>
                </section>
            <?php else: ?>
                <section aria-labelledby="details-title">
                    <div class="player-section-heading"><h2 id="details-title" class="h5 fw-semibold mb-1"><?= $isRtl ? 'تفاصيل المحتوى' : 'About this title' ?></h2></div>
                    <div id="epg-container" class="py-2" aria-live="polite"><p class="small text-secondary mb-0"><?= $isRtl ? 'التفاصيل تظهر عند توفرها من المصدر.' : 'Details appear when available from the source.' ?></p></div>
                </section>
            <?php endif; ?>
        </div>
        <?php if ($type === 'series'): ?>
            <aside aria-labelledby="episodes-title">
                <div class="player-section-heading"><h2 id="episodes-title" class="h5 fw-semibold mb-1"><?= $isRtl ? 'المواسم والحلقات' : 'Seasons and episodes' ?></h2><p class="small text-muted mb-0"><?= $isRtl ? 'اختر حلقة لبدء تشغيلها.' : 'Choose an episode to play.' ?></p></div>
                <div id="series-episodes-list" class="player-episodes" aria-live="polite"><p class="small text-secondary p-3"><?= $isRtl ? 'جارٍ تحميل الحلقات…' : 'Loading episodes…' ?></p></div>
            </aside>
        <?php endif; ?>
    </section>
</main>

<div class="modal fade" id="downloadResolutionModal" tabindex="-1" aria-labelledby="downloadModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><div><h2 class="modal-title h5 fw-bold mb-1" id="downloadModalTitle"><i class="bi bi-download me-2 text-primary" aria-hidden="true"></i><?= $isRtl ? 'تنزيل الملف' : 'Download file' ?></h2><p id="downloadItemName" class="small text-secondary mb-0"></p></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= $isRtl ? 'إغلاق' : 'Close' ?>"></button></div>
        <div class="modal-body"><div class="d-flex gap-3 border border-secondary p-3 rounded"><i class="bi bi-info-circle text-secondary mt-1" aria-hidden="true"></i><p class="small text-secondary mb-0"><?= $isRtl ? 'سيتم تنزيل النسخة التي يوفرها المصدر. لا يقوم التطبيق بتحويل الملف إلى دقة أخرى.' : 'The source-provided file will be downloaded. The app does not convert it to another resolution.' ?></p></div><div id="download-error" class="alert alert-danger d-none mt-3" role="alert"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= $isRtl ? 'إلغاء' : 'Cancel' ?></button><button type="button" class="btn btn-primary download-source-option"><i class="bi bi-download me-2" aria-hidden="true"></i><?= $isRtl ? 'تنزيل جودة المصدر' : 'Download source quality' ?></button></div>
    </div></div>
</div>

<script>
window.PLAYER_CONFIG = <?= json_encode([
    'serverId' => $serverId,
    'type' => $type,
    'streamId' => $streamId,
    'seriesId' => $seriesId,
    'extension' => $extension,
    'directKey' => $directKey,
    'title' => $displayTitle,
    'hasPaid' => $hasPaid,
    'isRtl' => $isRtl,
    'returnUrl' => $backHref,
    'messages' => $isRtl ? [
        'loading' => 'جارٍ تحميل البث…', 'autoplayBlocked' => 'اضغط تشغيل لبدء المشاهدة.', 'playbackError' => 'تعذر تحميل البث من المصدر.', 'retry' => 'إعادة محاولة التشغيل', 'epgEmpty' => 'لا تتوفر بيانات دليل البرامج لهذه القناة حاليًا.', 'detailsEmpty' => 'لا تتوفر تفاصيل إضافية لهذا المحتوى.', 'episodesLoading' => 'جارٍ تحميل الحلقات…', 'episodesEmpty' => 'لا توجد حلقات متاحة لهذا المسلسل.', 'episodesError' => 'تعذر تحميل الحلقات.', 'season' => 'الموسم', 'episode' => 'الحلقة', 'play' => 'تشغيل', 'source' => 'المصدر', 'noTitle' => 'بدون عنوان', 'sourceUnavailable' => 'تعذر الحصول على رابط التشغيل من المصدر.'
    ] : [
        'loading' => 'Loading stream…', 'autoplayBlocked' => 'Press play to start watching.', 'playbackError' => 'The source could not load this stream.', 'retry' => 'Retry playback', 'epgEmpty' => 'No program guide information is available for this channel right now.', 'detailsEmpty' => 'No additional details are available for this title.', 'episodesLoading' => 'Loading episodes…', 'episodesEmpty' => 'No episodes are available for this series.', 'episodesError' => 'Episodes could not be loaded.', 'season' => 'Season', 'episode' => 'Episode', 'play' => 'Play', 'source' => 'Source', 'noTitle' => 'Untitled', 'sourceUnavailable' => 'The source did not provide a playback URL.'
    ]
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/i18n.js"></script>
<script src="https://vjs.zencdn.net/8.3.0/video.min.js"></script>
<script src="assets/js/player.js"></script>
<script src="assets/js/download.js"></script>
</body>
</html>
