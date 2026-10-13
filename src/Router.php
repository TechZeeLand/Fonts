<?php
namespace App;

final class Router
{
    public static function dispatch(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
        $path = '/' . trim((string)preg_replace('#/+#', '/', $path), '/');
        if ($path === '') { $path = '/'; }
        $head = ($method === 'HEAD');
        if ($head) { $method = 'GET'; }

        // Public machine endpoints answer CORS pre-flights
        if ($method === 'OPTIONS' && preg_match('#^/(css2?|f/|api/|download/)#', $path)) { Web::preflight(); }

        $routes = [
            ['GET', '#^/$#', [Web::class, 'home']],
            ['GET', '#^/font/([a-z0-9-]+)$#', [Web::class, 'font']],
            ['GET', '#^/(docs|about|contact|contribute)$#', [Web::class, 'page']],
            ['GET', '#^/(privacy-policy)$#', [Web::class, 'page']],
            ['GET', '#^/(terms-and-conditions)$#', [Web::class, 'page']],
            ['GET', '#^/(cookie-policy)$#', [Web::class, 'page']],
            ['GET', '#^/(license-policy)$#', [Web::class, 'page']],
            ['GET', '#^/docs\.md$#', [Web::class, 'docsMarkdown']],
            ['GET', '#^/css2?$#', [Web::class, 'css']],
            ['GET', '#^/f/([a-z0-9-]+)/([^/]+)$#', [Web::class, 'file']],
            ['GET', '#^/api/fonts\.json$#', [Web::class, 'apiFonts']],
            ['GET', '#^/api/fonts/([a-z0-9-]+)\.json$#', [Web::class, 'apiFont']],
            ['GET', '#^/api/manifest\.json$#', [Web::class, 'apiManifest']],
            ['GET', '#^/download/([a-z0-9-]+)\.zip$#', [Web::class, 'zip']],
            ['GET', '#^/download/tools/([a-z0-9.-]+)$#', [Web::class, 'tool']],
            ['GET', '#^/robots\.txt$#', [Web::class, 'robots']],
            ['GET', '#^/sitemap\.xml$#', [Web::class, 'sitemap']],
            ['GET', '#^/health$#', [Web::class, 'health']],
            ['GET', '#^/error/(403|500)$#', [Web::class, 'errorPage']],

            ['GET', '#^/admin/login$#', [Admin::class, 'loginForm']],
            ['POST', '#^/admin/login$#', [Admin::class, 'loginPost']],
            ['POST', '#^/admin/logout$#', [Admin::class, 'logout']],
            ['GET', '#^/admin$#', [Admin::class, 'dashboard']],
            ['GET', '#^/admin/fonts$#', [Admin::class, 'fonts']],
            ['GET', '#^/admin/fonts/new$#', [Admin::class, 'fontNew']],
            ['POST', '#^/admin/fonts/new$#', [Admin::class, 'fontCreate']],
            ['GET', '#^/admin/fonts/([a-z0-9-]+)$#', [Admin::class, 'fontEdit']],
            ['POST', '#^/admin/fonts/([a-z0-9-]+)$#', [Admin::class, 'fontUpdate']],
            ['GET', '#^/admin/settings$#', [Admin::class, 'settings']],
            ['POST', '#^/admin/settings$#', [Admin::class, 'settingsSave']],
            ['GET', '#^/admin/account$#', [Admin::class, 'account']],
            ['POST', '#^/admin/account$#', [Admin::class, 'accountSave']],
            ['GET', '#^/admin/activity$#', [Admin::class, 'activity']],
            ['GET', '#^/admin/backup\.zip$#', [Admin::class, 'backup']],
            ['POST', '#^/admin/tools$#', [Admin::class, 'tools']],
        ];

        foreach ($routes as [$m, $re, $cb]) {
            if ($m !== $method || !preg_match($re, $path, $mt)) { continue; }
            array_shift($mt);
            if (strncmp($path, '/admin', 6) === 0) { Admin::guard($path); }
            $cb(...$mt);
            return;
        }

        // Known path but wrong method
        if (strncmp($path, '/admin', 6) === 0) { header('Allow: GET, POST'); View::error(405, 'ban', 'Method not allowed', 'That action is not available here.'); }
        Web::notFound();
    }
}
