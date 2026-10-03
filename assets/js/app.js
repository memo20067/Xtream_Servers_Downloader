// assets/js/app.js - Main Application Controller for Xtream IPTV

document.addEventListener('DOMContentLoaded', () => {
    let activeTab = 'live';
    let currentServerId = document.getElementById('server-select') ? document.getElementById('server-select').value : null;
    let loadedItems = [];
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
            seriesDetailView.classList.add('d-none');
            contentGrid.classList.remove('d-none');
            loadTabContent();
        });
    }

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

    // Initial Load
    if (currentServerId) {
        loadTabContent();
    } else {
        contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-muted">${t('error_loading')}</div>`;
    }

    async function loadTabContent() {
        if (!currentServerId) return;
        showLoading();

        // 1. Fetch Categories
        let catAction = 'get_live_categories';
        if (activeTab === 'movies') catAction = 'get_vod_categories';
        if (activeTab === 'series') catAction = 'get_series_categories';

        try {
            const catRes = await fetch(`api/proxy.php?server_id=${currentServerId}&action=${catAction}`);
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

        let streamAction = 'get_live_streams';
        if (activeTab === 'movies') streamAction = 'get_vod_streams';
        if (activeTab === 'series') streamAction = 'get_series';

        let url = `api/proxy.php?server_id=${currentServerId}&action=${streamAction}`;
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
        const filtered = loadedItems.filter(item => {
            const name = (item.name || item.title || '').toLowerCase();
            return name.includes(query);
        });

        renderGrid(filtered);
    }

    function renderGrid(items) {
        contentGrid.innerHTML = '';

        if (!items || items.length === 0) {
            contentGrid.innerHTML = `<div class="col-12 text-center py-5 text-muted">${t('no_content')}</div>`;
            return;
        }

        items.forEach(item => {
            const col = document.createElement('div');
            col.className = 'col-6 col-sm-4 col-md-3 col-lg-2';

            const title = item.name || item.title || 'Untitled';
            const icon = item.stream_icon || item.cover || 'https://via.placeholder.com/300x400?text=No+Cover';
            const id = item.stream_id || item.series_id;
            const containerExt = item.container_extension || 'mp4';

            let actionButtons = '';
            if (activeTab === 'live') {
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
                        <img src="${icon}" class="media-poster" alt="${title}" onerror="this.src='https://via.placeholder.com/300x400?text=No+Cover'">
                    </div>
                    <div class="media-card-body">
                        <div class="media-title" title="${title}">${title}</div>
                        ${actionButtons}
                    </div>
                </div>
            `;

            contentGrid.appendChild(col);
        });

        // Attach event listeners
        document.querySelectorAll('.play-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                const type = btn.getAttribute('data-type');
                const ext = btn.getAttribute('data-ext') || 'mp4';
                playStream(id, type, ext);
            });
        });

        document.querySelectorAll('.series-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const id = btn.getAttribute('data-id');
                const title = decodeURIComponent(btn.getAttribute('data-title'));
                openSeriesDetails(id, title);
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

            const dlUrl = `api/proxy.php?server_id=${currentServerId}&action=download_stream&type=${type}&stream_id=${id}&resolution=${res}&title=${encodeURIComponent(title)}&container_extension=mp4`;

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

    async function playStream(id, type, ext = 'mp4') {
        let fetchUrl = `api/proxy.php?server_id=${currentServerId}&action=get_stream_url&type=${type}&stream_id=${id}&container_extension=${ext}`;
        try {
            const res = await fetch(fetchUrl);
            const data = await res.json();

            if (data.stream_url) {
                const streamUrl = data.stream_url;
                const mimeType = (type === 'live' || streamUrl.includes('.m3u8')) ? 'application/x-mpegURL' : 'video/mp4';

                playerInstance.src({ src: streamUrl, type: mimeType });
                
                const playerModal = new bootstrap.Modal(document.getElementById('playerModal'));
                playerModal.show();
                playerInstance.play();
            }
        } catch (err) {
            console.error('Error fetching stream URL:', err);
        }
    }

    async function openSeriesDetails(seriesId, title) {
        showLoading();
        document.getElementById('series-title').textContent = title;

        try {
            const res = await fetch(`api/proxy.php?server_id=${currentServerId}&action=get_series_info&series_id=${seriesId}`);
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
