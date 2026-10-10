(() => {
    const sidebar = document.querySelector('[data-admin-sidebar]');
    const backdrop = document.querySelector('[data-admin-backdrop]');

    const toggleSidebar = (open) => {
        sidebar?.classList.toggle('is-open', open);
        backdrop?.classList.toggle('is-open', open);
    };

    document.querySelector('[data-admin-open]')?.addEventListener('click', () => toggleSidebar(true));
    document.querySelector('[data-admin-close]')?.addEventListener('click', () => toggleSidebar(false));
    backdrop?.addEventListener('click', () => toggleSidebar(false));
    document.addEventListener('keydown', (event) => event.key === 'Escape' && toggleSidebar(false));

    document.querySelector('[data-admin-theme]')?.addEventListener('click', () => {
        const dark = document.documentElement.classList.toggle('dark');

        try {
            localStorage.setItem('neo-admin-theme', dark ? 'dark' : 'light');
        } catch (error) {
        }
    });

    document.addEventListener('submit', (event) => {
        const message = event.target instanceof HTMLFormElement ? event.target.dataset.adminConfirm : undefined;

        if (message && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();