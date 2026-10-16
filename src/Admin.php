<?php
namespace App;

/**
 * Admin portal controllers (/admin/*). Every POST verifies a CSRF token.
 */
final class Admin
{
    public const SUBSETS = ['latin', 'latin-ext', 'vietnamese', 'cyrillic', 'greek', 'hebrew', 'arabic', 'devanagari', 'bengali', 'thai', 'japanese'];

    private static function back(string $to = '/admin'): void { App::redirect($to); }

    private static function str(string $key, int $max = 200): string
    {
        $v = trim((string)($_POST[$key] ?? ''));
        $v = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    }

    private static function urlOrEmpty(string $v): ?string
    {
        if ($v === '') { return ''; }
        return preg_match('#^https?://[^\s<>"]+$#i', $v) ? $v : null;
    }

    public static function fonts(): void { View::render('admin/fonts', ['fonts' => Store::fonts()]); }

    /* ---------- fonts ---------- */

    private static function readFontFields(array $font): array
    {
        $errors = [];
        $family = self::str('family', 80);
        if ($family === '') { $errors[] = 'Font family name is required.'; } else { $font['family'] = $family; }
        $cat = self::str('category', 20);
        $font['category'] = isset(Store::CATEGORIES[$cat]) ? $cat : 'sans-serif';
        $font['designer'] = self::str('designer', 160);
        $lic = self::str('license', 20);
        if (!isset(Store::LICENSES[$lic])) { $errors[] = 'Choose a license. Only open licenses that allow free commercial use are accepted.'; } else { $font['license'] = $lic; }
        $lu = self::urlOrEmpty(self::str('license_url', 300));
        if ($lu === null) { $errors[] = 'License URL must start with http:// or https://.'; } else { $font['license_url'] = $lu; }
        $su = self::urlOrEmpty(self::str('source_url', 300));
        if ($su === null) { $errors[] = 'Source URL must start with http:// or https://.'; } else { $font['source_url'] = $su; }
        $font['copyright'] = self::str('copyright', 300);
        $font['description'] = self::str('description', 600);
        $tags = [];
        foreach (explode(',', self::str('tags', 300)) as $t) {
            $t = trim($t);
            if ($t !== '' && count($tags) < 12) { $tags[] = mb_substr($t, 0, 30); }
        }
        $font['tags'] = array_values(array_unique($tags));
        $sub = [];
        foreach ((array)($_POST['subsets'] ?? []) as $s) { if (in_array($s, self::SUBSETS, true)) { $sub[] = $s; } }
        $font['subsets'] = $sub;
        $font['featured'] = !empty($_POST['featured']);
        $font['published'] = !empty($_POST['published']);
        if (($font['license'] ?? '') === 'Other' && ($font['license_url'] ?? '') === '' && self::str('license_text', 60000) === '') {
            $errors[] = 'For "Other" licenses, provide a license URL or paste the license text.';
        }
        return [$font, $errors];
    }

    public static function fontNew(): void
    {
        View::render('admin/font-form', ['font' => FontService::blank(''), 'isNew' => true, 'errors' => [], 'licenseText' => '']);
    }

    public static function fontCreate(): void
    {
        Auth::verifyCsrf();
        [$font, $errors] = self::readFontFields(FontService::blank(''));
        $font['slug'] = App::slugify((string)($font['family'] ?? ''));
        if ($font['slug'] === '' && !$errors) { $errors[] = 'Font family name must contain letters or numbers.'; }
        if ($font['slug'] !== '' && Store::font($font['slug'])) { $errors[] = 'A font named "' . $font['family'] . '" already exists. Open it from the Fonts list to add more styles.'; }
        $hasFile = false;
        foreach ((array)($_FILES['files']['error'] ?? []) as $e) { if ((int)$e !== UPLOAD_ERR_NO_FILE) { $hasFile = true; } }
        if (!$hasFile) { $errors[] = 'Upload at least one font file (TTF, OTF, WOFF or WOFF2).'; }
        $licenseText = (string)($_POST['license_text'] ?? '');
        if ($errors) {
            View::render('admin/font-form', ['font' => $font, 'isNew' => true, 'errors' => $errors, 'licenseText' => $licenseText]);
            return;
        }
        $res = FontService::addUploads($font, $_FILES['files'] ?? []);
        if ($res['added'] === 0) {
            App::rrmdir(Store::fontDir($font['slug']));
            View::render('admin/font-form', ['font' => $font, 'isNew' => true, 'errors' => $res['errors'] ?: ['No valid font files were uploaded.'], 'licenseText' => $licenseText]);
            return;
        }
        FontService::saveLicenseText($font['slug'], $licenseText);
        Store::saveFont($font);
        Store::log('Added font: ' . $font['family'] . ' (' . $res['added'] . ' file' . ($res['added'] === 1 ? '' : 's') . ')');
        self::flashResult($res, 'Font <strong>' . App::e($font['family']) . '</strong> was added.');
        self::back('/admin/fonts/' . $font['slug']);
    }

    private static function flashResult(array $res, string $okMsg): void
    {
        View::flash('ok', $okMsg);
        foreach ($res['notes'] as $n) { View::flash('info', App::e($n)); }
        foreach ($res['errors'] as $e) { View::flash('err', App::e($e)); }
    }

    public static function fontEdit(string $slug): void
    {
        $font = Store::font($slug);
        if (!$font) { Web::notFound(); }
        View::render('admin/font-form', ['font' => $font, 'isNew' => false, 'errors' => [], 'licenseText' => FontService::licenseText($font), 'caps' => FontKit::caps()]);
    }

    public static function fontUpdate(string $slug): void
    {
        Auth::verifyCsrf();
        $font = Store::font($slug);
        if (!$font) { Web::notFound(); }
        $do = (string)($_POST['do'] ?? 'save');
        $url = '/admin/fonts/' . $slug;

        if ($do === 'delete_font') {
            if (trim((string)($_POST['confirm_slug'] ?? '')) !== $slug) {
                View::flash('err', 'Type the font slug <code>' . App::e($slug) . '</code> in the confirmation box to delete it.');
                self::back($url);
            }
            Store::deleteFont($slug);
            Store::log('Deleted font: ' . $font['family']);
            View::flash('ok', 'Font <strong>' . App::e($font['family']) . '</strong> was deleted.');
            self::back('/admin/fonts');
        }

        if (preg_match('/^delete_style:(\d+)$/', $do, $m)) {
            FontService::removeStyle($font, (int)$m[1]);
            if (!$font['styles']) { View::flash('err', 'A font needs at least one style. Upload a file to add one.'); }
            FontService::finalize($font);
            Store::saveFont($font);
            Store::log('Removed a style from ' . $font['family']);
            View::flash('ok', 'Style removed.');
            self::back($url);
        }
        if (preg_match('/^delete_file:(\d+):(woff2|woff|ttf|otf)$/', $do, $m)) {
            FontService::removeFile($font, (int)$m[1], $m[2]);
            FontService::finalize($font);
            Store::saveFont($font);
            Store::log('Removed a ' . $m[2] . ' file from ' . $font['family']);
            View::flash('ok', strtoupper($m[2]) . ' file removed.');
            self::back($url);
        }
        if (preg_match('/^regen:(\d+)$/', $do, $m)) {
            if (FontService::regenWoff2($font, (int)$m[1])) {
                Store::saveFont($font);
                View::flash('ok', 'WOFF2 file generated.');
            } else {
                View::flash('err', 'Could not create WOFF2. The server needs python3 with fonttools and brotli.');
            }
            self::back($url);
        }

        // save + upload
        [$font, $errors] = self::readFontFields($font);
        if ($errors) {
            View::render('admin/font-form', ['font' => $font, 'isNew' => false, 'errors' => $errors, 'licenseText' => (string)($_POST['license_text'] ?? ''), 'caps' => FontKit::caps()]);
            return;
        }
        foreach ((array)($_POST['st_weight'] ?? []) as $i => $w) {
            $i = (int)$i;
            if (!isset($font['styles'][$i]) || !empty($font['styles'][$i]['variable'])) { continue; }
            $w = (int)$w;
            if ($w >= 1 && $w <= 1000) { $font['styles'][$i]['weight'] = $w; }
        }
        foreach ((array)($_POST['st_name'] ?? []) as $i => $nm) {
            $i = (int)$i;
            if (isset($font['styles'][$i])) { $font['styles'][$i]['name'] = mb_substr(trim((string)$nm), 0, 40); }
        }
        foreach ($font['styles'] as $i => $_) {
            if (empty($font['styles'][$i]['variable'])) { $font['styles'][$i]['italic'] = !empty($_POST['st_italic'][$i]); }
        }
        $res = FontService::addUploads($font, $_FILES['files'] ?? []);
        FontService::finalize($font);
        FontService::saveLicenseText($slug, (string)($_POST['license_text'] ?? ''));
        Store::saveFont($font);
        Store::log('Updated font: ' . $font['family']);
        self::flashResult($res, $res['added'] > 0 ? $res['added'] . ' file' . ($res['added'] === 1 ? '' : 's') . ' added and changes saved.' : 'Changes saved.');
        self::back($url);
    }

    /* ---------- tools ---------- */

    public static function fontTools(): void
    {
        Auth::verifyCsrf();
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'rescan') {
            $n = 0;
            foreach (Store::fonts() as $f) {
                $dir = Store::fontDir($f['slug']);
                foreach ($f['styles'] as $i => $s) {
                    foreach ($s['files'] as $ext => $fi) {
                        $p = $dir . '/' . basename((string)$fi['name']);
                        if (is_file($p)) { $f['styles'][$i]['files'][$ext] = FontService::fileRecord($p); }
                        else { unset($f['styles'][$i]['files'][$ext]); }
                    }
                }
                FontService::finalize($f);
                Store::saveFont($f);
                $n++;
            }
            Store::log('Rescanned ' . $n . ' fonts');
            View::flash('ok', 'Rescanned ' . $n . ' font(s). The manifest version was bumped, so cached copies will refresh.');
        } elseif ($do === 'clear_stats') {
            Store::write('stats.json', ['days' => []]);
            Store::log('Cleared statistics');
            View::flash('ok', 'Statistics cleared.');
        }
        self::back('/admin/fonts');
    }


    /* ---------- delivery settings (font-service specific) ---------- */

    public static function delivery(): void
    {
        $s = Store::settings();
        $h = '<form method="post" action="/admin/delivery" class="admin-form">' . \Tzl\Auth::field();
        $h .= \Tzl\Ui::card('Caching & delivery', 'zap',
            '<div class="field-row">'
            . \Tzl\Ui::text('CSS cache time (seconds)', 'css_ttl', (string)(int)$s['css_ttl'], ['type' => 'number', 'min' => 60, 'max' => 31536000, 'hint' => 'How long browsers and proxies reuse <code>/css2</code> responses. 86400 = 1 day.'])
            . \Tzl\Ui::text('Recommended refresh interval (days)', 'cache_days', (string)(int)$s['cache_days'], ['type' => 'number', 'min' => 1, 'max' => 365, 'hint' => 'Shown in the docs and used as the default of the PHP/Node helpers.']) . '</div>'
            . '<div class="field-row">'
            . \Tzl\Ui::select('Default font-display', 'default_display', array_combine(Css::DISPLAY, Css::DISPLAY), (string)$s['default_display'])
            . \Tzl\Ui::text('Max upload size per file (MB)', 'max_upload_mb', (string)(int)$s['max_upload_mb'], ['type' => 'number', 'min' => 1, 'max' => 200, 'hint' => 'Also limited by PHP upload_max_filesize and post_max_size (see System).']) . '</div>');
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . App::icon('save') . ' Save</button></div></form>';
        \Tzl\Admin::page('Delivery & caching', 'delivery', $h);
    }

    public static function deliverySave(): void
    {
        Auth::verifyCsrf();
        $int = static function (string $k, int $lo, int $hi, int $d): int {
            $v = self::str($k, 10);
            return max($lo, min($hi, is_numeric($v) ? (int)$v : $d));
        };
        $dd = self::str('default_display', 12);
        Store::saveSettings([
            'css_ttl' => $int('css_ttl', 60, 31536000, 86400), 'cache_days' => $int('cache_days', 1, 365, 7), 'max_upload_mb' => $int('max_upload_mb', 1, 200, 20),
            'default_display' => in_array($dd, Css::DISPLAY, true) ? $dd : 'swap',
        ]);
        Store::log('Delivery settings updated');
        View::flash('ok', 'Delivery settings saved.');
        self::back('/admin/delivery');
    }

    /** Dashboard cards and widgets for the shared admin dashboard. */
    public static function dashboardWidgets(array $days): array
    {
        $fonts = Store::fonts();
        $pub = count(array_filter($fonts, static fn($f) => !empty($f['published'])));
        $t = Store::totalBytes();
        $today = (array)end($days);
        $todayReq = (int)($today['ev']['css'] ?? 0) + (int)($today['ev']['file'] ?? 0);
        $caps = FontKit::caps();
        $rows = '';
        foreach (Store::topFonts(30, 8) as $slug => $c) {
            $rows .= '<li><a href="/admin/fonts/' . App::e($slug) . '">' . App::e($fonts[$slug]['family'] ?? $slug) . '</a><span>' . number_format($c) . '</span></li>';
        }
        $top = $rows !== '' ? '<ol class="rank">' . $rows . '</ol>' : '<p class="muted">No requests recorded yet.</p>';
        $sum = ['css' => 0, 'file' => 0, 'zip' => 0, 'api' => 0];
        foreach (Store::statDays(14) as $d) { foreach ($sum as $k => $_) { $sum[$k] += $d[$k]; } }
        $tools = '<form method="post" action="/admin/fonts/tools" class="tool-forms">' . Auth::field()
            . '<a class="btn btn-primary" href="/admin/fonts/new">' . App::icon('font-plus') . ' Add font</a>'
            . '<button class="btn btn-secondary" name="do" value="rescan" type="submit">' . App::icon('refresh') . ' Rescan files &amp; bump manifest</button></form>'
            . '<p class="hint">Rescan re-reads every file from disk and bumps the manifest version so cached copies pick up changes. Python fonttools/brotli (automatic WOFF2): <strong>' . ($caps['fonttools'] && $caps['brotli'] ? 'available' : 'missing') . '</strong>.</p>'
            . '<p class="hint">Last 14 days: CSS ' . number_format($sum['css']) . ', font files ' . number_format($sum['file']) . ', ZIP downloads ' . number_format($sum['zip']) . ', API ' . number_format($sum['api']) . '.</p>';
        return [
            'cards' => [['font', $pub . ' / ' . count($fonts), 'fonts published'], ['hard-drive', App::bytes((int)$t['bytes']), $t['files'] . ' font files'], ['activity', number_format($todayReq), 'CSS + file requests today'], ['database-refresh', 'v' . Store::version(), 'manifest version']],
            'html' => '<div class="admin-cols">' . \Tzl\Ui::card('Top fonts (30 days)', 'trophy', $top) . \Tzl\Ui::card('Font tools', 'wrench', $tools) . '</div>',
        ];
    }

    public static function register(): void
    {
        \Tzl\Router::get('#^/admin/fonts$#', [self::class, 'fonts']);
        \Tzl\Router::get('#^/admin/fonts/new$#', [self::class, 'fontNew']);
        \Tzl\Router::post('#^/admin/fonts/new$#', [self::class, 'fontCreate']);
        \Tzl\Router::post('#^/admin/fonts/tools$#', [self::class, 'fontTools']);
        \Tzl\Router::get('#^/admin/fonts/([a-z0-9-]+)$#', [self::class, 'fontEdit']);
        \Tzl\Router::post('#^/admin/fonts/([a-z0-9-]+)$#', [self::class, 'fontUpdate']);
        \Tzl\Router::get('#^/admin/delivery$#', [self::class, 'delivery']);
        \Tzl\Router::post('#^/admin/delivery$#', [self::class, 'deliverySave']);
    }
}
