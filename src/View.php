<?php
namespace App;

/**
 * Shared layout: <head>, header, footer, content pages, error pages, flash messages.
 */
final class View
{
    public const SOCIAL = [
        'youtube'   => ['YouTube', 'https://www.youtube.com/@TechZeeLand'],
        'facebook'  => ['Facebook', 'https://www.facebook.com/TechZeeLand'],
        'instagram' => ['Instagram', 'https://www.instagram.com/TechZeeLand'],
        'tiktok'    => ['TikTok', 'https://www.tiktok.com/@TechZeeLand'],
        'github'    => ['GitHub', 'https://github.com/TechZeeLand'],
    ];
    public const SUPPORT_URL = 'https://pathaopay.me/@azlanaziz';

    public static function asset(string $path): string
    {
        $f = App::$root . '/public/' . ltrim($path, '/');
        return '/' . ltrim($path, '/') . (is_file($f) ? '?v=' . filemtime($f) : '');
    }

    public static function site(): string { return (string)Store::setting('site_name'); }

    /** Render views/<name>.php with $vars extracted. */
    public static function render(string $name, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        require App::$root . '/views/' . $name . '.php';
    }

    /* ---------- flash messages (admin) ---------- */

    public static function flash(string $type, string $msg): void
    {
        Auth::start();
        $_SESSION['flash'][] = [$type, $msg];
    }

    public static function flashes(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE || empty($_SESSION['flash'])) { return ''; }
        $out = '';
        foreach ($_SESSION['flash'] as [$type, $msg]) {
            $icon = $type === 'err' ? 'alert-triangle' : ($type === 'info' ? 'info' : 'check-circle');
            $out .= '<div class="alert alert-' . App::e($type) . '" role="status">' . App::icon($icon) . '<div>' . $msg . '</div></div>';
        }
        $_SESSION['flash'] = [];
        return $out;
    }

    /* ---------- <head> + header ---------- */

    /**
     * @param array $opts nav, canonical, og_image, noindex, admin, body
     */
    public static function head(string $title, string $desc = '', array $opts = []): void
    {
        $site = self::site();
        if ($desc === '') { $desc = (string)Store::setting('tagline') . ' Free commercial-use fonts you can cache on your own server.'; }
        $full = !empty($opts['raw_title']) ? $title : $title . ' — ' . $site;
        $t = App::e($full);
        $d = App::e($desc);
        $base = App::baseUrl();
        $canon = $base . ($opts['canonical'] ?? strtok((string)($_SERVER['REQUEST_URI'] ?? '/'), '?'));
        $og = $base . ($opts['og_image'] ?? '/assets/img/og-image.png');
        $noindex = !empty($opts['noindex']) || !empty($opts['admin']) || !Store::setting('robots_index');
        $admin = !empty($opts['admin']);
        $nav = (string)($opts['nav'] ?? '');
        $iconOrigin = App::iconOrigin();

        header('Content-Type: text/html; charset=utf-8');
        if ($admin) { header('Cache-Control: no-store'); }
        App::securityHeaders();

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">' . "\n";
        echo '<title>' . $t . '</title><meta name="description" content="' . $d . '">' . "\n";
        if ($noindex) { echo '<meta name="robots" content="noindex, nofollow">' . "\n"; }
        echo '<link rel="canonical" href="' . App::e($canon) . '">' . "\n";
        echo '<meta property="og:type" content="website"><meta property="og:site_name" content="' . App::e($site) . '"><meta property="og:title" content="' . $t . '"><meta property="og:description" content="' . $d . '"><meta property="og:url" content="' . App::e($canon) . '"><meta property="og:image" content="' . App::e($og) . '">' . "\n";
        echo '<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="' . $t . '"><meta name="twitter:description" content="' . $d . '"><meta name="twitter:image" content="' . App::e($og) . '">' . "\n";
        echo '<link rel="icon" type="image/svg+xml" href="/assets/img/logo.svg"><link rel="alternate icon" href="/favicon.ico" sizes="any"><link rel="apple-touch-icon" href="/assets/favicon/apple-touch-icon.png"><link rel="manifest" href="/assets/favicon/site.webmanifest">' . "\n";
        echo '<meta name="theme-color" content="#6cc6af" media="(prefers-color-scheme: light)"><meta name="theme-color" content="#0c1513" media="(prefers-color-scheme: dark)">' . "\n";
        echo '<link rel="preload" href="/assets/fonts/Alata-UI.woff2" as="font" type="font/woff2" crossorigin>' . "\n";
        if ($iconOrigin !== '') { echo '<link rel="preconnect" href="' . App::e($iconOrigin) . '" crossorigin>' . "\n"; }
        echo '<link rel="stylesheet" href="' . App::e(App::iconCss()) . '">' . "\n";
        echo '<link rel="stylesheet" href="' . self::asset('assets/css/style.css') . '">' . "\n";
        echo '<script src="' . self::asset('assets/js/theme-init.js') . '"></script>' . "\n";
        echo '</head><body class="' . App::e(($admin ? 'is-admin ' : '') . ($opts['body'] ?? '')) . '">' . "\n";
        echo '<a class="skip-link" href="#main-content">Skip to content</a>' . "\n";

        if (!$admin && Store::setting('announcement_on') && trim((string)Store::setting('announcement_text')) !== '') {
            $txt = App::e((string)Store::setting('announcement_text'));
            $lnk = trim((string)Store::setting('announcement_link'));
            echo '<div class="announcement">' . ($lnk !== '' ? '<a href="' . App::e($lnk) . '">' . $txt . '</a>' : $txt) . '</div>';
        }

        echo '<header class="topbar"><div class="topbar-inner"><div class="site-title"><a class="brand-link" href="' . ($admin ? '/admin' : '/') . '"><img class="brand-logo" src="/assets/img/logo.svg" alt="" width="30" height="30"><span>' . App::e($site) . '</span></a>';
        if ($admin) { echo '<span class="badge badge-accent">Admin</span>'; }
        echo '</div>';
        if (!$admin) {
            echo '<nav class="main-nav" id="main-nav" aria-label="Main">';
            foreach (['fonts' => ['/#fonts', 'Fonts', 'font'], 'docs' => ['/docs', 'Docs', 'book-open'], 'about' => ['/about', 'About', 'info']] as $k => [$u, $l, $i]) {
                echo '<a href="' . $u . '"' . ($nav === $k ? ' class="active" aria-current="page"' : '') . '>' . App::icon($i) . '<span>' . $l . '</span></a>';
            }
            echo '</nav>';
        }
        echo '<div class="topbar-actions">';
        if ($admin) {
            echo '<a class="gh-link" href="/" target="_blank" rel="noopener">' . App::icon('external-link') . '<span>View site</span></a>';
        } else {
            echo '<a class="gh-link" href="' . App::e((string)Store::setting('github_url')) . '" target="_blank" rel="noopener">' . App::icon('github') . '<span>Source on GitHub</span></a>';
        }
        echo '<button type="button" class="theme-toggle" id="theme-toggle" aria-pressed="false" aria-label="Switch to dark mode" title="Switch theme">' . App::icon('sun', 'ico-sun') . App::icon('moon', 'ico-moon') . '</button>';
        if (!$admin) {
            echo '<button type="button" class="menu-toggle" id="menu-toggle" aria-expanded="false" aria-controls="main-nav" aria-label="Open menu">' . App::icon('menu') . '</button>';
        } else {
            echo '<button type="button" class="menu-toggle" id="admin-menu-toggle" aria-expanded="false" aria-controls="admin-nav" aria-label="Open admin menu">' . App::icon('menu') . '</button>';
        }
        echo '</div></div></header>' . "\n";
    }

    /* ---------- footer ---------- */

    public static function foot(array $opts = []): void
    {
        $admin = !empty($opts['admin']);
        $site = App::e(self::site());
        if (!$admin) {
            echo '<footer class="site-footer"><div class="footer-inner"><div class="footer-cols">';
            echo '<div class="footer-col footer-about"><a class="brand-link" href="/"><img class="brand-logo" src="/assets/img/logo.svg" alt="" width="30" height="30"><span>' . $site . '</span></a><p>' . App::e((string)Store::setting('tagline')) . ' Every font here is free for commercial use.</p></div>';
            echo '<nav class="footer-col" aria-label="Site pages"><h3>Pages</h3><ul class="footer-links">';
            foreach (['/' => 'Home', '/#fonts' => 'Browse fonts', '/docs' => 'Documentation', '/about' => 'About', '/contribute' => 'Contribute a font', '/contact' => 'Contact'] as $u => $l) {
                echo '<li><a href="' . $u . '">' . $l . '</a></li>';
            }
            echo '</ul></nav><nav class="footer-col" aria-label="Legal"><h3>Legal</h3><ul class="footer-links">';
            foreach (['/privacy-policy' => 'Privacy Policy', '/terms-and-conditions' => 'Terms &amp; Conditions', '/cookie-policy' => 'Cookie Policy', '/license-policy' => 'Font License Policy'] as $u => $l) {
                echo '<li><a href="' . $u . '">' . $l . '</a></li>';
            }
            echo '</ul></nav><nav class="footer-col" aria-label="Social links"><h3>Connect</h3><ul class="social-links">';
            foreach (self::SOCIAL as $k => [$l, $u]) {
                echo '<li><a href="' . $u . '" target="_blank" rel="noopener">' . App::icon($k) . ' ' . $l . '</a></li>';
            }
            echo '<li><a href="' . self::SUPPORT_URL . '" target="_blank" rel="noopener">' . App::icon('pathaopay') . ' Support</a></li>';
            echo '</ul></nav></div>';
            echo '<div class="footer-bottom"><p>&copy; ' . gmdate('Y') . ' TechZeeLand. ' . $site . ' is free &amp; open source under the <a href="https://www.gnu.org/licenses/agpl-3.0.html" target="_blank" rel="noopener">AGPL-3.0</a> license. Each font keeps its own open license.</p><p><a href="https://github.com/TechZeeLand" target="_blank" rel="noopener">Made by TechZeeLand</a></p></div></div></footer>';
        } else {
            echo '<footer class="admin-foot"><p>' . $site . ' admin &middot; v' . App::VERSION . '</p></footer>';
        }
        echo '<script src="' . self::asset('assets/js/theme.js') . '"></script>';
        echo '<script src="' . self::asset($admin ? 'assets/js/admin.js' : 'assets/js/app.js') . '"></script>';
        echo '</body></html>';
    }

    /* ---------- content + error pages ---------- */

    /** Simple prose page inside a card (About, policies…). $html is trusted markup written in the source. */
    public static function page(string $title, string $desc, string $h1, string $lede, string $html, array $opts = []): void
    {
        self::head($title, $desc, $opts);
        echo '<main id="main-content" class="content-wrap"><h1>' . $h1 . '</h1><p class="content-lede">' . $lede . '</p><div class="content-card prose">' . $html . '</div></main>';
        self::foot();
    }

    public static function error(int $code, string $icon, string $title, string $desc): void
    {
        http_response_code($code);
        self::head($title, $desc, ['noindex' => true, 'raw_title' => false]);
        echo '<main id="main-content" class="error-page"><div class="error-icon" aria-hidden="true">' . App::icon($icon) . '</div><p class="error-code">Error ' . (int)$code . '</p><h1 class="error-title">' . App::e($title) . '</h1><p class="error-desc">' . App::e($desc) . '</p>';
        echo '<div class="error-actions"><a href="/" class="btn btn-primary">' . App::icon('home') . ' Go to homepage</a> <a href="/contact" class="btn btn-secondary">' . App::icon('mail') . ' Contact us</a></div>';
        echo '<p class="error-links">Or try: <a href="/docs">Docs</a> &middot; <a href="/privacy-policy">Privacy Policy</a> &middot; <a href="/terms-and-conditions">Terms &amp; Conditions</a></p></main>';
        self::foot();
        exit;
    }

    /* ---------- admin shell ---------- */

    public static function adminOpen(string $title, string $active): void
    {
        self::head($title, '', ['admin' => true]);
        $items = [
            'dashboard' => ['/admin', 'Dashboard', 'dashboard'],
            'fonts'     => ['/admin/fonts', 'Fonts', 'font'],
            'new'       => ['/admin/fonts/new', 'Add font', 'font-plus'],
            'settings'  => ['/admin/settings', 'Settings', 'settings'],
            'account'   => ['/admin/account', 'Account', 'user-lock'],
            'activity'  => ['/admin/activity', 'Activity', 'history'],
        ];
        echo '<div class="admin-wrap"><aside class="admin-nav" id="admin-nav"><nav aria-label="Admin">';
        foreach ($items as $k => [$u, $l, $i]) {
            echo '<a href="' . $u . '"' . ($active === $k ? ' class="active" aria-current="page"' : '') . '>' . App::icon($i) . '<span>' . $l . '</span></a>';
        }
        echo '</nav><form method="post" action="/admin/logout" class="admin-logout">' . Auth::field() . '<button type="submit" class="btn btn-secondary btn-block">' . App::icon('logout') . ' Sign out</button></form></aside>';
        echo '<main id="main-content" class="admin-main">' . self::flashes();
    }

    public static function adminClose(): void
    {
        echo '</main></div>';
        self::foot(['admin' => true]);
    }
}
