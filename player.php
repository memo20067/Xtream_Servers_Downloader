<?php
// player.php - Dedicated Video Player & Series Episode Page
require_once __DIR__ . '/includes/auth.php';

if (!isLoggedIn()) {
    header("Location: login.php");
    exit;
}

$currentUser = getCurrentUser();

<<<<<<< feat/installer-subscriptions-profile-8131710756592078168
$serverId  = $_GET['server_id']  ?? null;
$type      = $_GET['type']       ?? 'live'; // live, movie, series, m3u_direct
$streamId  = $_GET['stream_id']   ?? null;
$title     = $_GET['title']      ?? 'IPTV Stream';
$icon      = $_GET['icon']       ?? '';
$ext       = $_GET['ext']        ?? 'mp4';
$seriesId  = $_GET['series_id']  ?? null;
$directUrl = $_GET['direct_url'] ?? null;
=======
$serverId =$_GET['server_id'] ?? null;
$type     =$_GET['type']      ?? 'live'; // live, movie, series
$streamId =$_GET['stream_id']  ?? null;
$title    =$_GET['title']     ?? 'IPTV Stream';
$icon     =$_GET['icon']      ?? '';
$ext      =$_GET['ext']       ?? 'mp4';
$seriesId =$_GET['series_id'] ?? null;
$streamUrl =$_GET['stream_url'] ?? null;
>>>>>>> main

// Determine text direction for RTL/LTR sidebar layout
$lang =$_SESSION['lang'] ?? 'ar';
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
    
    <style>
        /* إزالة الفراغات الزائدة وتطبيق ملاءمة دقيقة للمشغل */
        .video-js-wrapper {
            position: relative;
            width: 100%;
            height: 100%;
        }
        .video-js {
            width: 100% !important;
            height: 100% !important;
            position: absolute;
            top: 0;
            left: 0;
        }
    </style>
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
                <div class="ratio ratio-16x9 rounded overflow-hidden shadow video-js-wrapper">
                    <video id="iptv-player" class="video-js vjs-default-skin vjs-big-play-centered" controls preload="auto">
                    </video>
                </div>
            </div>

            <!-- Title & Channel/Movie Metadata Box below Player -->
            <div class="glass-panel p-4 mb-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
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

            <!-- EPG Program Guide / Overview Section directly below Metadata Box -->
            <div class="glass-panel p-4">
                <h5 class="fw-bold mb-3 text-info border-bottom border-secondary pb-2">
                    <i class="bi bi-calendar2-week me-2"></i><?= $isRtl ? 'جدول البرامج والدليل الإلكتروني (EPG)' : 'Electronic Program Guide (EPG)' ?>
                </h5>
                <div id="epg-container">
                    <div class="text-center py-3 text-white-50">
                        <div class="spinner-border spinner-border-sm text-info me-2" role="status"></div>
                        <small><?= $isRtl ? 'جاري جلب الدليل الإلكتروني للبرامج...' : 'Loading EPG guide...' ?></small>
                    </div>
                </div>
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
<<<<<<< feat/installer-subscriptions-profile-8131710756592078168
    const directUrl = <?= json_encode($directUrl) ?>;
=======
    let initialStreamUrl = <?= json_encode($streamUrl) ?>;
    let currentActiveStream = initialStreamUrl;
>>>>>>> main

    // تم تعطيل fluid وتفعيل responsive لمنع الارتفاعات والمساحات الوهمية بالشرائح
    const player = videojs('iptv-player', {
        controls: true,
        autoplay: true,
        preload: 'auto',
        fluid: false,
        responsive: true
    });

    // Auto-fallback to stream_proxy on playback/CORS errors
    let proxyRetryAttempted = false;
    player.on('error', function() {
        const err = player.error();
        console.warn('Playback error encountered:', err);
        if (!proxyRetryAttempted && currentActiveStream && !currentActiveStream.includes('action=stream_proxy')) {
            proxyRetryAttempted = true;
            console.log('Retrying stream through server proxy fallback...');
            const proxyUrl = `api/proxy.php?action=stream_proxy&url=${encodeURIComponent(currentActiveStream)}`;
            player.src({ src: proxyUrl, type: 'application/x-mpegURL' });
            player.play().catch(e => console.error('Proxy play error:', e));
        }
    });

    function playUrl(url, type = 'live') {
        if (!url) return;
        currentActiveStream = url;
        proxyRetryAttempted = false;
        const mimeType = (type === 'live' || url.includes('.m3u8') || url.includes('.m3u')) ? 'application/x-mpegURL' : 'video/mp4';
        player.src({ src: url, type: mimeType });
        player.play().catch(e => console.log('Autoplay blocked:', e));
    }

    async function loadStream(id, type, extension = 'mp4') {
        if (type === 'm3u_direct' && directUrl) {
            const mimeType = (directUrl.includes('.m3u8') || directUrl.includes('.m3u')) ? 'application/x-mpegURL' : 'video/mp4';
            player.src({ src: directUrl, type: mimeType });
            player.play();
            return;
        }

        let url = `api/proxy.php?server_id=${serverId}&action=get_stream_url&type=${type}&stream_id=${id}&container_extension=${extension}`;
        try {
            const res = await fetch(url);
            const data = await res.json();
            if (data.stream_url) {
                playUrl(data.stream_url, type);
            }
        } catch (err) {
            console.error('Error loading stream:', err);
        }
    }

<<<<<<< feat/installer-subscriptions-profile-8131710756592078168
    if (streamType === 'm3u_direct' && directUrl) {
        loadStream(null, 'm3u_direct');
=======
    if (initialStreamUrl) {
        playUrl(initialStreamUrl, streamType);
>>>>>>> main
    } else if (streamId && streamType !== 'series') {
        loadStream(streamId, streamType, ext);
    }

    // Load EPG Program Guide or Metadata Info
    async function loadEPG(id, type) {
        const container = document.getElementById('epg-container');
        if (!container) return;

        try {
            const res = await fetch(`api/proxy.php?server_id=${serverId}&action=get_epg&type=${type}&stream_id=${id}&series_id=${seriesId || id}`);
            const data = await res.json();

            if (data.epg_listings && Array.isArray(data.epg_listings) && data.epg_listings.length > 0) {
                let epgHtml = '<div class="list-group list-group-flush bg-transparent">';
                data.epg_listings.forEach(item => {
                    const title = item.title ? atob(item.title) : 'Program';
                    const desc = item.description ? atob(item.description) : '';
                    const start = item.start || '';
                    const end = item.end || '';
                    epgHtml += `
                        <div class="list-group-item bg-transparent text-white border-secondary px-0 py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="fw-bold text-info mb-1"><i class="bi bi-clock-history me-1"></i>${title}</h6>
                                <small class="badge bg-secondary">${start} - ${end}</small>
                            </div>
                            ${desc ? `<p class="small text-white-50 mb-0">${desc}</p>` : ''}
                        </div>
                    `;
                });
                epgHtml += '</div>';
                container.innerHTML = epgHtml;
            } else if (data.info && (data.info.plot || data.info.description || data.info.genre)) {
                const info = data.info;
                container.innerHTML = `
                    <div class="text-white">
                        ${info.genre ? `<div class="badge bg-primary me-2 mb-2">${info.genre}</div>` : ''}
                        ${info.releasedate ? `<div class="badge bg-secondary me-2 mb-2">${info.releasedate}</div>` : ''}
                        ${info.director ? `<p class="small text-info mb-1"><i class="bi bi-camera-reels me-1"></i>Director: ${info.director}</p>` : ''}
                        ${info.cast ? `<p class="small text-white-50 mb-2"><i class="bi bi-people me-1"></i>Cast: ${info.cast}</p>` : ''}
                        <p class="mt-2 text-light mb-0">${info.plot || info.description || 'No detailed plot summary available.'}</p>
                    </div>
                `;
            } else {
                container.innerHTML = `<p class="text-white-50 small mb-0"><i class="bi bi-info-circle me-1"></i>No EPG guide or plot details available for this item.</p>`;
            }
        } catch (err) {
            console.error('Error fetching EPG:', err);
            container.innerHTML = `<p class="text-white-50 small mb-0"><i class="bi bi-exclamation-circle me-1"></i>EPG guide unavailable.</p>`;
        }
    }

    if (streamId || seriesId) {
        loadEPG(streamId || seriesId, streamType);
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