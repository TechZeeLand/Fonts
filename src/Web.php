<?php
namespace App;

/**
 * Public controllers: pages, the CSS API, font files, JSON API, downloads.
 */
final class Web
{
    /* ---------- pages ---------- */

    public static function home(): void
    {
        View::render('home', ['fonts' => Store::fonts(true)]);
    }

    public static function font(string $slug): void
    {
        $font = Store::font($slug, true);
        if (!$font) { self::notFound(); }
        View::render('font', ['font' => $font, 'fonts' => Store::fonts(true)]);
    }

    public static function page(string $view): void { View::render($view); }

    /** Docs as raw Markdown. ?download=1 forces a file download (the button on /docs). */
    public static function docsMarkdown(): void
    {
        $md = Docs::markdown();
        header('Content-Type: text/markdown; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-cache');
        if (isset($_GET['download'])) { header('Content-Disposition: attachment; filename="tzl-fonts-docs.md"'); }
        echo $md;
        exit;
    }

    public static function notFound(): void
    {
        View::error(404, 'signpost', "This page doesn't exist", 'The link might be broken, or the page may have been moved or removed.');
    }

    /* ---------- plain-response helper for machine endpoints ---------- */

    private static function plain(int $status, string $msg, string $type = 'text/plain'): void
    {
        http_response_code($status);
        header('Content-Type: ' . $type . '; charset=utf-8');
        App::corsHeaders();
        header('X-Content-Type-Options: nosniff');
        echo $type === 'text/css' ? '/* ' . str_replace('*/', '', $msg) . " */\n" : $msg . "\n";
        exit;
    }

    public static function preflight(): void
    {
        App::corsHeaders();
        header('Access-Control-Max-Age: 86400');
        http_response_code(204);
        exit;
    }

    /* ---------- CSS API ---------- */

    public static function css(): void
    {
        [$reqs, $display, $legacy] = Css::parse((string)($_SERVER['QUERY_STRING'] ?? ''));
        if (!$reqs) {
            self::plain(400, 'TZL Fonts: add at least one family, e.g. /css2?family=Alata&display=swap  (docs: ' . App::url('/docs') . ')', 'text/css');
        }
        [$blocks, $slugs, $problems] = Css::build($reqs, $display, $legacy, App::baseUrl());
        $notes = '';
        foreach ($problems as $p) { $notes .= '/* ' . str_replace('*/', '', $p) . " */\n"; }
        if (!$blocks) {
            self::plain(404, trim($notes) !== '' ? trim(str_replace(["/* ", " */"], '', $notes)) : 'TZL Fonts: nothing matched your request.', 'text/css');
        }
        $css = '/* ' . Store::setting('site_name') . ' - open-source fonts. Docs: ' . App::url('/docs') . " */\n" . implode("\n\n", $blocks) . "\n" . ($notes !== '' ? "\n" . $notes : '');

        $ttl = max(60, (int)Store::setting('css_ttl'));
        header('Content-Type: text/css; charset=utf-8');
        header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=' . ($ttl * 7) . ', stale-if-error=' . ($ttl * 30));
        header('Vary: Accept-Encoding');
        header('X-Content-Type-Options: nosniff');
        App::corsHeaders();
        App::etag(substr(sha1($css), 0, 24));
        Store::hit('css', $slugs);
        echo $css;
        exit;
    }

    /* ---------- font files ---------- */

    public static function file(string $slug, string $name): void
    {
        $name = rawurldecode($name);
        $font = Store::font($slug, true);
        if (!$font) { self::plain(404, 'Font not found.'); }

        if ($name === 'LICENSE.txt') {
            $txt = FontService::licenseText($font);
            if ($txt === '') { self::plain(404, 'No license text stored for this font. See ' . App::url('/font/' . $slug)); }
            header('Content-Type: text/plain; charset=utf-8');
            header('Cache-Control: public, max-age=86400');
            App::corsHeaders();
            echo $txt;
            exit;
        }

        $rec = null;
        $ext = '';
        foreach ($font['styles'] as $s) {
            foreach ($s['files'] as $e => $fi) {
                if ($fi['name'] === $name) { $rec = $fi; $ext = (string)$e; break 2; }
            }
        }
        $path = Store::fontDir($slug) . '/' . basename($name);
        if (!$rec || !is_file($path)) { self::plain(404, 'File not found.'); }

        header('Content-Type: ' . (FontKit::MIME[$ext] ?? 'application/octet-stream'));
        header('X-Content-Type-Options: nosniff');
        App::corsHeaders();
        if (isset($_GET['v'])) {
            header('Cache-Control: public, max-age=31536000, immutable');
        } else {
            header('Cache-Control: public, max-age=86400, stale-while-revalidate=604800');
        }
        App::etag((string)$rec['sha1']);
        header('Content-Length: ' . filesize($path));
        Store::hit('file', $slug);
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'HEAD') { readfile($path); }
        exit;
    }

    /* ---------- JSON API ---------- */

    private static function summary(array $f): array
    {
        $base = App::baseUrl();
        $styles = [];
        foreach ($f['styles'] as $s) {
            $styles[] = ['weight' => (int)$s['weight'], 'italic' => !empty($s['italic']), 'variable' => !empty($s['variable']),
                'wmin' => $s['wmin'] ?? null, 'wmax' => $s['wmax'] ?? null, 'formats' => array_keys($s['files'])];
        }
        return [
            'slug' => $f['slug'], 'family' => $f['family'], 'category' => $f['category'], 'designer' => $f['designer'],
            'license' => $f['license'], 'license_name' => FontService::licenseName($f), 'license_url' => FontService::licenseUrl($f),
            'variable' => !empty($f['variable']), 'subsets' => $f['subsets'] ?? [], 'tags' => $f['tags'] ?? [],
            'styles' => $styles, 'hash' => $f['hash'] ?? '', 'updated' => $f['updated'] ?? '',
            'page' => $base . '/font/' . $f['slug'],
            'css' => $base . '/css2?family=' . str_replace(' ', '+', (string)$f['family']) . '&display=swap',
            'download' => $base . '/download/' . $f['slug'] . '.zip',
        ];
    }

    public static function apiFonts(): void
    {
        $list = [];
        foreach (Store::fonts(true) as $f) { $list[] = self::summary($f); }
        Store::hit('api');
        self::jsonCached(['version' => Store::version(), 'updated' => Store::updatedAt(), 'base' => App::baseUrl(),
            'count' => count($list), 'fonts' => $list], 'fonts-' . Store::version() . '-' . count($list), 300);
    }

    public static function apiFont(string $slug): void
    {
        $f = Store::font($slug, true);
        if (!$f) { App::json(['error' => 'Font not found'], 404); }
        $base = App::baseUrl();
        $out = self::summary($f);
        $out += ['description' => $f['description'] ?? '', 'copyright' => $f['copyright'] ?? '', 'source_url' => $f['source_url'] ?? '',
            'font_version' => $f['font_version'] ?? '', 'glyphs' => (int)($f['glyphs'] ?? 0), 'axes' => $f['axes'] ?? [], 'files' => []];
        foreach ($f['styles'] as $s) {
            foreach ($s['files'] as $ext => $fi) {
                $out['files'][] = ['format' => $ext, 'weight' => (int)$s['weight'], 'italic' => !empty($s['italic']), 'size' => (int)$fi['size'],
                    'sha1' => $fi['sha1'], 'url' => $base . '/f/' . $slug . '/' . rawurlencode((string)$fi['name']) . '?v=' . ($f['hash'] ?? '')];
            }
        }
        Store::hit('api');
        self::jsonCached($out, 'font-' . $slug . '-' . ($f['hash'] ?? '') . '-' . md5((string)($f['updated'] ?? '')), 300);
    }

    /** Tiny document for cache-refresh checks: slug => content hash. */
    public static function apiManifest(): void
    {
        $m = [];
        foreach (Store::fonts(true) as $f) { $m[$f['slug']] = $f['hash'] ?? ''; }
        self::jsonCached(['version' => Store::version(), 'updated' => Store::updatedAt(), 'fonts' => $m], 'manifest-' . Store::version(), 60);
    }

    private static function jsonCached(array $data, string $etag, int $ttl): void
    {
        header('Cache-Control: public, max-age=' . $ttl . ', stale-while-revalidate=' . ($ttl * 10));
        App::corsHeaders();
        App::etag(substr(sha1($etag), 0, 20));
        App::json($data);
    }

    /* ---------- downloads ---------- */

    public static function zip(string $slug): void
    {
        $f = Store::font($slug, true);
        if (!$f) { self::plain(404, 'Font not found.'); }
        $tmp = FontService::zip($f);
        if ($tmp === null) { self::plain(501, 'ZIP support (php-zip) is not available on this server.'); }
        Store::hit('zip', $slug);
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$f['family']) . '.zip"');
        header('Content-Length: ' . filesize($tmp));
        header('X-Content-Type-Options: nosniff');
        readfile($tmp);
        @unlink($tmp);
        exit;
    }

    /** Helper scripts people can drop into their own projects (placeholders filled with this site's URL). */
    public static function tool(string $name): void
    {
        $allowed = ['tzl-fonts.php' => 'application/x-httpd-php', 'tzl-fonts.mjs' => 'text/javascript'];
        if (!isset($allowed[$name])) { self::plain(404, 'Unknown tool.'); }
        $src = (string)@file_get_contents(App::$root . '/resources/tools/' . $name);
        if ($src === '') { self::plain(404, 'Tool not available.'); }
        $src = str_replace(['{{BASE_URL}}', '{{CACHE_DAYS}}'], [App::baseUrl(), (string)(int)Store::setting('cache_days')], $src);
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Cache-Control: public, max-age=3600');
        header('X-Content-Type-Options: nosniff');
        echo $src;
        exit;
    }

    /* ---------- misc ---------- */

    public static function register(): void
    {
        $g = static fn(string $re, string $m) => \Tzl\Router::get($re, [self::class, $m]);
        $g('#^/$#', 'home');
        $g('#^/font/([a-z0-9-]+)$#', 'font');
        $g('#^/(docs|contribute|license-policy)$#', 'page');
        $g('#^/docs\.md$#', 'docsMarkdown');
        $g('#^/css2?$#', 'css');
        $g('#^/f/([a-z0-9-]+)/([^/]+)$#', 'file');
        $g('#^/api/fonts\.json$#', 'apiFonts');
        $g('#^/api/fonts/([a-z0-9-]+)\.json$#', 'apiFont');
        $g('#^/api/manifest\.json$#', 'apiManifest');
        $g('#^/download/([a-z0-9-]+)\.zip$#', 'zip');
        $g('#^/download/tools/([a-z0-9.-]+)$#', 'tool');
    }
}
