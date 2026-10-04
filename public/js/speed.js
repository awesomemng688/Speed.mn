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
