<?php
declare(strict_types=1);
namespace Tzl;

/** Health checks and system information for /health and the admin System page. */
final class Monitor
{
    /** Quick checks shown on the dashboard. Each: [label, ok, help]. Services may add their own via Kit::cfg('checks'). */
    public static function checks(): array
    {
        $c = [];
        $c[] = ['Data folder is writable', is_writable(Kit::$data), 'Make ' . Kit::$data . ' writable by the web server user.'];
        $free = @disk_free_space(Kit::$data);
        $c[] = ['Disk space available', $free === false || $free > 200 * 1048576, 'Less than 200 MB free. Free some space or enlarge the volume.'];
        $c[] = ['Served over HTTPS', Kit::isHttps(), 'Put the service behind HTTPS. If a reverse proxy terminates TLS, set TZL_TRUST_PROXY=1.'];
        $c[] = ['Public URL is set', trim((string)Site::get('base_url')) !== '' || Kit::env('TZL_BASE_URL') !== '', 'Set it in Admin > Site so canonical links, sitemap and embeds use the right domain.'];
        $c[] = ['Admin password was changed', !Auth::mustChange(), 'Change the generated password in Account.'];
        $c[] = ['Contact email is set', trim((string)Site::get('contact_email')) !== '', 'Add one in Admin > Site.'];
        $c[] = ['Search engines can index the site', Site::on('robots_index'), 'Indexing is switched off in Admin > SEO.'];
        $c[] = ['Maintenance mode is off', !Site::on('maintenance_on'), 'Visitors currently see the maintenance page.'];
        $err = self::errorLogTail(1);
        $c[] = ['No recent PHP errors', !$err, 'See Admin > System for the error log.'];
        foreach ((array)Kit::cfg('checks', []) as $fn) {
            if (is_callable($fn)) { foreach ((array)$fn() as $row) { $c[] = $row; } }
        }
        return $c;
    }

    /** Last N non-empty lines of the PHP error log. */
    public static function errorLogTail(int $n = 40): array
    {
        $f = Json::path('error.log');
        if (!is_file($f) || filesize($f) === 0) { return []; }
        $size = filesize($f);
        $fp = @fopen($f, 'rb');
        if (!$fp) { return []; }
        $read = min($size, 20000);
        fseek($fp, -$read, SEEK_END);
        $txt = (string)fread($fp, $read);
        fclose($fp);
        $lines = array_values(array_filter(explode("\n", $txt), static fn($l) => trim($l) !== ''));
        return array_slice($lines, -$n);
    }

    public static function clearErrorLog(): void { @file_put_contents(Json::path('error.log'), ''); }

    public static function folderSize(string $dir, int $depth = 0): int
    {
        if (!is_dir($dir) || $depth > 6) { return 0; }
        $t = 0;
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            if (is_link($p)) { continue; }
            $t += is_dir($p) ? self::folderSize($p, $depth + 1) : (int)@filesize($p);
        }
        return $t;
    }

    /** @return array<string,string> */
    public static function system(): array
    {
        $first = Json::path('first-run.txt');
        $since = is_file($first) ? trim((string)file_get_contents($first)) : '';
        $exts = [];
        foreach (['mbstring', 'zip', 'gd', 'json', 'curl', 'openssl', 'pdo_sqlite', 'intl'] as $e) { $exts[] = $e . ': ' . (extension_loaded($e) ? 'yes' : 'no'); }
        return [
            'Service'          => (string)Kit::cfg('name') . ' (' . (string)Kit::cfg('service') . ')',
            'Kit version'      => Kit::VERSION,
            'App version'      => (string)Kit::cfg('version', '-'),
            'PHP'              => PHP_VERSION . ' (' . PHP_SAPI . ')',
            'Server'           => (string)($_SERVER['SERVER_SOFTWARE'] ?? '-'),
            'Memory limit'     => (string)ini_get('memory_limit'),
            'Upload limits'    => 'upload_max_filesize=' . ini_get('upload_max_filesize') . ', post_max_size=' . ini_get('post_max_size'),
            'Extensions'       => implode(', ', $exts),
            'Data folder'      => Kit::$data . ' (' . Kit::bytes(self::folderSize(Kit::$data)) . ')',
            'Disk free'        => (($f = @disk_free_space(Kit::$data)) === false) ? '-' : Kit::bytes((int)$f),
            'Installed since'  => $since !== '' ? $since . ' (' . Kit::ago($since) . ')' : '-',
            'Root domain'      => Kit::rootDomain(),
            'Public URL'       => Kit::baseUrl(),
            'HTTPS'            => Kit::isHttps() ? 'yes' : 'no',
            'Reverse proxy'    => Kit::trustProxy() ? 'trusted' : 'not trusted',
        ];
    }

    /**
     * Ping the other TZL services' health endpoints (3 s timeout, only when the admin asks).
     * @return array<string,array{ok:bool,ms:int,note:string}>
     */
    public static function ecosystem(): array
    {
        $out = [];
        $paths = ['fonts' => '/health', 'icons' => '/healthz', 'qr' => '/health', 'cover' => '/health'];
        foreach (Kit::SERVICES as $k => $s) {
            $url = Kit::service($k) . ($paths[$k] ?? '/health');
            $t = microtime(true);
            $ctx = stream_context_create(['http' => ['timeout' => 3, 'ignore_errors' => true, 'header' => "User-Agent: TZL-Monitor\r\n"], 'ssl' => ['verify_peer' => true]]);
            $body = @file_get_contents($url, false, $ctx);
            $ms = (int)round((microtime(true) - $t) * 1000);
            $code = 0;
            foreach (($http_response_header ?? []) as $h) { if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m)) { $code = (int)$m[1]; } }
            $out[$k] = ['ok' => $body !== false && $code >= 200 && $code < 400, 'ms' => $ms, 'note' => $body === false ? 'unreachable' : 'HTTP ' . $code];
        }
        return $out;
    }

    /** Payload for GET /health. */
    public static function health(): array
    {
        $ok = is_writable(Kit::$data);
        return [
            'ok'      => $ok,
            'service' => (string)Kit::cfg('service'),
            'name'    => (string)Kit::cfg('name'),
            'version' => (string)Kit::cfg('version', Kit::VERSION),
            'time'    => Kit::now(),
        ];
    }
}
