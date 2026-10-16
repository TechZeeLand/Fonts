<?php
declare(strict_types=1);
namespace Tzl;

/**
 * Content pages (About, Contact, Privacy, Terms, Cookies). Defaults are Markdown with {tokens};
 * a service can supply its own via Kit::boot(['pages' => [...]]); admins can edit any of them (data/pages.json).
 */
final class Pages
{
    public const SLUGS = [
        'about'                 => 'About',
        'contact'               => 'Contact',
        'privacy-policy'        => 'Privacy Policy',
        'terms-and-conditions'  => 'Terms & Conditions',
        'cookie-policy'         => 'Cookie Policy',
    ];

    public static function tokens(): array
    {
        return [
            '{site}'    => (string)Site::get('site_name'),
            '{email}'   => (string)Site::get('contact_email'),
            '{url}'     => Kit::baseUrl(),
            '{github}'  => (string)Site::get('github_url'),
            '{owner}'   => (string)Site::get('owner_name'),
            '{license}' => (string)Site::get('license_name'),
            '{year}'    => gmdate('Y'),
        ];
    }

    /** Default title, description and Markdown for a slug (before admin edits). */
    public static function defaults(string $slug): ?array
    {
        $own = Kit::cfg('pages', []);
        if (isset($own[$slug])) { return $own[$slug]; }
        $extra = (string)(Kit::cfg('page_extra', [])[$slug] ?? '');
        switch ($slug) {
            case 'about':
                return ['title' => 'About', 'desc' => 'About {site}.', 'md' =>
                    "## What it is\n\n{site} is a free, open-source tool by {owner}. " . (string)Kit::cfg('tagline') . "\n\n"
                    . "## Open source\n\nReleased under the {license} license. Source code, issues and contributions are on [GitHub]({github}).\n\n"
                    . "## Made in Bangladesh\n\nBuilt and maintained by {owner}, based in Bangladesh.\n" . $extra];
            case 'contact':
                return ['title' => 'Contact', 'desc' => 'Contact the {site} team.', 'md' =>
                    "Questions, bug reports or ideas? Email [{email}](mailto:{email}) or open an issue on [GitHub]({github}/issues).\n\n"
                    . "We read everything and reply as soon as we can." . $extra];
            case 'privacy-policy':
                return ['title' => 'Privacy Policy', 'desc' => 'How {site} handles your data.', 'md' =>
                    "## Summary\n\n{site} is built to collect as little as possible. There are no accounts for visitors, no advertising trackers and no third-party analytics unless the site owner adds them.\n\n"
                    . "## What we process\n\n- **Anonymous statistics.** Daily totals such as page views and feature usage. No IP addresses, no user agents and no identifiers are stored with them.\n"
                    . "- **Server logs.** Your hosting provider's web server may keep standard access logs (IP address, time, requested URL) for security and troubleshooting.\n"
                    . "- **Your own browser.** Your theme choice and, where a feature offers it, saved settings stay in your browser's local storage and never leave your device.\n" . $extra . "\n\n"
                    . "## Third-party services\n\nPages load the interface font from the TechZeeLand Fonts service and icons from the TechZeeLand Icons service. Those requests reveal your IP address to those services, like any web request.\n\n"
                    . "## Your choices\n\nYou can clear local storage at any time. For any privacy question write to [{email}](mailto:{email}).\n\n"
                    . "_Last updated: {year}._"];
            case 'terms-and-conditions':
                return ['title' => 'Terms & Conditions', 'desc' => 'Terms for using {site}.', 'md' =>
                    "## Using {site}\n\nBy using this website you agree to these terms. If you do not agree, please do not use it.\n\n"
                    . "## The service\n\n{site} is provided free of charge and \"as is\", without warranty of any kind. We may change, suspend or remove features at any time.\n\n"
                    . "## Acceptable use\n\nDo not abuse the service: no automated flooding, no attempts to break or bypass security, and no use that is unlawful. We may rate-limit or block abusive traffic.\n\n"
                    . "## Your content\n\nYou keep all rights to what you create. You are responsible for the content you generate and for having the right to use it.\n\n"
                    . "## Open source\n\nThe software is licensed under {license}. See the [repository]({github}) for the license text.\n\n"
                    . "## Liability\n\nTo the extent permitted by law, {owner} is not liable for any loss arising from use of the service.\n\n"
                    . "_Last updated: {year}._" . $extra];
            case 'cookie-policy':
                return ['title' => 'Cookie Policy', 'desc' => 'Cookies used by {site}.', 'md' =>
                    "## Cookies\n\nVisitors do not receive cookies from {site}. The only cookie ever set is an administrator session cookie on the /admin area, which is strictly necessary and never set for visitors.\n\n"
                    . "## Local storage\n\nYour light/dark theme choice is stored in your browser's local storage so it is remembered. It is not sent to any server.\n\n"
                    . "## Third parties\n\nIf the site owner enables optional services (for example ads or analytics), those providers may set their own cookies. They will be described here.\n\n"
                    . "_Last updated: {year}._" . $extra];
        }
        return null;
    }

    /** @return array{title:string,desc:string,md:string,edited:bool}|null */
    public static function get(string $slug): ?array
    {
        $d = self::defaults($slug);
        if ($d === null) { return null; }
        $ov = Json::read('pages.json', []);
        $o = is_array($ov) ? ($ov[$slug] ?? null) : null;
        $edited = is_array($o) && isset($o['md']) && trim((string)$o['md']) !== '';
        return [
            'title'  => $edited && !empty($o['title']) ? (string)$o['title'] : (string)$d['title'],
            'desc'   => (string)$d['desc'],
            'md'     => $edited ? (string)$o['md'] : (string)$d['md'],
            'edited' => $edited,
        ];
    }

    public static function save(string $slug, string $title, string $md): void
    {
        Json::update('pages.json', static function ($p) use ($slug, $title, $md) {
            $p = is_array($p) ? $p : [];
            $p[$slug] = ['title' => $title, 'md' => $md, 'updated' => Kit::now()];
            return $p;
        }, []);
    }

    public static function reset(string $slug): void
    {
        Json::update('pages.json', static function ($p) use ($slug) {
            $p = is_array($p) ? $p : [];
            unset($p[$slug]);
            return $p;
        }, []);
    }

    /** Render a content page to the browser. */
    public static function show(string $slug): void
    {
        $p = self::get($slug);
        if ($p === null) { Layout::error(404, 'search', 'Page not found', 'We could not find that page.'); }
        $tok = self::tokens();
        $html = Markdown::render(strtr($p['md'], $tok));
        Layout::page($p['title'], strtr($p['desc'], $tok), $p['title'], '', $html);
    }
}
