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
});
