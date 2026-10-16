(function () {
    'use strict';

    var $ = function (s, r) { return (r || document).querySelector(s); };
    var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

    // live filter on the fonts table
    var filter = $('#admin-filter');
    if (filter) {
        var rows = $$('#admin-fonts tbody tr[data-filter]');
        filter.addEventListener('input', function () {
            var q = filter.value.trim().toLowerCase();
            rows.forEach(function (r) { r.hidden = q && r.getAttribute('data-filter').indexOf(q) === -1; });
        });
    }

    // slug preview when adding a font
    var fam = $('#family'), slug = $('#slug-preview');
    if (fam && slug) {
        var update = function () {
            slug.textContent = fam.value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || '…';
        };
        fam.addEventListener('input', update); update();
    }

    // drop zone: list chosen files, allow dragging over
    var input = $('#files'), zone = $('#dropzone'), list = $('#dz-list');
    if (input && zone) {
        var show = function () {
            var names = Array.prototype.map.call(input.files, function (f) { return f.name + ' (' + Math.round(f.size / 1024) + ' KB)'; });
            list.textContent = names.join(' · ');
        };
        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.add('is-over'); }); });
        ['dragleave', 'drop'].forEach(function (ev) { zone.addEventListener(ev, function () { zone.classList.remove('is-over'); }); });
    }
})();
