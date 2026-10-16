<?php
namespace App;

use Tzl\Kit;

/**
 * Compatibility facade: the font code was written against App::*; everything now lives in the TZL Kit.
 */
final class App
{
    public const VERSION = '2.0.0';
    public static string $root = '';
    public static string $data = '';

    public static function init(): void { self::$root = Kit::$root; self::$data = Kit::$data; }

    public static function env(string $key, string $default = ''): string { return Kit::env($key, $default); }
    public static function iconCss(): string { return Kit::iconCssUrl(); }
    public static function trustProxy(): bool { return Kit::trustProxy(); }
    public static function isHttps(): bool { return Kit::isHttps(); }
    public static function clientIp(): string { return Kit::clientIp(); }
    public static function origin(): string { return Kit::origin(); }
    public static function baseUrl(): string { return Kit::baseUrl(); }
    public static function url(string $path = '/'): string { return Kit::url($path); }
    public static function e($s): string { return Kit::e($s); }
    public static function icon(string $name, string $extra = ''): string { return Kit::icon($name, $extra); }
    public static function slugify(string $s): string { return Kit::slugify($s); }
    public static function now(): string { return Kit::now(); }
    public static function bytes(int $b): string { return Kit::bytes($b); }
    public static function ago(string $iso): string { return Kit::ago($iso); }
    public static function rrmdir(string $dir): void { Kit::rrmdir($dir); }
    public static function redirect(string $to, int $code = 302): void { Kit::redirect($to, $code); }
    public static function json($data, int $status = 200, bool $cors = true, array $extra = []): void { Kit::json($data, $status, $cors, $extra); }
    public static function corsHeaders(): void { Kit::cors(); }
    public static function etag(string $tag): void { Kit::etag($tag); }
}
