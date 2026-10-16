<?php
/**
 * Self-test: lints every PHP file, then requests key pages through the real front controller
 * (in throw-away data) and reports status codes and PHP errors.
 *   docker compose exec <container> php bin/selftest.php            (or: php bin/selftest.php)
 *   php bin/selftest.php /extra-path /another-path                  (add your own paths)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$bad = 0;

echo "== 1. php -l on every file\n";
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
$n = 0;
foreach ($it as $f) {
    $p = (string)$f;
    if (substr($p, -4) !== '.php' || strpos($p, '/vendor/') !== false || strpos($p, '/data/') !== false) { continue; }
    $out = []; $rc = 0;
    exec('php -l ' . escapeshellarg($p) . ' 2>&1', $out, $rc);
    $n++;
    if ($rc !== 0) { $bad++; echo "  FAIL " . substr($p, strlen($root) + 1) . ': ' . implode(' ', $out) . "\n"; }
}
echo "  checked $n files\n";

echo "== 2. requests through public/index.php\n";
$paths = array_merge(['/', '/about', '/contact', '/privacy-policy', '/terms-and-conditions', '/cookie-policy', '/robots.txt', '/sitemap.xml', '/health', '/manifest.webmanifest', '/admin/login', '/does-not-exist'], array_slice($argv, 1));
$tmp = sys_get_temp_dir() . '/tzl-selftest-' . getmypid();
@mkdir($tmp, 0775, true);
$runner = $tmp . '/run.php';
file_put_contents($runner, '<?php
$_SERVER["REQUEST_METHOD"]="GET"; $_SERVER["REQUEST_URI"]=getenv("TZL_PATH"); $_SERVER["HTTP_HOST"]="localhost"; $_SERVER["REMOTE_ADDR"]="127.0.0.1";
register_shutdown_function(function(){ fwrite(STDERR, "\n__STATUS__".(http_response_code() ?: 200)); });
chdir(' . var_export($root . '/public', true) . ');
require ' . var_export($root . '/public/index.php', true) . ';
');
foreach ($paths as $p) {
    $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
    $proc = proc_open([PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=E_ALL', $runner], $desc, $pipes, $root, ['TZL_PATH' => $p, 'TZL_DATA_DIR' => $tmp . '/data', 'PATH' => getenv('PATH')]);
    $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
    proc_close($proc);
    preg_match('/__STATUS__(\d+)/', $err, $m);
    $status = (int)($m[1] ?? 0);
    $php = preg_match('/(Fatal error|Parse error|Warning|Notice|Deprecated|Uncaught)[^\n]*/i', $out . $err, $pm) ? $pm[0] : '';
    $expect = ($p === '/does-not-exist') ? 404 : 200;
    $ok = $status === $expect && $php === '';
    if (!$ok) { $bad++; }
    printf("  %-5s %-28s HTTP %s %s\n", $ok ? 'ok' : 'FAIL', $p, $status ?: '?', $php);
}
function rrm($d) { foreach (glob($d . '/{,.}[!.]*', GLOB_BRACE) ?: [] as $x) { is_dir($x) ? rrm($x) : @unlink($x); } @rmdir($d); }
rrm($tmp);
echo $bad ? "\n$bad problem(s). Send the FAIL lines to your assistant.\n" : "\nAll good.\n";
exit($bad ? 1 : 0);
