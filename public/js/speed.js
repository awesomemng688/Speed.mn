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

    document.addEventListener('click', (event) => {
        const toggle = event.target.closest('[data-player-toggle]');
        const close = event.target.closest('[data-player-close]');
        if (toggle) {
            const modal = toggle.parentElement.querySelector('[data-player-modal]');
            if (modal) {
                modal.hidden = false;
                document.body.classList.add('modal-open');
                modal.querySelector('.player-modal-close')?.focus();
            }
        }
        if (close) {
            const modal = close.closest('[data-player-modal]');
            if (modal) {
                modal.hidden = true;
                document.body.classList.remove('modal-open');
            }
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            document.querySelectorAll('[data-player-modal]:not([hidden])').forEach((modal) => {
                modal.hidden = true;
            });
            document.body.classList.remove('modal-open');
        }
    });
});
