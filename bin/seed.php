<?php
/** First-run setup (also safe to run any time): seeds bundled fonts and creates the admin account. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/../src/App.php';
App\App::boot();
App\FontService::seedIfEmpty();
$pw = App\Auth::ensureAdmin();
if ($pw !== null) {
    $a = App\Auth::admin();
    fwrite(STDOUT, "\n==============================================================\n"
        . " TZL Fonts first run\n"
        . "   Admin user    : " . ($a['username'] ?? 'admin') . "\n"
        . "   Admin password: " . $pw . "\n"
        . " You will be asked to change it after signing in at /admin\n"
        . "==============================================================\n\n");
}
