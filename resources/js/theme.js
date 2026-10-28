// Light/dark theme toggle.
//
// Bootstrap 5.3 reads `data-bs-theme` from <html>. The chosen theme is a
// per-browser convenience, so it lives in localStorage, which can throw or
// return nothing in private windows or with site data blocked. Every access is
// therefore guarded and the page must render correctly without it.

const STORAGE_KEY = 'lorine.theme';

function storedTheme() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

function storeTheme(theme) {
    try {
        localStorage.setItem(STORAGE_KEY, theme);
    } catch {
        // Not persistable in this browser; the theme still applies for this page.
    }
}

function preferredTheme() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(theme === 'dark'));
    });
}

applyTheme(storedTheme() ?? preferredTheme());

document.addEventListener('DOMContentLoaded', () => {
    applyTheme(storedTheme() ?? preferredTheme());

    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const next =
                document.documentElement.getAttribute('data-bs-theme') === 'dark'
                    ? 'light'
                    : 'dark';

            applyTheme(next);
            storeTheme(next);
        });
    });
});

// Follow the OS only while the user has made no explicit choice.
window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', () => {
        if (!storedTheme()) {
            applyTheme(preferredTheme());
        }
    });
