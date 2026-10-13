<?php
namespace App;

/**
 * Admin authentication: single admin account, PHP session (cookie scoped to /admin only),
 * CSRF tokens, login throttling. The public site never sets a cookie.
 */
final class Auth
{
    private const MAX_FAILS = 6;
    private const WINDOW = 900; // 15 minutes

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) { return; }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '50');
        ini_set('session.gc_maxlifetime', '28800');
        session_save_path(Store::path('sessions'));
        session_name('tzl_admin');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/admin',
            'secure'   => App::isHttps(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }

    public static function admin(): ?array
    {
        $a = Store::read('admin.json', null);
        return (is_array($a) && !empty($a['hash'])) ? $a : null;
    }

    /** Create the admin account on first run. Returns the generated password (or null if one existed/was provided). */
    public static function ensureAdmin(): ?string
    {
        if (self::admin()) { return null; }
        $user = App::env('TZL_ADMIN_USER', 'admin');
        $pass = App::env('TZL_ADMIN_PASSWORD');
        $generated = null;
        if ($pass === '') {
            $generated = $pass = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 16);
        }
        Store::write('admin.json', [
            'username'    => $user,
            'hash'        => password_hash($pass, PASSWORD_DEFAULT),
            'must_change' => $generated !== null,
            'created'     => App::now(),
        ]);
        @chmod(Store::path('admin.json'), 0660);
        if ($generated !== null) {
            $note = "TZL Fonts admin login\nUser: " . $user . "\nPassword: " . $generated . "\n\nYou must change this password after signing in. This file is deleted when you do.\n";
            @file_put_contents(Store::path('INITIAL_ADMIN_PASSWORD.txt'), $note);
            @chmod(Store::path('INITIAL_ADMIN_PASSWORD.txt'), 0600);
            error_log('TZL Fonts: first run. Admin user "' . $user . '", generated password: ' . $generated . ' (also saved in the data folder)');
        }
        Store::log('Admin account created');
        return $generated;
    }

    public static function user(): ?string
    {
        self::start();
        return isset($_SESSION['u']) ? (string)$_SESSION['u'] : null;
    }

    public static function check(): bool { return self::user() !== null; }

    public static function csrf(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(20)); }
        return (string)$_SESSION['csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . App::e(self::csrf()) . '">';
    }

    public static function verifyCsrf(): void
    {
        $t = (string)($_POST['_csrf'] ?? '');
        if ($t === '' || !hash_equals(self::csrf(), $t)) {
            View::error(403, 'lock', 'Session expired', 'The form could not be verified. Go back, reload the page and try again.');
        }
    }

    /* ---------- login ---------- */

    private static function throttleKey(): string { return sha1(App::clientIp()); }

    public static function locked(): bool
    {
        $t = Store::read('throttle.json', []);
        $k = self::throttleKey();
        $recent = array_filter((array)($t[$k] ?? []), static fn($ts) => $ts > time() - self::WINDOW);
        return count($recent) >= self::MAX_FAILS;
    }

    private static function fail(): void
    {
        $k = self::throttleKey();
        Store::update('throttle.json', static function ($t) use ($k) {
            $t = is_array($t) ? $t : [];
            foreach ($t as $key => $list) {
                $t[$key] = array_values(array_filter((array)$list, static fn($ts) => $ts > time() - self::WINDOW));
                if (!$t[$key]) { unset($t[$key]); }
            }
            $t[$k][] = time();
            return $t;
        }, []);
    }

    public static function attempt(string $user, string $pass): bool
    {
        $a = self::admin();
        if (!$a || self::locked()) { return false; }
        $okUser = hash_equals((string)$a['username'], $user);
        $okPass = password_verify($pass, (string)$a['hash']);
        if ($okUser && $okPass) {
            self::start();
            session_regenerate_id(true);
            $_SESSION['u'] = (string)$a['username'];
            $_SESSION['csrf'] = bin2hex(random_bytes(20));
            if (password_needs_rehash((string)$a['hash'], PASSWORD_DEFAULT)) {
                $a['hash'] = password_hash($pass, PASSWORD_DEFAULT);
                Store::write('admin.json', $a);
            }
            Store::log('Signed in');
            return true;
        }
        self::fail();
        Store::log('Failed sign-in attempt from ' . substr(sha1(App::clientIp()), 0, 8));
        return false;
    }

    public static function logout(): void
    {
        self::start();
        Store::log('Signed out');
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Strict']);
        }
        session_destroy();
    }

    public static function mustChange(): bool
    {
        $a = self::admin();
        return $a && !empty($a['must_change']);
    }

    public static function changeCredentials(string $newUser, string $newPass): void
    {
        $a = self::admin() ?? [];
        $a['username'] = $newUser;
        if ($newPass !== '') { $a['hash'] = password_hash($newPass, PASSWORD_DEFAULT); }
        $a['must_change'] = false;
        $a['updated'] = App::now();
        Store::write('admin.json', $a);
        $_SESSION['u'] = $newUser;
        @unlink(Store::path('INITIAL_ADMIN_PASSWORD.txt'));
        Store::log('Admin credentials changed');
    }
}
