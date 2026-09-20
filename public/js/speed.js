document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.menu-toggle');
    const nav = document.querySelector('#site-menu');

    menu?.addEventListener('click', () => {
        const open = nav.classList.toggle('is-open');
        menu.setAttribute('aria-expanded', String(open));
    });

    const search = document.querySelector('[data-server-search]');
    const cards = [...document.querySelectorAll('[data-server-card]')];
    const count = document.querySelector('[data-result-count]');
    const empty = document.querySelector('[data-no-results]');

    const updateResultCount = (visible) => {
        if (count) count.textContent = `${visible} server${visible === 1 ? '' : 's'}`;
        empty?.classList.toggle('is-hidden', visible > 0);
    };

    updateResultCount(cards.length);

    search?.addEventListener('input', () => {
        const term = search.value.trim().toLowerCase();
        let visible = 0;

        cards.forEach((card) => {
            const matches = !term || card.dataset.name.includes(term);
            card.hidden = !matches;
            if (matches) visible += 1;
        });

        updateResultCount(visible);
    });

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
