// Dedicated page player controller.
document.addEventListener('DOMContentLoaded', () => {
    const config = window.PLAYER_CONFIG;
    const videoElement = document.getElementById('iptv-player');
    if (!config || !videoElement || typeof window.videojs !== 'function') return;

    const message = (key) => (config.messages && config.messages[key]) || key;
    const status = document.getElementById('player-status');
    const feedback = document.getElementById('player-feedback');
    const feedbackMessage = document.getElementById('player-feedback-message');
    const epgContainer = document.getElementById('epg-container');
    const episodesContainer = document.getElementById('series-episodes-list');
    const titleElement = document.getElementById('current-stream-title');
    let activeStreamUrl = '';
    let currentItemType = config.type;
    let currentItemExtension = config.extension;

    const player = videojs(videoElement, {
        controls: true,
        autoplay: false,
        preload: 'auto',
        fluid: true,
        responsive: true,
        playbackRates: [0.75, 1, 1.25, 1.5, 2]
    });

    function setStatus(text) {
        if (status) status.textContent = text || '';
    }

    function showError(text) {
        if (feedbackMessage) feedbackMessage.textContent = text;
        feedback?.classList.remove('d-none');
        setStatus(text);
    }

    function clearError() {
        feedback?.classList.add('d-none');
    }

    function inferMimeType(url, type, extension) {
        const ext = String(extension || '').toLowerCase();
        if (type === 'live' || /\.(m3u8?|ts)(?:$|\?)/i.test(url) || ['m3u', 'm3u8', 'ts'].includes(ext)) return 'application/x-mpegURL';
        if (ext === 'webm') return 'video/webm';
        return 'video/mp4';
    }

    async function startPlayback(url, type, extension) {
        if (!url) {
            showError(message('sourceUnavailable'));
            return;
        }
        currentItemType = type;
        currentItemExtension = extension || config.extension;
        activeStreamUrl = url;
        clearError();
        setStatus(message('loading'));
        player.src({ src: url, type: inferMimeType(url, type, extension) });
        try {
            await player.play();
            setStatus('');
        } catch (error) {
            // Browser autoplay policy is not a stream failure; leave Video.js controls available.
            setStatus(message('autoplayBlocked'));
        }
    }

    async function loadStream() {
        if (config.type === 'series') {
            setStatus(config.isRtl ? 'اختر حلقة لبدء التشغيل.' : 'Choose an episode to start playback.');
            return;
        }
        if (config.type === 'm3u_direct') {
            let directUrl = '';
            try {
                directUrl = sessionStorage.getItem(`xtream:m3u:${config.directKey || ''}`) || '';
            } catch (error) {
                directUrl = '';
            }
            if (!/^https?:\/\//i.test(directUrl)) {
                showError(message('sourceUnavailable'));
                return;
            }
            await startPlayback(directUrl, 'm3u_direct', config.extension);
            return;
        }

        if (!config.serverId || !config.streamId) {
            showError(message('sourceUnavailable'));
            return;
        }

        const url = new URL('api/unified_proxy.php', window.location.href);
        url.searchParams.set('server_id', config.serverId);
        url.searchParams.set('action', 'get_stream_url');
        url.searchParams.set('type', config.type);
        url.searchParams.set('stream_id', config.streamId);
        url.searchParams.set('container_extension', config.extension || 'mp4');
        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || data.error || !data.stream_url) throw new Error(data.error || message('sourceUnavailable'));
            await startPlayback(data.stream_url, config.type, config.extension);
        } catch (error) {
            showError(error.message || message('playbackError'));
        }
    }

    document.getElementById('retry-playback')?.addEventListener('click', () => {
        if (activeStreamUrl) startPlayback(activeStreamUrl, currentItemType, currentItemExtension);
        else loadStream();
    });

    player.on('error', () => {
        const playerError = player.error();
        console.warn('Stream playback error:', playerError ? playerError.message : 'unknown');
        showError(message('playbackError'));
    });
    player.on('playing', () => {
        clearError();
        setStatus('');
    });

    function decodeBase64(value) {
        if (!value) return '';
        try {
            const binary = atob(value);
            const bytes = Uint8Array.from(binary, (char) => char.charCodeAt(0));
            return new TextDecoder('utf-8').decode(bytes);
        } catch (error) {
            return String(value);
        }
    }

    function addTextRow(container, title, description, time) {
        const row = document.createElement('div');
        row.className = 'py-3 border-bottom border-secondary';
        const heading = document.createElement('div');
        heading.className = 'd-flex justify-content-between align-items-start gap-3';
        const titleElement = document.createElement('strong');
        titleElement.className = 'small';
        titleElement.textContent = title;
        heading.appendChild(titleElement);
        if (time) {
            const timeElement = document.createElement('span');
            timeElement.className = 'badge text-bg-dark border border-secondary';
            timeElement.textContent = time;
            heading.appendChild(timeElement);
        }
        row.appendChild(heading);
        if (description) {
            const detail = document.createElement('p');
            detail.className = 'small text-secondary mb-0 mt-2';
            detail.textContent = description;
            row.appendChild(detail);
        }
        container.appendChild(row);
    }

    async function loadItemDetails() {
        if (!epgContainer || config.type === 'm3u_direct' || !config.serverId || !config.streamId) {
            if (epgContainer) epgContainer.textContent = message('epgEmpty');
            return;
        }
        const url = new URL('api/unified_proxy.php', window.location.href);
        url.searchParams.set('server_id', config.serverId);
        url.searchParams.set('action', 'get_epg');
        url.searchParams.set('type', config.type);
        url.searchParams.set(config.type === 'series' ? 'series_id' : 'stream_id', config.type === 'series' ? (config.seriesId || config.streamId) : config.streamId);
        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            epgContainer.replaceChildren();
            const listings = Array.isArray(data.epg_listings) ? data.epg_listings : [];
            if (listings.length) {
                listings.forEach((item) => {
                    const title = decodeBase64(item.title) || message('noTitle');
                    const description = decodeBase64(item.description);
                    const time = [item.start, item.end].filter(Boolean).join(' – ');
                    addTextRow(epgContainer, title, description, time);
                });
                return;
            }
            const info = data.info || data.movie_data || data;
            const detailRows = [
                [config.isRtl ? 'النوع' : 'Genre', info.genre],
                [config.isRtl ? 'تاريخ الإصدار' : 'Release date', info.releasedate || info.releaseDate],
                [config.isRtl ? 'الإخراج' : 'Director', info.director],
                [config.isRtl ? 'طاقم العمل' : 'Cast', info.cast],
                [config.isRtl ? 'الملخص' : 'Synopsis', info.plot || info.description]
            ].filter((row) => row[1]);
            if (detailRows.length) {
                detailRows.forEach(([label, value]) => addTextRow(epgContainer, String(label), String(value), ''));
            } else {
                epgContainer.textContent = config.type === 'live' ? message('epgEmpty') : message('detailsEmpty');
            }
        } catch (error) {
            epgContainer.textContent = config.type === 'live' ? message('epgEmpty') : message('detailsEmpty');
        }
    }

    async function loadEpisodes() {
        if (!episodesContainer || !config.serverId || !(config.seriesId || config.streamId)) return;
        const url = new URL('api/unified_proxy.php', window.location.href);
        url.searchParams.set('server_id', config.serverId);
        url.searchParams.set('action', 'get_series_info');
        url.searchParams.set('series_id', config.seriesId || config.streamId);
        try {
            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || data.error) throw new Error(data.error || message('episodesError'));
            episodesContainer.replaceChildren();
            const episodesBySeason = data.episodes || {};
            const seasons = Object.keys(episodesBySeason);
            if (!seasons.length) {
                episodesContainer.textContent = message('episodesEmpty');
                return;
            }
            seasons.forEach((seasonNumber) => {
                const seasonHeading = document.createElement('h3');
                seasonHeading.className = 'h6 fw-semibold text-secondary mt-3 mb-1';
                seasonHeading.textContent = `${message('season')} ${seasonNumber}`;
                episodesContainer.appendChild(seasonHeading);
                (episodesBySeason[seasonNumber] || []).forEach((episode) => {
                    const episodeTitle = String(episode.title || `${message('episode')} ${episode.episode_num || ''}`).trim();
                    const row = document.createElement('div');
                    row.className = 'episode-link';
                    const playButton = document.createElement('button');
                    playButton.type = 'button';
                    playButton.className = 'btn btn-link text-start text-decoration-none text-white p-0 flex-grow-1';
                    playButton.innerHTML = '<i class="bi bi-play-fill episode-play-icon me-2" aria-hidden="true"></i>';
                    const titleSpan = document.createElement('span');
                    titleSpan.textContent = episodeTitle;
                    playButton.appendChild(titleSpan);
                    playButton.addEventListener('click', async () => {
                        document.querySelectorAll('.episode-link').forEach((element) => element.classList.remove('active'));
                        row.classList.add('active');
                        if (titleElement) titleElement.textContent = episodeTitle;
                        const streamUrl = new URL('api/unified_proxy.php', window.location.href);
                        streamUrl.searchParams.set('server_id', config.serverId);
                        streamUrl.searchParams.set('action', 'get_stream_url');
                        streamUrl.searchParams.set('type', 'series');
                        streamUrl.searchParams.set('stream_id', String(episode.id || ''));
                        streamUrl.searchParams.set('container_extension', String(episode.container_extension || 'mp4'));
                        try {
                            const streamResponse = await fetch(streamUrl.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
                            const streamData = await streamResponse.json();
                            if (!streamResponse.ok || streamData.error || !streamData.stream_url) throw new Error(streamData.error || message('sourceUnavailable'));
                            activeStreamUrl = streamData.stream_url;
                            currentItemType = 'series';
                            currentItemExtension = episode.container_extension || 'mp4';
                            clearError();
                            player.src({ src: streamData.stream_url, type: inferMimeType(streamData.stream_url, 'series', episode.container_extension) });
                            player.play().catch(() => setStatus(message('autoplayBlocked')));
                        } catch (error) {
                            showError(error.message || message('playbackError'));
                        }
                    });
                    row.appendChild(playButton);
                    if (config.hasPaid) {
                        const download = document.createElement('button');
                        download.type = 'button';
                        download.className = 'btn btn-outline-secondary btn-sm trigger-download-btn';
                        download.dataset.id = String(episode.id || '');
                        download.dataset.type = 'series';
                        download.dataset.title = episodeTitle;
                        download.dataset.ext = String(episode.container_extension || 'mp4');
                        download.dataset.serverId = config.serverId;
                        download.setAttribute('aria-label', `${message('download')} ${episodeTitle}`);
                        download.innerHTML = '<i class="bi bi-download" aria-hidden="true"></i>';
                        row.appendChild(download);
                    }
                    episodesContainer.appendChild(row);
                });
            });
        } catch (error) {
            episodesContainer.textContent = message('episodesError');
        }
    }

    loadStream();
    if (config.type === 'series') loadEpisodes();
    else loadItemDetails();
});
