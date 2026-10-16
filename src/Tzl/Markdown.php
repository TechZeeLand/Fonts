<?php
declare(strict_types=1);
namespace Tzl;

/** Minimal, safe Markdown -> HTML (all raw HTML is escaped). Supports what docs.md uses. */
final class Markdown
{
    public static function slug(string $s): string
    {
        $s = strtolower(strip_tags($s));
        $s = (string)preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }

    /** @param array|null $toc receives [['level'=>2,'id'=>..,'text'=>..], ...] for h2/h3 */
    public static function render(string $md, ?array &$toc = null): string
    {
        $toc = [];
        $lines = explode("\n", str_replace("\r\n", "\n", $md));
        $out = '';
        $n = count($lines);
        $i = 0;
        $usedIds = [];
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') { $i++; continue; }

            // fenced code
            if (preg_match('/^```\s*([\w+-]*)\s*$/', $line, $m)) {
                $lang = $m[1];
                $buf = [];
                $i++;
                while ($i < $n && !preg_match('/^```\s*$/', $lines[$i])) { $buf[] = $lines[$i]; $i++; }
                $i++;
                $out .= '<div class="code-block"' . ($lang !== '' ? ' data-lang="' . Kit::e($lang) . '"' : '') . '><pre><code>' . Kit::e(implode("\n", $buf)) . '</code></pre></div>' . "\n";
                continue;
            }
            // heading
            if (preg_match('/^(#{1,4})\s+(.+?)\s*#*$/', $line, $m)) {
                $lvl = strlen($m[1]);
                $text = $m[2];
                $id = self::slug($text);
                $base = $id;
                $k = 2;
                while (isset($usedIds[$id])) { $id = $base . '-' . $k++; }
                $usedIds[$id] = true;
                if ($lvl === 2 || $lvl === 3) { $toc[] = ['level' => $lvl, 'id' => $id, 'text' => preg_replace('/`/', '', $text)]; }
                $out .= '<h' . $lvl . ' id="' . $id . '">' . self::inline($text) . ' <a class="anchor" href="#' . $id . '" aria-label="Link to this section">#</a></h' . $lvl . ">\n";
                $i++;
                continue;
            }
            // hr
            if (preg_match('/^(-{3,}|\*{3,})\s*$/', $line)) { $out .= "<hr>\n"; $i++; continue; }
            // table
            if (strpos($line, '|') !== false && $i + 1 < $n && preg_match('/^\s*\|?\s*:?-{2,}:?\s*(\|\s*:?-{2,}:?\s*)*\|?\s*$/', $lines[$i + 1])) {
                $head = self::cells($line);
                $i += 2;
                $rows = [];
                while ($i < $n && trim($lines[$i]) !== '' && strpos($lines[$i], '|') !== false) { $rows[] = self::cells($lines[$i]); $i++; }
                $out .= '<div class="table-wrap"><table class="doc-table"><thead><tr>';
                foreach ($head as $c) { $out .= '<th>' . self::inline($c) . '</th>'; }
                $out .= '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $out .= '<tr>';
                    foreach ($r as $c) { $out .= '<td>' . self::inline($c) . '</td>'; }
                    $out .= '</tr>';
                }
                $out .= "</tbody></table></div>\n";
                continue;
            }
            // blockquote
            if (preg_match('/^>\s?/', $line)) {
                $buf = [];
                while ($i < $n && preg_match('/^>\s?(.*)$/', $lines[$i], $m)) { $buf[] = $m[1]; $i++; }
                $out .= '<blockquote><p>' . self::inline(implode(' ', $buf)) . "</p></blockquote>\n";
                continue;
            }
            // lists
            if (preg_match('/^\s*([-*]|\d+\.)\s+/', $line)) {
                $ordered = (bool)preg_match('/^\s*\d+\./', $line);
                $out .= $ordered ? '<ol>' : '<ul>';
                while ($i < $n && preg_match('/^\s*(?:[-*]|\d+\.)\s+(.*)$/', $lines[$i], $m)) {
                    $item = $m[1];
                    $i++;
                    while ($i < $n && preg_match('/^\s{2,}(\S.*)$/', $lines[$i], $c) && !preg_match('/^\s*(?:[-*]|\d+\.)\s+/', $lines[$i])) { $item .= ' ' . $c[1]; $i++; }
                    $out .= '<li>' . self::inline($item) . '</li>';
                }
                $out .= $ordered ? "</ol>\n" : "</ul>\n";
                continue;
            }
            // paragraph
            $buf = [];
            while ($i < $n && trim($lines[$i]) !== '' && !preg_match('/^(```|#{1,4}\s|>\s?|\s*([-*]|\d+\.)\s+|-{3,}\s*$)/', $lines[$i])) { $buf[] = trim($lines[$i]); $i++; }
            if (!$buf) { $buf[] = trim($lines[$i]); $i++; }
            $out .= '<p>' . self::inline(implode(' ', $buf)) . "</p>\n";
        }
        return $out;
    }

    private static function cells(string $row): array
    {
        $row = trim($row);
        $row = trim($row, '|');
        return array_map('trim', explode('|', $row));
    }

    public static function inline(string $s): string
    {
        $codes = [];
        $s = (string)preg_replace_callback('/`([^`]+)`/', static function ($m) use (&$codes) {
            $codes[] = '<code>' . Kit::e($m[1]) . '</code>';
            return "\x01" . (count($codes) - 1) . "\x02";
        }, $s);
        $s = Kit::e($s);
        $s = (string)preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $s);
        $s = (string)preg_replace('/(?<![\w*])\*(?![\s*])(.+?)(?<![\s*])\*(?![\w*])/s', '<em>$1</em>', $s);
        $s = (string)preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/', static function ($m) {
            $u = html_entity_decode($m[2], ENT_QUOTES);
            if (!preg_match('#^(https?://|/|\#|mailto:)#', $u)) { return $m[0]; }
            $ext = preg_match('#^https?://#', $u) && strpos($u, Kit::baseUrl()) !== 0;
            return '<a href="' . Kit::e($u) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $s);
        return (string)preg_replace_callback('/\x01(\d+)\x02/', static fn($m) => $codes[(int)$m[1]], $s);
    }
}
