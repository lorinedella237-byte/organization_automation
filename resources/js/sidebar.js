// Off-canvas sidebar for small screens.
//
// Plain JS rather than Alpine: the sidebar is part of the shell, so it should
// work on every page without waiting for a component to initialise. Above the
// `lg` breakpoint CSS keeps the sidebar visible and this script stays inert.

const SELECTORS = {
    sidebar: '[data-sidebar]',
    toggle: '[data-sidebar-toggle]',
    backdrop: '[data-sidebar-backdrop]',
};

document.addEventListener('DOMContentLoaded', () => {
    const sidebar = document.querySelector(SELECTORS.sidebar);
    const backdrop = document.querySelector(SELECTORS.backdrop);

    if (!sidebar) {
        return;
    }

    const setOpen = (open) => {
        sidebar.classList.toggle('is-open', open);

        if (backdrop) {
            backdrop.hidden = !open;
        }

        document.querySelectorAll(SELECTORS.toggle).forEach((button) => {
            button.setAttribute('aria-expanded', String(open));
        });
    };

    setOpen(false);

    document.querySelectorAll(SELECTORS.toggle).forEach((button) => {
        button.addEventListener('click', () => {
            setOpen(!sidebar.classList.contains('is-open'));
        });
    });

    backdrop?.addEventListener('click', () => setOpen(false));

    // Escape closes the sidebar, and focus returns to the control that opened it.
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && sidebar.classList.contains('is-open')) {
            setOpen(false);
            document.querySelector(SELECTORS.toggle)?.focus();
        }
    });
});
