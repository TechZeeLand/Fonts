<?php
// Tiny global helpers used by the view templates.
if (!function_exists('h')) {
    function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
if (!function_exists('ico')) {
    /** Icon markup, e.g. ico('home') => <i class="zi zi-home"></i> */
    function ico(string $name, string $extra = ''): string { return \App\App::icon($name, $extra); }
}
