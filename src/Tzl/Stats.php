<?php
declare(strict_types=1);
namespace Tzl;

/**
 * Anonymous aggregate counters only: no IP addresses, no user agents stored, no cookies.
 * data/stats.json -> days[YYYY-MM-DD] = { pv, api, err, ev{}, p{}, r{}, d{}, nf{}, ms:[sum,count] }
 */
final class Stats
{
    private static array $ctx = [];
    private static array $events = [];
    private static bool $hooked = false;

    /** Called by the router before a handler runs. */
    public static function begin(string $path, bool $isAdmin): void
    {
        self::$ctx = ['path' => $path, 'admin' => $isAdmin, 'start' => microtime(true)];
        self::hook();
    }

    public static function end(float $started): void { /* recorded by the shutdown hook */ }

    public static function notFound(string $path): void
    {
        self::$ctx = ['path' => $path, 'admin' => false, 'start' => microtime(true)];
        self::hook();
    }

    /** Count a project event (e.g. 'qr', 'pdf', 'css', 'icon'). Recorded when the request ends. */
    public static function event(string $kind, ?string $key = null): void
    {
        $kind = substr((string)preg_replace('/[^a-z0-9_.:-]/i', '', $kind), 0, 40);
        if ($kind === '') { return; }
        self::$events[] = [$kind, $key === null ? null : substr($key, 0, 80)];
        self::hook();
    }

    private static function hook(): void
    {
        if (self::$hooked) { return; }
        self::$hooked = true;
        register_shutdown_function([self::class, 'flush']);
    }

    private static function device(): string
    {
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') { return 'unknown'; }
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|curl|wget|python-requests|headless/i', $ua)) { return 'bot'; }
        if (preg_match('/ipad|tablet/i', $ua)) { return 'tablet'; }
        if (preg_match('/mobi|android|iphone/i', $ua)) { return 'mobile'; }
        return 'desktop';
    }

    public static function flush(): void
    {
        try {
            if (!Site::on('stats_on') || (!self::$ctx && !self::$events)) { return; }
            $path = (string)(self::$ctx['path'] ?? '');
            $admin = !empty(self::$ctx['admin']);
            $status = http_response_code();
            $status = is_int($status) ? $status : 200;
            $ms = isset(self::$ctx['start']) ? (int)round((microtime(true) - self::$ctx['start']) * 1000) : 0;
            $method = strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET'));
            $ref = '';
            $rh = parse_url((string)($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST);
            if (is_string($rh) && $rh !== '' && $rh !== (string)($_SERVER['HTTP_HOST'] ?? '')) { $ref = strtolower(substr($rh, 0, 80)); }
            $dev = self::device();
            $events = self::$events;

            Json::update('stats.json', static function ($s) use ($path, $admin, $status, $ms, $method, $ref, $dev, $events) {
                $s = is_array($s) ? $s : [];
                $day = gmdate('Y-m-d');
                $d = $s['days'][$day] ?? [];
                if ($path !== '' && !$admin && $method === 'GET') {
                    if ($status === 404) {
                        if (count($d['nf'] ?? []) < 100 || isset($d['nf'][$path])) { $d['nf'][substr($path, 0, 120)] = (int)($d['nf'][substr($path, 0, 120)] ?? 0) + 1; }
                    } elseif ($status >= 500) {
                        $d['err'] = (int)($d['err'] ?? 0) + 1;
                    } elseif ($status < 400) {
                        $isApi = (bool)preg_match('#^/(api|css2?|i|f|download|health|embed)(/|$)#', $path);
                        if ($isApi) {
                            $d['api'] = (int)($d['api'] ?? 0) + 1;
                        } elseif ($dev !== 'bot') {
                            $d['pv'] = (int)($d['pv'] ?? 0) + 1;
                            if (count($d['p'] ?? []) < 150 || isset($d['p'][$path])) { $d['p'][substr($path, 0, 120)] = (int)($d['p'][substr($path, 0, 120)] ?? 0) + 1; }
                            if ($ref !== '') { $d['r'][$ref] = (int)($d['r'][$ref] ?? 0) + 1; }
                            $d['d'][$dev] = (int)($d['d'][$dev] ?? 0) + 1;
                        } else {
                            $d['bot'] = (int)($d['bot'] ?? 0) + 1;
                        }
                    }
                    $d['ms'] = [(int)($d['ms'][0] ?? 0) + $ms, (int)($d['ms'][1] ?? 0) + 1];
                }
                foreach ($events as [$k, $key]) {
                    $d['ev'][$k] = (int)($d['ev'][$k] ?? 0) + 1;
                    if ($key !== null && $key !== '') {
                        $d['evk'][$k][$key] = (int)($d['evk'][$k][$key] ?? 0) + 1;
                    }
                }
                $s['days'][$day] = $d;
                if (count($s['days']) > 100) { ksort($s['days']); $s['days'] = array_slice($s['days'], -90, null, true); }
                return $s;
            }, ['days' => []]);
        } catch (\Throwable $e) {
            // statistics must never break a request
        }
    }

    /* ---------- reading ---------- */

    public static function raw(): array
    {
        $s = Json::read('stats.json', ['days' => []]);
        return is_array($s) && isset($s['days']) ? $s['days'] : [];
    }

    /** Per-day series for the last $n days, oldest first: [date => row]. */
    public static function days(int $n = 14): array
    {
        $all = self::raw();
        $out = [];
        for ($i = $n - 1; $i >= 0; $i--) {
            $d = gmdate('Y-m-d', time() - $i * 86400);
            $out[$d] = $all[$d] ?? [];
        }
        return $out;
    }

    public static function sum(array $days, string $field): int
    {
        $t = 0;
        foreach ($days as $r) { $t += (int)($r[$field] ?? 0); }
        return $t;
    }

    public static function sumEvent(array $days, string $kind): int
    {
        $t = 0;
        foreach ($days as $r) { $t += (int)($r['ev'][$kind] ?? 0); }
        return $t;
    }

    /** Merge a map field (p, r, d, nf) over the days, descending. */
    public static function top(array $days, string $field, int $limit = 10): array
    {
        $tot = [];
        foreach ($days as $r) { foreach ((array)($r[$field] ?? []) as $k => $n) { $tot[$k] = ($tot[$k] ?? 0) + (int)$n; } }
        arsort($tot);
        return array_slice($tot, 0, $limit, true);
    }

    /** Merge a per-event key map: evk[kind][key] over days. */
    public static function topEventKeys(array $days, string $kind, int $limit = 10): array
    {
        $tot = [];
        foreach ($days as $r) { foreach ((array)($r['evk'][$kind] ?? []) as $k => $n) { $tot[$k] = ($tot[$k] ?? 0) + (int)$n; } }
        arsort($tot);
        return array_slice($tot, 0, $limit, true);
    }

    public static function avgMs(array $days): int
    {
        $s = 0; $c = 0;
        foreach ($days as $r) { $s += (int)($r['ms'][0] ?? 0); $c += (int)($r['ms'][1] ?? 0); }
        return $c > 0 ? (int)round($s / $c) : 0;
    }

    public static function clear(): void { Json::write('stats.json', ['days' => []]); }
}
