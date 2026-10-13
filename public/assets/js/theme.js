(function () {
    'use strict';

    var STORAGE_KEY = 'tzl-theme';
    var root = document.documentElement;
    var toggle = document.getElementById('theme-toggle');
    var mql = window.matchMedia ? window.matchMedia('(prefers-color-scheme: dark)') : null;

    function currentTheme() {
        return root.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(theme, persist) {
        root.setAttribute('data-theme', theme);
        if (toggle) {
            var isDark = theme === 'dark';
            toggle.setAttribute('aria-pressed', String(isDark));
            toggle.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
        }
        if (persist) {
            try { localStorage.setItem(STORAGE_KEY, theme); } catch (e) { /* private mode etc. */ }
        }
    }

    // Sync the toggle's ARIA state with what the blocking head script applied before paint.
    applyTheme(currentTheme(), false);

    if (toggle) {
        toggle.addEventListener('click', function () {
            applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
        });
    }

    // Until the visitor chooses explicitly, keep following the OS preference.
    if (mql) {
        var onChange = function (e) {
            var explicit = false;
            try { explicit = !!localStorage.getItem(STORAGE_KEY); } catch (err) { /* ignore */ }
            if (!explicit) { applyTheme(e.matches ? 'dark' : 'light', false); }
        };
        if (mql.addEventListener) { mql.addEventListener('change', onChange); }
    }
})();
