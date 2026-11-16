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
const preferenceDefaults = { appearance: 'system', accent: 'blue', density: 'comfortable', sidebar: 'expanded' };
const validPreferences = {
    appearance: ['system', 'light', 'dark'],
    accent: ['blue', 'indigo', 'teal', 'graphite'],
    density: ['comfortable', 'compact'],
    sidebar: ['expanded', 'compact'],
};
let preferences = { ...preferenceDefaults };

try {
    const raw = localStorage.getItem('prospectos-preferences');
    const saved = JSON.parse(raw || 'null');
    if (saved && typeof saved === 'object') preferences = { ...preferences, ...saved };
    else {
        const oldTheme = localStorage.getItem('prospectos-theme');
        if (oldTheme === 'light' || oldTheme === 'dark') preferences.appearance = oldTheme;
    }
} catch {}
for (const [key, allowed] of Object.entries(validPreferences)) {
    if (!allowed.includes(preferences[key])) preferences[key] = preferenceDefaults[key];
}

function renderPreferences() {
    const root = document.documentElement;
    const theme = preferences.appearance === 'system'
        ? (themeQuery.matches ? 'dark' : 'light')
        : preferences.appearance;

    root.dataset.appearance = preferences.appearance;
    root.dataset.theme = theme;
    root.dataset.accent = preferences.accent;
    root.dataset.density = preferences.density;
    root.dataset.sidebar = preferences.sidebar;
    themeColor?.setAttribute('content', theme === 'dark' ? '#1c1c1e' : '#f5f5f7');

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const label = theme === 'dark' ? 'Use light theme' : 'Use dark theme';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
    });

    document.querySelectorAll('[data-preference]').forEach((button) => {
        const selected = preferences[button.dataset.preference] === button.dataset.value;
        button.setAttribute(button.getAttribute('role') === 'switch' ? 'aria-checked' : 'aria-pressed', String(selected));
    });
}

function savePreferences() {
    renderPreferences();
    try { localStorage.setItem('prospectos-preferences', JSON.stringify(preferences)); } catch {}
}

document.querySelectorAll('[data-appearance-control]').forEach((control) => {
    const trigger = control.querySelector('[data-appearance-trigger]');
    const panel = control.querySelector('[data-appearance-panel]');

    const close = (returnFocus = false) => {
        panel.hidden = true;
        trigger.setAttribute('aria-expanded', 'false');
        if (returnFocus) trigger.focus();
    };

    trigger.addEventListener('click', () => {
        const isOpening = panel.hidden;
        panel.hidden = !isOpening;
        trigger.setAttribute('aria-expanded', String(isOpening));
        if (isOpening) panel.querySelector('[data-preference]')?.focus();
    });

    control.querySelector('[data-appearance-close]').addEventListener('click', () => close(true));
    control.addEventListener('click', (event) => {
        const button = event.target.closest('[data-preference]');
        if (button) {
            const key = button.dataset.preference;
            if (key === 'sidebar') preferences.sidebar = preferences.sidebar === 'compact' ? 'expanded' : 'compact';
            else preferences[key] = button.dataset.value;
            savePreferences();
        }

        if (event.target.closest('[data-preference-reset]')) {
            preferences = { ...preferenceDefaults };
            savePreferences();
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (!control.contains(event.target) && !panel.hidden) close();
    });
    control.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault();
            close(true);
        }
    });
});

document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        preferences.appearance = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        savePreferences();
    });
});

renderPreferences();
themeQuery.addEventListener('change', () => {
    if (preferences.appearance === 'system') renderPreferences();
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            // The app remains usable when the browser or host disables PWA support.
        });
    });
}
