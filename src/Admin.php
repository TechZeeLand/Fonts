<?php
namespace App;

/**
 * Admin portal controllers (/admin/*). Every POST verifies a CSRF token.
 */
final class Admin
{
    public const SUBSETS = ['latin', 'latin-ext', 'vietnamese', 'cyrillic', 'greek', 'hebrew', 'arabic', 'devanagari', 'bengali', 'thai', 'japanese'];

    public static function guard(string $path): void
    {
        Auth::start();
        if ($path === '/admin/login') { return; }
        if (!Auth::check()) { App::redirect('/admin/login'); }
        if (Auth::mustChange() && $path !== '/admin/account' && $path !== '/admin/logout') {
            View::flash('info', 'Welcome! Please choose your own username and password before continuing.');
            App::redirect('/admin/account');
        }
    }

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

    /* ---------- auth ---------- */

    public static function loginForm(): void
    {
        if (Auth::check()) { App::redirect('/admin'); }
        Auth::ensureAdmin();
        View::render('admin/login', ['error' => '', 'firstRun' => is_file(Store::path('INITIAL_ADMIN_PASSWORD.txt'))]);
    }

    public static function loginPost(): void
    {
        Auth::verifyCsrf();
        Auth::ensureAdmin();
        if (Auth::locked()) {
            http_response_code(429);
            View::render('admin/login', ['error' => 'Too many failed attempts. Please wait 15 minutes and try again.', 'firstRun' => false]);
            return;
        }
        if (Auth::attempt(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) { App::redirect('/admin'); }
        http_response_code(401);
        View::render('admin/login', ['error' => 'Incorrect username or password.', 'firstRun' => is_file(Store::path('INITIAL_ADMIN_PASSWORD.txt'))]);
    }

    public static function logout(): void
    {
        Auth::verifyCsrf();
        Auth::logout();
        App::redirect('/admin/login');
    }

    /* ---------- dashboard ---------- */

    public static function dashboard(): void
    {
        $days = Store::statDays(14);
        $sum = ['css' => 0, 'file' => 0, 'zip' => 0, 'api' => 0];
        foreach ($days as $d) { foreach ($sum as $k => $_) { $sum[$k] += $d[$k]; } }
        $today = end($days);
        $fonts = Store::fonts();
        $pub = count(array_filter($fonts, static fn($f) => !empty($f['published'])));
        $caps = FontKit::caps();
        $checks = [
            ['Data folder is writable', is_writable(App::$data), 'Make ' . App::$data . ' writable by the web server user.'],
            ['Automatic WOFF2 conversion', $caps['fonttools'] && $caps['brotli'], 'Install python3 + fonttools + brotli (included in the Docker image). Without it, uploaded TTF/OTF files are served as they are.'],
            ['ZIP downloads (php-zip)', class_exists('ZipArchive'), 'Enable the PHP zip extension to offer family downloads and backups.'],
            ['Served over HTTPS', App::isHttps(), 'Put the service behind HTTPS. If a reverse proxy terminates TLS, set TZL_TRUST_PROXY=1.'],
            ['Public URL is set', trim((string)Store::setting('base_url')) !== '' || App::env('TZL_BASE_URL') !== '', 'Set it in Settings so generated CSS and embed code always use the right domain.'],
            ['Admin password was changed', !Auth::mustChange(), 'Change the generated password in Account.'],
        ];
        View::render('admin/dashboard', [
            'days' => $days, 'sum' => $sum, 'today' => $today, 'top' => Store::topFonts(30, 8), 'fonts' => $fonts,
            'pub' => $pub, 'totals' => Store::totalBytes(), 'checks' => $checks, 'recent' => Store::activity(8),
            'ini' => ['upload' => ini_get('upload_max_filesize'), 'post' => ini_get('post_max_size')],
        ]);
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

    /* ---------- settings / account / activity ---------- */

    public static function settings(): void { View::render('admin/settings', ['s' => Store::settings(), 'errors' => []]); }

    public static function settingsSave(): void
    {
        Auth::verifyCsrf();
        $errors = [];
        $new = [];
        $new['site_name'] = self::str('site_name', 60) ?: 'TechZeeLand Fonts';
        $new['short_name'] = self::str('short_name', 30) ?: 'TZL Fonts';
        $new['tagline'] = self::str('tagline', 160);
        $bu = self::urlOrEmpty(rtrim(self::str('base_url', 200), '/'));
        if ($bu === null) { $errors[] = 'Public URL must start with http:// or https://.'; } else { $new['base_url'] = $bu; }
        $em = self::str('contact_email', 120);
        if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Contact email is not valid.'; } else { $new['contact_email'] = $em; }
        $gh = self::urlOrEmpty(self::str('github_url', 200));
        if ($gh === null) { $errors[] = 'GitHub URL must start with http:// or https://.'; } else { $new['github_url'] = $gh; }
        $new['announcement_on'] = !empty($_POST['announcement_on']);
        $new['announcement_text'] = self::str('announcement_text', 200);
        $al = self::urlOrEmpty(self::str('announcement_link', 300));
        if ($al === null) { $errors[] = 'Announcement link must start with http:// or https://.'; } else { $new['announcement_link'] = $al; }
        $ttl = (int)($_POST['css_ttl'] ?? 0);
        if ($ttl < 60 || $ttl > 31536000) { $errors[] = 'CSS cache time must be between 60 and 31,536,000 seconds.'; } else { $new['css_ttl'] = $ttl; }
        $days = (int)($_POST['cache_days'] ?? 0);
        if ($days < 1 || $days > 365) { $errors[] = 'Recommended refresh interval must be 1–365 days.'; } else { $new['cache_days'] = $days; }
        $cors = self::str('cors_origin', 200);
        if ($cors !== '*' && self::urlOrEmpty($cors) === null) { $errors[] = 'CORS origin must be * or a full origin such as https://example.com.'; } else { $new['cors_origin'] = $cors === '' ? '*' : $cors; }
        $mu = (int)($_POST['max_upload_mb'] ?? 0);
        if ($mu < 1 || $mu > 200) { $errors[] = 'Max upload size must be 1–200 MB.'; } else { $new['max_upload_mb'] = $mu; }
        $dd = self::str('default_display', 12);
        $new['default_display'] = in_array($dd, Css::DISPLAY, true) ? $dd : 'swap';
        $new['stats_on'] = !empty($_POST['stats_on']);
        $new['robots_index'] = !empty($_POST['robots_index']);
        if ($errors) {
            View::render('admin/settings', ['s' => array_merge(Store::settings(), $new), 'errors' => $errors]);
            return;
        }
        Store::saveSettings($new);
        Store::log('Settings updated');
        View::flash('ok', 'Settings saved.');
        self::back('/admin/settings');
    }

    public static function account(): void { View::render('admin/account', ['errors' => [], 'admin' => Auth::admin()]); }

    public static function accountSave(): void
    {
        Auth::verifyCsrf();
        $a = Auth::admin();
        $errors = [];
        $cur = (string)($_POST['current'] ?? '');
        $user = self::str('username', 40);
        $p1 = (string)($_POST['new_password'] ?? '');
        $p2 = (string)($_POST['new_password2'] ?? '');
        if (!$a || !password_verify($cur, (string)$a['hash'])) { $errors[] = 'Your current password is not correct.'; }
        if (!preg_match('/^[A-Za-z0-9._-]{3,40}$/', $user)) { $errors[] = 'Username must be 3–40 characters: letters, numbers, dot, dash or underscore.'; }
        if ($p1 !== '' || Auth::mustChange()) {
            if (strlen($p1) < 10) { $errors[] = 'New password must be at least 10 characters.'; }
            if ($p1 !== $p2) { $errors[] = 'New passwords do not match.'; }
            if ($p1 === $cur && $p1 !== '') { $errors[] = 'Choose a password different from the current one.'; }
        }
        if ($errors) { View::render('admin/account', ['errors' => $errors, 'admin' => $a]); return; }
        Auth::changeCredentials($user, $p1);
        View::flash('ok', 'Account updated.');
        self::back('/admin/account');
    }

    public static function activity(): void { View::render('admin/activity', ['rows' => Store::activity(150)]); }

    /* ---------- tools ---------- */

    public static function tools(): void
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
        self::back('/admin');
    }

    public static function backup(): void
    {
        if (!class_exists('ZipArchive')) { View::flash('err', 'php-zip is not installed.'); self::back('/admin'); }
        $tmp = tempnam(sys_get_temp_dir(), 'tzlbk');
        $z = new \ZipArchive();
        if ($tmp === false || $z->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { View::flash('err', 'Could not create the backup.'); self::back('/admin'); }
        foreach (['fonts.json', 'settings.json', 'stats.json'] as $f) {
            if (is_file(Store::path($f))) { $z->addFile(Store::path($f), 'tzl-fonts-data/' . $f); }
        }
        $root = Store::path('files');
        if (is_dir($root)) {
            $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            foreach ($it as $file) {
                if ($file->isFile()) { $z->addFile($file->getPathname(), 'tzl-fonts-data/files/' . substr($file->getPathname(), strlen($root) + 1)); }
            }
        }
        $z->close();
        Store::log('Downloaded a backup');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="tzl-fonts-backup-' . gmdate('Y-m-d') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }
}
