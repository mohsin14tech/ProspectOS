import './stimulus_bootstrap.js';
import './styles/app.css';

/*
 * Turbo Drive replaces <body> on every navigation, so all interactions here use
 * document-level delegation or run again on `turbo:load` instead of binding once.
 */

const themeQuery = window.matchMedia('(prefers-color-scheme: dark)');
const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
const preferenceDefaults = { appearance: 'system', accent: 'blue', density: 'comfortable', sidebar: 'expanded' };
const validPreferences = {
    appearance: ['system', 'light', 'dark'],
    accent: ['blue', 'indigo', 'teal', 'graphite'],
    density: ['comfortable', 'compact'],
    sidebar: ['expanded', 'compact'],
};
let preferences = { ...preferenceDefaults };

try {
    const saved = JSON.parse(localStorage.getItem('prospectos-preferences') || 'null');
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
    const theme = preferences.appearance === 'system' ? (themeQuery.matches ? 'dark' : 'light') : preferences.appearance;

    root.dataset.appearance = preferences.appearance;
    root.dataset.theme = theme;
    root.dataset.accent = preferences.accent;
    root.dataset.density = preferences.density;
    root.dataset.sidebar = preferences.sidebar;
    document.querySelector('meta[name="theme-color"]')?.setAttribute('content', theme === 'dark' ? '#0e1014' : '#f4f5f8');

    document.querySelectorAll('[data-theme-toggle]').forEach((toggle) => {
        const label = theme === 'dark' ? 'Use light theme' : 'Use dark theme';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', label);
        toggle.setAttribute('aria-pressed', String(theme === 'dark'));
    });
    document.querySelectorAll('[data-sidebar-toggle]').forEach((toggle) => {
        const label = preferences.sidebar === 'compact' ? 'Expand sidebar' : 'Collapse sidebar';
        toggle.setAttribute('aria-label', label);
        toggle.setAttribute('title', `${label} ( [ )`);
        toggle.setAttribute('aria-expanded', String(preferences.sidebar !== 'compact'));
        const text = toggle.querySelector('.nav-label');
        if (text) text.textContent = preferences.sidebar === 'compact' ? 'Expand' : 'Collapse';
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

/* Theme switches cross-fade instead of flashing, where the browser supports it. */
function withTransition(change) {
    if (document.startViewTransition && !reducedMotion.matches) document.startViewTransition(change);
    else change();
}

function closeAppearance(control, returnFocus = false) {
    const panel = control.querySelector('[data-appearance-panel]');
    const trigger = control.querySelector('[data-appearance-trigger]');
    if (!panel || panel.hidden) return;
    panel.hidden = true;
    trigger?.setAttribute('aria-expanded', 'false');
    if (returnFocus) trigger?.focus();
}

document.addEventListener('click', (event) => {
    const target = event.target instanceof Element ? event.target : null;
    if (!target) return;

    if (target.closest('[data-theme-toggle]')) {
        preferences.appearance = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
        withTransition(savePreferences);
        return;
    }
    if (target.closest('[data-sidebar-toggle]')) {
        preferences.sidebar = preferences.sidebar === 'compact' ? 'expanded' : 'compact';
        savePreferences();
        return;
    }

    const control = target.closest('[data-appearance-control]');
    if (control) {
        const panel = control.querySelector('[data-appearance-panel]');
        const trigger = control.querySelector('[data-appearance-trigger]');
        if (target.closest('[data-appearance-trigger]')) {
            const opening = panel.hidden;
            panel.hidden = !opening;
            trigger.setAttribute('aria-expanded', String(opening));
            if (opening) panel.querySelector('[data-preference]')?.focus();
            return;
        }
        if (target.closest('[data-appearance-close]')) { closeAppearance(control, true); return; }
        const button = target.closest('[data-preference]');
        if (button) {
            const key = button.dataset.preference;
            if (key === 'sidebar') preferences.sidebar = preferences.sidebar === 'compact' ? 'expanded' : 'compact';
            else preferences[key] = button.dataset.value;
            key === 'appearance' ? withTransition(savePreferences) : savePreferences();
        }
        if (target.closest('[data-preference-reset]')) {
            preferences = { ...preferenceDefaults };
            withTransition(savePreferences);
        }
        return;
    }

    const mascot = target.closest('.mascot');
    if (mascot && !reducedMotion.matches) {
        mascot.classList.remove('is-waving');
        void mascot.getBoundingClientRect();
        mascot.classList.add('is-waving');
    }
});

document.addEventListener('pointerdown', (event) => {
    document.querySelectorAll('[data-appearance-control]').forEach((control) => {
        if (!control.contains(event.target)) closeAppearance(control);
    });
});
document.addEventListener('keydown', (event) => {
    // "[" toggles the sidebar, as in Linear; ignored while typing.
    if (event.key === '[' && !event.ctrlKey && !event.metaKey && !event.altKey
        && !(event.target instanceof Element && event.target.closest('input, textarea, select, [contenteditable="true"]'))
        && document.querySelector('[data-sidebar-toggle]')) {
        preferences.sidebar = preferences.sidebar === 'compact' ? 'expanded' : 'compact';
        savePreferences();
        return;
    }
    if (event.key !== 'Escape') return;
    document.querySelectorAll('[data-appearance-control]').forEach((control) => closeAppearance(control, true));
});
document.addEventListener('animationend', (event) => {
    if (event.animationName === 'mascot-wave') event.target.closest('.mascot')?.classList.remove('is-waving');
});

/* The mascot's eye follows the pointer, a few SVG units at most. */
let pointer = null;
let eyeFrame = 0;
function moveEyes() {
    eyeFrame = 0;
    if (!pointer || reducedMotion.matches) return;
    document.querySelectorAll('[data-mascot-track]').forEach((svg) => {
        const box = svg.getBoundingClientRect();
        if (!box.width) return;
        const dx = pointer.x - (box.left + box.width * 0.55);
        const dy = pointer.y - (box.top + box.height * 0.58);
        const distance = Math.hypot(dx, dy) || 1;
        const reach = Math.min(1, distance / 240) * 24;
        const eye = svg.querySelector('.mascot-eye-socket');
        if (eye) eye.style.transform = `translate(${(dx / distance) * reach}px, ${(dy / distance) * reach}px)`;
    });
}
document.addEventListener('pointermove', (event) => {
    pointer = { x: event.clientX, y: event.clientY };
    if (!eyeFrame) eyeFrame = requestAnimationFrame(moveEyes);
}, { passive: true });

/* Dashboard figures count up once when a page loads. */
function countUp() {
    if (reducedMotion.matches) return;
    document.querySelectorAll('[data-count-up]').forEach((element) => {
        const target = Number.parseInt(element.textContent.trim(), 10);
        if (!Number.isFinite(target) || target <= 0) return;
        const started = performance.now();
        const duration = Math.min(900, 300 + target * 40);
        const step = (now) => {
            const progress = Math.min(1, (now - started) / duration);
            element.textContent = String(Math.round(target * (1 - (1 - progress) ** 3)));
            if (progress < 1) requestAnimationFrame(step);
        };
        element.textContent = '0';
        requestAnimationFrame(step);
    });
}

function onPageLoad() {
    renderPreferences();
    countUp();
}

renderPreferences();
document.addEventListener('turbo:load', onPageLoad);
if (!window.Turbo) document.addEventListener('DOMContentLoaded', onPageLoad);
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
