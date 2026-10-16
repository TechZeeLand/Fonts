<?php
declare(strict_types=1);
namespace Tzl;

/**
 * Site settings: everything an admin can edit (branding, links, footer, SEO, security, maintenance...).
 * Stored in data/site.json. Defaults come from this class, then from the service's Kit::boot() 'defaults'.
 */
final class Site
{
    public const SOCIAL_DEFAULT = [
        ['icon' => 'youtube',   'label' => 'YouTube',   'url' => 'https://www.youtube.com/@TechZeeLand'],
        ['icon' => 'facebook',  'label' => 'Facebook',  'url' => 'https://www.facebook.com/TechZeeLand'],
        ['icon' => 'instagram', 'label' => 'Instagram', 'url' => 'https://www.instagram.com/TechZeeLand'],
        ['icon' => 'tiktok',    'label' => 'TikTok',    'url' => 'https://www.tiktok.com/@TechZeeLand'],
        ['icon' => 'github',    'label' => 'GitHub',    'url' => 'https://github.com/TechZeeLand'],
        ['icon' => 'pathaopay', 'label' => 'Support',   'url' => 'https://pathaopay.me/@azlanaziz'],
    ];

    public const BASE_DEFAULTS = [
        // identity
        'site_name'       => '',
        'short_name'      => '',
        'tagline'         => '',
        'description'     => '',
        'base_url'        => '',
        'root_domain'     => '',
        'svc_fonts'       => '',
        'svc_icons'       => '',
        'svc_qr'          => '',
        'svc_cover'       => '',
        'contact_email'   => 'azlanaziz@rayaz.org',
        'github_url'      => '',
        'github_label'    => 'Source on GitHub',
        'show_github'     => true,
        'owner_name'      => 'TechZeeLand',
        'owner_url'       => 'https://github.com/TechZeeLand',
        'license_name'    => 'AGPL-3.0',
        'license_url'     => 'https://www.gnu.org/licenses/agpl-3.0.html',
        // look
        'accent'          => '#6cc6af',
        'default_theme'   => 'auto',       // auto | light | dark
        'logo_path'       => '',           // uploaded logo (served from /brand/logo)
        'og_image'        => '',           // absolute URL or /path
        'custom_css'      => '',
        // navigation (null = use the service defaults)
        'header_links'    => null,
        'footer_columns'  => null,
        'social_links'    => null,
        'show_services'   => true,
        'services_title'  => 'More from TechZeeLand',
        'footer_text'     => '',
        // announcement bar
        'announcement_on'   => false,
        'announcement_text' => '',
        'announcement_link' => '',
        // seo / robots / ads
        'robots_index'    => true,
        'robots_extra'    => '',
        'ads_txt'         => '',
        'custom_head_html'   => '',
        'custom_footer_html' => '',
        // security
        'cors_origin'        => '*',
        'csp_script_hosts'   => '',
        'csp_connect_hosts'  => '',
        'csp_img_hosts'      => '',
        'csp_allow_inline'   => false,
        // operations
        'stats_on'            => true,
        'maintenance_on'      => false,
        'maintenance_message' => 'We are doing some maintenance and will be back shortly.',
        'cookie_banner_on'    => false,
        'cookie_banner_text'  => 'This site uses only the cookies it needs to work. Read our cookie policy for details.',
    ];

    private static ?array $cache = null;

    public static function defaults(): array
    {
        $d = self::BASE_DEFAULTS;
        $d['site_name']   = (string)Kit::cfg('name', 'TechZeeLand');
        $d['short_name']  = (string)Kit::cfg('short', $d['site_name']);
        $d['tagline']     = (string)Kit::cfg('tagline', '');
        $d['description'] = (string)Kit::cfg('description', '');
        $d['github_url']  = (string)Kit::cfg('github', '');
        foreach ((array)Kit::cfg('defaults', []) as $k => $v) { $d[$k] = $v; }
        return $d;
    }

    public static function all(): array
    {
        if (self::$cache === null) {
            $s = Json::read('site.json', null);
            if (!is_array($s)) { $s = Json::read('settings.json', []); } // settings from TZL Fonts 1.x
            self::$cache = array_merge(self::defaults(), is_array($s) ? $s : []);
        }
        return self::$cache;
    }

    /** Raw value. Safe to call before Kit::boot() finished (returns '' then). */
    public static function get(string $key)
    {
        if (Kit::$root === '') { return ''; }
        $a = self::all();
        return $a[$key] ?? '';
    }

    public static function on(string $key): bool { return !empty(self::get($key)); }

    public static function save(array $new): void
    {
        $old = Json::read('site.json', []);
        if (is_array($old) && $old) {
            $dir = 'site-history';
            Json::write($dir . '/site-' . gmdate('Ymd-His') . '.json', $old);
            $files = glob(Json::path($dir . '/site-*.json')) ?: [];
            sort($files);
            while (count($files) > 15) { @unlink((string)array_shift($files)); }
        }
        $merged = array_merge(is_array($old) ? $old : [], $new);
        Json::write('site.json', $merged);
        self::$cache = null;
    }

    /** @return string[] file names of saved earlier versions, newest first */
    public static function history(): array
    {
        $files = glob(Json::path('site-history/site-*.json')) ?: [];
        rsort($files);
        return array_map('basename', array_slice($files, 0, 15));
    }

    public static function restore(string $file): bool
    {
        if (!preg_match('/^site-\d{8}-\d{6}\.json$/', $file)) { return false; }
        $d = Json::read('site-history/' . $file, null);
        if (!is_array($d)) { return false; }
        self::save($d);
        // save() keeps merged keys; make restore exact
        Json::write('site.json', $d);
        self::$cache = null;
        return true;
    }

    /* ---------- sanitising helpers (used by the admin forms) ---------- */

    /** Only relative paths, #anchors, http(s), mailto and tel links are allowed. Returns '' if unsafe. */
    public static function cleanUrl(string $u): string
    {
        $u = trim(preg_replace('/[\x00-\x1F\x7F]/', '', $u) ?? '');
        if ($u === '') { return ''; }
        if (preg_match('#^(/(?!/)|\#|\?)#', $u) || preg_match('#^(https?://|mailto:|tel:)[^\s<>"\']+$#i', $u)) {
            return mb_substr($u, 0, 300);
        }
        return '';
    }

    public static function cleanIcon(string $i): string
    {
        return mb_substr((string)preg_replace('/[^a-z0-9-]/', '', strtolower($i)), 0, 40);
    }

    public static function cleanColor(string $c, string $fallback = '#6cc6af'): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? strtolower($c) : $fallback;
    }

    /** Clean a posted list of links: [ ['label'=>..,'url'=>..,'icon'=>..,'new'=>..], ... ] */
    public static function cleanLinks($rows, int $max = 20): array
    {
        $out = [];
        foreach ((array)$rows as $r) {
            if (!is_array($r)) { continue; }
            $label = trim((string)($r['label'] ?? ''));
            $url = self::cleanUrl((string)($r['url'] ?? ''));
            if ($label === '' || $url === '') { continue; }
            $out[] = [
                'label' => mb_substr($label, 0, 40),
                'url'   => $url,
                'icon'  => self::cleanIcon((string)($r['icon'] ?? '')),
                'new'   => !empty($r['new']),
            ];
            if (count($out) >= $max) { break; }
        }
        return $out;
    }

    /* ---------- effective navigation ---------- */

    public static function headerLinks(): array
    {
        $v = self::get('header_links');
        if (is_array($v)) { return $v; }
        $out = [];
        foreach ((array)Kit::cfg('nav', []) as $n) {
            $out[] = ['label' => $n[0], 'url' => $n[1], 'icon' => $n[2] ?? '', 'new' => false];
        }
        return $out;
    }

    public static function footerColumns(): array
    {
        $v = self::get('footer_columns');
        if (is_array($v)) { return $v; }
        $pages = [['label' => 'Home', 'url' => '/', 'icon' => '', 'new' => false]];
        foreach ((array)Kit::cfg('footer_extra', []) as $n) {
            $pages[] = ['label' => $n[0], 'url' => $n[1], 'icon' => '', 'new' => false];
        }
        foreach ([['About', '/about'], ['Contact', '/contact']] as $n) {
            $pages[] = ['label' => $n[0], 'url' => $n[1], 'icon' => '', 'new' => false];
        }
        $legal = [];
        foreach ([['Privacy Policy', '/privacy-policy'], ['Terms & Conditions', '/terms-and-conditions'], ['Cookie Policy', '/cookie-policy']] as $n) {
            $legal[] = ['label' => $n[0], 'url' => $n[1], 'icon' => '', 'new' => false];
        }
        return [['title' => 'Pages', 'links' => $pages], ['title' => 'Legal', 'links' => $legal]];
    }

    public static function socialLinks(): array
    {
        $v = self::get('social_links');
        if (is_array($v)) { return $v; }
        $out = [];
        foreach (self::SOCIAL_DEFAULT as $s) { $out[] = ['label' => $s['label'], 'url' => $s['url'], 'icon' => $s['icon'], 'new' => true]; }
        return $out;
    }

    /** Footer sentence with {year}, {site}, {owner}, {license} tokens. */
    public static function footerText(): string
    {
        $t = trim((string)self::get('footer_text'));
        if ($t === '') {
            $t = '&copy; {year} {owner}. {site} is free &amp; open source under the {license} license.';
            return strtr($t, [
                '{year}' => gmdate('Y'), '{owner}' => Kit::e(self::get('owner_name')), '{site}' => Kit::e(self::get('site_name')),
                '{license}' => '<a href="' . Kit::e(self::get('license_url')) . '" target="_blank" rel="noopener">' . Kit::e(self::get('license_name')) . '</a>',
            ]);
        }
        return strtr(Kit::e($t), [
            '{year}' => gmdate('Y'), '{owner}' => Kit::e(self::get('owner_name')), '{site}' => Kit::e(self::get('site_name')),
            '{license}' => Kit::e(self::get('license_name')),
        ]);
    }
}
