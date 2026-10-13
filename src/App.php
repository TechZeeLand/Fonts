<?php
namespace App;

/**
 * Core: paths, config, URL helpers, security headers, tiny response helpers.
 */
final class App
{
    public const VERSION = '1.0.0';
    /** The ONE place the icon stylesheet URL lives (override with TZL_ICON_CSS). */
    public const ICON_CSS = 'https://icons.rayaz.org/zi.css';

    public static string $root = '';
    public static string $data = '';

    public static function boot(): void
    {
        self::$root = dirname(__DIR__);
        $d = getenv('TZL_DATA_DIR');
        self::$data = rtrim(($d !== false && $d !== '') ? $d : self::$root . '/data', '/');

        spl_autoload_register(static function (string $cls): void {
            if (strncmp($cls, 'App\\', 4) === 0) {
                $f = __DIR__ . '/' . str_replace('\\', '/', substr($cls, 4)) . '.php';
                if (is_file($f)) { require $f; }
            }
        });
        require_once __DIR__ . '/helpers.php';

        date_default_timezone_set('UTC');
        if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
        foreach (['', '/files', '/sessions'] as $sub) {
            if (!is_dir(self::$data . $sub)) { @mkdir(self::$data . $sub, 0775, true); }
        }
    }

    /* ---------- environment / URLs ---------- */

    public static function env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : (string)$v;
    }

    public static function iconCss(): string { return self::env('TZL_ICON_CSS', self::ICON_CSS); }

    public static function iconOrigin(): string
    {
        $p = parse_url(self::iconCss());
        if (!$p || empty($p['host'])) { return ''; }
        return ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    public static function trustProxy(): bool { return self::env('TZL_TRUST_PROXY') === '1'; }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { return true; }
        if (self::trustProxy() && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') { return true; }
        return false;
    }

    public static function clientIp(): string
    {
        if (self::trustProxy() && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_FOR'])[0]);
        }
        return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public static function origin(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        if (self::trustProxy() && !empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = trim(explode(',', (string)$_SERVER['HTTP_X_FORWARDED_HOST'])[0]);
        }
        if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) { $host = 'localhost'; }
        return (self::isHttps() ? 'https' : 'http') . '://' . $host;
    }

    /** Public base URL: Admin > Settings, then TZL_BASE_URL, then the request origin. */
    public static function baseUrl(): string
    {
        $b = trim((string)Store::setting('base_url'));
        if ($b === '') { $b = self::env('TZL_BASE_URL'); }
        return rtrim($b !== '' ? $b : self::origin(), '/');
    }

    public static function url(string $path = '/'): string { return self::baseUrl() . '/' . ltrim($path, '/'); }

    /* ---------- small utilities ---------- */

    public static function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function icon(string $name, string $extra = ''): string
    {
        return '<i class="zi zi-' . self::e($name) . ($extra !== '' ? ' ' . self::e($extra) : '') . '" aria-hidden="true"></i>';
    }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        $s = (string)preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    public static function now(): string { return gmdate('c'); }

    public static function bytes(int $b): string
    {
        if ($b < 1024) { return $b . ' B'; }
        if ($b < 1048576) { return round($b / 1024, 1) . ' KB'; }
        if ($b < 1073741824) { return round($b / 1048576, 1) . ' MB'; }
        return round($b / 1073741824, 2) . ' GB';
    }

    public static function ago(string $iso): string
    {
        $t = strtotime($iso);
        if (!$t) { return ''; }
        $d = time() - $t;
        if ($d < 60) { return 'just now'; }
        if ($d < 3600) { return floor($d / 60) . ' min ago'; }
        if ($d < 86400) { return floor($d / 3600) . ' h ago'; }
        if ($d < 86400 * 30) { return floor($d / 86400) . ' d ago'; }
        return gmdate('j M Y', $t);
    }

    public static function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) && !is_link($p) ? self::rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    public static function redirect(string $to, int $code = 302): void
    {
        header('Location: ' . $to, true, $code);
        exit;
    }

    /** JSON response. $cors adds permissive CORS (public read-only API). */
    public static function json($data, int $status = 200, bool $cors = true, array $extra = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($cors) { self::corsHeaders(); }
        foreach ($extra as $k => $v) { header($k . ': ' . $v); }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function corsHeaders(): void
    {
        $o = trim((string)Store::setting('cors_origin'));
        header('Access-Control-Allow-Origin: ' . ($o !== '' ? $o : '*'));
        header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
        header('Access-Control-Expose-Headers: ETag, Content-Length');
        header('Cross-Origin-Resource-Policy: cross-origin');
        header('Timing-Allow-Origin: *');
    }

    /** Conditional GET helper. Sends 304 and exits when the client copy is current. */
    public static function etag(string $tag): void
    {
        $etag = '"' . $tag . '"';
        header('ETag: ' . $etag);
        $inm = (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        if ($inm !== '') {
            foreach (explode(',', $inm) as $c) {
                // Apache's mod_deflate appends -gzip/-br to ETags; ignore that suffix
                $c = (string)preg_replace('/-(gzip|br)"$/', '"', trim($c));
                if ($c === $etag || $c === 'W/' . $etag) {
                    http_response_code(304);
                    exit;
                }
            }
        }
    }

    public static function securityHeaders(): void
    {
        header_remove('X-Powered-By');
        $icons = self::iconOrigin();
        $csp = "default-src 'self'; img-src 'self' data:" . ($icons ? ' ' . $icons : '')
            . "; style-src 'self'" . ($icons ? ' ' . $icons : '')
            . "; font-src 'self'; script-src 'self'; connect-src 'self'; object-src 'none'"
            . "; base-uri 'self'; form-action 'self'; frame-ancestors 'self'";
        header('Content-Security-Policy: ' . $csp);
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
        if (self::isHttps()) { header('Strict-Transport-Security: max-age=15552000'); }
    }
}
