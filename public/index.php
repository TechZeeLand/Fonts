<?php
/**
 * TZL Fonts front controller. Every request that is not a real file lands here
 * (Apache FallbackResource / .htaccess, or `php -S localhost:8080 -t public public/index.php`).
 */
require __DIR__ . '/../src/Tzl/Kit.php';
require __DIR__ . '/../src/Store.php';

use Tzl\Kit;

Kit::boot([
    'root'        => dirname(__DIR__),
    'service'     => 'fonts',
    'version'     => '2.0.0',
    'name'        => 'TechZeeLand Fonts',
    'short'       => 'TZL Fonts',
    'tagline'     => 'Open-source fonts, served fast, cached on your server.',
    'description' => 'Free, open-source fonts for commercial use that you can pull with one line of CSS and cache on your own server.',
    'github'      => 'https://github.com/TechZeeLand/TZL-Fonts',
    'js'          => ['assets/js/app.js'],
    'admin_js'    => ['assets/js/admin.js'],
    'nav'         => [['Fonts', '/#fonts', 'font'], ['Docs', '/docs', 'book-open'], ['About', '/about', 'info']],
    'footer_extra' => [['Browse fonts', '/#fonts'], ['Documentation', '/docs'], ['Contribute a font', '/contribute'], ['Font License Policy', '/license-policy']],
    'sitemap'     => ['/', '/docs', '/contribute', '/license-policy'],
    'sitemap_fn'  => static function (): array {
        $u = [];
        foreach (App\Store::fonts(true) as $f) { $u[] = '/font/' . $f['slug']; }
        return $u;
    },
    'robots_disallow' => ['/f/', '/api/', '/download/'],
    'defaults'    => App\Store::DEFAULTS,
    'admin_menu'  => [
        'fonts'    => ['/admin/fonts', 'Fonts', 'font'],
        'new'      => ['/admin/fonts/new', 'Add font', 'font-plus'],
        'delivery' => ['/admin/delivery', 'Delivery & caching', 'zap'],
    ],
    'dashboard'   => [App\Admin::class, 'dashboardWidgets'],
    'checks'      => [static function (): array {
        $caps = App\FontKit::caps();
        return [
            ['Automatic WOFF2 conversion', $caps['fonttools'] && $caps['brotli'], 'Install python3 + fonttools + brotli (included in the Docker image). Without it, uploaded TTF/OTF files are served as they are.'],
            ['ZIP downloads (php-zip)', class_exists('ZipArchive'), 'Enable the PHP zip extension to offer family downloads and backups.'],
        ];
    }],
    'page_extra'  => [
        'privacy-policy' => "\n- **Anonymous counters.** How many CSS, font file, ZIP and API requests happened per day and per font. They contain no personal data.\n- **Helper scripts.** If you cache fonts with our PHP/Node/Nginx helpers, *your server* makes an occasional request to ours (about once per refresh interval), so your server's address may appear in logs instead of your visitors'.",
        'about' => "\n## Only truly free fonts\n\nEvery font must carry an open license that allows free commercial use and redistribution, such as the SIL Open Font License. Read the [Font License Policy](/license-policy) and, if you have a font to share, [contribute it](/contribute). All credit belongs to the original designers, whose names and licenses are shown on each font page.\n\nThe interface is set in [Alata](/font/alata), the first font in the collection.",
    ],
]);
App\App::init();

if (PHP_SAPI === 'cli-server') {
    $p = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    if ($p !== '/' && is_file(__DIR__ . $p) && substr((string)$p, -4) !== '.php') { return false; }
}

App\FontService::seedIfEmpty();
Tzl\Core::run(static function (): void {
    App\Admin::register();
    App\Web::register();
});
