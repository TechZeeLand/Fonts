<?php
declare(strict_types=1);
namespace Tzl;

/**
 * The shared admin portal (/admin/*). A service adds its own screens by registering routes under /admin
 * and listing them in Kit::boot(['admin_menu' => [key => [url, label, icon]]]).
 */
final class Admin
{
    /* ---------- plumbing ---------- */

    public static function mount(): void
    {
        $g = static fn(string $re, string $m) => Router::get($re, [self::class, $m]);
        $p = static fn(string $re, string $m) => Router::post($re, [self::class, $m]);
        $g('#^/admin/login$#', 'loginForm');
        $p('#^/admin/login$#', 'loginPost');
        $p('#^/admin/logout$#', 'logout');
        $g('#^/admin$#', 'dashboard');
        $g('#^/admin/site$#', 'site');           $p('#^/admin/site$#', 'siteSave');
        $g('#^/admin/navigation$#', 'nav');      $p('#^/admin/navigation$#', 'navSave');
        $g('#^/admin/appearance$#', 'look');     $p('#^/admin/appearance$#', 'lookSave');
        $g('#^/admin/pages$#', 'pages');
        $g('#^/admin/pages/([a-z-]+)$#', 'pageEdit');  $p('#^/admin/pages/([a-z-]+)$#', 'pageSave');
        $g('#^/admin/seo$#', 'seo');             $p('#^/admin/seo$#', 'seoSave');
        $g('#^/admin/security$#', 'security');   $p('#^/admin/security$#', 'securitySave');
        $g('#^/admin/traffic$#', 'traffic');
        $g('#^/admin/system$#', 'system');
        $g('#^/admin/activity$#', 'activity');
        $g('#^/admin/account$#', 'account');     $p('#^/admin/account$#', 'accountSave');
        $g('#^/admin/backup\.zip$#', 'backup');
        $g('#^/admin/export\.json$#', 'export');
        $p('#^/admin/tools$#', 'tools');
    }

    public static function guard(string $path): void
    {
        Auth::start();
        if ($path === '/admin/login') { return; }
        if (!Auth::check()) { Kit::redirect('/admin/login'); }
        if (Auth::mustChange() && $path !== '/admin/account' && $path !== '/admin/logout') {
            self::flash('info', 'Welcome! Please choose your own username and password before continuing.');
            Kit::redirect('/admin/account');
        }
    }

    public static function menu(): array
    {
        $m = ['dashboard' => ['/admin', 'Dashboard', 'dashboard']];
        foreach ((array)Kit::cfg('admin_menu', []) as $k => $v) { $m[$k] = $v; }
        $m['site']       = ['/admin/site', 'Site & domains', 'globe'];
        $m['navigation'] = ['/admin/navigation', 'Header & footer', 'menu'];
        $m['appearance'] = ['/admin/appearance', 'Appearance', 'palette'];
        $m['pages']      = ['/admin/pages', 'Pages & policies', 'file-text'];
        $m['seo']        = ['/admin/seo', 'SEO, ads & code', 'search'];
        $m['traffic']    = ['/admin/traffic', 'Traffic', 'bar-chart'];
        $m['system']     = ['/admin/system', 'System & backup', 'server'];
        $m['security']   = ['/admin/security', 'Security', 'shield-check'];
        $m['account']    = ['/admin/account', 'Account', 'user-lock'];
        $m['activity']   = ['/admin/activity', 'Activity log', 'history'];
        return $m;
    }

    public static function flash(string $type, string $msg): void
    {
        Auth::start();
        $_SESSION['flash'][] = [$type, $msg];
    }

    private static function flashes(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash'])) { return ''; }
        $out = '';
        foreach ($_SESSION['flash'] as [$type, $msg]) {
            $icon = $type === 'err' ? 'alert-triangle' : ($type === 'info' ? 'info' : 'check-circle');
            $out .= '<div class="alert alert-' . Kit::e($type) . '" role="status">' . Kit::icon($icon) . '<div>' . $msg . '</div></div>';
        }
        $_SESSION['flash'] = [];
        return $out;
    }

    /** Print a full admin page. $body is trusted HTML built from escaped parts. */
    public static function page(string $title, string $active, string $body, string $actions = '', bool $heading = true): void
    {
        Layout::head($title, '', ['admin' => true]);
        echo '<div class="admin-wrap"><aside class="admin-nav" id="admin-nav"><nav aria-label="Admin">';
        foreach (self::menu() as $k => [$u, $l, $i]) {
            echo '<a href="' . Kit::e($u) . '"' . ($active === $k ? ' class="active" aria-current="page"' : '') . '>' . Kit::icon($i) . '<span>' . Kit::e($l) . '</span></a>';
        }
        echo '</nav><form method="post" action="/admin/logout" class="admin-logout">' . Auth::field() . '<button type="submit" class="btn btn-secondary btn-block">' . Kit::icon('logout') . ' Sign out</button></form></aside>';
        echo '<main id="main-content" class="admin-main">' . self::flashes();
        echo ($heading ? '<div class="page-head"><h1>' . Kit::e($title) . '</h1>' . $actions . '</div>' : '') . $body . Ui::iconList();
        echo '</main></div>';
        Layout::foot(['admin' => true]);
    }

    /* ---------- request helpers ---------- */

    public static function str(string $key, int $max = 200): string
    {
        $v = trim((string)($_POST[$key] ?? ''));
        $v = (string)preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $v);
        return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    }

    public static function raw(string $key, int $max = 20000): string
    {
        $v = (string)($_POST[$key] ?? '');
        $v = str_replace("\r\n", "\n", $v);
        return function_exists('mb_substr') ? mb_substr($v, 0, $max) : substr($v, 0, $max);
    }

    public static function bool(string $key): bool { return !empty($_POST[$key]); }

    private static function urlOrEmpty(string $v): ?string
    {
        if ($v === '') { return ''; }
        return preg_match('#^https?://[^\s<>"]+$#i', $v) ? $v : null;
    }

    private static function back(string $to): void { Kit::redirect($to); }

    /* ---------- login / logout ---------- */

    private static function loginView(string $error): void
    {
        $first = is_file(Json::path('INITIAL_ADMIN_PASSWORD.txt'));
        Layout::head('Sign in', '', ['admin' => true, 'noindex' => true]);
        echo '<main id="main-content" class="login-wrap"><form method="post" action="/admin/login" class="login-card card" autocomplete="on">' . Auth::field();
        echo '<span class="login-ico" aria-hidden="true">' . Kit::icon('lock') . '</span><h1>Admin sign in</h1>';
        if ($error !== '') { echo '<div class="alert alert-err" role="alert">' . Kit::icon('alert-triangle') . '<div>' . Kit::e($error) . '</div></div>'; }
        if ($first) { echo '<div class="alert alert-info">' . Kit::icon('info') . '<div>First run: the generated password is in the container log (<code>docker compose logs</code>) and in <code>INITIAL_ADMIN_PASSWORD.txt</code> inside the data folder.</div></div>'; }
        echo '<div class="field"><label for="username">Username</label><input id="username" name="username" autocomplete="username" required autofocus></div>';
        echo '<div class="field"><label for="password">Password</label><input id="password" name="password" type="password" autocomplete="current-password" required></div>';
        echo '<button class="btn btn-primary btn-block" type="submit">' . Kit::icon('login') . ' Sign in</button></form></main>';
        Layout::foot(['admin' => true]);
    }

    public static function loginForm(): void
    {
        if (Auth::check()) { Kit::redirect('/admin'); }
        Auth::ensureAdmin();
        self::loginView('');
    }

    public static function loginPost(): void
    {
        Auth::verifyCsrf();
        Auth::ensureAdmin();
        if (Auth::locked()) {
            http_response_code(429);
            self::loginView('Too many failed attempts. Please wait 15 minutes and try again.');
            return;
        }
        if (Auth::attempt(trim((string)($_POST['username'] ?? '')), (string)($_POST['password'] ?? ''))) { Kit::redirect('/admin'); }
        http_response_code(401);
        self::loginView('Incorrect username or password.');
    }

    public static function logout(): void
    {
        Auth::verifyCsrf();
        Auth::logout();
        Kit::redirect('/admin/login');
    }

    /* ---------- dashboard ---------- */

    public static function dashboard(): void
    {
        $days = Stats::days(14);
        $dates = array_keys($days);
        $pv = []; $api = [];
        foreach ($days as $d => $r) { $pv[$d] = (int)($r['pv'] ?? 0); $api[$d] = (int)($r['api'] ?? 0); }
        $today = end($days) ?: [];
        $cards = [
            ['eye', number_format((int)($today['pv'] ?? 0)), 'page views today'],
            ['bar-chart', number_format(Stats::sum($days, 'pv')), 'page views, 14 days'],
            ['zap', number_format(Stats::sum($days, 'api')), 'API / asset requests, 14 days'],
            ['timer', Stats::avgMs($days) . ' ms', 'average response time'],
            ['alert-triangle', number_format(Stats::sum($days, 'err')), 'server errors, 14 days'],
        ];
        $extra = '';
        $fn = Kit::cfg('dashboard', null);
        if (is_callable($fn)) {
            $r = $fn($days);
            foreach ((array)($r['cards'] ?? []) as $c) { array_unshift($cards, $c); }
            $extra = (string)($r['html'] ?? '');
        }
        $h = '<div class="stat-grid">';
        foreach (array_slice($cards, 0, 8) as [$i, $v, $l]) {
            $h .= '<div class="stat"><span class="stat-ico">' . Kit::icon($i) . '</span><div><strong>' . Kit::e($v) . '</strong><span>' . Kit::e($l) . '</span></div></div>';
        }
        $h .= '</div>';
        $h .= '<div class="admin-cols">';
        $h .= Ui::card('Traffic, last 14 days', 'bar-chart', Ui::chart($dates, [['page views', $pv, 'bar-file'], ['API / assets', $api, 'bar-css']])
            . (!Site::on('stats_on') ? '<p class="hint">Statistics are switched off in Security.</p>' : ''));
        $h .= Ui::card('Top pages (14 days)', 'trophy', Ui::rank(Stats::top($days, 'p', 8), static fn($k) => '<a href="' . Kit::e($k) . '" target="_blank" rel="noopener">' . Kit::e($k) . '</a>'));
        $h .= '</div>' . $extra;

        $ck = '<ul class="checks">';
        foreach (Monitor::checks() as [$label, $ok, $help]) {
            $ck .= '<li class="' . ($ok ? 'ok' : 'warn') . '">' . Kit::icon($ok ? 'check-circle' : 'alert-triangle') . '<div><strong>' . Kit::e($label) . '</strong>' . (!$ok ? '<small>' . Kit::e($help) . '</small>' : '') . '</div></li>';
        }
        $ck .= '</ul>';
        $act = '<ul class="activity-mini">';
        foreach (Json::activity(8) as $r) { $act .= '<li><span>' . Kit::e(Kit::ago((string)$r['t'])) . '</span> ' . Kit::e($r['m']) . '</li>'; }
        if (!Json::activity(1)) { $act .= '<li class="muted">Nothing yet.</li>'; }
        $act .= '</ul>';
        $h .= '<div class="admin-cols">' . Ui::card('System health', 'shield-check', $ck) . Ui::card('Recent activity', 'history', $act) . '</div>';

        $links = '<div class="tool-forms"><a class="btn btn-secondary" href="/admin/site">' . Kit::icon('globe') . ' Site & domains</a><a class="btn btn-secondary" href="/admin/navigation">' . Kit::icon('menu') . ' Header & footer</a><a class="btn btn-secondary" href="/admin/pages">' . Kit::icon('file-text') . ' Edit pages</a><a class="btn btn-secondary" href="/admin/backup.zip">' . Kit::icon('archive') . ' Download backup</a></div>';
        $h .= Ui::card('Shortcuts', 'rocket', $links);
        self::page('Dashboard', 'dashboard', $h, '<a class="btn btn-secondary" href="/" target="_blank" rel="noopener">' . Kit::icon('external-link') . ' View site</a>');
    }

    /* ---------- Site & domains ---------- */

    public static function site(): void
    {
        $s = Site::all();
        $rows = '';
        foreach (Kit::SERVICES as $k => $sv) {
            $rows .= '<tr><td>' . Kit::icon($sv['icon']) . ' ' . Kit::e($sv['name']) . '</td><td><code>' . Kit::e(Kit::service($k)) . '</code></td><td>' . Ui::text('', 'svc_' . $k, $s['svc_' . $k], ['placeholder' => 'automatic', 'type' => 'url']) . '</td></tr>';
        }
        $h = '<form method="post" action="/admin/site" class="admin-form">' . Auth::field();
        $h .= Ui::card('Identity', 'id-card',
            '<div class="field-row">' . Ui::text('Site name', 'site_name', $s['site_name'], ['maxlength' => 60]) . Ui::text('Short name', 'short_name', $s['short_name'], ['maxlength' => 30]) . '</div>'
            . Ui::text('Tagline', 'tagline', $s['tagline'], ['maxlength' => 160, 'hint' => 'Shown in the footer and used for link previews.'])
            . Ui::area('Meta description', 'description', $s['description'], ['rows' => 2, 'maxlength' => 300, 'hint' => 'Default description for search results. Leave empty to use the tagline.'])
            . '<div class="field-row">' . Ui::text('Contact email', 'contact_email', $s['contact_email'], ['type' => 'email']) . Ui::text('GitHub repository URL', 'github_url', $s['github_url'], ['type' => 'url']) . '</div>'
            . '<div class="field-row">' . Ui::text('Owner / brand name', 'owner_name', $s['owner_name'], ['maxlength' => 60]) . Ui::text('Owner link', 'owner_url', $s['owner_url'], ['type' => 'url']) . '</div>'
            . '<div class="field-row">' . Ui::text('License name', 'license_name', $s['license_name'], ['maxlength' => 30]) . Ui::text('License URL', 'license_url', $s['license_url'], ['type' => 'url']) . '</div>');
        $h .= Ui::card('Domain', 'globe',
            Ui::text('Public URL of this service', 'base_url', $s['base_url'], ['type' => 'url', 'placeholder' => 'https://example.rayaz.org', 'hint' => 'Used for canonical links, the sitemap and embed snippets. Leave empty to detect it from the request (set it when behind a proxy).'])
            . Ui::text('Root domain of all TechZeeLand services', 'root_domain', $s['root_domain'], ['placeholder' => Kit::DEFAULT_ROOT_DOMAIN, 'hint' => 'Moving to a new domain? Change this one value (for example <code>techzeeland.com</code>) in each service. The subdomains <code>fonts</code>, <code>icons</code>, <code>qrcode</code> and <code>assignmentcovergenerator</code> are added automatically and every icon, font and cross-link follows.'])
            . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Service</th><th>Resolved URL</th><th>Override (optional)</th></tr></thead><tbody>' . $rows . '</tbody></table></div>'
            . '<p class="hint">Current root domain: <code>' . Kit::e(Kit::rootDomain()) . '</code>. The icon stylesheet is <code>' . Kit::e(Kit::iconCssUrl()) . '</code>.</p>');
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save</button></div></form>';
        self::page('Site & domains', 'site', $h);
    }

    public static function siteSave(): void
    {
        Auth::verifyCsrf();
        $new = [
            'site_name' => self::str('site_name', 60), 'short_name' => self::str('short_name', 30), 'tagline' => self::str('tagline', 160),
            'description' => self::str('description', 300), 'contact_email' => self::str('contact_email', 120),
            'owner_name' => self::str('owner_name', 60), 'license_name' => self::str('license_name', 30),
        ];
        $errors = [];
        if ($new['site_name'] === '') { $errors[] = 'Site name cannot be empty.'; }
        if ($new['contact_email'] !== '' && !filter_var($new['contact_email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'Contact email is not valid.'; }
        foreach (['github_url', 'owner_url', 'license_url', 'base_url', 'svc_fonts', 'svc_icons', 'svc_qr', 'svc_cover'] as $k) {
            $u = self::urlOrEmpty(self::str($k, 300));
            if ($u === null) { $errors[] = $k . ' must start with http:// or https://.'; } else { $new[$k] = rtrim($u, '/'); }
        }
        $root = strtolower((string)preg_replace('#^https?://#i', '', self::str('root_domain', 120)));
        $root = trim($root, '/ ');
        if ($root !== '' && !preg_match('/^[a-z0-9.\-]+$/', $root)) { $errors[] = 'Root domain may only contain letters, numbers, dots and hyphens (e.g. techzeeland.com).'; } else { $new['root_domain'] = $root; }
        if ($errors) { foreach ($errors as $e) { self::flash('err', Kit::e($e)); } self::back('/admin/site'); }
        Site::save($new);
        Json::log('Updated site & domain settings');
        self::flash('ok', 'Site settings saved.');
        self::back('/admin/site');
    }

    /* ---------- Header & footer ---------- */

    public static function nav(): void
    {
        $custom = is_array(Site::get('header_links'));
        $h = '<form method="post" action="/admin/navigation" class="admin-form">' . Auth::field();
        $h .= Ui::card('Header links', 'menu', '<p class="hint">' . ($custom ? 'You are using a custom menu.' : 'Using the default menu for this service. Edit and save to customise it.') . ' Use <code>/path</code> for pages on this site and a full <code>https://…</code> address for others.</p>'
            . Ui::linkEditor('header_links', Site::headerLinks())
            . '<div class="switch-list mt">' . Ui::check('show_github', 'Show the GitHub button in the header', Site::on('show_github')) . '</div>'
            . '<div class="field-row mt">' . Ui::text('GitHub button label', 'github_label', (string)Site::get('github_label'), ['maxlength' => 30]) . '</div>'
            . ($custom ? '<label class="row-check mt"><input type="checkbox" name="header_reset" value="1"><span class="switch"></span><span class="row-check-label">Reset header links to the default</span></label>' : ''));
        $cols = '';
        $i = 0;
        foreach (Site::footerColumns() as $col) {
            $cols .= '<div class="footer-edit"><div class="field"><label>Column title</label><input type="text" name="footer_columns[' . $i . '][title]" value="' . Kit::e($col['title']) . '" maxlength="40"></div>'
                . Ui::linkEditor('footer_cols_links_' . $i, (array)$col['links'], false, true) . '</div>';
            $i++;
        }
        $h .= Ui::card('Footer columns', 'layout', '<p class="hint">Up to four columns. Leave a column title empty (or remove all its links) to delete it.</p>' . $cols
            . '<div class="footer-edit"><div class="field"><label>New column title</label><input type="text" name="footer_columns[' . $i . '][title]" maxlength="40" placeholder="Optional"></div>' . Ui::linkEditor('footer_cols_links_' . $i, [], false, true) . '</div>'
            . (is_array(Site::get('footer_columns')) ? '<label class="row-check mt"><input type="checkbox" name="footer_reset" value="1"><span class="switch"></span><span class="row-check-label">Reset footer columns to the default</span></label>' : ''));
        $h .= Ui::card('Social & support links', 'share', Ui::linkEditor('social_links', Site::socialLinks())
            . '<p class="hint">Icon names come from the <a href="' . Kit::e(Kit::service('icons')) . '/icons" target="_blank" rel="noopener">TechZeeLand Icons</a> library (for example <code>youtube</code>, <code>github</code>, <code>pathaopay</code>).</p>');
        $h .= Ui::card('Footer text & services', 'file-text',
            Ui::area('Footer sentence', 'footer_text', (string)Site::get('footer_text'), ['rows' => 2, 'maxlength' => 400, 'hint' => 'Tokens: <code>{year}</code> <code>{site}</code> <code>{owner}</code> <code>{license}</code>. Leave empty for the default sentence.'])
            . '<div class="switch-list">' . Ui::check('show_services', 'Show links to the other TechZeeLand services (header menu and footer)', Site::on('show_services')) . '</div>'
            . '<div class="field-row mt">' . Ui::text('Services heading', 'services_title', (string)Site::get('services_title'), ['maxlength' => 40]) . '</div>');
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save navigation</button></div></form>';
        self::page('Header & footer', 'navigation', $h);
    }

    public static function navSave(): void
    {
        Auth::verifyCsrf();
        $new = [
            'show_github' => self::bool('show_github'), 'github_label' => self::str('github_label', 30),
            'show_services' => self::bool('show_services'), 'services_title' => self::str('services_title', 40),
            'footer_text' => self::str('footer_text', 400),
            'social_links' => Site::cleanLinks($_POST['social_links'] ?? [], 16),
        ];
        $new['header_links'] = self::bool('header_reset') ? null : Site::cleanLinks($_POST['header_links'] ?? [], 12);
        if (self::bool('footer_reset')) {
            $new['footer_columns'] = null;
        } else {
            $cols = [];
            foreach ((array)($_POST['footer_columns'] ?? []) as $i => $c) {
                $title = trim((string)($c['title'] ?? ''));
                $links = Site::cleanLinks($_POST['footer_cols_links_' . $i] ?? [], 12);
                if ($title === '' || !$links) { continue; }
                $cols[] = ['title' => mb_substr($title, 0, 40), 'links' => $links];
                if (count($cols) >= 4) { break; }
            }
            $new['footer_columns'] = $cols;
        }
        Site::save($new);
        Json::log('Updated header and footer');
        self::flash('ok', 'Header and footer saved.');
        self::back('/admin/navigation');
    }

    /* ---------- Appearance ---------- */

    public static function look(): void
    {
        $s = Site::all();
        $logo = '<div class="logo-row"><img src="' . Kit::e(Layout::logoUrl()) . '?v=' . substr(sha1((string)$s['logo_path']), 0, 6) . '" alt="Current logo" width="56" height="56"><div>'
            . '<div class="field"><label for="logo">Upload a new logo (SVG, PNG, WebP or JPG, up to 1 MB)</label><input type="file" id="logo" name="logo" accept=".svg,.png,.webp,.jpg,.jpeg,image/*"></div>'
            . ($s['logo_path'] !== '' ? '<label class="row-check"><input type="checkbox" name="logo_reset" value="1"><span class="switch"></span><span class="row-check-label">Remove the uploaded logo and use the built-in one</span></label>' : '')
            . '</div></div>';
        $h = '<form method="post" action="/admin/appearance" enctype="multipart/form-data" class="admin-form">' . Auth::field();
        $h .= Ui::card('Brand', 'palette', Ui::color('Accent colour', 'accent', (string)$s['accent'], 'Default <code>#6cc6af</code>. Light and dark variants are derived automatically.')
            . Ui::select('Default theme', 'default_theme', ['auto' => 'Follow the visitor\'s system', 'light' => 'Light', 'dark' => 'Dark'], (string)$s['default_theme'], ['hint' => 'Visitors can still switch with the sun/moon button.'])
            . $logo
            . Ui::text('Social preview image (Open Graph)', 'og_image', (string)$s['og_image'], ['placeholder' => '/assets/img/og-image.png', 'hint' => 'A path on this site or a full URL. Recommended 1200×630.']));
        $h .= Ui::card('Announcement bar', 'megaphone',
            '<div class="switch-list">' . Ui::check('announcement_on', 'Show the announcement above the header', Site::on('announcement_on')) . '</div>'
            . '<div class="field-row mt">' . Ui::text('Text', 'announcement_text', (string)$s['announcement_text'], ['maxlength' => 200]) . Ui::text('Link (optional)', 'announcement_link', (string)$s['announcement_link'], ['placeholder' => '/docs or https://…']) . '</div>');
        $h .= Ui::card('Cookie / notice banner', 'info',
            '<div class="switch-list">' . Ui::check('cookie_banner_on', 'Show a dismissible notice banner', Site::on('cookie_banner_on'), 'turn on if you add ads or analytics') . '</div>'
            . Ui::area('Banner text', 'cookie_banner_text', (string)$s['cookie_banner_text'], ['rows' => 2, 'maxlength' => 300]));
        $h .= Ui::card('Custom CSS', 'code', Ui::area('CSS added to every public page', 'custom_css', (string)$s['custom_css'], ['rows' => 8, 'mono' => true, 'maxlength' => 20000, 'hint' => 'Advanced. Override variables such as <code>--accent</code>, <code>--radius</code> or <code>--bg</code>.']));
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save appearance</button></div></form>';
        self::page('Appearance', 'appearance', $h);
    }

    public static function lookSave(): void
    {
        Auth::verifyCsrf();
        $new = [
            'accent' => Site::cleanColor(self::str('accent', 7)),
            'default_theme' => in_array($_POST['default_theme'] ?? '', ['auto', 'light', 'dark'], true) ? (string)$_POST['default_theme'] : 'auto',
            'og_image' => self::str('og_image', 300),
            'announcement_on' => self::bool('announcement_on'), 'announcement_text' => self::str('announcement_text', 200),
            'announcement_link' => Site::cleanUrl(self::str('announcement_link', 300)),
            'cookie_banner_on' => self::bool('cookie_banner_on'), 'cookie_banner_text' => self::str('cookie_banner_text', 300),
            'custom_css' => str_ireplace('</style', '', self::raw('custom_css', 20000)),
        ];
        if ($new['og_image'] !== '' && Site::cleanUrl($new['og_image']) === '') { $new['og_image'] = ''; self::flash('err', 'The preview image address was not valid and was cleared.'); }

        if (self::bool('logo_reset')) {
            $old = (string)Site::get('logo_path');
            if ($old !== '') { @unlink(Json::path('uploads/' . basename($old))); }
            $new['logo_path'] = '';
        }
        $f = $_FILES['logo'] ?? null;
        if ($f && (int)($f['error'] ?? 4) === UPLOAD_ERR_OK) {
            $res = self::saveLogo($f);
            if (is_string($res) && strncmp($res, 'ERR:', 4) === 0) { self::flash('err', Kit::e(substr($res, 4))); }
            else { $old = (string)Site::get('logo_path'); if ($old !== '') { @unlink(Json::path('uploads/' . basename($old))); } $new['logo_path'] = $res; }
        } elseif ($f && (int)($f['error'] ?? 4) !== UPLOAD_ERR_NO_FILE) {
            self::flash('err', 'The logo could not be uploaded (error ' . (int)$f['error'] . '). Check the PHP upload limits in System.');
        }
        Site::save($new);
        Json::log('Updated appearance');
        self::flash('ok', 'Appearance saved.');
        self::back('/admin/appearance');
    }

    /** @return string stored file name, or "ERR:message" */
    private static function saveLogo(array $f): string
    {
        if ((int)$f['size'] > 1048576) { return 'ERR:The logo is larger than 1 MB.'; }
        $ext = strtolower(pathinfo((string)$f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['svg', 'png', 'webp', 'jpg', 'jpeg'], true)) { return 'ERR:Logo must be SVG, PNG, WebP or JPG.'; }
        $tmp = (string)$f['tmp_name'];
        if (!is_uploaded_file($tmp)) { return 'ERR:Upload failed.'; }
        if ($ext === 'svg') {
            $svg = (string)file_get_contents($tmp);
            if (stripos($svg, '<svg') === false || preg_match('/<script|on[a-z]+\s*=|javascript:|<foreignObject|<iframe/i', $svg)) { return 'ERR:That SVG contains scripts or unsafe elements. Use a plain SVG.'; }
        } elseif (@getimagesize($tmp) === false) {
            return 'ERR:That file is not a valid image.';
        }
        $name = 'logo-' . substr(bin2hex(random_bytes(4)), 0, 8) . '.' . $ext;
        if (!move_uploaded_file($tmp, Json::path('uploads/' . $name))) { return 'ERR:Could not save the logo (is the data folder writable?).'; }
        return $name;
    }

    /* ---------- Pages ---------- */

    public static function pages(): void
    {
        $rows = '';
        foreach (Pages::SLUGS as $slug => $label) {
            $p = Pages::get($slug);
            $rows .= '<tr><td><a class="strong-link" href="/admin/pages/' . $slug . '">' . Kit::e($p['title'] ?? $label) . '</a></td><td><code>/' . $slug . '</code></td><td>' . (!empty($p['edited']) ? '<span class="badge badge-accent">edited</span>' : '<span class="badge badge-soft">default</span>') . '</td><td class="right"><a class="icon-btn" href="/' . $slug . '" target="_blank" rel="noopener" aria-label="View">' . Kit::icon('external-link') . '</a> <a class="icon-btn" href="/admin/pages/' . $slug . '" aria-label="Edit">' . Kit::icon('square-pen') . '</a></td></tr>';
        }
        $h = Ui::card('Content pages', 'file-text', '<p class="hint">Edit the wording of every policy and info page. Pages use Markdown. Tokens such as <code>{site}</code>, <code>{email}</code>, <code>{url}</code> and <code>{github}</code> are replaced automatically, so the text keeps working after a domain change.</p>'
            . '<div class="table-wrap"><table class="data-table"><thead><tr><th>Page</th><th>Address</th><th>Status</th><th></th></tr></thead><tbody>' . $rows . '</tbody></table></div>');
        self::page('Pages & policies', 'pages', $h);
    }

    public static function pageEdit(string $slug): void
    {
        $p = Pages::get($slug);
        if (!$p) { Layout::error(404, 'search', 'Page not found', 'Unknown page.'); }
        $tok = '';
        foreach (array_keys(Pages::tokens()) as $t) { $tok .= '<code>' . Kit::e($t) . '</code> '; }
        $h = '<form method="post" action="/admin/pages/' . Kit::e($slug) . '" class="admin-form">' . Auth::field();
        $h .= Ui::card('Edit page', 'file-text', Ui::text('Page title', 'title', $p['title'], ['maxlength' => 80]) . Ui::area('Content (Markdown)', 'md', $p['md'], ['rows' => 22, 'mono' => true, 'maxlength' => 40000, 'hint' => 'Headings with <code>##</code>, lists with <code>-</code>, links <code>[text](url)</code>, <strong>**bold**</strong>. Tokens: ' . $tok]));
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save page</button> <button class="btn btn-secondary" type="submit" name="do" value="reset" data-confirm="Restore the default text for this page?">' . Kit::icon('rotate-ccw') . ' Restore default</button> <a class="btn btn-secondary" href="/admin/pages">Back</a></div></form>';
        self::page('Edit: ' . $p['title'], 'pages', $h);
    }

    public static function pageSave(string $slug): void
    {
        Auth::verifyCsrf();
        if (!isset(Pages::SLUGS[$slug])) { Layout::error(404, 'search', 'Page not found', 'Unknown page.'); }
        if (($_POST['do'] ?? '') === 'reset') {
            Pages::reset($slug);
            Json::log('Restored default page: ' . $slug);
            self::flash('ok', 'Default text restored.');
        } else {
            Pages::save($slug, self::str('title', 80), self::raw('md', 40000));
            Json::log('Edited page: ' . $slug);
            self::flash('ok', 'Page saved.');
        }
        self::back('/admin/pages/' . $slug);
    }

    /* ---------- SEO, ads & code ---------- */

    public static function seo(): void
    {
        $s = Site::all();
        $h = '<form method="post" action="/admin/seo" class="admin-form">' . Auth::field();
        $h .= Ui::card('Search engines', 'search',
            '<div class="switch-list">' . Ui::check('robots_index', 'Allow search engines to index this site', Site::on('robots_index')) . '</div>'
            . Ui::area('Extra robots.txt lines', 'robots_extra', (string)$s['robots_extra'], ['rows' => 3, 'mono' => true, 'maxlength' => 2000, 'hint' => 'Appended to <a href="/robots.txt" target="_blank" rel="noopener">/robots.txt</a>. The sitemap is at <a href="/sitemap.xml" target="_blank" rel="noopener">/sitemap.xml</a>.']));
        $h .= Ui::card('Advertising (optional)', 'ad',
            Ui::area('ads.txt', 'ads_txt', (string)$s['ads_txt'], ['rows' => 4, 'mono' => true, 'maxlength' => 5000, 'placeholder' => 'google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0', 'hint' => 'Served at <a href="/ads.txt" target="_blank" rel="noopener">/ads.txt</a>. Empty means no ads.txt.']));
        $h .= Ui::card('Analytics & custom code', 'code',
            Ui::area('Extra <head> HTML', 'custom_head_html', (string)$s['custom_head_html'], ['rows' => 4, 'mono' => true, 'maxlength' => 10000, 'hint' => 'Verification meta tags or analytics <code>&lt;script src&gt;</code> tags. Scripts only run if their host is allowed under Security.'])
            . Ui::area('HTML before </body>', 'custom_footer_html', (string)$s['custom_footer_html'], ['rows' => 3, 'mono' => true, 'maxlength' => 10000])
            . '<p class="hint">This HTML is inserted as written. Only paste code you trust.</p>');
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save</button></div></form>';
        self::page('SEO, ads & code', 'seo', $h);
    }

    public static function seoSave(): void
    {
        Auth::verifyCsrf();
        Site::save([
            'robots_index' => self::bool('robots_index'), 'robots_extra' => self::raw('robots_extra', 2000),
            'ads_txt' => self::raw('ads_txt', 5000), 'custom_head_html' => self::raw('custom_head_html', 10000), 'custom_footer_html' => self::raw('custom_footer_html', 10000),
        ]);
        Json::log('Updated SEO, ads and custom code');
        self::flash('ok', 'Saved.');
        self::back('/admin/seo');
    }

    /* ---------- Security ---------- */

    public static function security(): void
    {
        $s = Site::all();
        $h = '<form method="post" action="/admin/security" class="admin-form">' . Auth::field();
        $h .= Ui::card('Privacy & statistics', 'sliders', '<div class="switch-list">' . Ui::check('stats_on', 'Count visits (anonymous daily totals, no IP addresses)', Site::on('stats_on')) . '</div>');
        $h .= Ui::card('Public API (CORS)', 'zap', Ui::text('Allowed origin', 'cors_origin', (string)$s['cors_origin'], ['hint' => '<code>*</code> lets any website call the public API. Use one origin such as <code>https://example.com</code> to restrict it.']));
        $h .= Ui::card('Content Security Policy', 'shield', '<p class="hint">By default only this site\'s own scripts run. Add hosts here if you use analytics or ads (for example <code>https://www.googletagmanager.com</code>).</p>'
            . Ui::text('Extra script hosts', 'csp_script_hosts', (string)$s['csp_script_hosts'], ['placeholder' => 'https://www.googletagmanager.com https://pagead2.googlesyndication.com'])
            . Ui::text('Extra connect hosts', 'csp_connect_hosts', (string)$s['csp_connect_hosts'], ['placeholder' => 'https://www.google-analytics.com'])
            . Ui::text('Extra image hosts', 'csp_img_hosts', (string)$s['csp_img_hosts'], ['placeholder' => 'https://www.google-analytics.com'])
            . '<div class="switch-list">' . Ui::check('csp_allow_inline', 'Allow inline scripts', Site::on('csp_allow_inline'), 'weakens XSS protection; only if a snippet requires it') . '</div>');
        $h .= Ui::card('Maintenance mode', 'wrench', '<div class="switch-list">' . Ui::check('maintenance_on', 'Show the maintenance page to visitors (admin stays available)', Site::on('maintenance_on')) . '</div>'
            . Ui::area('Message', 'maintenance_message', (string)$s['maintenance_message'], ['rows' => 2, 'maxlength' => 300]));
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save</button></div></form>';
        $info = '<p>Failed sign-ins in the last 15 minutes: <strong>' . Auth::recentFailures() . '</strong>. After six failures from one address, sign-in is locked for 15 minutes.</p><p class="hint">Sessions use a cookie limited to <code>/admin</code> (HttpOnly, SameSite=Strict). Every form is CSRF-protected.</p>';
        $h .= Ui::card('Sign-in protection', 'lock', $info);
        self::page('Security', 'security', $h);
    }

    public static function securitySave(): void
    {
        Auth::verifyCsrf();
        $hosts = static fn(string $k): string => trim((string)preg_replace('/[^a-zA-Z0-9:\/\.\-\*\s]/', '', self::str($k, 400)));
        Site::save([
            'stats_on' => self::bool('stats_on'), 'cors_origin' => self::str('cors_origin', 200) ?: '*',
            'csp_script_hosts' => $hosts('csp_script_hosts'), 'csp_connect_hosts' => $hosts('csp_connect_hosts'), 'csp_img_hosts' => $hosts('csp_img_hosts'),
            'csp_allow_inline' => self::bool('csp_allow_inline'),
            'maintenance_on' => self::bool('maintenance_on'), 'maintenance_message' => self::str('maintenance_message', 300),
        ]);
        Json::log('Updated security settings' . (self::bool('maintenance_on') ? ' (maintenance mode ON)' : ''));
        self::flash('ok', 'Security settings saved.');
        self::back('/admin/security');
    }

    /* ---------- Traffic ---------- */

    public static function traffic(): void
    {
        $range = (int)($_GET['days'] ?? 30);
        if (!in_array($range, [7, 14, 30, 90], true)) { $range = 30; }
        $days = Stats::days($range);
        $pv = []; $api = []; $bot = [];
        foreach ($days as $d => $r) { $pv[$d] = (int)($r['pv'] ?? 0); $api[$d] = (int)($r['api'] ?? 0); $bot[$d] = (int)($r['bot'] ?? 0); }
        $tabs = '<p class="range">';
        foreach ([7, 14, 30, 90] as $n) { $tabs .= '<a class="chip' . ($n === $range ? ' is-active' : '') . '" href="/admin/traffic?days=' . $n . '">' . $n . ' days</a> '; }
        $tabs .= '</p>';
        $h = $tabs . Ui::card('Requests per day', 'bar-chart', Ui::chart(array_keys($days), [['page views', $pv, 'bar-file'], ['API / assets', $api, 'bar-css']], 170));
        $h .= '<div class="admin-cols">'
            . Ui::card('Top pages', 'trophy', Ui::rank(Stats::top($days, 'p', 12)))
            . Ui::card('Referring sites', 'link', Ui::rank(Stats::top($days, 'r', 12))) . '</div>';
        $h .= '<div class="admin-cols">'
            . Ui::card('Devices', 'smartphone', Ui::rank(Stats::top($days, 'd', 6)))
            . Ui::card('Not found (404)', 'search', Ui::rank(Stats::top($days, 'nf', 12)) . '<p class="hint">Frequent 404s point to broken links or scanners probing the site.</p>') . '</div>';
        $evs = [];
        foreach ($days as $r) { foreach ((array)($r['ev'] ?? []) as $k => $n) { $evs[$k] = ($evs[$k] ?? 0) + (int)$n; } }
        arsort($evs);
        $h .= '<div class="admin-cols">' . Ui::card('Feature usage', 'activity', Ui::rank($evs))
            . Ui::card('Health of the traffic', 'gauge', '<ul class="checks"><li class="ok">' . Kit::icon('timer') . '<div><strong>' . Stats::avgMs($days) . ' ms</strong><small>average response time</small></div></li>'
                . '<li class="' . (Stats::sum($days, 'err') ? 'warn' : 'ok') . '">' . Kit::icon(Stats::sum($days, 'err') ? 'alert-triangle' : 'check-circle') . '<div><strong>' . Stats::sum($days, 'err') . ' server errors</strong><small>HTTP 5xx in this period</small></div></li>'
                . '<li class="ok">' . Kit::icon('scan') . '<div><strong>' . number_format(Stats::sum($days, 'bot')) . ' bot requests</strong><small>counted separately, not in page views</small></div></li></ul>') . '</div>';
        self::page('Traffic', 'traffic', $h);
    }

    /* ---------- System ---------- */

    public static function system(): void
    {
        $rows = '';
        foreach (Monitor::system() as $k => $v) { $rows .= '<tr><th scope="row">' . Kit::e($k) . '</th><td>' . Kit::e($v) . '</td></tr>'; }
        $h = Ui::card('Server', 'server', '<div class="table-wrap"><table class="data-table"><tbody>' . $rows . '</tbody></table></div>');

        $eco = '';
        if (($_GET['ping'] ?? '') === '1') {
            $eco = '<div class="table-wrap"><table class="data-table"><thead><tr><th>Service</th><th>URL</th><th>Status</th><th>Time</th></tr></thead><tbody>';
            foreach (Monitor::ecosystem() as $k => $r) {
                $eco .= '<tr><td>' . Kit::e(Kit::SERVICES[$k]['name']) . '</td><td><code>' . Kit::e(Kit::service($k)) . '</code></td><td>' . ($r['ok'] ? '<span class="badge badge-ok">up</span>' : '<span class="badge badge-warn">' . Kit::e($r['note']) . '</span>') . '</td><td>' . $r['ms'] . ' ms</td></tr>';
            }
            $eco .= '</tbody></table></div>';
        }
        $h .= Ui::card('TechZeeLand services status', 'activity', $eco . '<p><a class="btn btn-secondary" href="/admin/system?ping=1">' . Kit::icon('refresh') . ' Check all services now</a></p><p class="hint">Contacts the health endpoint of each service from this server (3 second timeout).</p>');

        $tools = '<form method="post" action="/admin/tools" class="tool-forms">' . Auth::field()
            . '<a class="btn btn-secondary" href="/admin/backup.zip">' . Kit::icon('archive') . ' Download backup (.zip)</a>'
            . '<a class="btn btn-secondary" href="/admin/export.json">' . Kit::icon('download') . ' Export settings (.json)</a>'
            . '<button class="btn btn-secondary" name="do" value="clear_stats" type="submit" data-confirm="Clear all traffic statistics?">' . Kit::icon('trash') . ' Clear statistics</button>'
            . '<button class="btn btn-secondary" name="do" value="clear_errors" type="submit">' . Kit::icon('trash') . ' Clear error log</button></form>'
            . '<form method="post" action="/admin/tools" enctype="multipart/form-data" class="inline-form mt">' . Auth::field() . '<input type="hidden" name="do" value="import"><input type="file" name="settings" accept=".json,application/json" required><button class="btn btn-secondary" type="submit" data-confirm="Import settings from this file? Current settings will be replaced.">' . Kit::icon('upload') . ' Import settings</button></form>'
            . '<p class="hint">Backups contain settings, pages, uploads and statistics (never the admin password). Importing a settings export is the quickest way to copy the look of one service to another.</p>';
        $h .= Ui::card('Backup & restore', 'archive', $tools);

        $hist = '';
        foreach (Site::history() as $f) {
            $hist .= '<li><span>' . Kit::e(preg_replace('/^site-(\d{4})(\d{2})(\d{2})-(\d{2})(\d{2})(\d{2})\.json$/', '$1-$2-$3 $4:$5:$6 UTC', $f)) . '</span><form method="post" action="/admin/tools" class="inline-form">' . Auth::field() . '<input type="hidden" name="do" value="restore"><input type="hidden" name="file" value="' . Kit::e($f) . '"><button class="btn btn-secondary btn-sm" type="submit" data-confirm="Restore these settings?">' . Kit::icon('undo') . ' Restore</button></form></li>';
        }
        $h .= Ui::card('Settings history', 'history', $hist !== '' ? '<ul class="history-list">' . $hist . '</ul>' : '<p class="muted">Earlier versions appear here after the first save.</p>', 'The last 15 versions of the site settings are kept so you can undo a mistake.');

        $log = Monitor::errorLogTail(40);
        $h .= Ui::card('PHP error log', 'bug', $log ? '<pre class="log-view">' . Kit::e(implode("\n", $log)) . '</pre>' : '<p class="muted">No errors logged. 🎉</p>');
        self::page('System & backup', 'system', $h);
    }

    public static function tools(): void
    {
        Auth::verifyCsrf();
        $do = (string)($_POST['do'] ?? '');
        if ($do === 'clear_stats') { Stats::clear(); Json::log('Cleared statistics'); self::flash('ok', 'Statistics cleared.'); }
        elseif ($do === 'clear_errors') { Monitor::clearErrorLog(); Json::log('Cleared error log'); self::flash('ok', 'Error log cleared.'); }
        elseif ($do === 'restore') {
            if (Site::restore(self::str('file', 60))) { Json::log('Restored earlier site settings'); self::flash('ok', 'Settings restored.'); }
            else { self::flash('err', 'That version could not be restored.'); }
        } elseif ($do === 'import') {
            $f = $_FILES['settings'] ?? null;
            $d = ($f && (int)$f['error'] === UPLOAD_ERR_OK && (int)$f['size'] < 2097152) ? json_decode((string)file_get_contents((string)$f['tmp_name']), true) : null;
            if (!is_array($d) || !isset($d['site']) || !is_array($d['site'])) { self::flash('err', 'That is not a valid settings export.'); }
            else {
                $allowed = array_keys(Site::BASE_DEFAULTS);
                $in = array_intersect_key($d['site'], array_flip($allowed));
                unset($in['logo_path']);
                Site::save($in);
                if (isset($d['pages']) && is_array($d['pages'])) { Json::write('pages.json', $d['pages']); }
                Json::log('Imported settings');
                self::flash('ok', 'Settings imported. (The logo file is not part of an export; upload it again under Appearance.)');
            }
        }
        self::back('/admin/system');
    }

    public static function export(): void
    {
        $site = Json::read('site.json', []);
        unset($site['logo_path']);
        foreach ((array)Kit::cfg('secret_keys', []) as $sk) { unset($site[$sk]); }   // never export secrets (e.g. OAuth client secret)
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="tzl-settings-' . Kit::cfg('service') . '-' . gmdate('Ymd') . '.json"');
        echo json_encode(['kit' => Kit::VERSION, 'service' => Kit::cfg('service'), 'exported' => Kit::now(), 'site' => $site, 'pages' => Json::read('pages.json', [])], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        Json::log('Exported settings');
        exit;
    }

    public static function backup(): void
    {
        if (!class_exists('ZipArchive')) { self::flash('err', 'The PHP zip extension is not installed.'); self::back('/admin/system'); }
        $tmp = tempnam(sys_get_temp_dir(), 'tzlbk');
        $zip = new \ZipArchive();
        if ($tmp === false || $zip->open($tmp, \ZipArchive::OVERWRITE) !== true) { self::flash('err', 'Could not create the backup.'); self::back('/admin/system'); }
        $root = Kit::$data;
        $skip = ['admin.json', 'throttle.json', 'INITIAL_ADMIN_PASSWORD.txt', 'error.log'];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $path = (string)$file;
            $rel = ltrim(substr($path, strlen($root)), '/');
            if (in_array($rel, $skip, true) || strncmp($rel, 'sessions/', 9) === 0 || substr($rel, -5) === '.lock' || substr($rel, -4) === '.tmp') { continue; }
            $zip->addFile($path, $rel);
        }
        $zip->close();
        Json::log('Downloaded a backup');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . Kit::cfg('service') . '-backup-' . gmdate('Ymd-His') . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /* ---------- Activity & account ---------- */

    public static function activity(): void
    {
        $rows = '';
        foreach (Json::activity(200) as $r) {
            $rows .= '<tr><td class="nowrap" title="' . Kit::e($r['t']) . '">' . Kit::e(Kit::ago((string)$r['t'])) . '</td><td>' . Kit::e($r['u']) . '</td><td>' . Kit::e($r['m']) . '</td></tr>';
        }
        $h = Ui::card('Audit log', 'history', $rows !== '' ? '<div class="table-wrap"><table class="data-table"><thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead><tbody>' . $rows . '</tbody></table></div>' : '<p class="muted">Nothing recorded yet.</p>', 'The last 200 admin actions.');
        self::page('Activity log', 'activity', $h);
    }

    public static function account(): void
    {
        $a = Auth::admin();
        $h = '<form method="post" action="/admin/account" class="admin-form narrow-form">' . Auth::field();
        $h .= Ui::card('Sign-in details', 'user-lock',
            Ui::text('Username', 'username', (string)($a['username'] ?? ''), ['maxlength' => 40, 'required' => true, 'autocomplete' => 'username'])
            . '<div class="field"><label for="current">Current password</label><input type="password" id="current" name="current" autocomplete="current-password" required></div>'
            . '<div class="field"><label for="new">New password</label><input type="password" id="new" name="new" autocomplete="new-password" minlength="10"><p class="hint">At least 10 characters. Leave empty to keep the current one.</p></div>'
            . '<div class="field"><label for="new2">Repeat new password</label><input type="password" id="new2" name="new2" autocomplete="new-password"></div>');
        $h .= '<div class="form-actions"><button class="btn btn-primary" type="submit">' . Kit::icon('save') . ' Save</button></div></form>';
        if (!empty($a['last_login'])) { $h .= '<p class="hint">Last sign-in: ' . Kit::e(Kit::ago((string)$a['last_login'])) . '.</p>'; }
        self::page('Account', 'account', $h);
    }

    public static function accountSave(): void
    {
        Auth::verifyCsrf();
        $user = self::str('username', 40);
        $cur = (string)($_POST['current'] ?? '');
        $new = (string)($_POST['new'] ?? '');
        $err = [];
        if (!preg_match('/^[A-Za-z0-9._@-]{3,40}$/', $user)) { $err[] = 'Username must be 3 to 40 characters: letters, numbers and . _ @ -'; }
        if (!Auth::verifyPassword($cur)) { $err[] = 'The current password is not correct.'; }
        if ($new !== '' && strlen($new) < 10) { $err[] = 'The new password must be at least 10 characters.'; }
        if ($new !== (string)($_POST['new2'] ?? '')) { $err[] = 'The two new passwords do not match.'; }
        if (Auth::mustChange() && $new === '') { $err[] = 'Please choose a new password.'; }
        if ($err) { foreach ($err as $e) { self::flash('err', Kit::e($e)); } self::back('/admin/account'); }
        Auth::changeCredentials($user, $new);
        self::flash('ok', 'Your details were updated.');
        self::back('/admin');
    }
}
