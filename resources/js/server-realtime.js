// Real-time server status updates via Laravel Echo
document.addEventListener('DOMContentLoaded', () => {
    if (typeof window.Echo === 'undefined') {
        return;
    }

    // Get server ID from page (either single server page or server list)
    const serverId = document.body.dataset.serverId;
    const serverCards = document.querySelectorAll('[data-server-id]');

    function updateServerCard(serverId, data) {
        const card = document.querySelector(`[data-server-id="${serverId}"]`);
        if (!card) return;

        const statusBadge = card.querySelector('.server-status-badge, .status-badge, [data-status]');
        const playersEl = card.querySelector('[data-players], .players-count');
        const mapEl = card.querySelector('[data-map], .map-name');
        const pingEl = card.querySelector('[data-ping], .response-time');

        if (data.online !== undefined) {
            const isOnline = data.online;
            if (statusBadge) {
                statusBadge.textContent = isOnline ? 'ОНЛАЙН' : 'ОФЛАЙН';
                statusBadge.className = statusBadge.className.replace(/(online|offline|stale|unknown)/g, '') + ' ' + (isOnline ? 'online' : 'offline');
            }
        }

        if (data.players !== undefined && playersEl) {
            playersEl.textContent = `${data.players}/${data.max_players || '—'}`;
        }

        if (data.map !== undefined && mapEl) {
            mapEl.textContent = data.map || '—';
        }

        if (data.response_time !== undefined && pingEl) {
            pingEl.textContent = data.response_time !== null ? `${data.response_time} мс` : '—';
        }
    }

    function updateServerDetailPage(data) {
        const statusBadge = document.querySelector('.status');
        const playersEl = document.querySelector('[data-players] .detail-metrics div:first-child strong, .detail-metrics strong');
        const mapEl = document.querySelector('[data-map] strong, .detail-metrics div:nth-child(2) strong');
        const pingEl = document.querySelector('[data-ping] strong, .detail-metrics div:nth-child(3) strong');

        if (data.online !== undefined && statusBadge) {
            const isOnline = data.online;
            const label = isOnline ? 'ОНЛАЙН' : 'ОФЛАЙН';
            const statusClass = isOnline ? 'online' : 'offline';
            statusBadge.textContent = label;
            statusBadge.className = `status ${statusClass}`;
        }

        if (data.players !== undefined) {
            const maxPlayers = data.max_players || document.querySelector('[data-max-players]')?.textContent || '—';
            const playersEls = document.querySelectorAll('.detail-metrics strong');
            if (playersEls.length > 0) {
                playersEls[0].textContent = `${data.players}/${maxPlayers}`;
            }
        }

        if (data.map !== undefined) {
            const mapEls = document.querySelectorAll('.detail-metrics strong');
            if (mapEls.length > 1) {
                mapEls[1].textContent = data.map || '—';
            }
        }

        if (data.response_time !== undefined) {
            const pingEls = document.querySelectorAll('.detail-metrics strong');
            if (pingEls.length > 2) {
                pingEls[2].textContent = data.response_time !== null ? `${data.response_time} мс` : '—';
            }
        }
    }

    // Listen for all server updates
    const channelAll = window.Echo.channel('server-status-all');
    channelAll.listen('server.status.updated', (data) => {
        console.log('Server status update:', data);
        if (serverId) {
            // Single server detail page
            if (parseInt(serverId) === data.server_id) {
                updateServerDetailPage(data);
            }
        } else {
            // Server list page
            updateServerCard(data.server_id, data);
        }
    });

    // Also listen on specific server channel if on detail page
    if (serverId) {
        const channel = window.Echo.channel(`server-status.${serverId}`);
        channel.listen('server.status.updated', (data) => {
            console.log('Single server status update:', data);
            updateServerDetailPage(data);
        });
    }
});