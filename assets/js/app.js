// assets/js/app.js - Main Application Controller for Xtream IPTV

document.addEventListener('DOMContentLoaded', () => {
    let activeTab = 'live';
    let currentServerId = document.getElementById('server-select') ? document.getElementById('server-select').value : null;
    let loadedItems = [];
    let filteredItems = [];
    let displayedCount = 0;
    const BATCH_SIZE = 20;
    let playerInstance = null;

    const contentGrid = document.getElementById('content-grid');
    const categorySelect = document.getElementById('category-select');
    const searchInput = document.getElementById('search-input');
    const serverSelect = document.getElementById('server-select');
    const seriesDetailView = document.getElementById('series-detail-view');
    const btnBackSeries = document.getElementById('btn-back-series');
    const sidebarToggleBtn = document.getElementById('sidebar-toggle-btn');
    const sidebar = document.getElementById('sidebar');

    const hasPaidSub = typeof window.HAS_PAID_SUBSCRIPTION !== 'undefined' ? window.HAS_PAID_SUBSCRIPTION : false;

    // Sidebar Toggle Logic
    if (sidebarToggleBtn && sidebar) {
        sidebarToggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
        });
    }

    // Initialize Video.js
    if (document.getElementById('iptv-player')) {
        playerInstance = videojs('iptv-player', {
            controls: true,
            autoplay: false,
            preload: 'auto',
            fluid: true
        });
    }

    // Server Selection Change
    if (serverSelect) {
        serverSelect.addEventListener('change', (e) => {
            currentServerId = e.target.value;
            updateTabVisibility();
            if (seriesDetailView) seriesDetailView.classList.add('d-none');
            contentGrid.classList.remove('d-none');
            loadTabContent();
        });
    }

    function updateTabVisibility() {
        const isM3u = currentServerId && currentServerId.startsWith('m3u_');
        document.querySelectorAll('.tab-xtream-only').forEach(el => {
            if (isM3u) {
                el.classList.add('d-none');
            } else {
                el.classList.remove('d-none');
            }
        });
        if (isM3u && (activeTab === 'movies' || activeTab === 'series')) {
            activeTab = 'live';
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            const liveTab = document.querySelector('.tab-link[data-tab="live"]');
            if (liveTab) liveTab.classList.add('active');
        }
    }

    updateTabVisibility();

    // Tab Navigation
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            activeTab = link.getAttribute('data-tab');
            seriesDetailView.classList.add('d-none');
            contentGrid.classList.remove('d-none');
            loadTabContent();
        });
    });

    // Category Filter Change
    if (categorySelect) {
        categorySelect.addEventListener('change', () => {
            loadTabStreams(categorySelect.value);
        });
    }

    // Search Input Event
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            filterAndRenderItems();
        });
    }

    // Back to Series Button
    if (btnBackSeries) {
        btnBackSeries.addEventListener('click', () => {
            seriesDetailView.classList.add('d-none');
            contentGrid.classList.remove('d-none');
        });
    }

    // Load M3U Playlists into Selector Dropdown
    loadM3uPlaylists();

    const addM3uForm = document.getElementById('addM3uForm');
    if (addM3uForm) {
        addM3uForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const name = document.getElementById('m3uNameInput').value;
            const url = document.getElementById('m3uUrlInput').value;
            const alertContainer = document.getElementById('m3uAlertContainer');

            try {
                const formData = new FormData();
                formData.append('name', name);
                formData.append('url', url);

                const res = await fetch('api/proxy.php?action=add_m3u_playlist', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    alertContainer.innerHTML = `<div class="alert alert-success">Playlist added successfully!</div>`;
                    await loadM3uPlaylists();
                    if (serverSelect) {
                        serverSelect.value = `m3u_${data.m3u_id}`;
                        currentServerId = `m3u_${data.m3u_id}`;
                        loadTabContent();
                    }
                    setTimeout(() => {
                        const modalEl = document.getElementById('addM3uModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();
                    }, 1000);
                } else {
                    alertContainer.innerHTML = `<div class="alert alert-danger">${data.error || 'Failed to add M3U playlist.'}</div>`;
                }
            } catch (err) {
                console.error(err);
                alertContainer.innerHTML = `<div class="alert alert-danger">Error connecting to server.</div>`;
            }
        });
    }

    async function loadM3uPlaylists() {
        const optgroup = document.getElementById('optgroup-m3u');
        if (!optgroup) return;

        try {
            const res = await fetch('api/proxy.php?action=get_m3u_playlists');
            const playlists = await res.json();

            optgroup.innerHTML = '';
            if (Array.isArray(playlists) && playlists.length > 0) {
                playlists.forEach(pl => {
                    const opt = document.createElement('option');
                    opt.value = `m3u_${pl.id}`;
                    opt.textContent = `${pl.name} (M3U)`;
                    optgroup.appendChild(opt);
                });
            } else {
                optgroup.innerHTML = `<option value="" disabled>No M3U Playlists Added</option>`;
            }
        } catch (err) {
            console.error('Error fetching M3U playlists:', err);
        }
    }

    // Initial Load
    if (currentServerId) {
        loadTabContent();
    } else {
        contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-muted">${t('error_loading')}</div>`;
    }

    async function loadTabContent() {
        if (!currentServerId) return;
        showLoading();

        if (currentServerId.startsWith('m3u_')) {
            const m3uId = currentServerId.replace('m3u_', '');
            try {
                const res = await fetch(`api/proxy.php?action=get_m3u_content&m3u_id=${m3uId}`);
                const data = await res.json();

                categorySelect.innerHTML = `<option value="" data-i18n="all_categories">${t('all_categories')}</option>`;
                if (data.categories && Array.isArray(data.categories)) {
                    data.categories.forEach(cat => {
                        const opt = document.createElement('option');
                        opt.value = cat.category_id;
                        opt.textContent = cat.category_name;
                        categorySelect.appendChild(opt);
                    });
                }

                loadedItems = data.channels || [];
                filterAndRenderItems();
            } catch (err) {
                console.error('Error loading M3U content:', err);
                contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-danger">${t('error_loading')}</div>`;
            }
            return;
        }

        const realServerId = currentServerId.replace('xtream_', '');
        if (!currentServerId) return;
        showLoading();

        const cleanServerId = currentServerId.replace('xtream_', '');

        // 1. Fetch Categories
        let catAction = 'get_live_categories';
        if (activeTab === 'movies') catAction = 'get_vod_categories';
        if (activeTab === 'series') catAction = 'get_series_categories';

        try {
            const catRes = await fetch(`api/proxy.php?server_id=${cleanServerId}&action=${catAction}`);
            const categories = await catRes.json();

            // Populate category dropdown
            categorySelect.innerHTML = `<option value="" data-i18n="all_categories">${t('all_categories')}</option>`;
            if (Array.isArray(categories)) {
                categories.forEach(cat => {
                    const opt = document.createElement('option');
                    opt.value = cat.category_id;
                    opt.textContent = cat.category_name;
                    categorySelect.appendChild(opt);
                });
            }

            // 2. Fetch Streams/Series
            await loadTabStreams('');
        } catch (err) {
            console.error('Error fetching data:', err);
            contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-danger">${t('error_loading')}</div>`;
        }
    }

    async function loadTabStreams(categoryId = '') {
        showLoading();

        if (currentServerId.startsWith('m3u_')) {
            filterAndRenderItems();
            return;
        }

        const realServerId = currentServerId.replace('xtream_', '');
        let streamAction = 'get_live_streams';
        if (activeTab === 'movies') streamAction = 'get_vod_streams';
        if (activeTab === 'series') streamAction = 'get_series';

        let url = `api/proxy.php?server_id=${realServerId}&action=${streamAction}`;
        if (categoryId) {
            url += `&category_id=${categoryId}`;
        }

        try {
            const res = await fetch(url);
            const data = await res.json();

            if (Array.isArray(data)) {
                loadedItems = data;
                filterAndRenderItems();
            } else {
                loadedItems = [];
                contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-muted">${t('no_content')}</div>`;
            }
        } catch (err) {
            console.error('Error loading streams:', err);
            contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-danger">${t('error_loading')}</div>`;
        }
    }

    function filterAndRenderItems() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCategory = categorySelect ? categorySelect.value : '';

        filteredItems = loadedItems.filter(item => {
            const name = (item.name || item.title || '').toLowerCase();
            const matchesQuery = name.includes(query);
            const matchesCat = !selectedCategory || (item.category_id && String(item.category_id) === String(selectedCategory));
            return matchesQuery && matchesCat;
        });

        contentGrid.innerHTML = '';
        displayedCount = 0;
        appendNextBatch();
    }

    // Infinite Scroll Event Listener
    window.addEventListener('scroll', () => {
        if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 400) {
            if (displayedCount < filteredItems.length) {
                appendNextBatch();
            }
        }
    });

    function appendNextBatch() {
        if (!filteredItems || filteredItems.length === 0) {
            contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-muted">${t('no_content')}</div>`;
            return;
        }

        const batch = filteredItems.slice(displayedCount, displayedCount + BATCH_SIZE);
        displayedCount += batch.length;

        renderBatch(batch);
    }

    // Grid Zooming Logic
    const btnZoomOut = document.getElementById('btn-grid-zoom-out');
    const btnZoomReset = document.getElementById('btn-grid-zoom-reset');
    const btnZoomIn = document.getElementById('btn-grid-zoom-in');
    let currentZoomClass = 'grid-col-zoom-md';

    if (btnZoomOut) {
        btnZoomOut.addEventListener('click', () => setGridZoom('grid-col-zoom-sm'));
    }
    if (btnZoomReset) {
        btnZoomReset.addEventListener('click', () => setGridZoom('grid-col-zoom-md'));
    }
    if (btnZoomIn) {
        btnZoomIn.addEventListener('click', () => setGridZoom('grid-col-zoom-lg'));
    }

    function setGridZoom(zoomClass) {
        currentZoomClass = zoomClass;
        document.querySelectorAll('#content-grid > div').forEach(col => {
            col.className = `col-6 col-sm-4 ${zoomClass}`;
        });
    }

    function renderBatch(items) {
        items.forEach(item => {
            const col = document.createElement('div');
            col.className = `col-6 col-sm-4 ${currentZoomClass}`;

            const title = item.name || item.title || 'Untitled';
            let rawIcon = item.stream_icon || item.cover || 'https://via.placeholder.com/300x400?text=No+Cover';
            if (rawIcon.startsWith('http://') || rawIcon.startsWith('https://')) {
                const cleanServer = currentServerId ? currentServerId.replace('xtream_', '').replace('m3u_', '') : 'global';
                const mediaType = activeTab === 'movies' ? 'movies' : (activeTab === 'series' ? 'series' : 'live');
                rawIcon = `api/cache_image.php?url=${encodeURIComponent(rawIcon)}&server=${encodeURIComponent(cleanServer)}&type=${mediaType}`;
            }

            const id = item.stream_id || item.series_id;
            const containerExt = item.container_extension || 'mp4';
            const directUrl = item.url || '';

            let actionButtons = '';
            if (currentServerId.startsWith('m3u_')) {
                actionButtons = `
                    <button class="btn btn-primary btn-sm w-100 mt-2 play-m3u-btn" data-url="${encodeURIComponent(directUrl)}">
                        <i class="bi bi-play-fill me-1"></i>${t('play')}
                    </button>`;
            } else if (activeTab === 'live') {
                actionButtons = `
                    <button class="btn btn-primary btn-sm w-100 mt-2 play-btn" data-id="${id}" data-type="live">
                        <i class="bi bi-play-fill me-1"></i>${t('play')}
                    </button>`;
            } else if (activeTab === 'movies') {
                let downloadBtnHtml = '';
                if (hasPaidSub) {
                    downloadBtnHtml = `
                        <button class="btn btn-outline-success btn-sm trigger-download-btn" data-id="${id}" data-type="movie" data-title="${encodeURIComponent(title)}" data-ext="${containerExt}" title="${t('download')}">
                            <i class="bi bi-download"></i>
                        </button>`;
                } else {
                    downloadBtnHtml = `
                        <button class="btn btn-outline-secondary btn-sm disabled" disabled title="Download requires paid subscription">
                            <i class="bi bi-lock-fill"></i>
                        </button>`;
                }

                actionButtons = `
                    <div class="d-flex gap-1 mt-2">
                        <button class="btn btn-primary btn-sm flex-fill play-btn" data-id="${id}" data-type="movie" data-ext="${containerExt}">
                            <i class="bi bi-play-fill me-1"></i>${t('play')}
                        </button>
                        ${downloadBtnHtml}
                    </div>`;
            } else if (activeTab === 'series') {
                actionButtons = `
                    <button class="btn btn-info btn-sm w-100 mt-2 series-btn" data-id="${id}" data-title="${encodeURIComponent(title)}">
                        <i class="bi bi-list-nested me-1"></i>${t('episodes')}
                    </button>`;
            }

            col.innerHTML = `
                <div class="media-card">
                    <div class="media-poster-wrapper ${activeTab === 'live' ? 'square' : ''}">
                        <img src="${rawIcon}" loading="lazy" class="media-poster" alt="${title}" onerror="this.src='https://via.placeholder.com/300x400?text=No+Cover'">
                    </div>
                    <div class="media-card-body">
                        <div class="media-title" title="${title}">${title}</div>
                        ${actionButtons}
                    </div>
                </div>
            `;

            contentGrid.appendChild(col);
        });

        // Attach event listeners to open dedicated player.php
        document.querySelectorAll('.play-m3u-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const directUrl = decodeURIComponent(btn.getAttribute('data-url'));
                playDirectUrl(directUrl);
            });
        });

        document.querySelectorAll('.play-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                const type = btn.getAttribute('data-type');
                const ext = btn.getAttribute('data-ext') || 'mp4';
                const cleanServerId = currentServerId.replace('xtream_', '').replace('m3u_', '');

                // Find title and icon
                const card = btn.closest('.media-card');
                const title = card ? card.querySelector('.media-title').getAttribute('title') : 'Video';
                const icon = card ? card.querySelector('.media-poster').getAttribute('src') : '';

                window.location.href = `player.php?server_id=${cleanServerId}&type=${type}&stream_id=${id}&title=${encodeURIComponent(title)}&icon=${encodeURIComponent(icon)}&ext=${ext}`;
            });
        });

        document.querySelectorAll('.series-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                const title = decodeURIComponent(btn.getAttribute('data-title'));
                const cleanServerId = currentServerId.replace('xtream_', '').replace('m3u_', '');

                const card = btn.closest('.media-card');
                const icon = card ? card.querySelector('.media-poster').getAttribute('src') : '';

                window.location.href = `player.php?server_id=${cleanServerId}&type=series&series_id=${id}&title=${encodeURIComponent(title)}&icon=${encodeURIComponent(icon)}`;
            });
        });

        document.querySelectorAll('.trigger-download-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                const type = btn.getAttribute('data-type');
                const title = decodeURIComponent(btn.getAttribute('data-title'));
                openDownloadModal(id, type, title);
            });
        });
    }

    let pendingDownloadTarget = null;

    function openDownloadModal(id, type, title) {
        pendingDownloadTarget = { id, type, title };
        const itemNameEl = document.getElementById('downloadItemName');
        if (itemNameEl) {
            itemNameEl.textContent = `Target File: "${title}" (.mp4)`;
        }
        const dlModal = new bootstrap.Modal(document.getElementById('downloadResolutionModal'));
        dlModal.show();
    }

    document.querySelectorAll('.download-res-option').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!pendingDownloadTarget) return;
            const res = btn.getAttribute('data-res');
            const { id, type, title } = pendingDownloadTarget;

            const cleanServerId = currentServerId.replace('xtream_', '').replace('m3u_', '');
            const dlUrl = `api/proxy.php?server_id=${cleanServerId}&action=download_stream&type=${type}&stream_id=${id}&resolution=${res}&title=${encodeURIComponent(title)}&container_extension=mp4`;

            // Trigger file download
            const a = document.createElement('a');
            a.href = dlUrl;
            a.target = '_blank';
            a.download = `${title}.mp4`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);

            // Hide modal
            const dlModalEl = document.getElementById('downloadResolutionModal');
            const dlModal = bootstrap.Modal.getInstance(dlModalEl);
            if (dlModal) dlModal.hide();
        });
    });

    function playDirectUrl(streamUrl) {
        if (!streamUrl) return;
        const mimeType = (streamUrl.includes('.m3u8') || streamUrl.includes('.m3u')) ? 'application/x-mpegURL' : 'video/mp4';
        playerInstance.src({ src: streamUrl, type: mimeType });
        const playerModal = new bootstrap.Modal(document.getElementById('playerModal'));
        playerModal.show();
        playerInstance.play();
    }

    async function playStream(id, type, ext = 'mp4') {
        const realServerId = currentServerId.replace('xtream_', '');
        let fetchUrl = `api/proxy.php?server_id=${realServerId}&action=get_stream_url&type=${type}&stream_id=${id}&container_extension=${ext}`;
        try {
            const res = await fetch(fetchUrl);
            const data = await res.json();

            if (data.stream_url) {
                playDirectUrl(data.stream_url);
            }
        } catch (err) {
            console.error('Error fetching stream URL:', err);
        }
    }

    async function openSeriesDetails(seriesId, title) {
        showLoading();
        document.getElementById('series-title').textContent = title;
        const cleanServerId = currentServerId.replace('xtream_', '').replace('m3u_', '');

        try {
            const res = await fetch(`api/proxy.php?server_id=${cleanServerId}&action=get_series_info&series_id=${seriesId}`);
            const data = await res.json();

            contentGrid.classList.add('d-none');
            seriesDetailView.classList.remove('d-none');

            const container = document.getElementById('series-seasons-container');
            container.innerHTML = '';

            const episodesObj = data.episodes || {};
            const seasons = Object.keys(episodesObj);

            if (seasons.length === 0) {
                container.innerHTML = `<div class="col-12 text-muted py-4">${t('no_content')}</div>`;
                return;
            }

            seasons.forEach(seasonNum => {
                const seasonCol = document.createElement('div');
                seasonCol.className = 'col-12 mb-4';

                const episodesList = episodesObj[seasonNum] || [];
                let episodesHtml = '';

                episodesList.forEach(ep => {
                    const epTitle = ep.title || `Episode ${ep.episode_num}`;
                    const epId = ep.id;
                    const ext = ep.container_extension || 'mp4';

                    let epDownloadBtn = '';
                    if (hasPaidSub) {
                        epDownloadBtn = `
                            <button class="btn btn-outline-success btn-sm trigger-download-btn" data-id="${epId}" data-type="series" data-title="${encodeURIComponent(epTitle)}" data-ext="${ext}" title="${t('download')}">
                                <i class="bi bi-download me-1"></i>${t('download')}
                            </button>`;
                    } else {
                        epDownloadBtn = `
                            <button class="btn btn-outline-secondary btn-sm disabled" disabled title="Download requires paid subscription">
                                <i class="bi bi-lock-fill me-1"></i>${t('download')}
                            </button>`;
                    }

                    episodesHtml += `
                        <div class="episode-item">
                            <div>
                                <span class="fw-bold me-2"><i class="bi bi-play-circle-fill text-primary me-2"></i>${epTitle}</span>
                                <small class="text-muted">(${t('season')} ${seasonNum}, ${t('episode')} ${ep.episode_num})</small>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-primary btn-sm play-ep-btn" data-id="${epId}" data-ext="${ext}">
                                    <i class="bi bi-play-fill me-1"></i>${t('play')}
                                </button>
                                ${epDownloadBtn}
                            </div>
                        </div>
                    `;
                });

                seasonCol.innerHTML = `
                    <div class="card bg-secondary text-white border-0 shadow-sm">
                        <div class="card-header border-bottom border-dark fw-bold">
                            <i class="bi bi-collection-play me-2"></i>${t('season')} ${seasonNum}
                        </div>
                        <div class="card-body p-3">
                            ${episodesHtml}
                        </div>
                    </div>
                `;

                container.appendChild(seasonCol);
            });

            // Attach play & download event handlers for episodes
            document.querySelectorAll('.play-ep-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const epId = btn.getAttribute('data-id');
                    const ext = btn.getAttribute('data-ext');
                    playStream(epId, 'series', ext);
                });
            });

            document.querySelectorAll('#series-seasons-container .trigger-download-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    const id = btn.getAttribute('data-id');
                    const type = btn.getAttribute('data-type');
                    const title = decodeURIComponent(btn.getAttribute('data-title'));
                    openDownloadModal(id, type, title);
                });
            });

        } catch (err) {
            console.error('Error fetching series details:', err);
            seriesDetailView.classList.add('d-none');
            contentGrid.classList.remove('d-none');
            contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-danger">${t('error_loading')}</div>`;
        }
    }

    function showLoading() {
        contentGrid.innerHTML = `
            <div class="col-12 text-center py-5">
                <div class="spinner-border text-primary" role="status"></div>
                <p class="mt-2 text-muted">${t('loading')}</p>
            </div>`;
    }
});
