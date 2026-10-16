<?php
declare(strict_types=1);
namespace Tzl;

/**
 * TZL Kit core: paths, env, URLs, service registry, security headers, small helpers.
 * Identical in every TechZeeLand PHP service. Project specifics are passed to Kit::boot().
 */
final class Kit
{
    public const VERSION = '1.0.0';

    /** The four services. Subdomains stay the same when the root domain changes. */
    public const SERVICES = [
        'fonts' => ['name' => 'TZL Fonts',                  'sub' => 'fonts',                    'icon' => 'font',     'desc' => 'Free commercial fonts you can cache on your own server'],
        'icons' => ['name' => 'TZL Icons',                  'sub' => 'icons',                    'icon' => 'sparkles', 'desc' => 'Open-source SVG icon library with a CDN'],
        'qr'    => ['name' => 'QR & Barcode Generator',     'sub' => 'qrcode',                   'icon' => 'qr-code',  'desc' => 'Customizable QR codes and barcodes, no tracking'],
        'cover' => ['name' => 'Assignment Cover Generator', 'sub' => 'assignmentcovergenerator', 'icon' => 'file-pdf', 'desc' => 'Print-ready assignment cover pages as PDF'],
    ];
    public const DEFAULT_ROOT_DOMAIN = 'rayaz.org';

    public static string $root = '';
    public static string $data = '';
    public static array $cfg = [];

    /**
     * @param array $cfg root (project dir), service (key in SERVICES), name, short, tagline, description,
     *                   github, nav [[label,url,icon]], pages [slug=>[title,desc,md]]
     */
    public static function boot(array $cfg): void
    {
        self::$cfg = $cfg;
        self::$root = rtrim((string)$cfg['root'], '/');
        $d = self::env('TZL_DATA_DIR');
        self::$data = rtrim($d !== '' ? $d : self::$root . '/data', '/');

        spl_autoload_register(static function (string $cls): void {
            if (strncmp($cls, 'Tzl\\', 4) === 0) {
                $f = __DIR__ . '/' . str_replace('\\', '/', substr($cls, 4)) . '.php';
                if (is_file($f)) { require $f; }
            } elseif (strncmp($cls, 'App\\', 4) === 0) {
                $f = Kit::$root . '/src/' . str_replace('\\', '/', substr($cls, 4)) . '.php';
                if (is_file($f)) { require $f; }
            }
        });

        date_default_timezone_set('UTC');
        if (function_exists('mb_internal_encoding')) { mb_internal_encoding('UTF-8'); }
        foreach (['', '/sessions', '/uploads'] as $sub) {
            if (!is_dir(self::$data . $sub)) { @mkdir(self::$data . $sub, 0775, true); }
        }
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', self::$data . '/error.log');
        if (!is_file(self::$data . '/first-run.txt')) { @file_put_contents(self::$data . '/first-run.txt', self::now()); }
    }

    public static function cfg(string $key, $default = '')
    {
        return self::$cfg[$key] ?? $default;
    }

    /* ---------- environment ---------- */

    public static function env(string $key, string $default = ''): string
    {
        $v = getenv($key);
        return ($v === false || $v === '') ? $default : (string)$v;
    }

    public static function trustProxy(): bool { return self::env('TZL_TRUST_PROXY') === '1'; }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { return true; }
        return self::trustProxy() && strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
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

    /* ---------- domains and service URLs (change the domain in ONE place) ---------- */

    /** Root domain shared by all services. Admin > Site, then TZL_ROOT_DOMAIN, then rayaz.org. */
    public static function rootDomain(): string
    {
        $d = trim((string)Site::get('root_domain'));
        if ($d === '') { $d = self::env('TZL_ROOT_DOMAIN', self::DEFAULT_ROOT_DOMAIN); }
        $d = strtolower((string)preg_replace('#^https?://#i', '', $d));
        $d = trim($d, "/ \t");
        return preg_match('/^[a-z0-9.\-]+$/', $d) ? $d : self::DEFAULT_ROOT_DOMAIN;
    }

    /** Public URL of a TZL service, e.g. Kit::service('icons') => https://icons.rayaz.org */
    public static function service(string $key): string
    {
        $over = trim((string)Site::get('svc_' . $key));
        if ($over !== '') { return rtrim($over, '/'); }
        $envOver = self::env('TZL_URL_' . strtoupper($key));
        if ($envOver !== '') { return rtrim($envOver, '/'); }
        $sub = self::SERVICES[$key]['sub'] ?? $key;
        return 'https://' . $sub . '.' . self::rootDomain();
    }

    public static function iconCssUrl(): string { return self::service('icons') . '/zi.css'; }

    /** Google-Fonts compatible stylesheet from our own fonts service. $families: "Alata|Other Name". */
    public static function fontCssUrl(string $families = 'Alata'): string
    {
        $q = [];
        foreach (explode('|', $families) as $f) { $q[] = 'family=' . rawurlencode(trim($f)); }
        // the fonts service serves its own UI font from itself (works on localhost too)
        $host = self::cfg('service') === 'fonts' ? '' : self::service('fonts');
        return $host . '/css2?' . implode('&', $q) . '&display=swap';
    }

    /** Origins of the icon and font services (for the CSP). */
    public static function origins(): array
    {
        $o = [];
        foreach (['icons', 'fonts'] as $k) {
            $p = parse_url(self::service($k));
            if ($p && !empty($p['host'])) { $o[$k] = ($p['scheme'] ?? 'https') . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : ''); }
        }
        return $o;
    }

    /** This service's own public base URL: Admin > Site, then TZL_BASE_URL, then the request origin. */
    public static function baseUrl(): string
    {
        $b = trim((string)Site::get('base_url'));
        if ($b === '') { $b = self::env('TZL_BASE_URL'); }
        return rtrim($b !== '' ? $b : self::origin(), '/');
    }

    public static function url(string $path = '/'): string { return self::baseUrl() . '/' . ltrim($path, '/'); }

    /* ---------- tiny utilities ---------- */

    public static function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

    public static function icon(string $name, string $extra = ''): string
    {
        $name = (string)preg_replace('/[^a-z0-9-]/', '', strtolower($name));
        return '<i class="zi zi-' . $name . ($extra !== '' ? ' ' . self::e($extra) : '') . '" aria-hidden="true"></i>';
    }

    public static function now(): string { return gmdate('c'); }

    public static function slugify(string $s): string
    {
        $s = strtolower(trim($s));
        return trim((string)preg_replace('/[^a-z0-9]+/', '-', $s), '-');
    }

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

    public static function json($data, int $status = 200, bool $cors = false, array $extra = []): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        if ($cors) { self::cors(); }
        foreach ($extra as $k => $v) { header($k . ': ' . $v); }
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    public static function cors(): void
    {
        $o = trim((string)Site::get('cors_origin'));
        header('Access-Control-Allow-Origin: ' . ($o !== '' ? $o : '*'));
        header('Access-Control-Allow-Methods: GET, HEAD, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');
        header('Access-Control-Expose-Headers: ETag, Content-Length');
        header('Cross-Origin-Resource-Policy: cross-origin');
    }

    /** Conditional GET. Sends 304 and exits when the client copy is current. */
    public static function etag(string $tag): void
    {
        $etag = '"' . $tag . '"';
        header('ETag: ' . $etag);
        $inm = (string)($_SERVER['HTTP_IF_NONE_MATCH'] ?? '');
        if ($inm === '') { return; }
        foreach (explode(',', $inm) as $c) {
            $c = (string)preg_replace('/-(gzip|br)"$/', '"', trim($c));
            if ($c === $etag || $c === 'W/' . $etag) { http_response_code(304); exit; }
        }
    }

    /**
     * Security headers. $extra widens the CSP per directive, e.g. ['img-src' => 'blob:'].
     * Inline scripts are never allowed: the kit and the services use external scripts only.
     */
    public static function securityHeaders(array $extra = []): void
    {
        header_remove('X-Powered-By');
        $o = array_values(self::origins());
        $ext = $o ? ' ' . implode(' ', $o) : '';
        $csp = [
            'default-src'     => "'self'",
            'img-src'         => "'self' data:" . $ext,
            'style-src'       => "'self' 'unsafe-inline'" . $ext,
            'font-src'        => "'self' data:" . $ext,
            'script-src'      => "'self'",
            'connect-src'     => "'self'",
            'object-src'      => "'none'",
            'base-uri'        => "'self'",
            'form-action'     => "'self'",
            'frame-ancestors' => "'self'",
        ];
        foreach ($extra as $k => $v) { $csp[$k] = isset($csp[$k]) ? $csp[$k] . ' ' . $v : $v; }
        $parts = [];
        foreach ($csp as $k => $v) { $parts[] = $k . ' ' . $v; }
        header('Content-Security-Policy: ' . implode('; ', $parts));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
        if (self::isHttps()) { header('Strict-Transport-Security: max-age=15552000'); }
    }

    /** URL of a file under public/ with a cache-busting query string. */
    public static function asset(string $path): string
    {
        $f = self::$root . '/public/' . ltrim($path, '/');
        return '/' . ltrim($path, '/') . (is_file($f) ? '?v=' . filemtime($f) : '');
    }
}
