<?php
namespace App;

use Tzl\Admin as KitAdmin;
use Tzl\Layout;

/** Facade over the TZL Kit layout so the existing views keep working unchanged. */
final class View
{
    private static array $adminCtx = [];

    public static function asset(string $path): string { return \Tzl\Kit::asset($path); }
    public static function site(): string { return (string)\Tzl\Site::get('site_name'); }

    public static function render(string $name, array $vars = []): void
    {
        extract($vars, EXTR_SKIP);
        require App::$root . '/views/' . $name . '.php';
    }

    public static function flash(string $type, string $msg): void { KitAdmin::flash($type, $msg); }

    public static function head(string $title, string $desc = '', array $opts = []): void { Layout::head($title, $desc, $opts); }

    public static function foot(array $opts = []): void { Layout::foot($opts); }

    public static function page(string $title, string $desc, string $h1, string $lede, string $html, array $opts = []): void
    {
        Layout::page($title, $desc, $h1, $lede, $html, $opts);
    }

    public static function error(int $code, string $icon, string $title, string $desc): void { Layout::error($code, $icon, $title, $desc); }

    /** Admin views call adminOpen(), echo their markup, then adminClose(): buffer it and hand it to the kit shell. */
    public static function adminOpen(string $title, string $active): void
    {
        self::$adminCtx = [$title, $active];
        ob_start();
    }

    public static function adminClose(): void
    {
        $body = (string)ob_get_clean();
        [$title, $active] = self::$adminCtx;
        KitAdmin::page($title, $active, $body, '', false);
    }
}
