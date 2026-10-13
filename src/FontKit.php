<?php
namespace App;

/**
 * Font file inspection + WOFF2 conversion.
 * Uses bin/fontkit.py (fonttools + brotli) when available; otherwise a small pure-PHP
 * reader for TTF/OTF/WOFF metadata. WOFF2 inspection needs the Python helper.
 */
final class FontKit
{
    public const EXT = ['woff2', 'woff', 'ttf', 'otf'];
    public const MIME = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'];
    public const FORMAT = ['woff2' => 'woff2', 'woff' => 'woff', 'ttf' => 'truetype', 'otf' => 'opentype'];

    private static ?array $caps = null;

    /** @return array{python:bool,fonttools:bool,brotli:bool} */
    public static function caps(): array
    {
        if (self::$caps === null) {
            $r = self::run(['check']);
            self::$caps = [
                'python'    => $r !== null,
                'fonttools' => (bool)($r['fonttools'] ?? false),
                'brotli'    => (bool)($r['brotli'] ?? false),
            ];
        }
        return self::$caps;
    }

    private static function run(array $args): ?array
    {
        if (!function_exists('proc_open')) { return null; }
        $cmd = array_merge([App::env('TZL_PYTHON', 'python3'), App::$root . '/bin/fontkit.py'], $args);
        $p = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($p)) { return null; }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $code = proc_close($p);
        if ($code !== 0) {
            if (trim((string)$err) !== '') { error_log('fontkit: ' . trim((string)$err)); }
            return null;
        }
        $j = json_decode((string)$out, true);
        return is_array($j) ? $j : null;
    }

    /** Real file type from magic bytes (never trust the extension). */
    public static function sniff(string $path): ?string
    {
        $fp = @fopen($path, 'rb');
        if (!$fp) { return null; }
        $m = (string)fread($fp, 4);
        fclose($fp);
        if ($m === "\x00\x01\x00\x00" || $m === 'true') { return 'ttf'; }
        if ($m === 'OTTO') { return 'otf'; }
        if ($m === 'wOFF') { return 'woff'; }
        if ($m === 'wOF2') { return 'woff2'; }
        return null;
    }

    public static function info(string $path, string $ext): array
    {
        if (self::caps()['fonttools']) {
            $r = self::run(['info', $path]);
            if ($r) { return $r; }
        }
        return self::phpInfo($path, $ext);
    }

    public static function toWoff2(string $src, string $dest): bool
    {
        $c = self::caps();
        if (!$c['fonttools'] || !$c['brotli']) { return false; }
        $r = self::run(['woff2', $src, $dest]);
        return $r !== null && is_file($dest) && filesize($dest) > 0;
    }

    /* ---------- pure PHP fallback (TTF / OTF / WOFF1) ---------- */

    public static function emptyInfo(): array
    {
        return ['family' => '', 'subfamily' => '', 'full_name' => '', 'ps_name' => '', 'weight' => 0, 'italic' => false,
            'version' => '', 'copyright' => '', 'license_desc' => '', 'license_url' => '', 'designer' => '',
            'glyphs' => 0, 'variable' => false, 'axes' => [], 'wmin' => null, 'wmax' => null, 'subsets' => [], 'flavor' => ''];
    }

    private static function phpInfo(string $path, string $ext): array
    {
        $info = self::emptyInfo();
        $data = @file_get_contents($path);
        if ($data === false || strlen($data) < 12) { return $info; }
        $sig = substr($data, 0, 4);
        $tables = [];
        $want = ['name', 'OS/2', 'maxp', 'fvar'];

        if ($sig === 'wOFF') {
            $n = unpack('n', substr($data, 12, 2))[1];
            for ($i = 0; $i < $n; $i++) {
                $e = unpack('a4tag/Noff/NcompLen/NorigLen', substr($data, 44 + $i * 20, 16));
                if (!in_array($e['tag'], $want, true)) { continue; }
                $raw = substr($data, $e['off'], $e['compLen']);
                if ($e['compLen'] < $e['origLen']) { $raw = @gzuncompress($raw); }
                if (is_string($raw)) { $tables[$e['tag']] = $raw; }
            }
        } elseif ($sig === "\x00\x01\x00\x00" || $sig === 'OTTO' || $sig === 'true') {
            $n = unpack('n', substr($data, 4, 2))[1];
            for ($i = 0; $i < $n; $i++) {
                $e = unpack('a4tag/Nsum/Noff/Nlen', substr($data, 12 + $i * 16, 16));
                if (in_array($e['tag'], $want, true)) { $tables[$e['tag']] = substr($data, $e['off'], $e['len']); }
            }
        } else {
            return $info; // WOFF2 and others need the Python helper
        }

        if (isset($tables['name'])) {
            $nm = self::readNames($tables['name']);
            $info['family'] = $nm[16] ?? ($nm[1] ?? '');
            $info['subfamily'] = $nm[17] ?? ($nm[2] ?? '');
            $info['full_name'] = $nm[4] ?? '';
            $info['ps_name'] = $nm[6] ?? '';
            $info['version'] = $nm[5] ?? '';
            $info['copyright'] = $nm[0] ?? '';
            $info['license_desc'] = $nm[13] ?? '';
            $info['license_url'] = $nm[14] ?? '';
            $info['designer'] = $nm[9] ?? ($nm[8] ?? '');
        }
        if (isset($tables['OS/2']) && strlen($tables['OS/2']) >= 64) {
            $info['weight'] = unpack('n', substr($tables['OS/2'], 4, 2))[1];
            $sel = unpack('n', substr($tables['OS/2'], 62, 2))[1];
            $info['italic'] = (bool)($sel & 1);
        }
        if (isset($tables['maxp']) && strlen($tables['maxp']) >= 6) {
            $info['glyphs'] = unpack('n', substr($tables['maxp'], 4, 2))[1];
        }
        if (isset($tables['fvar']) && strlen($tables['fvar']) >= 16) {
            $fv = $tables['fvar'];
            $off = unpack('n', substr($fv, 4, 2))[1];
            $cnt = unpack('n', substr($fv, 8, 2))[1];
            $size = unpack('n', substr($fv, 10, 2))[1];
            $info['variable'] = true;
            for ($i = 0; $i < $cnt; $i++) {
                $a = substr($fv, $off + $i * $size, 20);
                if (strlen($a) < 16) { break; }
                $tag = substr($a, 0, 4);
                $v = [];
                foreach ([4, 8, 12] as $p) {
                    $x = unpack('N', substr($a, $p, 4))[1];
                    if ($x & 0x80000000) { $x -= 4294967296; }
                    $v[] = $x / 65536;
                }
                $info['axes'][] = ['tag' => $tag, 'min' => $v[0], 'default' => $v[1], 'max' => $v[2]];
                if ($tag === 'wght') { $info['wmin'] = (int)$v[0]; $info['wmax'] = (int)$v[2]; }
            }
        }
        return $info;
    }

    /** @return array<int,string> nameID => string (Windows English preferred) */
    private static function readNames(string $t): array
    {
        if (strlen($t) < 6) { return []; }
        $count = unpack('n', substr($t, 2, 2))[1];
        $strOff = unpack('n', substr($t, 4, 2))[1];
        $best = [];
        for ($i = 0; $i < $count; $i++) {
            $r = substr($t, 6 + $i * 12, 12);
            if (strlen($r) < 12) { break; }
            $x = unpack('nplat/nenc/nlang/nid/nlen/noff', $r);
            $raw = substr($t, $strOff + $x['off'], $x['len']);
            if ($x['plat'] === 3 || $x['plat'] === 0) {
                $s = function_exists('mb_convert_encoding') ? mb_convert_encoding($raw, 'UTF-8', 'UTF-16BE') : $raw;
                $score = ($x['plat'] === 3 && $x['lang'] === 0x409) ? 3 : 1;
            } else {
                $s = $raw;
                $score = 2;
            }
            $s = trim((string)$s);
            if ($s === '') { continue; }
            if (!isset($best[$x['id']]) || $score > $best[$x['id']][0]) { $best[$x['id']] = [$score, $s]; }
        }
        $out = [];
        foreach ($best as $id => $v) { $out[$id] = $v[1]; }
        return $out;
    }
}
