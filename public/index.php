<?php
/**
 * TZL Fonts front controller. Every request that is not a real file lands here
 * (Apache FallbackResource / .htaccess, or `php -S ... public/index.php`).
 */
require __DIR__ . '/../src/App.php';

App\App::boot();

set_exception_handler(static function (Throwable $e): void {
    error_log('TZL Fonts: ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        try {
            App\View::error(500, 'alert-triangle', 'Something went wrong', 'The server hit an unexpected problem. Please try again in a moment.');
        } catch (Throwable $e2) {
            http_response_code(500);
            echo 'Internal server error';
        }
    }
});

ini_set('display_errors', '0');

// php -S: let the built-in server serve real static files itself
if (PHP_SAPI === 'cli-server') {
    $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $f = __DIR__ . $p;
    if ($p !== '/' && is_file($f) && substr($f, -4) !== '.php') { return false; }
}

App\FontService::seedIfEmpty();
App\Router::dispatch();
