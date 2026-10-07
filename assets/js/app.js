// Source-scoped catalog controller (PHP templates + Bootstrap + Fetch API).
document.addEventListener('DOMContentLoaded', () => {
    const config = window.CATALOG_CONFIG || {};
    const contentGrid = document.getElementById('content-grid');
    const serverSelect = document.getElementById('server-select');
    const categorySelect = document.getElementById('category-select');
    const searchInput = document.getElementById('search-input');
    const resultsTitle = document.getElementById('results-title');
    const resultsContext = document.getElementById('results-context');
    const resultsCount = document.getElementById('results-count');
    const searchScopeName = document.getElementById('search-scope-name');
    const resultsSentinel = document.getElementById('results-sentinel');
    const seriesDetailView = document.getElementById('series-detail-view');
    const seriesSeasonsContainer = document.getElementById('series-seasons-container');
    const state = {
        source: config.source || (serverSelect && serverSelect.value) || '',
        tab: ['live', 'movies', 'series'].includes(config.tab) ? config.tab : 'live',
        items: [],
        filtered: [],
        rendered: 0,
        batchSize: 30,
        loadToken: 0,
        category: config.category || '',
        query: config.query || '',
        observer: null
    };

    if (!contentGrid || !serverSelect) return;
    if (searchInput) searchInput.value = state.query;
    serverSelect.value = state.source;

    function text(key) {
        return typeof window.t === 'function' ? window.t(key) : key;
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, (char) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
        })[char]);
    }

    function currentSourceName() {
        const option = serverSelect.selectedOptions[0];
        return option ? option.textContent.trim() : text('no_source');
    }

    function updateSourcePresentation() {
        if (searchScopeName) searchScopeName.textContent = currentSourceName();
        document.querySelectorAll('[data-source-type]').forEach((button) => {
            const isCurrent = button.dataset.sourceType === (state.source.startsWith('m3u_') ? 'm3u' : 'xtream');
            button.classList.toggle('active', isCurrent);
            button.setAttribute('aria-pressed', String(isCurrent));
        });
        const availableSourceTypes = new Set([...serverSelect.options].filter((option) => option.value).map((option) => option.value.startsWith('m3u_') ? 'm3u' : 'xtream'));
        document.querySelectorAll('[data-source-type]').forEach((button) => {
            button.classList.toggle('d-none', !availableSourceTypes.has(button.dataset.sourceType));
        });
        document.querySelectorAll('.tab-xtream-only').forEach((element) => {
            element.classList.toggle('d-none', state.source.startsWith('m3u_'));
        });
        if (state.source.startsWith('m3u_') && state.tab !== 'live') {
            state.tab = 'live';
            document.querySelectorAll('.tab-link').forEach((link) => link.classList.toggle('active', link.dataset.tab === 'live'));
        }
        document.querySelectorAll('.tab-link').forEach((link) => {
            const active = link.dataset.tab === state.tab;
            link.classList.toggle('active', active);
            link.setAttribute('aria-current', active ? 'page' : 'false');
        });
        if (resultsTitle) resultsTitle.textContent = `${text(`tab_${state.tab}`)} · ${text('catalog_results')}`;
        if (resultsContext) resultsContext.textContent = `${text('source_label')}: ${currentSourceName()} · ${text('content_type')}: ${text(`tab_${state.tab}`)}`;
    }

    function syncLocation() {
        const url = new URL(window.location.href);
        url.searchParams.set('source', state.source);
        url.searchParams.set('tab', state.tab);
        if (state.query) url.searchParams.set('q', state.query); else url.searchParams.delete('q');
        if (state.category) url.searchParams.set('category', state.category); else url.searchParams.delete('category');
        window.history.replaceState({}, '', url);
    }

    function apiUrl(path, params) {
        const url = new URL(path, window.location.href);
        Object.entries(params || {}).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') url.searchParams.set(key, value);
        });
        return url.toString();
    }

    async function fetchJson(url) {
        const response = await fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const payload = await response.json();
        if (!response.ok || (payload && payload.error)) {
            throw new Error(payload && payload.error ? payload.error : text('error_loading'));
        }
        return payload;
    }

    function setLoading() {
        contentGrid.setAttribute('aria-busy', 'true');
        contentGrid.innerHTML = `<div class="catalog-state" role="status"><span class="catalog-state-icon"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></span>${escapeHtml(text('loading'))}</div>`;
    }

    function setError(error) {
        contentGrid.setAttribute('aria-busy', 'false');
        contentGrid.innerHTML = `<div class="catalog-state" role="alert"><span class="catalog-state-icon"><i class="bi bi-exclamation-circle" aria-hidden="true"></i></span><p>${escapeHtml(error || text('error_loading'))}</p><button type="button" class="btn btn-outline-secondary mt-2" data-action="retry-load">${escapeHtml(text('retry'))}</button></div>`;
    }

    function populateCategories(categories) {
        if (!categorySelect) return;
        const previousValue = state.category || categorySelect.value;
        categorySelect.replaceChildren();
        const allOption = document.createElement('option');
        allOption.value = '';
        allOption.textContent = text('all_categories');
        categorySelect.appendChild(allOption);
        (Array.isArray(categories) ? categories : []).forEach((category) => {
            const option = document.createElement('option');
            option.value = String(category.category_id ?? '');
            option.textContent = String(category.category_name ?? '');
            categorySelect.appendChild(option);
        });
        categorySelect.value = [...categorySelect.options].some((option) => option.value === previousValue) ? previousValue : '';
        state.category = categorySelect.value;
    }

    async function loadM3u() {
        const playlistId = state.source.replace('m3u_', '');
        const data = await fetchJson(apiUrl('api/proxy.php', { action: 'get_m3u_content', m3u_id: playlistId }));
        populateCategories(data.categories || []);
        return Array.isArray(data.channels) ? data.channels : [];
    }

    async function loadXtream() {
        const serverId = state.source.replace('xtream_', '');
        if (!serverId) return [];
        const categoryAction = state.tab === 'movies' ? 'get_vod_categories' : (state.tab === 'series' ? 'get_series_categories' : 'get_live_categories');
        const categories = await fetchJson(apiUrl('api/proxy.php', { server_id: serverId, action: categoryAction }));
        if (!Array.isArray(categories)) throw new Error(text('error_loading'));
        populateCategories(categories);

        const action = state.tab === 'movies' ? 'get_vod_streams' : (state.tab === 'series' ? 'get_series' : 'get_live_streams');
        const items = await fetchJson(apiUrl('api/proxy.php', { server_id: serverId, action, category_id: state.category }));
        return Array.isArray(items) ? items : [];
    }

    function filterAndRender() {
        state.query = (searchInput ? searchInput.value : '').trim();
        state.category = categorySelect ? categorySelect.value : '';
        const query = state.query.toLocaleLowerCase();
        state.filtered = state.items.filter((item) => {
            const name = String(item.name || item.title || '').toLocaleLowerCase();
            const categoryMatches = !state.category || String(item.category_id || '') === state.category;
            return name.includes(query) && categoryMatches;
        });
        state.rendered = 0;
        contentGrid.replaceChildren();
        contentGrid.setAttribute('aria-busy', 'false');
        if (resultsCount) resultsCount.textContent = state.filtered.length ? `${state.filtered.length} ${text('results_label')}` : '';
        syncLocation();
        appendNextBatch();
    }

    function imageUrlFor(item) {
        const raw = String(item.stream_icon || item.cover || item.icon || '').trim();
        if (!raw || !/^https?:\/\//i.test(raw)) return '';
        const cleanId = state.source.replace(/^(xtream_|m3u_)/, '');
        const mediaType = state.tab === 'movies' ? 'movies' : (state.tab === 'series' ? 'series' : 'live');
        return apiUrl('api/cache_image.php', { url: raw, server: `server_${cleanId}`, type: mediaType });
    }

    function playerUrl(item, directKey = '') {
        const isM3u = state.source.startsWith('m3u_');
        const isSeries = state.tab === 'series';
        const itemId = item.stream_id ?? item.series_id ?? item.id ?? '';
        const title = String(item.name || item.title || text('untitled'));
        const params = new URLSearchParams({
            server_id: isM3u ? state.source : state.source.replace('xtream_', ''),
            type: isM3u ? 'm3u_direct' : state.tab === 'movies' ? 'movie' : state.tab,
            title,
            icon: imageUrlFor(item),
            ext: String(item.container_extension || 'mp4'),
            back_source: state.source,
            back_tab: state.tab,
            back_q: state.query,
            back_category: state.category,
            source_name: currentSourceName()
        });
        if (!isM3u) params.set(isSeries ? 'series_id' : 'stream_id', String(itemId));
        if (isM3u && directKey) params.set('direct_key', directKey);
        return `player.php?${params.toString()}`;
    }

    function renderItem(item) {
        const type = state.source.startsWith('m3u_') ? 'live' : state.tab;
        const isLive = type === 'live';
        const isMovie = type === 'movies';
        const isSeries = type === 'series';
        const title = String(item.name || item.title || text('untitled'));
        const imageUrl = imageUrlFor(item);
        const itemId = String(item.stream_id ?? item.series_id ?? item.id ?? '');
        const isM3uSource = state.source.startsWith('m3u_');
        const itemPlayerUrl = isM3uSource ? '' : playerUrl(item);
        const iconClass = isLive ? 'bi-broadcast' : isMovie ? 'bi-film' : 'bi-collection-play';
        const artClass = `result-art${isLive ? '' : ' is-poster'}`;
        let art = imageUrl
            ? `<div class="${artClass}"><img src="${escapeHtml(imageUrl)}" alt="${escapeHtml(title)}" loading="lazy" decoding="async"></div>`
            : `<div class="${artClass}"><span class="result-art-fallback" aria-hidden="true"><i class="bi ${iconClass}"></i></span></div>`;
        let actions = '';
        if (state.source.startsWith('m3u_')) {
            actions = item.url
                ? `<button class="btn result-primary" type="button" data-action="play-m3u" data-result-index="${state.filtered.indexOf(item)}"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>${escapeHtml(text('play_channel'))}</button>`
                : `<button class="btn result-secondary" type="button" disabled><i class="bi bi-lock me-1" aria-hidden="true"></i>${escapeHtml(text('play_channel'))}</button>`;
        } else if (isLive) {
            actions = `<a class="btn result-primary" href="${escapeHtml(itemPlayerUrl)}"><i class="bi bi-play-fill me-1" aria-hidden="true"></i>${escapeHtml(text('play_channel'))}</a>`;
        } else if (isMovie) {
            const downloadAction = document.body.dataset.paid === '1'
                ? `<button class="btn result-secondary trigger-download-btn" type="button" data-id="${escapeHtml(itemId)}" data-type="movie" data-title="${escapeHtml(title)}" data-ext="${escapeHtml(item.container_extension || 'mp4')}" data-server-id="${escapeHtml(state.source.replace('xtream_', ''))}"><i class="bi bi-download me-1" aria-hidden="true"></i>${escapeHtml(text('download'))}</button>`
                : `<a class="btn result-secondary" href="subscriptions.php"><i class="bi bi-lock me-1" aria-hidden="true"></i>${escapeHtml(text('download_plan'))}</a>`;
            actions = `<a class="btn result-primary" href="${escapeHtml(itemPlayerUrl || '#')}" ${itemPlayerUrl ? '' : 'aria-disabled="true"'}><i class="bi bi-play-fill me-1" aria-hidden="true"></i>${escapeHtml(text('play_movie'))}</a>${downloadAction}`;
        } else if (isSeries) {
            actions = `<a class="btn result-primary" href="${escapeHtml(itemPlayerUrl || '#')}" ${itemPlayerUrl ? '' : 'aria-disabled="true"'}><i class="bi bi-collection-play me-1" aria-hidden="true"></i>${escapeHtml(text('view_episodes'))}</a>`;
        }
        const meta = isLive ? text('live_channel') : isMovie ? text('movie_title') : text('series_title');
        const row = document.createElement('article');
        row.className = 'result-row';
        row.innerHTML = `${art}<div class="result-copy"><h3 title="${escapeHtml(title)}">${escapeHtml(title)}</h3><p>${escapeHtml(meta)} · ${escapeHtml(currentSourceName())}</p></div><div class="result-actions">${actions}</div>`;
        row.querySelectorAll('.result-art img').forEach((image) => {
            image.addEventListener('error', () => {
                const artContainer = image.closest('.result-art');
                if (!artContainer) return;
                artContainer.replaceChildren();
                const fallback = document.createElement('span');
                fallback.className = 'result-art-fallback';
                fallback.setAttribute('aria-hidden', 'true');
                fallback.textContent = title.slice(0, 1).toLocaleUpperCase() || '•';
                artContainer.appendChild(fallback);
            }, { once: true });
        });
        return row;
    }

    function appendNextBatch() {
        if (state.filtered.length === 0) {
            const isSearch = state.query !== '';
            const message = isSearch ? text('no_search_results') : state.items.length ? text('no_category_results') : text('no_content');
            contentGrid.innerHTML = `<div class="catalog-state"><span class="catalog-state-icon"><i class="bi ${isSearch ? 'bi-search' : 'bi-inbox'}" aria-hidden="true"></i></span><p>${escapeHtml(message)}</p>${isSearch ? `<button type="button" class="btn btn-outline-secondary btn-sm mt-2" data-action="clear-search">${escapeHtml(text('clear_search'))}</button>` : ''}</div>`;
            return;
        }
        const batch = state.filtered.slice(state.rendered, state.rendered + state.batchSize);
        batch.forEach((item) => contentGrid.appendChild(renderItem(item)));
        state.rendered += batch.length;
    }

    function setActiveTab(tab) {
        state.tab = tab;
        document.querySelectorAll('.tab-link').forEach((link) => {
            const active = link.dataset.tab === tab;
            link.classList.toggle('active', active);
            link.setAttribute('aria-current', active ? 'page' : 'false');
        });
        updateSourcePresentation();
        syncLocation();
        loadContent();
    }

    async function loadContent() {
        if (!state.source) {
            contentGrid.setAttribute('aria-busy', 'false');
            contentGrid.innerHTML = `<div class="catalog-state"><span class="catalog-state-icon"><i class="bi bi-hdd-network" aria-hidden="true"></i></span><p>${escapeHtml(text('no_source'))}</p><button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#addPersonalServerModal">${escapeHtml(text('add_server'))}</button></div>`;
            return;
        }
        const token = ++state.loadToken;
        contentGrid.setAttribute('aria-busy', 'true');
        setLoading();
        try {
            state.items = state.source.startsWith('m3u_') ? await loadM3u() : await loadXtream();
            if (token !== state.loadToken) return;
            updateSourcePresentation();
            filterAndRender();
        } catch (error) {
            if (token !== state.loadToken) return;
            setError(error.message);
        }
    }

    serverSelect.addEventListener('change', () => {
        state.source = serverSelect.value;
        state.query = '';
        state.category = '';
        if (searchInput) searchInput.value = '';
        updateSourcePresentation();
        syncLocation();
        loadContent();
    });

    document.querySelectorAll('[data-source-type]').forEach((button) => {
        button.addEventListener('click', () => {
            const wanted = button.dataset.sourceType === 'm3u' ? 'm3u_' : 'xtream_';
            const option = [...serverSelect.options].find((candidate) => candidate.value.startsWith(wanted));
            if (option) {
                serverSelect.value = option.value;
                serverSelect.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
    });

    document.querySelectorAll('.tab-link').forEach((link) => {
        link.addEventListener('click', (event) => {
            event.preventDefault();
            if (link.dataset.tab === 'movies' || link.dataset.tab === 'series') {
                state.category = '';
                if (categorySelect) categorySelect.value = '';
            }
            setActiveTab(link.dataset.tab);
        });
    });

    if (categorySelect) categorySelect.addEventListener('change', () => {
        state.category = categorySelect.value;
        if (state.source.startsWith('m3u_')) filterAndRender();
        else loadContent();
    });
    if (searchInput) searchInput.addEventListener('input', filterAndRender);
    const clearSearch = () => {
        if (searchInput) {
            searchInput.value = '';
            searchInput.focus();
        }
        filterAndRender();
    };
    document.getElementById('clear-search')?.addEventListener('click', clearSearch);
    document.getElementById('btn-back-series')?.addEventListener('click', () => {
        seriesDetailView.classList.add('d-none');
        document.getElementById('content-grid')?.classList.remove('d-none');
    });

    contentGrid.addEventListener('click', (event) => {
        const actionButton = event.target.closest('[data-action]');
        if (!actionButton) return;
        if (actionButton.dataset.action === 'retry-load') loadContent();
        if (actionButton.dataset.action === 'clear-search') clearSearch();
        if (actionButton.dataset.action === 'play-m3u') {
            const item = state.filtered[Number(actionButton.dataset.resultIndex)];
            if (!item || !item.url) return;
            const key = `m3u-${Date.now()}-${Math.random().toString(36).slice(2)}`;
            try {
                sessionStorage.setItem(`xtream:m3u:${key}`, String(item.url));
                window.location.href = playerUrl(item, key);
            } catch (error) {
                actionButton.disabled = true;
                actionButton.title = text('direct_play_unavailable');
            }
        }
    });

    if (resultsSentinel && 'IntersectionObserver' in window) {
        state.observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => entry.isIntersecting) && state.rendered < state.filtered.length) appendNextBatch();
        }, { rootMargin: '300px 0px' });
        state.observer.observe(resultsSentinel);
    } else {
        window.addEventListener('scroll', () => {
            if (state.rendered < state.filtered.length && window.innerHeight + window.scrollY >= document.body.offsetHeight - 300) appendNextBatch();
        }, { passive: true });
    }

    document.getElementById('addM3uForm')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const alertContainer = document.getElementById('m3uAlertContainer');
        alertContainer.replaceChildren();
        const formData = new FormData();
        formData.append('name', document.getElementById('m3uNameInput').value);
        formData.append('url', document.getElementById('m3uUrlInput').value);
        try {
            const response = await fetch('api/proxy.php?action=add_m3u_playlist', { method: 'POST', credentials: 'same-origin', body: formData });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.error || text('playlist_add_failed'));
            const option = document.createElement('option');
            option.value = `m3u_${data.m3u_id}`;
            option.textContent = `${data.name} · M3U`;
            serverSelect.appendChild(option);
            serverSelect.value = option.value;
            serverSelect.dispatchEvent(new Event('change', { bubbles: true }));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addM3uModal')).hide();
            event.target.reset();
        } catch (error) {
            const alert = document.createElement('div');
            alert.className = 'alert alert-danger mb-0';
            alert.setAttribute('role', 'alert');
            alert.textContent = error.message;
            alertContainer.appendChild(alert);
        }
    });

    window.addEventListener('languageChanged', () => {
        updateSourcePresentation();
        filterAndRender();
    });

    updateSourcePresentation();
    syncLocation();
    loadContent();
});
