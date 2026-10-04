<?php
// player.php - Dedicated Video Player & Series Episode Page
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();

$serverId = $_GET['server_id'] ?? null;
$type     = $_GET['type']      ?? 'live'; // live, movie, series
$streamId = $_GET['stream_id']  ?? null;
$title    = $_GET['title']     ?? 'IPTV Stream';
$icon     = $_GET['icon']      ?? '';
$ext      = $_GET['ext']       ?? 'mp4';
$seriesId = $_GET['series_id'] ?? null;

// Determine text direction for RTL/LTR sidebar layout
$lang = $_SESSION['lang'] ?? 'ar';
$isRtl = ($lang === 'ar');
?>
<!DOCTYPE html>
<html lang="<?= $lang ?>" dir="<?= $isRtl ? 'rtl' : 'ltr' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Xtream IPTV Player</title>

    <!-- Bootstrap CSS & Icons -->
    <?php if ($isRtl): ?>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <?php else: ?>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php endif; ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.3.0/video-js.css" rel="stylesheet" />

    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-dark text-light">

<!-- Top Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark bg-secondary px-4 shadow-sm sticky-top">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold text-primary" href="index.php">
            <i class="bi bi-arrow-left-circle me-2"></i><?= $isRtl ? 'العودة للقائمة الرئيسية' : 'Back to Dashboard' ?>
        </a>
        <div class="d-flex align-items-center gap-3">
            <span class="navbar-text text-white fw-bold"><?= htmlspecialchars($title) ?></span>
        </div>
    </div>
</nav>

<!-- Player Layout Container -->
<div class="container-fluid px-4 py-4">
    <div class="row g-4">
        <!-- Main Video Player & Channel Info Area -->
        <div class="<?= $type === 'series' ? 'col-lg-8 col-xl-9' : 'col-12' ?>">
            <div class="glass-panel p-3 mb-4">
                <div class="ratio ratio-16x9 rounded overflow-hidden shadow">
                    <video id="iptv-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto" width="100%" height="100%">
                    </video>
                </div>
            </div>

            <!-- Title & Channel/Movie Metadata Box below Player -->
            <div class="glass-panel p-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div class="d-flex align-items-center gap-3">
                    <?php if (!empty($icon)): ?>
                        <img src="<?= htmlspecialchars($icon) ?>" alt="Logo" class="rounded img-thumbnail bg-dark" style="width: 70px; height: 70px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded bg-secondary d-flex align-items-center justify-content-center" style="width: 70px; height: 70px;">
                            <i class="bi bi-tv fs-2 text-info"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <h4 class="fw-bold mb-1" id="current-stream-title"><?= htmlspecialchars($title) ?></h4>
                        <small class="text-white-50"><i class="bi bi-tag-fill me-1 text-primary"></i>Type: <?= strtoupper(htmlspecialchars($type)) ?></small>
                    </div>
                </div>

                <!-- Download Button for Movies -->
                <?php if ($type === 'movie' || $type === 'vod'): ?>
                    <div>
                        <?php if (hasPaidSubscription()): ?>
                            <a href="api/proxy.php?server_id=<?= htmlspecialchars($serverId) ?>&action=download_stream&type=movie&stream_id=<?= htmlspecialchars($streamId) ?>&title=<?= urlencode($title) ?>" class="btn btn-neon-blue btn-lg px-4" target="_blank">
                                <i class="bi bi-download me-2"></i><?= $isRtl ? 'تحميل الفيلم (.mp4)' : 'Download Movie (.mp4)' ?>
                            </a>
                        <?php else: ?>
                            <button class="btn btn-outline-secondary btn-lg px-4 disabled" disabled>
                                <i class="bi bi-lock-fill me-2"></i><?= $isRtl ? 'التحميل يتطلب اشتراك مدفوع' : 'Download Requires Paid Subscription' ?>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Vertical Series Episodes Sidebar (Displayed on Right for RTL, Left for LTR if Series) -->
        <?php if ($type === 'series'): ?>
            <div class="col-lg-4 col-xl-3">
                <div class="glass-panel p-3 h-100">
                    <h5 class="fw-bold mb-3 border-bottom border-secondary pb-2">
                        <i class="bi bi-collection-play me-2 text-info"></i><?= $isRtl ? 'قائمة الحلقات' : 'Episodes List' ?>
                    </h5>
                    <div id="series-episodes-list" class="overflow-y-auto" style="max-height: 650px;">
                        <div class="text-center py-4">
                            <div class="spinner-border text-info" role="status"></div>
                            <p class="mt-2 text-white-50 small"><?= $isRtl ? 'جاري تحميل الحلقات...' : 'Loading episodes...' ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JS Libraries -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://vjs.zencdn.net/8.3.0/video.min.js"></script>
<script>
    const serverId = <?= json_encode($serverId) ?>;
    const streamType = <?= json_encode($type) ?>;
    const streamId = <?= json_encode($streamId) ?>;
    const seriesId = <?= json_encode($seriesId) ?>;
    const ext = <?= json_encode($ext) ?>;

    const player = videojs('iptv-player', {
        controls: true,
        autoplay: true,
        preload: 'auto',
        fluid: true
    });

    async function loadStream(id, type, extension = 'mp4') {
        let url = `api/proxy.php?server_id=${serverId}&action=get_stream_url&type=${type}&stream_id=${id}&container_extension=${extension}`;
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.stream_url) {
                const streamUrl = data.stream_url;
                const mimeType = (type === 'live' || streamUrl.includes('.m3u8')) ? 'application/x-mpegURL' : 'video/mp4';
                player.src({ src: streamUrl, type: mimeType });
                player.play();
            }
        } catch (err) {
            console.error('Error loading stream:', err);
        }
    }

    if (streamId && streamType !== 'series') {
        loadStream(streamId, streamType, ext);
    }

    // Load Series Episodes List if series
    if (streamType === 'series' && (seriesId || streamId)) {
        const targetSeriesId = seriesId || streamId;
        fetch(`api/proxy.php?server_id=${serverId}&action=get_series_info&series_id=${targetSeriesId}`)
            .then(res => res.json())
            .then(data => {
                const container = document.getElementById('series-episodes-list');
                container.innerHTML = '';
                const episodesObj = data.episodes || {};
                const seasons = Object.keys(episodesObj);

                if (seasons.length === 0) {
                    container.innerHTML = '<div class="text-white-50 p-3">No episodes found.</div>';
                    return;
                }

                seasons.forEach(seasonNum => {
                    const seasonHeader = document.createElement('div');
                    seasonHeader.className = 'fw-bold text-info mt-3 mb-2 small text-uppercase';
                    seasonHeader.innerHTML = `<i class="bi bi-folder2-open me-1"></i>Season ${seasonNum}`;
                    container.appendChild(seasonHeader);

                    const epList = episodesObj[seasonNum] || [];
                    epList.forEach(ep => {
                        const epItem = document.createElement('a');
                        epItem.href = '#';
                        epItem.className = 'd-block glass-card p-2 mb-2 text-decoration-none text-white small episode-link';
                        epItem.innerHTML = `<i class="bi bi-play-circle-fill text-primary me-2"></i>E${ep.episode_num}: ${ep.title || 'Episode ' + ep.episode_num}`;
                        epItem.onclick = (e) => {
                            e.preventDefault();
                            document.querySelectorAll('.episode-link').forEach(l => l.classList.remove('border-primary'));
                            epItem.classList.add('border-primary');
                            document.getElementById('current-stream-title').textContent = ep.title || `Season ${seasonNum} Episode ${ep.episode_num}`;
                            loadStream(ep.id, 'series', ep.container_extension || 'mp4');
                        };
                        container.appendChild(epItem);
                    });
                });

                // Auto play first episode
                const firstEp = container.querySelector('.episode-link');
                if (firstEp) firstEp.click();
            })
            .catch(err => {
                console.error(err);
                document.getElementById('series-episodes-list').innerHTML = '<div class="text-danger p-3">Failed to load episodes.</div>';
            });
    }
</script>
</body>
</html>
