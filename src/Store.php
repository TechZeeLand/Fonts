<?php
namespace App;

/**
 * Flat-file JSON storage (no database). Everything lives in the data directory:
 *   fonts.json, settings.json, admin.json, stats.json, activity.log, files/<slug>/*
 */
final class Store
{
    public const DEFAULTS = [
        'site_name'         => 'TechZeeLand Fonts',
        'short_name'        => 'TZL Fonts',
        'tagline'           => 'Open-source fonts, served fast, cached on your server.',
        'base_url'          => '',
        'contact_email'     => 'azlanaziz@rayaz.org',
        'github_url'        => 'https://github.com/TechZeeLand/TZL-Fonts',
        'announcement_on'   => false,
        'announcement_text' => '',
        'announcement_link' => '',
        'css_ttl'           => 86400,   // seconds browsers/proxies may reuse /css2 responses
        'cache_days'        => 7,       // recommended refresh interval for self-hosted copies
        'cors_origin'       => '*',
        'stats_on'          => true,
        'max_upload_mb'     => 20,
        'default_display'   => 'swap',
        'robots_index'      => true,
    ];

    /** Only licenses that allow free commercial use + redistribution are accepted. */
    public const LICENSES = [
        'OFL-1.1'    => ['SIL Open Font License 1.1', 'https://openfontlicense.org'],
        'Apache-2.0' => ['Apache License 2.0', 'https://www.apache.org/licenses/LICENSE-2.0'],
        'UFL-1.0'    => ['Ubuntu Font License 1.0', 'https://ubuntu.com/legal/font-licence'],
        'MIT'        => ['MIT License', 'https://opensource.org/license/mit'],
        'CC0-1.0'    => ['CC0 1.0 (Public Domain)', 'https://creativecommons.org/publicdomain/zero/1.0/'],
        'CC-BY-4.0'  => ['Creative Commons Attribution 4.0', 'https://creativecommons.org/licenses/by/4.0/'],
        'Other'      => ['Other open license (free commercial use)', ''],
    ];

    public const CATEGORIES = [
        'sans-serif'  => 'Sans Serif',
        'serif'       => 'Serif',
        'display'     => 'Display',
        'handwriting' => 'Handwriting',
        'monospace'   => 'Monospace',
    ];

    private static ?array $db = null;
    private static ?array $settings = null;

    /* ---------- low-level JSON ---------- */

    public static function path(string $rel = ''): string
    {
        return App::$data . ($rel !== '' ? '/' . ltrim($rel, '/') : '');
    }

    public static function read(string $rel, $default = null)
    {
        $f = self::path($rel);
        if (!is_file($f)) { return $default; }
        $fp = @fopen($f, 'rb');
        if (!$fp) { return $default; }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        $d = json_decode((string)$raw, true);
        return $d === null ? $default : $d;
    }

    public static function write(string $rel, $data): bool
    {
        $f = self::path($rel);
        $dir = dirname($f);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ok = $json !== false && file_put_contents($tmp, $json, LOCK_EX) !== false;
        if ($ok) { @chmod($tmp, 0664); $ok = rename($tmp, $f); }
        if (!$ok) { @unlink($tmp); }
        return $ok;
    }

    /** Atomic read-modify-write under an exclusive lock. $fn receives the current value, returns the new one. */
    public static function update(string $rel, callable $fn, $default = [])
    {
        $dir = dirname(self::path($rel));
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $lock = fopen(self::path($rel) . '.lock', 'c');
        if (!$lock) { throw new \RuntimeException('Cannot lock ' . $rel); }
        flock($lock, LOCK_EX);
        try {
            $new = $fn(self::read($rel, $default));
            self::write($rel, $new);
            return $new;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /* ---------- settings ---------- */

    public static function settings(): array
    {
        if (self::$settings === null) {
            $s = self::read('settings.json', []);
            self::$settings = array_merge(self::DEFAULTS, is_array($s) ? $s : []);
        }
        return self::$settings;
    }

    public static function setting(string $key)
    {
        $s = self::settings();
        return $s[$key] ?? (self::DEFAULTS[$key] ?? null);
    }

    public static function saveSettings(array $new): void
    {
        $merged = array_merge(self::settings(), $new);
        self::write('settings.json', $merged);
        self::$settings = $merged;
    }

    /* ---------- fonts ---------- */

    private static function db(): array
    {
        if (self::$db === null) {
            $d = self::read('fonts.json', null);
            self::$db = (is_array($d) && isset($d['fonts'])) ? $d : ['version' => 1, 'updated' => App::now(), 'fonts' => []];
        }
        return self::$db;
    }

    /** All fonts (slug => record), alphabetical. */
    public static function fonts(bool $publishedOnly = false): array
    {
        $f = self::db()['fonts'];
        if ($publishedOnly) {
            $f = array_filter($f, static fn($x) => !empty($x['published']));
        }
        uasort($f, static fn($a, $b) => strcasecmp((string)$a['family'], (string)$b['family']));
        return $f;
    }

    public static function font(string $slug, bool $publishedOnly = false): ?array
    {
        $f = self::db()['fonts'][$slug] ?? null;
        if ($f === null || ($publishedOnly && empty($f['published']))) { return null; }
        return $f;
    }

    /** Find a published font by family name (case-insensitive) or slug. */
    public static function findByFamily(string $name): ?array
    {
        $slug = App::slugify($name);
        $f = self::font($slug, true);
        if ($f) { return $f; }
        foreach (self::fonts(true) as $x) {
            if (strcasecmp((string)$x['family'], trim($name)) === 0) { return $x; }
        }
        return null;
    }

    /** Monotonic counter, bumped on every change. Exposed via /api/manifest.json. */
    public static function version(): int { return (int)(self::db()['version'] ?? 1); }
    public static function updatedAt(): string { return (string)(self::db()['updated'] ?? ''); }

    public static function hashFont(array $f): string
    {
        $parts = [(string)($f['family'] ?? ''), (string)($f['font_version'] ?? '')];
        foreach (($f['styles'] ?? []) as $s) {
            foreach (($s['files'] ?? []) as $ext => $fi) { $parts[] = $ext . ':' . ($fi['sha1'] ?? ''); }
        }
        return substr(sha1(implode('|', $parts)), 0, 10);
    }

    public static function saveFont(array $font): void
    {
        $slug = (string)$font['slug'];
        $font['updated'] = App::now();
        if (empty($font['added'])) { $font['added'] = $font['updated']; }
        $font['hash'] = self::hashFont($font);
        self::$db = self::update('fonts.json', static function ($db) use ($slug, $font) {
            if (!is_array($db) || !isset($db['fonts'])) { $db = ['version' => 0, 'fonts' => []]; }
            $db['fonts'][$slug] = $font;
            $db['version'] = (int)($db['version'] ?? 0) + 1;
            $db['updated'] = App::now();
            return $db;
        }, ['version' => 0, 'fonts' => []]);
    }

    public static function deleteFont(string $slug): void
    {
        self::$db = self::update('fonts.json', static function ($db) use ($slug) {
            if (!is_array($db) || !isset($db['fonts'])) { return ['version' => 1, 'fonts' => []]; }
            unset($db['fonts'][$slug]);
            $db['version'] = (int)($db['version'] ?? 0) + 1;
            $db['updated'] = App::now();
            return $db;
        }, ['version' => 0, 'fonts' => []]);
        App::rrmdir(self::fontDir($slug));
    }

    public static function fontDir(string $slug): string { return self::path('files/' . $slug); }

    public static function totalBytes(): array
    {
        $files = 0; $bytes = 0;
        foreach (self::db()['fonts'] as $f) {
            foreach (($f['styles'] ?? []) as $s) {
                foreach (($s['files'] ?? []) as $fi) { $files++; $bytes += (int)($fi['size'] ?? 0); }
            }
        }
        return ['files' => $files, 'bytes' => $bytes];
    }

    /* ---------- stats (aggregate counters only, no IPs) ---------- */

    /** Count a request. $slugs: one slug or a list of slugs the request touched. */
    public static function hit(string $kind, $slugs = null): void
    {
        if (!self::setting('stats_on')) { return; }
        $slugs = $slugs === null ? [] : (array)$slugs;
        try {
            self::update('stats.json', static function ($s) use ($kind, $slugs) {
                if (!is_array($s)) { $s = []; }
                $day = gmdate('Y-m-d');
                $s['days'][$day][$kind] = (int)($s['days'][$day][$kind] ?? 0) + 1;
                foreach ($slugs as $slug) {
                    $s['days'][$day]['f'][$slug] = (int)($s['days'][$day]['f'][$slug] ?? 0) + 1;
                }
                if (count($s['days']) > 100) { ksort($s['days']); $s['days'] = array_slice($s['days'], -90, null, true); }
                return $s;
            }, ['days' => []]);
        } catch (\Throwable $e) {
            // statistics must never break a font request
        }
    }

    /** Totals per day for the last $n days (oldest first). */
    public static function statDays(int $n = 14): array
    {
        $s = self::read('stats.json', ['days' => []]);
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $row = $s['days'][$d] ?? [];
            $out[$d] = ['css' => (int)($row['css'] ?? 0), 'file' => (int)($row['file'] ?? 0), 'zip' => (int)($row['zip'] ?? 0), 'api' => (int)($row['api'] ?? 0)];
        }
        return $out;
    }

    public static function topFonts(int $days = 30, int $limit = 8): array
    {
        $s = self::read('stats.json', ['days' => []]);
        $min = gmdate('Y-m-d', time() - $days * 86400);
        $tot = [];
        foreach (($s['days'] ?? []) as $d => $row) {
            if ($d < $min) { continue; }
            foreach (($row['f'] ?? []) as $slug => $n) { $tot[$slug] = ($tot[$slug] ?? 0) + (int)$n; }
        }
        arsort($tot);
        return array_slice($tot, 0, $limit, true);
    }

    /* ---------- activity log ---------- */

    public static function log(string $msg): void
    {
        $f = self::path('activity.log');
        $user = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['u'] ?? '-') : '-';
        @file_put_contents($f, json_encode(['t' => App::now(), 'u' => $user, 'm' => $msg], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        if (@filesize($f) > 200000) {
            $lines = file($f, FILE_IGNORE_NEW_LINES) ?: [];
            @file_put_contents($f, implode("\n", array_slice($lines, -300)) . "\n", LOCK_EX);
        }
    }

    public static function activity(int $n = 50): array
    {
        $f = self::path('activity.log');
        if (!is_file($f)) { return []; }
        $lines = array_slice(file($f, FILE_IGNORE_NEW_LINES) ?: [], -$n);
        $out = [];
        foreach (array_reverse($lines) as $l) {
            $d = json_decode($l, true);
            if (is_array($d)) { $out[] = $d; }
        }
        return $out;
    }
}
