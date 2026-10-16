<?php
/** First-run setup (safe to run any time): seeds bundled fonts and creates the admin account. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/Tzl/Kit.php';
require __DIR__ . '/../src/Store.php';
Tzl\Kit::boot(['root' => dirname(__DIR__), 'service' => 'fonts', 'name' => 'TechZeeLand Fonts', 'defaults' => App\Store::DEFAULTS]);
App\App::init();
App\FontService::seedIfEmpty();
$pw = Tzl\Auth::ensureAdmin();
if ($pw !== null) {
    $a = Tzl\Auth::admin();
    fwrite(STDOUT, "\n==============================================================\n"
        . " TZL Fonts first run\n"
        . "   Admin user    : " . ($a['username'] ?? 'admin') . "\n"
        . "   Admin password: " . $pw . "\n"
        . " You will be asked to change it after signing in at /admin\n"
        . "==============================================================\n\n");
}
