document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-map-image]').forEach((image) => {
        image.addEventListener('error', () => {
            if (image.dataset.fallbackSrc && image.src !== image.dataset.fallbackSrc) {
                image.src = image.dataset.fallbackSrc;
                return;
            }

            image.hidden = true;
            const fallback = image.nextElementSibling;
            if (fallback?.classList.contains('server-card-cover-fallback')) fallback.hidden = false;
        });

        if (image.complete && image.naturalWidth === 0) image.dispatchEvent(new Event('error'));
    });

    document.querySelectorAll('[data-copy-address]').forEach((button) => {
        button.addEventListener('click', async () => {
            const address = button.dataset.copyAddress;
            try {
                await navigator.clipboard.writeText(address);
            } catch {
                const input = document.createElement('textarea');
                input.value = address;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.opacity = '0';
                document.body.append(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }

            const originalText = button.textContent;
            button.textContent = 'Хуулагдлаа';
            button.setAttribute('aria-live', 'polite');
            window.setTimeout(() => { button.textContent = originalText; }, 1800);
        });
    });

    const menu = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#site-menu');

    menu?.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        menu.setAttribute('aria-expanded', String(open));
    });

    const accountTrigger = document.querySelector('.account-trigger');
    const accountDropdown = document.querySelector('#account-dropdown');
    accountTrigger?.addEventListener('click', () => {
        const open = accountDropdown.hasAttribute('hidden');
        accountDropdown.toggleAttribute('hidden', !open);
        accountTrigger.setAttribute('aria-expanded', String(open));
    });

    document.addEventListener('click', (event) => {
        if (nav?.classList.contains('is-open') && !event.target.closest('.nav, .menu-toggle')) {
            nav.classList.remove('is-open');
            menu?.setAttribute('aria-expanded', 'false');
        }
        if (accountDropdown && accountTrigger && !event.target.closest('.account-menu')) {
            accountDropdown.setAttribute('hidden', '');
            accountTrigger.setAttribute('aria-expanded', 'false');
        }
    });

    const liveServerElements = [...document.querySelectorAll('[data-server-id], [data-live-server-id]')];
    if (liveServerElements.length) {
        const statusLabels = {
            online: 'ОНЛАЙН',
            offline: 'ОФЛАЙН',
            stale: 'ХУУЧИРСАН',
            unknown: 'МЭДЭЭЛЭЛ АЛГА',
        };
        let refreshInProgress = false;

        const formatServerTime = (value) => new Intl.DateTimeFormat('mn-MN', {
            timeZone: 'Asia/Ulaanbaatar',
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23',
        }).format(new Date(value));

        const updateServerCard = (card, server) => {
            const state = server.status_state || 'unknown';
            const online = state === 'online';
            const status = card.querySelector('.server-card-overlay .status');
            if (status) {
                status.className = `status ${state}`;
                status.replaceChildren(document.createElement('i'), document.createTextNode(statusLabels[state] || statusLabels.unknown));
            }

            const metrics = card.querySelectorAll('.server-metrics > div > strong');
            if (metrics[0]) metrics[0].textContent = `${online ? server.players : state === 'offline' ? 0 : '—'}/${server.max_players ?? '—'}`;
            if (metrics[1]) metrics[1].textContent = online ? (server.map || '—') : '—';
            if (metrics[2]) metrics[2].textContent = online && server.ping ? `${server.ping}мс` : '—';

            const body = card.querySelector('.server-card-body');
            let updated = card.querySelector('.updated-at');
            if (!updated && body) {
                updated = document.createElement('small');
                updated.className = 'updated-at';
                body.append(updated);
            }
            if (updated) {
                const timestamp = server.last_polled_at || server.last_update;
                if (!timestamp) {
                    updated.textContent = 'Статусын мэдээлэл хараахан ирээгүй';
                } else {
                    const time = document.createElement('time');
                    time.dateTime = timestamp;
                    time.textContent = formatServerTime(timestamp);
                    updated.replaceChildren(
                        document.createTextNode(state === 'stale' ? 'Мэдээлэл хуучирсан · сүүлд шалгасан ' : 'Сүүлд шалгасан '),
                        time,
                        document.createTextNode(' (УБ)'),
                    );
                }
            }
        };

        const refreshLiveServers = async () => {
            if (refreshInProgress || document.visibilityState === 'hidden') return;
            refreshInProgress = true;

            try {
                const response = await fetch('/api/servers?per_page=100', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                });
                if (!response.ok) return;

                const payload = await response.json();
                const servers = payload.data || [];
                const serversById = new Map(servers.map((server) => [String(server.id), server]));
                liveServerElements.forEach((element) => {
                    const serverId = element.dataset.serverId || element.dataset.liveServerId;
                    const server = serversById.get(serverId);
                    if (!server) return;

                    if (element.matches('[data-server-card]')) {
                        updateServerCard(element, server);
                        return;
                    }

                    const label = element.querySelector('.hero-server-content small');
                    if (label) {
                        label.textContent = `${statusLabels[server.status_state] || statusLabels.unknown} · ${server.online ? server.players : 0}/${server.max_players} тоглогч`;
                    }
                });

                if ((payload.meta?.total ?? servers.length) !== servers.length) return;

                const total = payload.meta?.total ?? servers.length;
                const online = servers.filter((server) => server.status_state === 'online').length;
                const players = servers.reduce((sum, server) => sum + (server.online ? (server.players || 0) : 0), 0);
                const stale = servers.filter((server) => server.status_state === 'stale').length;
                const unknown = servers.filter((server) => server.status_state === 'unknown').length;
                const stats = document.querySelectorAll('.stats-row > div > strong');
                if (stats[0]) stats[0].textContent = total;
                if (stats[1]) stats[1].textContent = online;
                if (stats[2]) stats[2].textContent = players;

                const notice = document.querySelector('.live-notice');
                if (notice) {
                    const values = notice.querySelectorAll('strong');
                    if (values[0]) values[0].textContent = `${online} сервер онлайн`;
                    if (values[1]) values[1].textContent = `${players} хүн тоглож байна`;
                    const detail = notice.querySelector('small');
                    if (detail) detail.textContent = `${stale > 0 && unknown > 0 ? 'Зарим серверийн мэдээлэл хуучирсан эсвэл алга' : stale > 0 ? 'Зарим серверийн мэдээлэл хуучирсан' : unknown > 0 ? 'Зарим серверийн төлөв ирээгүй' : 'Бүх сервер хэвийн'} · ${stale} серверийн мэдээлэл хуучирсан, ${unknown} серверийн төлөв ирээгүй`;
                }
            } catch {
                // Keep the last known status visible while the API is unavailable.
            } finally {
                refreshInProgress = false;
            }
        };

        refreshLiveServers();
        window.setInterval(refreshLiveServers, 15000);
        document.addEventListener('visibilitychange', refreshLiveServers);
    }

    const closePlayerModal = (modal) => {
        modal.hidden = true;
        modal.setAttribute('hidden', '');
        document.body.classList.remove('modal-open');
    };

    document.querySelectorAll('[data-player-toggle]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const modal = toggle.closest('[data-server-card]')?.querySelector('[data-player-modal]');
            if (!modal) return;

            modal.hidden = false;
            modal.removeAttribute('hidden');
            document.body.classList.add('modal-open');
            modal.querySelector('.player-modal-close')?.focus();
        });
    });

    document.querySelectorAll('[data-player-close]').forEach((close) => {
        close.addEventListener('click', () => {
            const modal = close.closest('[data-player-modal]');
            if (modal) closePlayerModal(modal);
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-player-modal]').forEach(closePlayerModal);
        }
    });
});
