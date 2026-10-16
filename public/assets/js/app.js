(function () {
    'use strict';

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    /* ---------- font stylesheet loader (shared) ---------- */
    var loaded = {};
    function loadCss(href) {
        if (loaded[href]) { return; }
        loaded[href] = true;
        var l = document.createElement('link');
        l.rel = 'stylesheet'; l.href = href;
        document.head.appendChild(l);
    }

    /* ---------- catalog (home) ---------- */
    var grid = $('#font-grid');
    if (grid) {
        var cards = $$('.font-card', grid);
        var search = $('#font-search'), sortSel = $('#font-sort'), chips = $$('#cat-chips .chip');
        var text = $('#preview-text'), size = $('#preview-size'), sizeOut = $('#preview-size-out');
        var noRes = $('#no-results');
        var cat = 'all';

        var observer = ('IntersectionObserver' in window) ? new IntersectionObserver(function (entries) {
            entries.forEach(function (en) {
                if (!en.isIntersecting) { return; }
                var c = en.target;
                loadCss(c.getAttribute('data-css'));
                $('.font-sample', c).style.fontFamily = "'" + c.getAttribute('data-family') + "', system-ui, sans-serif";
                observer.unobserve(c);
            });
        }, { rootMargin: '300px' }) : null;

        cards.forEach(function (c) {
            if (observer) { observer.observe(c); }
            else { loadCss(c.getAttribute('data-css')); $('.font-sample', c).style.fontFamily = "'" + c.getAttribute('data-family') + "', system-ui, sans-serif"; }
        });

        var apply = function () {
            var q = (search.value || '').trim().toLowerCase(), shown = 0;
            cards.forEach(function (c) {
                var ok = (cat === 'all' || c.getAttribute('data-cat') === cat) && (!q || c.getAttribute('data-search').indexOf(q) !== -1);
                c.hidden = !ok; if (ok) { shown++; }
            });
            if (noRes) { noRes.hidden = shown !== 0; }
        };
        var sort = function () {
            var mode = sortSel.value;
            var list = cards.slice().sort(function (a, b) {
                if (mode === 'newest') { return (+b.getAttribute('data-added')) - (+a.getAttribute('data-added')); }
                if (mode === 'styles') { return (+b.getAttribute('data-styles')) - (+a.getAttribute('data-styles')) || a.getAttribute('data-name').localeCompare(b.getAttribute('data-name')); }
                return a.getAttribute('data-name').localeCompare(b.getAttribute('data-name'));
            });
            list.forEach(function (c) { grid.appendChild(c); });
        };
        search.addEventListener('input', apply);
        sortSel.addEventListener('change', sort);
        chips.forEach(function (ch) {
            ch.addEventListener('click', function () {
                chips.forEach(function (x) { x.classList.remove('is-active'); });
                ch.classList.add('is-active'); cat = ch.getAttribute('data-cat'); apply();
            });
        });
        text.addEventListener('input', function () {
            var v = text.value || 'The quick brown fox jumps over the lazy dog';
            cards.forEach(function (c) { $('.font-sample', c).textContent = v; });
        });
        size.addEventListener('input', function () {
            grid.style.setProperty('--preview-size', size.value + 'px');
            sizeOut.textContent = size.value + 'px';
        });
    }

    /* ---------- font page ---------- */
    var page = $('.font-page');
    if (page) {
        var family = page.getAttribute('data-family'), base = page.getAttribute('data-base'), plus = page.getAttribute('data-plus');
        var hasItalic = page.getAttribute('data-has-italic') === '1';
        var meta = [];
        try { meta = JSON.parse(page.getAttribute('data-styles')); } catch (e) { meta = []; }
        var fam = "'" + family + "', system-ui, sans-serif";

        var title = $('[data-font-title]'); if (title) { title.style.fontFamily = fam; }
        $$('.style-sample').forEach(function (el) {
            el.style.fontFamily = fam;
            el.style.fontWeight = el.getAttribute('data-w');
            el.style.fontStyle = el.getAttribute('data-it') === '1' ? 'italic' : 'normal';
        });
        $$('.glyph-grid').forEach(function (g) { g.style.fontFamily = fam; });

        // specimen
        var prev = $('#spec-preview'), st = $('#spec-text'), ss = $('#spec-size'), so = $('#spec-size-out'), sel = $('#spec-style');
        var paint = function () {
            var m = meta[sel ? +sel.value : 0] || meta[0] || { w: 400, it: false };
            prev.style.fontFamily = fam; prev.style.fontWeight = m.w; prev.style.fontStyle = m.it ? 'italic' : 'normal';
            prev.style.fontSize = ss.value + 'px'; so.textContent = ss.value + 'px';
            prev.textContent = st.value || ' ';
        };
        [st, ss, sel].forEach(function (el) { if (el) { el.addEventListener('input', paint); el.addEventListener('change', paint); } });
        paint();

        // embed builder
        var picks = $$('input[name="embed-style"]');
        var out = function (k) { return $('[data-out="' + k + '"]'); };
        var build = function () {
            var chosen = picks.filter(function (p) { return p.checked; }).map(function (p) { return meta[+p.value]; });
            if (!picks.length) { chosen = [meta[0]]; }
            if (!chosen.length) { chosen = [meta[0]]; }
            var tuples = chosen.map(function (m) { return [m.it ? 1 : 0, m.w]; }).sort(function (a, b) { return a[0] - b[0] || a[1] - b[1]; });
            var seen = {}, parts = [];
            tuples.forEach(function (t) { var s = hasItalic ? t[0] + ',' + t[1] : String(t[1]); if (!seen[s]) { seen[s] = 1; parts.push(s); } });
            var spec = plus + ':' + (hasItalic ? 'ital,wght@' : 'wght@') + parts.join(';');
            var specPlain = spec.replace(/\+/g, ' ');
            var url = base + '/css2?family=' + spec + '&display=swap';
            var set = function (k, v) { var el = out(k); if (el) { el.textContent = v; } };
            set('link', '<link rel="stylesheet" href="' + url + '">');
            set('import', "@import url('" + url + "');");
            set('php', "<?= tzl_fonts('" + specPlain + "') ?>");
            set('node', 'node tzl-fonts.mjs "' + specPlain + '" --out public/fonts --url /fonts');
            set('nginx', '<link rel="stylesheet" href="/tzl-fonts/css2?family=' + spec + '&display=swap">');
        };
        picks.forEach(function (p) { p.addEventListener('change', build); });
        build();

        // tabs
        $$('[data-tabs]').forEach(function (tabs) {
            var btns = $$('[role="tab"]', tabs), panels = $$('[data-panel]', tabs);
            btns.forEach(function (b) {
                b.addEventListener('click', function () {
                    btns.forEach(function (x) { x.setAttribute('aria-selected', String(x === b)); });
                    panels.forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== b.getAttribute('data-tab'); });
                });
            });
        });
    }

    /* ---------- docs ---------- */
    var toc = $('.docs-toc');
    if (toc) {
        var toggle = $('.docs-toc-toggle', toc);
        toggle.addEventListener('click', function () {
            var open = toc.classList.toggle('is-open'); toggle.setAttribute('aria-expanded', String(open));
        });
        var links = $$('a', toc), map = {};
        links.forEach(function (a) {
            map[a.getAttribute('href').slice(1)] = a;
            a.addEventListener('click', function () { toc.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); });
        });
        if ('IntersectionObserver' in window) {
            var heads = $$('.docs-body h2, .docs-body h3');
            var spy = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting && map[en.target.id]) {
                        links.forEach(function (l) { l.classList.remove('is-current'); });
                        map[en.target.id].classList.add('is-current');
                    }
                });
            }, { rootMargin: '-80px 0px -70% 0px' });
            heads.forEach(function (h) { spy.observe(h); });
        }
    }

})();
