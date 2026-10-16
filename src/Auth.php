<?php
namespace App;

/** Admin auth now comes from the TZL Kit; views still call Auth::field(). */
final class Auth
{
    public static function field(): string { return \Tzl\Auth::field(); }
    public static function verifyCsrf(): void { \Tzl\Auth::verifyCsrf(); }
    public static function start(): void { \Tzl\Auth::start(); }
    public static function mustChange(): bool { return \Tzl\Auth::mustChange(); }
    public static function admin(): ?array { return \Tzl\Auth::admin(); }
}
