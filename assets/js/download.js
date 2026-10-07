// Shared paid download dialog for catalog and player pages.
document.addEventListener('DOMContentLoaded', () => {
    const modalElement = document.getElementById('downloadResolutionModal');
    if (!modalElement || !window.bootstrap) return;

    const modal = bootstrap.Modal.getOrCreateInstance(modalElement);
    const itemName = document.getElementById('downloadItemName');
    const errorBox = document.getElementById('download-error');
    const downloadButton = modalElement.querySelector('.download-source-option');
    let target = null;

    function showError(message) {
        if (!errorBox) return;
        errorBox.textContent = message || (window.t ? t('download_failed') : 'The download could not be started.');
        errorBox.classList.remove('d-none');
    }

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('.trigger-download-btn');
        if (!trigger) return;
        target = {
            serverId: trigger.dataset.serverId || (window.PLAYER_CONFIG && PLAYER_CONFIG.serverId) || '',
            id: trigger.dataset.id || '',
            type: trigger.dataset.type || 'movie',
            title: trigger.dataset.title || '',
            extension: trigger.dataset.ext || 'mp4'
        };
        if (itemName) itemName.textContent = target.title;
        if (errorBox) {
            errorBox.textContent = '';
            errorBox.classList.add('d-none');
        }
        if (downloadButton) downloadButton.disabled = false;
        modal.show();
    });

    downloadButton?.addEventListener('click', async () => {
        if (!target || !target.serverId || !target.id) {
            showError(window.t ? t('download_target_missing') : 'The source item could not be identified.');
            return;
        }
        downloadButton.disabled = true;
        const originalLabel = downloadButton.innerHTML;
        downloadButton.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>${window.t ? t('preparing_download') : 'Preparing download…'}`;
        try {
            const url = new URL('api/unified_proxy.php', window.location.href);
            url.searchParams.set('action', 'download_stream');
            url.searchParams.set('prepare', '1');
            url.searchParams.set('server_id', target.serverId);
            url.searchParams.set('type', target.type);
            url.searchParams.set('stream_id', target.id);
            url.searchParams.set('container_extension', target.extension);
            url.searchParams.set('resolution', 'source');
            url.searchParams.set('title', target.title);

            const response = await fetch(url.toString(), { credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const payload = await response.json();
            if (!response.ok || payload.error || !payload.download_url) {
                throw new Error(payload.error || (window.t ? t('download_failed') : 'The download could not be started.'));
            }

            const link = document.createElement('a');
            link.href = payload.download_url;
            link.download = payload.filename || 'video';
            link.rel = 'noopener';
            document.body.appendChild(link);
            link.click();
            link.remove();
            modal.hide();
            const notice = document.getElementById('download-status');
            if (notice) {
                notice.textContent = window.t ? t('download_started') : 'The source file download has been requested.';
                notice.classList.remove('d-none');
            }
        } catch (error) {
            showError(error.message);
        } finally {
            downloadButton.innerHTML = originalLabel;
            downloadButton.disabled = false;
        }
    });
});
