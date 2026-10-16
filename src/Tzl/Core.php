<?php
declare(strict_types=1);
namespace Tzl;

/** Registers the routes every TZL service has, and runs the front controller. */
final class Core
{
    public static function register(): void
    {
        Router::get('#^/health$#', static function (): void {
            Kit::json(Monitor::health(), 200, true, ['Cache-Control' => 'no-store']);
        });

        Router::get('#^/(about|contact|privacy-policy|terms-and-conditions|cookie-policy)$#', static function (string $slug): void {
            Pages::show($slug);
        });

        Router::get('#^/robots\.txt$#', static function (): void {
            header('Content-Type: text/plain; charset=utf-8');
            if (!Site::on('robots_index')) { echo "User-agent: *\nDisallow: /\n"; exit; }
            echo "User-agent: *\nAllow: /\nDisallow: /admin\n";
            foreach ((array)Kit::cfg('robots_disallow', []) as $d) { echo 'Disallow: ' . $d . "\n"; }
            $x = trim((string)Site::get('robots_extra'));
            if ($x !== '') { echo $x . "\n"; }
            echo 'Sitemap: ' . Kit::url('/sitemap.xml') . "\n";
            exit;
        });

        Router::get('#^/sitemap\.xml$#', static function (): void {
            header('Content-Type: application/xml; charset=utf-8');
            $urls = ['/' => 1.0];
            foreach ((array)Kit::cfg('sitemap', ['/']) as $u) { $urls[$u] = $u === '/' ? 1.0 : 0.7; }
            foreach (array_keys(Pages::SLUGS) as $s) { $urls['/' . $s] = 0.3; }
            $extra = Kit::cfg('sitemap_fn', null);
            if (is_callable($extra)) { foreach ((array)$extra() as $u) { $urls[$u] = 0.6; } }
            echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
            foreach ($urls as $u => $p) { echo '<url><loc>' . Kit::e(Kit::url($u)) . '</loc><priority>' . number_format((float)$p, 1) . '</priority></url>' . "\n"; }
            echo '</urlset>';
            exit;
        });

        Router::get('#^/ads\.txt$#', static function (): void {
            $t = trim((string)Site::get('ads_txt'));
            if ($t === '') { http_response_code(404); header('Content-Type: text/plain'); echo "Not found\n"; exit; }
            header('Content-Type: text/plain; charset=utf-8');
            echo $t . "\n";
            exit;
        });

        Router::get('#^/manifest\.webmanifest$#', static function (): void {
            header('Content-Type: application/manifest+json; charset=utf-8');
            header('Cache-Control: public, max-age=3600');
            $icons = [['src' => Layout::logoUrl(), 'sizes' => 'any', 'type' => 'image/svg+xml']];
            foreach (['icon-192.png' => '192x192', 'icon-512.png' => '512x512'] as $f => $sz) {
                if (is_file(Kit::$root . '/public/assets/favicon/' . $f)) { $icons[] = ['src' => '/assets/favicon/' . $f, 'sizes' => $sz, 'type' => 'image/png']; }
            }
            echo json_encode([
                'name' => (string)Site::get('site_name'), 'short_name' => (string)Site::get('short_name'),
                'description' => (string)Site::get('tagline'), 'start_url' => '/', 'scope' => '/', 'display' => 'standalone',
                'theme_color' => Site::cleanColor((string)Site::get('accent')), 'background_color' => '#0c1513', 'icons' => $icons,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        });

        Router::get('#^/brand/logo$#', static function (): void {
            $rel = (string)Site::get('logo_path');
            $f = $rel !== '' ? Json::path('uploads/' . basename($rel)) : '';
            if ($f === '' || !is_file($f)) { Kit::redirect('/assets/img/logo.svg'); }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $mime = ['svg' => 'image/svg+xml', 'png' => 'image/png', 'webp' => 'image/webp', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'][$ext] ?? 'application/octet-stream';
            header('Content-Type: ' . $mime);
            header('Cache-Control: public, max-age=3600');
            header('X-Content-Type-Options: nosniff');
            if ($ext === 'svg') { header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; sandbox"); }
            readfile($f);
            exit;
        });

        Router::get('#^/favicon\.ico$#', static function (): void {
            $f = Kit::$root . '/public/favicon.ico';
            if (is_file($f)) { header('Content-Type: image/x-icon'); header('Cache-Control: public, max-age=86400'); readfile($f); exit; }
            Kit::redirect(Layout::logoUrl());
        });

        Admin::mount();
    }

    /**
     * Front controller. $register is the service's own route registration callback.
     * Call from public/index.php after Kit::boot(); if it returns false, index.php must `return false;` (php -S static files).
     */
    public static function run(callable $register): ?bool
    {
        set_exception_handler(static function (\Throwable $e): void {
            error_log(get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (!headers_sent()) {
                try {
                    Layout::error(500, 'alert-triangle', 'Something went wrong', 'The server hit an unexpected problem. Please try again in a moment.', false);
                } catch (\Throwable $e2) {
                    http_response_code(500);
                    echo 'Internal server error';
                }
            }
        });

        // php -S: let the built-in server serve real static files itself
        if (PHP_SAPI === 'cli-server') {
            $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            if ($p !== '/' && is_file(Kit::$root . '/public' . $p) && substr((string)$p, -4) !== '.php') { return false; }
        }

        $register();
        self::register();
        Router::dispatch();
        return null;
    }
}
