import './stimulus_bootstrap.js';
/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';

const themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
const themeColor = document.querySelector('meta[name="theme-color"]');

function setTheme(theme, persist = false) {
    document.documentElement.dataset.theme = theme;
    document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
        button.setAttribute('aria-pressed', String(theme === 'dark'));
        button.textContent = theme === 'dark' ? 'Use light theme' : 'Use dark theme';
    });
    themeColor?.setAttribute('content', theme === 'dark' ? '#111827' : '#e9f3ff');
    if (persist) {
        try { localStorage.setItem('prospectos-theme', theme); } catch {}
    }
}

setTheme(document.documentElement.dataset.theme || (themeQuery.matches ? 'dark' : 'light'));
document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        setTheme(document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark', true);
    });
});
themeQuery.addEventListener('change', (event) => {
    try {
        if (localStorage.getItem('prospectos-theme') === null) setTheme(event.matches ? 'dark' : 'light');
    } catch {
        setTheme(event.matches ? 'dark' : 'light');
    }
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // The app remains usable when the browser or host disables PWA support.
        });
    });
}
