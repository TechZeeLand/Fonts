<?php
declare(strict_types=1);
namespace Tzl;

/** Small HTML builders for admin forms. All output is escaped. */
final class Ui
{
    public static function text(string $label, string $name, $value, array $o = []): string
    {
        $type = $o['type'] ?? 'text';
        $attrs = '';
        foreach (['placeholder', 'maxlength', 'min', 'max', 'step', 'pattern', 'autocomplete'] as $a) {
            if (isset($o[$a])) { $attrs .= ' ' . $a . '="' . Kit::e($o[$a]) . '"'; }
        }
        if (!empty($o['required'])) { $attrs .= ' required'; }
        $cls = !empty($o['narrow']) ? ' class="narrow"' : '';
        return '<div class="field"><label for="f_' . Kit::e($name) . '">' . Kit::e($label) . '</label><input' . $cls . ' type="' . Kit::e($type) . '" id="f_' . Kit::e($name) . '" name="' . Kit::e($name) . '" value="' . Kit::e($value) . '"' . $attrs . '>'
            . (!empty($o['hint']) ? '<p class="hint">' . $o['hint'] . '</p>' : '') . '</div>';
    }

    public static function area(string $label, string $name, $value, array $o = []): string
    {
        $rows = (int)($o['rows'] ?? 5);
        $cls = !empty($o['mono']) ? ' class="mono"' : '';
        return '<div class="field"><label for="f_' . Kit::e($name) . '">' . Kit::e($label) . '</label><textarea' . $cls . ' id="f_' . Kit::e($name) . '" name="' . Kit::e($name) . '" rows="' . $rows . '"' . (isset($o['maxlength']) ? ' maxlength="' . (int)$o['maxlength'] . '"' : '') . ($o['placeholder'] ?? '' ? ' placeholder="' . Kit::e($o['placeholder']) . '"' : '') . '>' . Kit::e($value) . '</textarea>'
            . (!empty($o['hint']) ? '<p class="hint">' . $o['hint'] . '</p>' : '') . '</div>';
    }

    public static function select(string $label, string $name, array $options, $value, array $o = []): string
    {
        $h = '<div class="field"><label for="f_' . Kit::e($name) . '">' . Kit::e($label) . '</label><select id="f_' . Kit::e($name) . '" name="' . Kit::e($name) . '">';
        foreach ($options as $k => $v) { $h .= '<option value="' . Kit::e($k) . '"' . ((string)$k === (string)$value ? ' selected' : '') . '>' . Kit::e($v) . '</option>'; }
        return $h . '</select>' . (!empty($o['hint']) ? '<p class="hint">' . $o['hint'] . '</p>' : '') . '</div>';
    }

    public static function check(string $name, string $label, bool $on, string $hint = ''): string
    {
        return '<label class="row-check"><input type="checkbox" name="' . Kit::e($name) . '" value="1"' . ($on ? ' checked' : '') . '><span class="switch"></span><span class="row-check-label">' . Kit::e($label)
            . ($hint !== '' ? ' <small>— ' . Kit::e($hint) . '</small>' : '') . '</span></label>';
    }

    public static function color(string $label, string $name, string $value, string $hint = ''): string
    {
        $v = Site::cleanColor($value);
        return '<div class="field"><label for="f_' . Kit::e($name) . '">' . Kit::e($label) . '</label><div class="color-row"><input type="color" id="f_' . Kit::e($name) . '" name="' . Kit::e($name) . '" value="' . Kit::e($v) . '" data-color-sync>'
            . '<input type="text" value="' . Kit::e($v) . '" maxlength="7" data-color-text aria-label="' . Kit::e($label) . ' hex" class="narrow"></div>' . ($hint !== '' ? '<p class="hint">' . $hint . '</p>' : '') . '</div>';
    }

    public static function card(string $title, string $icon, string $inner, string $intro = ''): string
    {
        return '<section class="card"><h2>' . Kit::icon($icon) . ' ' . Kit::e($title) . '</h2>' . ($intro !== '' ? '<p class="hint">' . $intro . '</p>' : '') . $inner . '</section>';
    }

    /**
     * Repeatable list editor for links. Inputs are named {$name}[i][label|url|icon|new]; tzl.js adds, removes and reorders rows.
     * @param bool $icons show the icon column
     */
    public static function linkEditor(string $name, array $rows, bool $icons = true, bool $withNew = true): string
    {
        $row = static function (string $i, array $r) use ($name, $icons, $withNew): string {
            $n = Kit::e($name) . '[' . $i . ']';
            return '<div class="link-row" data-row>'
                . '<input type="text" name="' . $n . '[label]" value="' . Kit::e($r['label'] ?? '') . '" placeholder="Label" maxlength="40" aria-label="Label">'
                . '<input type="text" name="' . $n . '[url]" value="' . Kit::e($r['url'] ?? '') . '" placeholder="/path or https://…" maxlength="300" aria-label="URL">'
                . ($icons ? '<input type="text" name="' . $n . '[icon]" value="' . Kit::e($r['icon'] ?? '') . '" placeholder="icon name" maxlength="40" aria-label="Icon name" list="zi-names">' : '')
                . ($withNew ? '<label class="mini-check" title="Open in a new tab"><input type="checkbox" name="' . $n . '[new]" value="1"' . (!empty($r['new']) ? ' checked' : '') . '><span>New tab</span></label>' : '')
                . '<span class="row-tools"><button type="button" class="icon-btn" data-move="-1" aria-label="Move up">' . Kit::icon('chevron-up') . '</button><button type="button" class="icon-btn" data-move="1" aria-label="Move down">' . Kit::icon('chevron-down') . '</button><button type="button" class="icon-btn danger" data-remove aria-label="Remove">' . Kit::icon('trash') . '</button></span></div>';
        };
        $h = '<div class="link-editor" data-link-editor data-name="' . Kit::e($name) . '"><div class="link-rows" data-rows>';
        $i = 0;
        foreach ($rows as $r) { $h .= $row((string)$i++, (array)$r); }
        $h .= '</div><template data-template>' . $row('__i__', []) . '</template>';
        $h .= '<button type="button" class="btn btn-secondary btn-sm" data-add>' . Kit::icon('plus') . ' Add link</button></div>';
        return $h;
    }

    /** Datalist of common icon names (the icons service has many more). */
    public static function iconList(): string
    {
        $n = ['home', 'info', 'mail', 'book-open', 'font', 'sparkles', 'qr-code', 'barcode', 'file-pdf', 'github', 'youtube', 'facebook', 'instagram', 'tiktok', 'x', 'linkedin', 'whatsapp', 'pathaopay', 'heart', 'globe', 'star-outline', 'download', 'code', 'shield-check', 'life-buoy', 'rocket', 'tag', 'phone', 'message-square', 'map-pin', 'link'];
        $h = '<datalist id="zi-names">';
        foreach ($n as $x) { $h .= '<option value="' . $x . '">'; }
        return $h . '</datalist>';
    }

    /** Daily bar chart (SVG). $series: [ [label, [date=>value], cssClass], ... ] stacked. */
    public static function chart(array $dates, array $series, int $H = 150): string
    {
        $n = max(1, count($dates));
        $W = 600; $bw = 26; $gap = ($W - $n * $bw) / ($n + 1);
        $max = 1;
        foreach ($dates as $d) { $t = 0; foreach ($series as $s) { $t += (int)($s[1][$d] ?? 0); } $max = max($max, $t); }
        $h = '<div class="chart-wrap"><svg viewBox="0 0 ' . $W . ' ' . ($H + 22) . '" class="chart" role="img" aria-label="Daily chart">';
        $i = 0;
        foreach ($dates as $d) {
            $x = $gap + $i * ($bw + $gap);
            $y = $H;
            foreach ($series as $s) {
                $v = (int)($s[1][$d] ?? 0);
                $hh = (int)round($v / $max * $H);
                if ($hh > 0) { $y -= $hh; $h .= '<rect class="' . Kit::e($s[2]) . '" x="' . round($x, 1) . '" y="' . $y . '" width="' . $bw . '" height="' . $hh . '" rx="3"><title>' . Kit::e($d . ': ' . $v . ' ' . $s[0]) . '</title></rect>'; }
            }
            if ($n <= 16 ? $i % 2 === 0 : $i % 5 === 0) { $h .= '<text class="chart-label" x="' . round($x + $bw / 2, 1) . '" y="' . ($H + 16) . '" text-anchor="middle">' . Kit::e(substr($d, 5)) . '</text>'; }
            $i++;
        }
        $h .= '</svg></div><p class="legend">';
        foreach ($series as $s) { $h .= '<span class="dot ' . Kit::e($s[2]) . '"></span> ' . Kit::e($s[0]) . ' (' . number_format(array_sum(array_map('intval', $s[1]))) . ') '; }
        return $h . '</p>';
    }

    /** Ranked list: [label => count]. */
    public static function rank(array $rows, ?callable $link = null): string
    {
        if (!$rows) { return '<p class="muted">Nothing recorded yet.</p>'; }
        $h = '<ol class="rank">';
        foreach ($rows as $k => $n) {
            $lab = $link ? $link((string)$k) : Kit::e((string)$k);
            $h .= '<li><span class="rank-label">' . $lab . '</span><span>' . number_format((int)$n) . '</span></li>';
        }
        return $h . '</ol>';
    }
}
