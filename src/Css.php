<?php
namespace App;

/**
 * Google-Fonts-compatible CSS API:
 *   /css2?family=Alata:wght@400;700&family=Name:ital,wght@0,400;1,700&display=swap
 *   /css?family=Alata:400,700italic|Name        (legacy v1 syntax)
 */
final class Css
{
    public const DISPLAY = ['auto', 'block', 'swap', 'fallback', 'optional'];
    private const MAX_FAMILIES = 20;

    /** @return array{0:array,1:string,2:bool} requests, display, legacy */
    public static function parse(string $qs): array
    {
        $reqs = [];
        $display = (string)Store::setting('default_display');
        if (!in_array($display, self::DISPLAY, true)) { $display = 'swap'; }
        $legacy = false;
        foreach (explode('&', $qs) as $pair) {
            if ($pair === '') { continue; }
            $kv = explode('=', $pair, 2);
            $k = urldecode($kv[0]);
            $v = urldecode($kv[1] ?? '');
            if ($k === 'family') {
                foreach (explode('|', $v) as $one) {
                    if (trim($one) !== '' && count($reqs) < self::MAX_FAMILIES) { $reqs[] = self::parseFamily($one); }
                }
            } elseif ($k === 'display' && in_array($v, self::DISPLAY, true)) {
                $display = $v;
            } elseif ($k === 'legacy') {
                $legacy = ($v !== '0' && $v !== 'false');
            }
        }
        return [$reqs, $display, $legacy];
    }

    private static function parseFamily(string $v): array
    {
        $name = $v;
        $spec = '';
        $pos = strpos($v, ':');
        if ($pos !== false) { $name = substr($v, 0, $pos); $spec = substr($v, $pos + 1); }
        $tuples = [];
        if ($spec === '') {
            $tuples[] = ['ital' => 0, 'wght' => '400'];
        } elseif (strpos($spec, '@') !== false) {
            [$axes, $vals] = explode('@', $spec, 2);
            $axes = array_map('trim', explode(',', $axes));
            foreach (explode(';', $vals) as $tp) {
                $parts = array_map('trim', explode(',', $tp));
                if (count($parts) !== count($axes)) { continue; }
                $row = array_combine($axes, $parts);
                $tuples[] = ['ital' => (int)($row['ital'] ?? 0), 'wght' => (string)($row['wght'] ?? '400')];
            }
        } else {
            foreach (explode(',', $spec) as $t) {
                $t = strtolower(trim($t));
                $it = (bool)preg_match('/i(talic)?$/', $t);
                if (preg_match('/^\d{3}/', $t)) { $w = (int)substr($t, 0, 3); }
                else { $w = (strpos($t, 'bold') === 0) ? 700 : 400; }
                $tuples[] = ['ital' => $it ? 1 : 0, 'wght' => (string)$w];
            }
        }
        return ['name' => trim($name), 'tuples' => array_slice($tuples, 0, 40)];
    }

    /** @return array{0:string[],1:string[],2:string[]} css blocks, font slugs, problems */
    public static function build(array $reqs, string $display, bool $legacy, string $base): array
    {
        $blocks = [];
        $slugs = [];
        $problems = [];
        foreach ($reqs as $r) {
            $font = Store::findByFamily($r['name']);
            if (!$font) { $problems[] = 'Font family "' . $r['name'] . '" was not found.'; continue; }
            $slugs[$font['slug']] = true;
            $done = [];
            foreach ($r['tuples'] as $t) {
                $idx = self::pick($font, !empty($t['ital']), (string)$t['wght']);
                if ($idx === null) {
                    $problems[] = $font['family'] . ': no style for weight ' . $t['wght'] . (!empty($t['ital']) ? ' italic' : '') . '.';
                    continue;
                }
                if (isset($done[$idx])) { continue; }
                $done[$idx] = true;
                $blocks[] = self::face($font, $font['styles'][$idx], $display, $legacy, $base);
            }
        }
        return [$blocks, array_keys($slugs), $problems];
    }

    private static function pick(array $font, bool $italic, string $wght): ?int
    {
        $w = (int)$wght;
        foreach ($font['styles'] as $i => $s) {
            if ((bool)($s['italic'] ?? false) !== $italic) { continue; }
            if (!empty($s['variable'])) {
                if ($w >= (int)($s['wmin'] ?? 1) && $w <= (int)($s['wmax'] ?? 1000)) { return $i; }
            } elseif ((int)$s['weight'] === $w) {
                return $i;
            }
        }
        return null;
    }

    private static function face(array $font, array $style, string $display, bool $legacy, string $base): string
    {
        $files = $style['files'] ?? [];
        $v = (string)($font['hash'] ?? '');
        $src = [];
        foreach (['woff2', 'woff', 'ttf', 'otf'] as $ext) {
            if (!isset($files[$ext])) { continue; }
            $url = $base . '/f/' . $font['slug'] . '/' . rawurlencode((string)$files[$ext]['name']) . ($v !== '' ? '?v=' . $v : '');
            $src[] = 'url(' . $url . ") format('" . FontKit::FORMAT[$ext] . "')";
            if (!$legacy) { break; }
        }
        $weight = !empty($style['variable']) ? ((int)$style['wmin'] . ' ' . (int)$style['wmax']) : (string)(int)$style['weight'];
        $italic = !empty($style['italic']);
        return '/* ' . $font['slug'] . ' ' . (!empty($style['variable']) ? 'variable' : $weight) . ' ' . ($italic ? 'italic' : 'normal') . " */\n"
            . "@font-face {\n"
            . "  font-family: '" . str_replace(["'", '\\'], ['\\\'', '\\\\'], (string)$font['family']) . "';\n"
            . '  font-style: ' . ($italic ? 'italic' : 'normal') . ";\n"
            . '  font-weight: ' . $weight . ";\n"
            . '  font-display: ' . $display . ";\n"
            . '  src: ' . implode(', ', $src) . ";\n"
            . '}';
    }
}
