<?php
namespace App;

/**
 * Font records: importing uploads, styles, licence text, ZIP bundles, first-run seeding.
 */
final class FontService
{
    public const WEIGHTS = [100 => 'Thin', 200 => 'ExtraLight', 300 => 'Light', 400 => 'Regular', 500 => 'Medium',
        600 => 'SemiBold', 700 => 'Bold', 800 => 'ExtraBold', 900 => 'Black'];

    public static function weightName(int $w): string
    {
        $best = 400;
        $d = 9999;
        foreach (self::WEIGHTS as $k => $_) {
            if (abs($k - $w) < $d) { $d = abs($k - $w); $best = $k; }
        }
        return self::WEIGHTS[$best];
    }

    /** @return array{0:int,1:bool,2:bool} weight, italic, variable guessed from a file name */
    public static function guessFromName(string $file): array
    {
        $n = strtolower($file);
        $italic = (strpos($n, 'italic') !== false || strpos($n, 'oblique') !== false);
        $map = ['extrabold' => 800, 'ultrabold' => 800, 'extralight' => 200, 'ultralight' => 200, 'semibold' => 600,
            'demibold' => 600, 'thin' => 100, 'hairline' => 100, 'light' => 300, 'medium' => 500, 'black' => 900,
            'heavy' => 900, 'bold' => 700, 'regular' => 400, 'book' => 400];
        $w = 400;
        foreach ($map as $k => $v) {
            if (strpos($n, $k) !== false) { $w = $v; break; }
        }
        $variable = (strpos($n, 'variable') !== false || strpos($n, '[wght') !== false);
        return [$w, $italic, $variable];
    }

    public static function blank(string $family): array
    {
        return [
            'slug' => App::slugify($family), 'family' => $family, 'category' => 'sans-serif', 'designer' => '',
            'license' => 'OFL-1.1', 'license_url' => '', 'copyright' => '', 'source_url' => '', 'description' => '',
            'tags' => [], 'subsets' => [], 'variable' => false, 'axes' => [], 'glyphs' => 0, 'font_version' => '',
            'featured' => false, 'published' => true, 'styles' => [],
        ];
    }

    public static function styleLabel(array $s): string
    {
        if (!empty($s['variable'])) {
            return 'Variable' . (!empty($s['italic']) ? ' Italic' : '') . ' ' . (int)($s['wmin'] ?? 100) . '–' . (int)($s['wmax'] ?? 900);
        }
        return self::weightName((int)$s['weight']) . (!empty($s['italic']) ? ' Italic' : '') . ' ' . (int)$s['weight'];
    }

    public static function uploadError(int $code): string
    {
        switch ($code) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: return 'file is larger than the server upload limit';
            case UPLOAD_ERR_PARTIAL: return 'upload was interrupted';
            case UPLOAD_ERR_NO_TMP_DIR: return 'server has no temp directory';
            case UPLOAD_ERR_CANT_WRITE: return 'server could not write the file';
            default: return 'error code ' . $code;
        }
    }

    /* ---------- uploads ---------- */

    /** Import every file of a multi-file <input name="files[]">. */
    public static function addUploads(array &$font, array $files): array
    {
        $res = ['added' => 0, 'errors' => [], 'notes' => []];
        if (empty($files['name'])) { return $res; }
        foreach ((array)$files['name'] as $i => $orig) {
            $err = (int)(((array)$files['error'])[$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err === UPLOAD_ERR_NO_FILE) { continue; }
            if ($err !== UPLOAD_ERR_OK) {
                $res['errors'][] = $orig . ': ' . self::uploadError($err) . '.';
                continue;
            }
            $tmp = (string)(((array)$files['tmp_name'])[$i] ?? '');
            if (!is_uploaded_file($tmp)) { $res['errors'][] = $orig . ': not a valid upload.'; continue; }
            self::importFile($font, $tmp, (string)$orig, $res, true);
        }
        self::finalize($font);
        return $res;
    }

    public static function importFile(array &$font, string $tmp, string $orig, array &$res, bool $uploaded): bool
    {
        $max = max(1, (int)Store::setting('max_upload_mb')) * 1048576;
        if (filesize($tmp) > $max) {
            $res['errors'][] = $orig . ': larger than the ' . (int)($max / 1048576) . ' MB limit.';
            return false;
        }
        $ext = FontKit::sniff($tmp);
        if ($ext === null) {
            $res['errors'][] = $orig . ': not a TTF, OTF, WOFF or WOFF2 font file.';
            return false;
        }
        $info = FontKit::info($tmp, $ext);
        [$gw, $gi, $gv] = self::guessFromName($orig);
        $known = !empty($info['family']);
        $variable = !empty($info['variable']) || (!$known && $gv);
        $weight = (int)(!empty($info['weight']) ? $info['weight'] : $gw);
        $italic = $known ? !empty($info['italic']) : $gi;

        if (trim((string)$font['family']) === '') {
            $font['family'] = $known ? $info['family'] : ucwords(str_replace(['-', '_'], ' ', preg_replace('/[-_ ](regular|bold|italic|light|medium|thin|black|variable).*$/i', '', pathinfo($orig, PATHINFO_FILENAME))));
            if (empty($font['slug'])) { $font['slug'] = App::slugify($font['family']); }
        }

        $dir = Store::fontDir($font['slug']);
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            $res['errors'][] = $orig . ': cannot create the font directory (check permissions of the data folder).';
            return false;
        }
        $fam = preg_replace('/[^A-Za-z0-9]+/', '', (string)$font['family']);
        if ($fam === '') { $fam = 'Font'; }
        if ($variable) {
            $label = ($italic ? 'Italic' : '') . 'Variable';
        } elseif ($weight === 400) {
            $label = $italic ? 'Italic' : 'Regular';
        } else {
            $label = self::weightName($weight) . ($italic ? 'Italic' : '');
        }
        $base = $fam . '-' . $label;
        $dest = $dir . '/' . $base . '.' . $ext;
        $ok = $uploaded ? move_uploaded_file($tmp, $dest) : copy($tmp, $dest);
        if (!$ok) {
            $res['errors'][] = $orig . ': could not store the file.';
            return false;
        }
        @chmod($dest, 0664);

        // find or create the style entry
        $idx = null;
        foreach ($font['styles'] as $k => $s) {
            if ((bool)($s['variable'] ?? false) === $variable && (bool)($s['italic'] ?? false) === $italic
                && ($variable || (int)$s['weight'] === $weight)) {
                $idx = $k;
                break;
            }
        }
        if ($idx === null) {
            $font['styles'][] = ['weight' => $variable ? (int)($info['wmin'] ?? 100) : $weight, 'italic' => $italic,
                'variable' => $variable, 'name' => '', 'files' => []];
            $idx = array_key_last($font['styles']);
        }
        $st =& $font['styles'][$idx];
        if ($variable) {
            $st['wmin'] = (int)($info['wmin'] ?? 100);
            $st['wmax'] = (int)($info['wmax'] ?? 900);
            $st['weight'] = $st['wmin'];
        }
        $st['name'] = $variable ? ('Variable' . ($italic ? ' Italic' : '')) : (self::weightName($weight) . ($italic ? ' Italic' : ''));
        $st['files'][$ext] = self::fileRecord($dest);

        if ($ext !== 'woff2' && !isset($st['files']['woff2'])) {
            $w2 = $dir . '/' . $base . '.woff2';
            if (FontKit::toWoff2($dest, $w2)) {
                $st['files']['woff2'] = self::fileRecord($w2);
                $res['notes'][] = $orig . ': WOFF2 version generated.';
            } else {
                $res['notes'][] = $orig . ': stored as ' . strtoupper($ext) . ' only (automatic WOFF2 needs python3 + fonttools + brotli on the server).';
            }
        }
        unset($st);

        // fill blanks from the font's own metadata
        if (empty($font['designer']) && !empty($info['designer'])) { $font['designer'] = $info['designer']; }
        if (empty($font['copyright']) && !empty($info['copyright'])) { $font['copyright'] = $info['copyright']; }
        if (empty($font['license_url']) && !empty($info['license_url'])) { $font['license_url'] = $info['license_url']; }
        if (empty($font['font_version']) && !empty($info['version'])) {
            $font['font_version'] = trim((string)preg_replace('/;.*$/', '', (string)$info['version']));
        }
        if (!empty($info['variable'])) { $font['variable'] = true; $font['axes'] = $info['axes']; }
        $font['glyphs'] = max((int)($font['glyphs'] ?? 0), (int)($info['glyphs'] ?? 0));
        $font['subsets'] = array_values(array_unique(array_merge($font['subsets'] ?? [], $info['subsets'] ?? [])));
        $res['added']++;
        return true;
    }

    public static function fileRecord(string $path): array
    {
        return ['name' => basename($path), 'size' => (int)filesize($path), 'sha1' => (string)sha1_file($path)];
    }

    /** Sort styles and refresh derived flags. */
    public static function finalize(array &$font): void
    {
        usort($font['styles'], static function ($a, $b) {
            return [(int)!empty($a['italic']), (int)$a['weight']] <=> [(int)!empty($b['italic']), (int)$b['weight']];
        });
        $font['styles'] = array_values($font['styles']);
        $font['variable'] = false;
        foreach ($font['styles'] as $s) {
            if (!empty($s['variable'])) { $font['variable'] = true; }
        }
    }

    public static function removeStyle(array &$font, int $idx): void
    {
        if (!isset($font['styles'][$idx])) { return; }
        $dir = Store::fontDir($font['slug']);
        foreach (($font['styles'][$idx]['files'] ?? []) as $fi) { @unlink($dir . '/' . basename((string)$fi['name'])); }
        array_splice($font['styles'], $idx, 1);
    }

    public static function removeFile(array &$font, int $idx, string $ext): void
    {
        if (!isset($font['styles'][$idx]['files'][$ext])) { return; }
        @unlink(Store::fontDir($font['slug']) . '/' . basename((string)$font['styles'][$idx]['files'][$ext]['name']));
        unset($font['styles'][$idx]['files'][$ext]);
        if (empty($font['styles'][$idx]['files'])) { array_splice($font['styles'], $idx, 1); }
    }

    /** Create a WOFF2 for a style that only has TTF/OTF/WOFF. */
    public static function regenWoff2(array &$font, int $idx): bool
    {
        $files = $font['styles'][$idx]['files'] ?? [];
        $dir = Store::fontDir($font['slug']);
        foreach (['ttf', 'otf', 'woff'] as $ext) {
            if (!isset($files[$ext])) { continue; }
            $src = $dir . '/' . basename((string)$files[$ext]['name']);
            $dest = $dir . '/' . pathinfo($src, PATHINFO_FILENAME) . '.woff2';
            if (FontKit::toWoff2($src, $dest)) {
                $font['styles'][$idx]['files']['woff2'] = self::fileRecord($dest);
                return true;
            }
        }
        return false;
    }

    /* ---------- licence + bundle ---------- */

    public static function licenseText(array $font): string
    {
        $f = Store::fontDir($font['slug']) . '/LICENSE.txt';
        if (is_file($f)) { return (string)file_get_contents($f); }
        if (($font['license'] ?? '') === 'OFL-1.1') {
            $tpl = (string)@file_get_contents(App::$root . '/resources/licenses/OFL-1.1.txt');
            $c = trim((string)($font['copyright'] ?? ''));
            return str_replace('{{COPYRIGHT}}', $c !== '' ? $c : 'Copyright (c) the authors of ' . $font['family'], $tpl);
        }
        return '';
    }

    public static function saveLicenseText(string $slug, string $text): void
    {
        $dir = Store::fontDir($slug);
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $text = trim(str_replace("\r\n", "\n", $text));
        if ($text === '') { @unlink($dir . '/LICENSE.txt'); return; }
        file_put_contents($dir . '/LICENSE.txt', $text . "\n");
    }

    public static function licenseName(array $font): string
    {
        $k = (string)($font['license'] ?? '');
        return Store::LICENSES[$k][0] ?? $k;
    }

    public static function licenseUrl(array $font): string
    {
        $u = trim((string)($font['license_url'] ?? ''));
        return $u !== '' ? $u : (Store::LICENSES[$font['license'] ?? ''][1] ?? '');
    }

    /** Build a ZIP of all files + licence in a temp file. Caller deletes it. */
    public static function zip(array $font): ?string
    {
        if (!class_exists('ZipArchive')) { return null; }
        $tmp = tempnam(sys_get_temp_dir(), 'tzlzip');
        $z = new \ZipArchive();
        if ($tmp === false || $z->open($tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) { return null; }
        $root = str_replace(' ', '-', (string)$font['family']);
        $dir = Store::fontDir($font['slug']);
        foreach ($font['styles'] as $s) {
            foreach ($s['files'] as $fi) {
                $p = $dir . '/' . basename((string)$fi['name']);
                if (is_file($p)) { $z->addFile($p, $root . '/' . basename((string)$fi['name'])); }
            }
        }
        $lic = self::licenseText($font);
        if ($lic !== '') { $z->addFromString($root . '/LICENSE.txt', $lic); }
        $z->addFromString($root . '/README.txt', self::readme($font));
        $z->close();
        return $tmp;
    }

    private static function readme(array $font): string
    {
        $base = App::baseUrl();
        $l = [];
        $l[] = $font['family'] . ' - from ' . Store::setting('site_name');
        $l[] = str_repeat('=', 40);
        $l[] = '';
        if (!empty($font['designer'])) { $l[] = 'Designer : ' . $font['designer']; }
        $l[] = 'License  : ' . self::licenseName($font) . ' (see LICENSE.txt)';
        if (!empty($font['source_url'])) { $l[] = 'Source   : ' . $font['source_url']; }
        $l[] = 'Page     : ' . $base . '/font/' . $font['slug'];
        $l[] = '';
        $l[] = 'Use it on the web:';
        $l[] = '  <link rel="stylesheet" href="' . $base . '/css2?family=' . str_replace(' ', '+', (string)$font['family']) . '&display=swap">';
        $l[] = '';
        $l[] = 'Or self-host the .woff2 files from this folder with an @font-face rule.';
        $l[] = 'Keep LICENSE.txt with the font files when you redistribute them.';
        return implode("\n", $l) . "\n";
    }

    /* ---------- first-run seeding ---------- */

    public static function seedIfEmpty(): void
    {
        $marker = Store::path('.seeded');
        if (is_file($marker)) { return; }
        if (Store::fonts() !== []) { @touch($marker); return; }
        foreach (glob(App::$root . '/seed/*/meta.json') ?: [] as $m) {
            $font = json_decode((string)file_get_contents($m), true);
            if (!is_array($font) || empty($font['slug'])) { continue; }
            $src = dirname($m);
            $dst = Store::fontDir($font['slug']);
            if (!is_dir($dst)) { @mkdir($dst, 0775, true); }
            foreach ($font['styles'] as $s) {
                foreach ($s['files'] as $fi) {
                    if (is_file($src . '/' . $fi['name'])) { copy($src . '/' . $fi['name'], $dst . '/' . $fi['name']); }
                }
            }
            file_put_contents($dst . '/LICENSE.txt', self::licenseText($font));
            Store::saveFont($font);
            Store::log('Seeded font: ' . $font['family']);
        }
        @touch($marker);
    }
}
