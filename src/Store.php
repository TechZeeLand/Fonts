<?php
namespace App;

use Tzl\Json;
use Tzl\Site;
use Tzl\Stats;

/**
 * Font library storage (data/fonts.json + data/files/<slug>/*). Settings, stats and the activity log
 * are provided by the TZL Kit; the methods below delegate so the font code stays unchanged.
 */
final class Store
{
    /** Font-service specific settings (merged into the kit's Site defaults from public/index.php). */
    public const DEFAULTS = [
        'css_ttl'         => 86400,   // seconds browsers/proxies may reuse /css2 responses
        'cache_days'      => 7,       // recommended refresh interval for self-hosted copies
        'max_upload_mb'   => 20,
        'default_display' => 'swap',
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

    /* ---------- kit delegates ---------- */

    public static function path(string $rel = ''): string { return Json::path($rel); }
    public static function read(string $rel, $default = null) { return Json::read($rel, $default); }
    public static function write(string $rel, $data): bool { return Json::write($rel, $data); }
    public static function update(string $rel, callable $fn, $default = []) { return Json::update($rel, $fn, $default); }
    public static function settings(): array { return Site::all(); }
    public static function setting(string $key) { return Site::get($key); }
    public static function saveSettings(array $new): void { Site::save($new); }
    public static function log(string $msg): void { Json::log($msg); }
    public static function activity(int $n = 50): array { return Json::activity($n); }

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
        Stats::event($kind);
        foreach ($slugs === null ? [] : (array)$slugs as $slug) { Stats::event('font', (string)$slug); }
    }

    /** Totals per day for the last $n days (oldest first). */
    public static function statDays(int $n = 14): array
    {
        $out = [];
        foreach (Stats::days($n) as $d => $r) {
            $ev = (array)($r['ev'] ?? []);
            $out[$d] = ['css' => (int)($ev['css'] ?? 0), 'file' => (int)($ev['file'] ?? 0), 'zip' => (int)($ev['zip'] ?? 0), 'api' => (int)($ev['api'] ?? 0)];
        }
        return $out;
    }

    public static function topFonts(int $days = 30, int $limit = 8): array
    {
        return Stats::topEventKeys(Stats::days($days), 'font', $limit);
    }
}
