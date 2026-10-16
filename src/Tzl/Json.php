<?php
declare(strict_types=1);
namespace Tzl;

/** Flat-file JSON storage with locking, plus the audit log. Everything lives in Kit::$data. */
final class Json
{
    public static function path(string $rel = ''): string
    {
        return Kit::$data . ($rel !== '' ? '/' . ltrim($rel, '/') : '');
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

    /** Atomic read-modify-write under an exclusive lock. $fn receives the current value and returns the new one. */
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

    /* ---------- audit log ---------- */

    public static function log(string $msg): void
    {
        $f = self::path('activity.log');
        $user = (session_status() === PHP_SESSION_ACTIVE) ? ($_SESSION['u'] ?? '-') : '-';
        @file_put_contents($f, json_encode(['t' => Kit::now(), 'u' => $user, 'm' => $msg], JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
        if (@filesize($f) > 300000) {
            $lines = file($f, FILE_IGNORE_NEW_LINES) ?: [];
            @file_put_contents($f, implode("\n", array_slice($lines, -500)) . "\n", LOCK_EX);
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
