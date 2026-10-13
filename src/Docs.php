<?php
namespace App;

/** The documentation lives in resources/docs.md; placeholders are filled from live data. */
final class Docs
{
    public static function markdown(): string
    {
        $src = (string)file_get_contents(App::$root . '/resources/docs.md');
        $base = App::baseUrl();
        $fonts = Store::fonts(true);
        $first = $fonts ? reset($fonts) : null;
        $example = $first ? (string)$first['family'] : 'Alata';

        $rows = ["| Font | Category | Styles | License |", "| --- | --- | --- | --- |"];
        $names = [];
        foreach ($fonts as $f) {
            $label = !empty($f['variable']) ? 'variable' : count($f['styles']) . ' style' . (count($f['styles']) === 1 ? '' : 's');
            $cat = Store::CATEGORIES[$f['category']] ?? $f['category'];
            $rows[] = '| [' . $f['family'] . '](' . $base . '/font/' . $f['slug'] . ') | ' . $cat . ' | ' . $label . ' | ' . FontService::licenseName($f) . ' |';
            $names[] = '`' . $f['family'] . '`';
        }
        if (!$fonts) { $rows[] = '| _No fonts published yet_ | | | |'; }

        $ttlH = max(1, (int)round(((int)Store::setting('css_ttl')) / 3600));
        $map = [
            '{{BASE_URL}}'      => $base,
            '{{HOST}}'          => (string)parse_url($base, PHP_URL_HOST),
            '{{SITE_NAME}}'     => (string)Store::setting('site_name'),
            '{{SHORT_NAME}}'    => (string)Store::setting('short_name'),
            '{{FONT_COUNT}}'    => (string)count($fonts),
            '{{FONT_NAMES}}'    => $names ? implode(', ', $names) : '_none yet_',
            '{{FONT_TABLE}}'    => implode("\n", $rows),
            '{{EXAMPLE}}'       => $example,
            '{{EXAMPLE_FILE}}'  => (string)(preg_replace('/[^A-Za-z0-9]+/', '', $example) ?: 'Font'),
            '{{EXAMPLE_SLUG}}'  => App::slugify($example),
            '{{EXAMPLE_PLUS}}'  => str_replace(' ', '+', $example),
            '{{CACHE_DAYS}}'    => (string)(int)Store::setting('cache_days'),
            '{{CSS_TTL_HOURS}}' => (string)$ttlH,
            '{{CONTACT}}'       => (string)Store::setting('contact_email'),
            '{{GITHUB}}'        => (string)Store::setting('github_url'),
            '{{VERSION}}'       => Store::version() . ' (' . gmdate('j M Y', strtotime(Store::updatedAt()) ?: time()) . ')',
        ];
        return strtr($src, $map);
    }
}
