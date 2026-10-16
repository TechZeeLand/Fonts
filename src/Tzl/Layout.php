<?php
declare(strict_types=1);
namespace Tzl;

/** Shared page shell: <head>, header, footer, prose pages, error pages. Everything comes from Site settings. */
final class Layout
{
    public static function e($s): string { return Kit::e($s); }

    /** Mix two #rrggbb colours: $t=0 => $a, $t=1 => $b. */
    public static function mix(string $a, string $b, float $t): string
    {
        $a = ltrim($a, '#'); $b = ltrim($b, '#');
        $o = '#';
        for ($i = 0; $i < 6; $i += 2) {
            $x = hexdec(substr($a, $i, 2)); $y = hexdec(substr($b, $i, 2));
            $o .= str_pad(dechex((int)round($x + ($y - $x) * $t)), 2, '0', STR_PAD_LEFT);
        }
        return $o;
    }

    private static function lum(string $hex): float
    {
        $h = ltrim($hex, '#');
        return (0.2126 * hexdec(substr($h, 0, 2)) + 0.7152 * hexdec(substr($h, 2, 2)) + 0.0722 * hexdec(substr($h, 4, 2))) / 255;
    }

    /** CSS variable overrides for a custom accent colour (empty when the default is used). */
    public static function accentCss(): string
    {
        $a = Site::cleanColor((string)Site::get('accent'));
        if ($a === '#6cc6af') { return ''; }
        $ink = self::lum($a) > 0.55 ? '#10201b' : '#ffffff';
        return ':root{--accent:' . $a . ';--accent-ink:' . $ink . ';--accent-dark:' . self::mix($a, '#000000', .12) . ';--accent-text:' . self::mix($a, '#000000', .38)
            . ';--accent-soft:' . self::mix($a, '#ffffff', .78) . '}'
            . 'html[data-theme="dark"]{--accent-text:' . $a . ';--accent-dark:' . self::mix($a, '#ffffff', .2) . ';--accent-soft:' . self::mix($a, '#0c1513', .82) . '}';
    }

    public static function logoUrl(): string
    {
        return Site::get('logo_path') !== '' ? '/brand/logo' : '/assets/img/logo.svg';
    }

    public static function asset(string $p): string { return Kit::asset($p); }

    private static function linkAttrs(array $l): string
    {
        return !empty($l['new']) || preg_match('#^https?://#i', (string)$l['url']) && strpos((string)$l['url'], Kit::baseUrl()) !== 0
            ? ' target="_blank" rel="noopener"' : '';
    }

    private static function isActive(string $url, string $path): bool
    {
        $u = (string)parse_url($url, PHP_URL_PATH);
        if ($u === '' || preg_match('#^https?://#i', $url)) { return false; }
        if ($u === '/') { return $path === '/' && strpos($url, '#') === false; }
        return $path === $u || strncmp($path, $u . '/', strlen($u) + 1) === 0;
    }

    /**
     * @param array $opts canonical, og_image, noindex, admin(bool), body(class), raw_title, csp(array), head(html), css(array), nav
     */
    public static function head(string $title, string $desc = '', array $opts = []): void
    {
        $site = (string)Site::get('site_name');
        if ($desc === '') { $desc = trim((string)Site::get('description')) ?: (string)Site::get('tagline'); }
        $full = !empty($opts['raw_title']) ? $title : ($title === '' ? $site : $title . ' — ' . $site);
        $admin = !empty($opts['admin']);
        $base = Kit::baseUrl();
        $path = Router::path();
        $canon = $base . ($opts['canonical'] ?? $path);
        $ogImg = (string)($opts['og_image'] ?? Site::get('og_image'));
        if ($ogImg === '') { $ogImg = '/assets/img/og-image.png'; }
        if ($ogImg[0] === '/') { $ogImg = $base . $ogImg; }
        $noindex = !empty($opts['noindex']) || $admin || !Site::on('robots_index');
        $t = self::e($full); $d = self::e($desc);

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            if ($admin) { header('Cache-Control: no-store'); }
            $csp = (array)($opts['csp'] ?? []);
            foreach (['script-src' => 'csp_script_hosts', 'connect-src' => 'csp_connect_hosts', 'img-src' => 'csp_img_hosts'] as $dir => $key) {
                $h = trim((string)Site::get($key));
                if ($h !== '' && !$admin) { $csp[$dir] = (isset($csp[$dir]) ? $csp[$dir] . ' ' : '') . preg_replace('/[^a-zA-Z0-9:\/\.\-\*\s]/', '', $h); }
            }
            $hook = Kit::cfg('csp_extra', null);
            if (is_callable($hook) && !$admin) {
                foreach ((array)$hook() as $dir => $val) { $csp[$dir] = (isset($csp[$dir]) ? $csp[$dir] . ' ' : '') . $val; }
            }
            if (Site::on('csp_allow_inline') && !$admin) { $csp['script-src'] = (isset($csp['script-src']) ? $csp['script-src'] . ' ' : '') . "'unsafe-inline'"; }
            Kit::securityHeaders($csp);
        }

        echo '<!DOCTYPE html><html lang="en" data-theme-default="' . self::e(Site::get('default_theme')) . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        echo '<title>' . $t . '</title><meta name="description" content="' . $d . '">' . "\n";
        if ($noindex) { echo '<meta name="robots" content="noindex, nofollow">' . "\n"; }
        echo '<link rel="canonical" href="' . self::e($canon) . '">' . "\n";
        echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . self::e($site) . '"><meta property="og:title" content="' . $t . '"><meta property="og:description" content="' . $d . '"><meta property="og:url" content="' . self::e($canon) . '"><meta property="og:image" content="' . self::e($ogImg) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . $t . '"><meta name="twitter:description" content="' . $d . '"><meta name="twitter:image" content="' . self::e($ogImg) . '">' . "\n";
        echo '<link rel="icon" type="image/svg+xml" href="' . self::e(self::logoUrl()) . '"><link rel="alternate icon" href="/favicon.ico" sizes="any"><link rel="manifest" href="/manifest.webmanifest">' . "\n";
        $acc = Site::cleanColor((string)Site::get('accent'));
        echo '<meta name="theme-color" content="' . $acc . '" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0c1513" media="(prefers-color-scheme: dark)">' . "\n";
        foreach (Kit::origins() as $o) { echo '<link rel="preconnect" href="' . self::e($o) . '" crossorigin>' . "\n"; }
        echo '<link rel="stylesheet" href="' . self::e(Kit::fontCssUrl((string)Kit::cfg('ui_font', 'Alata'))) . '">' . "\n";
        echo '<link rel="stylesheet" href="' . self::e(Kit::iconCssUrl()) . '">' . "\n";
        echo '<link rel="stylesheet" href="' . self::asset('assets/tzl/tzl.css') . '">' . "\n";
        foreach ((array)Kit::cfg('css', []) as $css) { echo '<link rel="stylesheet" href="' . self::asset($css) . '">' . "\n"; }
        foreach ((array)($opts['css'] ?? []) as $css) { echo '<link rel="stylesheet" href="' . self::asset($css) . '">' . "\n"; }
        $ac = self::accentCss();
        $cc = trim((string)Site::get('custom_css'));
        if ($ac !== '' || ($cc !== '' && !$admin)) {
            echo '<style>' . $ac . ($admin ? '' : str_replace('</', '<\/', $cc)) . '</style>' . "\n";
        }
        echo '<script src="' . self::asset('assets/tzl/theme-init.js') . '"></script>' . "\n";
        if (!empty($opts['head'])) { echo $opts['head']; }
        if (!$admin && trim((string)Site::get('custom_head_html')) !== '') { echo (string)Site::get('custom_head_html') . "\n"; }
        echo '</head><body class="' . self::e(($admin ? 'is-admin ' : '') . ($opts['body'] ?? '')) . '">' . "\n";
        echo '<a class="skip-link" href="#main-content">Skip to content</a>' . "\n";

        if (!$admin && Site::on('announcement_on') && trim((string)Site::get('announcement_text')) !== '') {
            $txt = self::e(Site::get('announcement_text'));
            $lnk = Site::cleanUrl((string)Site::get('announcement_link'));
            echo '<div class="announcement">' . ($lnk !== '' ? '<a href="' . self::e($lnk) . '">' . $txt . '</a>' : $txt) . '</div>';
        }

        $home = $admin ? '/admin' : '/';
        echo '<header class="topbar"><div class="topbar-inner"><div class="site-title"><a class="brand-link" href="' . $home . '"><img class="brand-logo" src="' . self::e(self::logoUrl()) . '" alt="" width="30" height="30"><span>' . self::e($site) . '</span></a>';
        if ($admin) { echo '<span class="badge badge-accent">Admin</span>'; }
        echo '</div>';
        if (!$admin) {
            echo '<nav class="main-nav" id="main-nav" aria-label="Main">';
            foreach (Site::headerLinks() as $l) {
                $act = self::isActive((string)$l['url'], $path);
                echo '<a href="' . self::e($l['url']) . '"' . self::linkAttrs($l) . ($act ? ' class="active" aria-current="page"' : '') . '>' . (!empty($l['icon']) ? Kit::icon((string)$l['icon']) : '') . '<span>' . self::e($l['label']) . '</span></a>';
            }
            echo '</nav>';
        }
        echo '<div class="topbar-actions">';
        if ($admin) {
            echo '<a class="gh-link" href="/" target="_blank" rel="noopener">' . Kit::icon('external-link') . '<span>View site</span></a>';
        } elseif (Site::on('show_github') && Site::get('github_url') !== '') {
            echo '<a class="gh-link" href="' . self::e(Site::get('github_url')) . '" target="_blank" rel="noopener">' . Kit::icon('github') . '<span>' . self::e(Site::get('github_label')) . '</span></a>';
        }
        $hx = Kit::cfg('header_extra', null);
        if (!$admin && is_callable($hx)) { echo (string)$hx(); }
        if (Site::on('show_services')) { echo self::servicesMenu(); }
        echo '<button type="button" class="theme-toggle" id="theme-toggle" aria-pressed="false" aria-label="Switch to dark mode" title="Switch theme">' . Kit::icon('sun', 'ico-sun') . Kit::icon('moon', 'ico-moon') . '</button>';
        echo '<button type="button" class="menu-toggle" id="' . ($admin ? 'admin-menu-toggle' : 'menu-toggle') . '" aria-expanded="false" aria-controls="' . ($admin ? 'admin-nav' : 'main-nav') . '" aria-label="Open menu">' . Kit::icon('menu') . '</button>';
        echo '</div></div></header>' . "\n";
    }

    /** Dropdown that links the four TZL services (uses <details>, no JS needed). */
    public static function servicesMenu(): string
    {
        $self = (string)Kit::cfg('service', '');
        $h = '<details class="svc-menu"><summary class="icon-btn" aria-label="TechZeeLand services" title="TechZeeLand services">' . Kit::icon('grid') . '</summary><div class="svc-pop"><p class="svc-title">' . self::e(Site::get('services_title')) . '</p>';
        foreach (Kit::SERVICES as $k => $s) {
            $cur = $k === $self;
            $h .= '<a href="' . self::e($cur ? '/' : Kit::service($k)) . '"' . ($cur ? ' aria-current="true"' : '') . '>' . Kit::icon($s['icon']) . '<span><strong>' . self::e($s['name']) . '</strong><small>' . self::e($s['desc']) . '</small></span></a>';
        }
        return $h . '</div></details>';
    }

    private static function linkList(array $links): string
    {
        $o = '';
        foreach ($links as $l) {
            $o .= '<li><a href="' . self::e($l['url']) . '"' . self::linkAttrs($l) . '>' . (!empty($l['icon']) ? Kit::icon((string)$l['icon']) . ' ' : '') . self::e($l['label']) . '</a></li>';
        }
        return $o;
    }

    public static function foot(array $opts = []): void
    {
        $admin = !empty($opts['admin']);
        $site = self::e(Site::get('site_name'));
        if (!$admin) {
            $bf = Kit::cfg('before_footer', null);
            if (is_callable($bf)) { echo (string)$bf(); }
            echo '<footer class="site-footer"><div class="footer-inner"><div class="footer-cols">';
            echo '<div class="footer-col footer-about"><a class="brand-link" href="/"><img class="brand-logo" src="' . self::e(self::logoUrl()) . '" alt="" width="30" height="30"><span>' . $site . '</span></a><p>' . self::e(Site::get('tagline')) . '</p></div>';
            foreach (Site::footerColumns() as $col) {
                echo '<nav class="footer-col" aria-label="' . self::e($col['title']) . '"><h3>' . self::e($col['title']) . '</h3><ul class="footer-links">' . self::linkList((array)$col['links']) . '</ul></nav>';
            }
            if (Site::on('show_services')) {
                echo '<nav class="footer-col" aria-label="Services"><h3>' . self::e(Site::get('services_title')) . '</h3><ul class="footer-links">';
                $self = (string)Kit::cfg('service', '');
                foreach (Kit::SERVICES as $k => $s) {
                    if ($k === $self) { continue; }
                    echo '<li><a href="' . self::e(Kit::service($k)) . '">' . Kit::icon($s['icon']) . ' ' . self::e($s['name']) . '</a></li>';
                }
                echo '</ul></nav>';
            }
            $soc = Site::socialLinks();
            if ($soc) { echo '<nav class="footer-col" aria-label="Social links"><h3>Connect</h3><ul class="social-links">' . self::linkList($soc) . '</ul></nav>'; }
            echo '</div><div class="footer-bottom"><p>' . Site::footerText() . '</p><p><a href="' . self::e(Site::get('owner_url')) . '" target="_blank" rel="noopener">Made by ' . self::e(Site::get('owner_name')) . '</a></p></div></div></footer>';
            if (trim((string)Site::get('custom_footer_html')) !== '') { echo (string)Site::get('custom_footer_html'); }
            if (Site::on('cookie_banner_on')) {
                if (Kit::cfg('consent_banner', '') !== '') {
                    // two-choice consent banner (services that load ads or other third-party scripts); the service's own JS reads data-consent
                    echo '<div class="cookie-banner" id="cookie-banner" data-consent-banner role="dialog" aria-live="polite" aria-label="Cookie preferences" hidden><p>' . self::e((string)Kit::cfg('consent_banner')) . ' <a href="/cookie-policy">Cookie Policy</a></p>'
                        . '<div class="cookie-actions"><button type="button" class="btn btn-secondary btn-sm" data-consent="rejected">Essential only</button><button type="button" class="btn btn-primary btn-sm" data-consent="accepted">Accept all</button></div></div>';
                } else {
                    echo '<div class="cookie-banner" id="cookie-banner" hidden><p>' . self::e(Site::get('cookie_banner_text')) . ' <a href="/cookie-policy">Learn more</a></p><button type="button" class="btn btn-primary btn-sm" id="cookie-ok">Got it</button></div>';
                }
            }
            $fx = Kit::cfg('foot_extra', null);
            if (is_callable($fx)) { echo (string)$fx(); }
        } else {
            echo '<footer class="admin-foot"><p>' . $site . ' admin &middot; TZL Kit v' . Kit::VERSION . '</p></footer>';
        }
        echo '<script src="' . self::asset('assets/tzl/tzl.js') . '"></script>';
        foreach ((array)Kit::cfg($admin ? 'admin_js' : 'js', []) as $js) { echo '<script src="' . self::asset($js) . '"></script>'; }
        foreach ((array)($opts['js'] ?? []) as $js) { echo '<script src="' . self::asset($js) . '"></script>'; }
        echo '</body></html>';
    }

    /** Prose page in a card. $html is trusted markup. */
    public static function page(string $title, string $desc, string $h1, string $lede, string $html, array $opts = []): void
    {
        self::head($title, $desc, $opts);
        echo '<main id="main-content" class="content-wrap"><h1>' . self::e($h1) . '</h1>' . ($lede !== '' ? '<p class="content-lede">' . self::e($lede) . '</p>' : '') . '<div class="content-card prose">' . $html . '</div></main>';
        self::foot($opts);
    }

    public static function error(int $code, string $icon, string $title, string $desc, bool $links = true): void
    {
        http_response_code($code);
        self::head($title, $desc, ['noindex' => true]);
        echo '<main id="main-content" class="error-page"><div class="error-icon" aria-hidden="true">' . Kit::icon($icon) . '</div><p class="error-code">Error ' . $code . '</p><h1 class="error-title">' . self::e($title) . '</h1><p class="error-desc">' . self::e($desc) . '</p>';
        if ($links) {
            echo '<div class="error-actions"><a href="/" class="btn btn-primary">' . Kit::icon('home') . ' Go to homepage</a> <a href="/contact" class="btn btn-secondary">' . Kit::icon('mail') . ' Contact us</a></div>';
            echo '<p class="error-links">Or try: <a href="/about">About</a> &middot; <a href="/privacy-policy">Privacy Policy</a> &middot; <a href="/terms-and-conditions">Terms &amp; Conditions</a></p>';
        }
        echo '</main>';
        self::foot();
        exit;
    }
}
