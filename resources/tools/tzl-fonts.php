<?php
/**
 * TZL Fonts cache helper - one file, no dependencies (PHP 7.4+, cURL or allow_url_fopen).
 *
 *   require __DIR__ . '/tzl-fonts.php';
 *   echo tzl_fonts('Alata:wght@400;700');      // inside <head>
 *
 * Fonts are downloaded once from {{BASE_URL}} into a cache folder on YOUR server, the CSS is
 * rewritten to point at those local copies, and the service is re-checked only when the
 * cache is older than the TTL (conditional request - unchanged fonts are not downloaded again).
 * If the service is unreachable the old copy keeps being used; with no copy at all the helper
 * falls back to the remote stylesheet, so your site never breaks.
 *
 * Options (second argument):
 *   dir      cache folder, must be writable           default: ./tzl-fonts-cache next to this file
 *   url      public URL path of that folder           default: /tzl-fonts-cache
 *   ttl      seconds before re-checking               default: {{CACHE_DAYS}} days
 *   display  font-display value                       default: swap
 *   inline   true = <style>, false = <link> to .css   default: true
 *   preload  preload the first WOFF2 file             default: true
 *   base     TZL Fonts server                         default: {{BASE_URL}}
 *   force    ignore the TTL and refresh now           default: false
 *
 * Pre-warm from the command line (cron):  php tzl-fonts.php refresh "Alata:wght@400;700" [--dir=...] [--url=...]
 */

if (!defined('TZL_FONTS_BASE')) {
    define('TZL_FONTS_BASE', '{{BASE_URL}}');
}

/** @param string|string[] $families e.g. 'Alata:wght@400;700' or an array of such specs */
function tzl_fonts($families, array $options = []): string
{
    $ttl = (int)'{{CACHE_DAYS}}' * 86400;
    $o = array_merge([
        'dir'     => __DIR__ . '/tzl-fonts-cache',
        'url'     => '/tzl-fonts-cache',
        'ttl'     => $ttl > 0 ? $ttl : 7 * 86400,
        'display' => 'swap',
        'inline'  => true,
        'preload' => true,
        'base'    => TZL_FONTS_BASE,
        'force'   => false,
        'timeout' => 6,
    ], $options);
    return TZL_Fonts::render((array)$families, $o);
}

final class TZL_Fonts
{
    public static function render(array $families, array $o): string
    {
        $base = rtrim((string)$o['base'], '/');
        $query = self::query($families, (string)$o['display']);
        $prefix = rtrim((string)$o['url'], '/');
        $key = substr(sha1($base . '?' . $query . '|' . $prefix), 0, 12);
        $dir = rtrim((string)$o['dir'], '/\\');
        $cssFile = $dir . '/' . $key . '.css';
        $metaFile = $dir . '/' . $key . '.json';

        $meta = is_file($metaFile) ? json_decode((string)@file_get_contents($metaFile), true) : null;
        $fresh = is_array($meta) && is_file($cssFile) && (time() - (int)($meta['fetched'] ?? 0)) < (int)$o['ttl'];
        if (!$fresh || !empty($o['force'])) {
            self::refresh($base, $query, $prefix, $key, $dir, $o, is_array($meta) ? $meta : null);
            $meta = is_file($metaFile) ? json_decode((string)@file_get_contents($metaFile), true) : null;
        }

        if (is_file($cssFile)) {
            $out = '';
            if (!empty($o['preload']) && is_array($meta)) {
                foreach ((array)($meta['files'] ?? []) as $f) {
                    if (substr($f, -6) === '.woff2') {
                        $out .= '<link rel="preload" href="' . self::h($prefix . '/' . $f) . '" as="font" type="font/woff2" crossorigin>' . "\n";
                        break;
                    }
                }
            }
            if (!empty($o['inline'])) {
                return $out . '<style>' . (string)file_get_contents($cssFile) . '</style>';
            }
            // the .css file lives in the cache folder, which is served from $prefix
            return $out . '<link rel="stylesheet" href="' . self::h($prefix . '/' . $key . '.css') . '">';
        }
        // nothing cached and the service is unreachable: use the remote stylesheet
        return '<link rel="stylesheet" href="' . self::h($base . '/css2?' . $query) . '">';
    }

    private static function query(array $families, string $display): string
    {
        $parts = [];
        foreach ($families as $f) {
            $f = trim((string)$f);
            if ($f !== '') { $parts[] = 'family=' . str_replace('%20', '+', rawurlencode($f)); }
        }
        return implode('&', $parts) . '&display=' . rawurlencode($display);
    }

    private static function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

    public static function refresh(string $base, string $query, string $prefix, string $key, string $dir, array $o, ?array $old): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) { return; }
        if (!is_writable($dir)) { return; }
        $failFile = $dir . '/' . $key . '.fail';
        if (empty($o['force']) && is_file($failFile) && time() - (int)@filemtime($failFile) < 60) { return; }

        $lock = @fopen($dir . '/' . $key . '.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) { return; } // someone else is refreshing; use what we have
        try {
            $cssFile = $dir . '/' . $key . '.css';
            $metaFile = $dir . '/' . $key . '.json';
            $etag = (is_array($old) && is_file($cssFile) && empty($o['force'])) ? (string)($old['etag'] ?? '') : '';

            [$code, $body, $newEtag] = self::http($base . '/css2?' . $query, $etag, (int)$o['timeout']);
            if ($code === 304 && is_array($old)) {
                $old['fetched'] = time();
                self::put($metaFile, json_encode($old));
                @unlink($failFile);
                return;
            }
            if ($code !== 200 || $body === '') {
                self::failed($failFile, $metaFile, $old, (int)$o['ttl']);
                return;
            }

            $host = parse_url($base, PHP_URL_HOST);
            $files = [];
            $css = $body;
            if (preg_match_all('/url\(\s*([\'"]?)([^)\'"]+)\1\s*\)/', $body, $m)) {
                foreach (array_unique($m[2]) as $ref) {
                    $p = parse_url($ref);
                    if (!$p || ($p['host'] ?? '') !== $host) { continue; } // only fetch from the TZL Fonts host
                    $ext = strtolower(pathinfo((string)($p['path'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['woff2', 'woff', 'ttf', 'otf'], true)) { continue; }
                    parse_str((string)($p['query'] ?? ''), $q);
                    $stem = pathinfo(rawurldecode((string)$p['path']), PATHINFO_FILENAME);
                    $local = preg_replace('/[^A-Za-z0-9._-]+/', '_', $stem . '-' . ($q['v'] ?? '0') . '.' . $ext);
                    $dest = $dir . '/' . $local;
                    if (!is_file($dest)) {
                        [$c2, $bin] = self::http($ref, '', (int)$o['timeout']);
                        if ($c2 !== 200 || $bin === '') {
                            self::failed($failFile, $metaFile, $old, (int)$o['ttl']);
                            return;
                        }
                        self::put($dest, $bin);
                    }
                    $files[] = $local;
                    $css = str_replace($ref, $prefix . '/' . $local, $css);
                }
            }
            self::put($cssFile, $css);
            self::put($metaFile, json_encode(['source' => $base . '/css2?' . $query, 'etag' => $newEtag, 'fetched' => time(), 'files' => $files]));
            @unlink($failFile);
            // drop files that the previous version needed but this one does not
            if (is_array($old)) {
                foreach ((array)($old['files'] ?? []) as $f) {
                    if (!in_array($f, $files, true)) { @unlink($dir . '/' . basename($f)); }
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    /** Remember a failure: retry soon instead of on every request. */
    private static function failed(string $failFile, string $metaFile, ?array $old, int $ttl): void
    {
        @touch($failFile);
        if (is_array($old)) {
            $old['fetched'] = time() - $ttl + 600; // keep serving the old copy, retry in ~10 minutes
            self::put($metaFile, json_encode($old));
        }
    }

    private static function put(string $file, string $data): void
    {
        $tmp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($tmp, $data) !== false) { @chmod($tmp, 0644); @rename($tmp, $file); }
    }

    /** @return array{0:int,1:string,2:string} status, body, etag */
    private static function http(string $url, string $etag, int $timeout): array
    {
        $headers = ['User-Agent: tzl-fonts-cache/1.0'];
        if ($etag !== '') { $headers[] = 'If-None-Match: ' . $etag; }
        if (function_exists('curl_init')) {
            $newEtag = '';
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
                CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => $timeout, CURLOPT_HTTPHEADER => $headers,
                CURLOPT_HEADERFUNCTION => static function ($c, $h) use (&$newEtag) {
                    if (stripos($h, 'etag:') === 0) { $newEtag = trim(substr($h, 5)); }
                    return strlen($h);
                },
            ]);
            $body = curl_exec($ch);
            $code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);
            return [$code, is_string($body) ? $body : '', $newEtag];
        }
        $ctx = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => $timeout, 'ignore_errors' => true]]);
        $body = @file_get_contents($url, false, $ctx);
        $code = 0;
        $newEtag = '';
        foreach (($http_response_header ?? []) as $h) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $mm)) { $code = (int)$mm[1]; }
            elseif (stripos($h, 'etag:') === 0) { $newEtag = trim(substr($h, 5)); }
        }
        return [$code, is_string($body) ? $body : '', $newEtag];
    }
}

// CLI: php tzl-fonts.php refresh "Alata:wght@400;700" [--dir=/path] [--url=/public/path]
if (PHP_SAPI === 'cli' && isset($_SERVER['argv'][0]) && realpath($_SERVER['argv'][0]) === __FILE__) {
    $args = array_slice($_SERVER['argv'], 1);
    $cmd = array_shift($args);
    $fam = [];
    $opt = ['force' => true];
    foreach ($args as $a) {
        if (strpos($a, '--dir=') === 0) { $opt['dir'] = substr($a, 6); }
        elseif (strpos($a, '--url=') === 0) { $opt['url'] = substr($a, 6); }
        elseif (strpos($a, '--ttl=') === 0) { $opt['ttl'] = (int)substr($a, 6); }
        else { $fam[] = $a; }
    }
    if ($cmd !== 'refresh' || !$fam) {
        fwrite(STDERR, "Usage: php tzl-fonts.php refresh \"Family:wght@400;700\" [--dir=/path] [--url=/public/path]\n");
        exit(1);
    }
    $html = tzl_fonts($fam, $opt);
    echo (strpos($html, '<style>') !== false || strpos($html, 'tzl-fonts-cache') !== false || strpos($html, $opt['url'] ?? '/tzl-fonts-cache') !== false)
        ? "TZL Fonts: cache refreshed.\n" : "TZL Fonts: service unreachable, nothing cached.\n";
}
