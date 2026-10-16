<?php
declare(strict_types=1);
namespace Tzl;

/** Tiny regex router. Services register routes in public/index.php; the kit registers /admin, /health, /robots.txt etc. */
final class Router
{
    /** @var array<int,array{0:string,1:string,2:callable}> */
    private static array $routes = [];

    public static function get(string $re, callable $cb): void { self::$routes[] = ['GET', $re, $cb]; }
    public static function post(string $re, callable $cb): void { self::$routes[] = ['POST', $re, $cb]; }

    public static function path(): string
    {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
        $path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
        $path = '/' . trim((string)preg_replace('#/+#', '/', $path), '/');
        return $path === '' ? '/' : $path;
    }

    public static function dispatch(): void
    {
        $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $path = self::path();
        if ($method === 'HEAD') { $method = 'GET'; }

        if ($method === 'OPTIONS') {
            Kit::cors();
            http_response_code(204);
            exit;
        }

        $isAdmin = ($path === '/admin' || strncmp($path, '/admin/', 7) === 0);
        $started = microtime(true);

        // maintenance mode: everything except admin, health and static assets shows a 503 page
        if (!$isAdmin && $path !== '/health' && Site::on('maintenance_on')) {
            http_response_code(503);
            header('Retry-After: 600');
            Layout::error(503, 'wrench', 'Back soon', (string)Site::get('maintenance_message'), false);
        }

        $known = false;
        foreach (self::$routes as [$m, $re, $cb]) {
            if (!preg_match($re, $path, $mt)) { continue; }
            $known = true;
            if ($m !== $method) { continue; }
            array_shift($mt);
            if ($isAdmin) { Admin::guard($path); }
            Stats::begin($path, $isAdmin);
            $cb(...$mt);
            Stats::end($started);
            return;
        }

        if ($known) {
            header('Allow: GET, POST');
            Layout::error(405, 'ban', 'Method not allowed', 'That action is not available here.');
        }
        Stats::notFound($path);
        Layout::error(404, 'search', 'Page not found', 'We could not find that page. It may have moved or never existed.');
    }
}
