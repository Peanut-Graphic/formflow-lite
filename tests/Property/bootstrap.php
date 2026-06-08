<?php
/**
 * Standalone bootstrap for the Property test suite (net 6).
 *
 * The functions under property test are PURE PHP — they rely only on
 * filter_var / preg_* / native string ops. WordPress is NOT booted. We define
 * the minimal constants the production class files guard on (ABSPATH) plus
 * thin passthrough stubs for the few WP helpers referenced inside *other*
 * (non-property) branches, so the class file can be loaded without a WP env.
 *
 * @package FormFlow_Lite\Tests\Property
 */

// Production class files `exit` unless ABSPATH is defined.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fffl-property/');
}

// Minimal passthrough stubs. These are only reached by code paths OUTSIDE the
// pure functions under property test; the pure branches never touch them. They
// exist purely so the class file loads in a WordPress-free process.
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('sanitize_email')) {
    // Faithful-enough stub: strip characters disallowed in an email address.
    function sanitize_email($email) {
        return (string) preg_replace('/[^a-zA-Z0-9.@_+\-]/', '', (string) $email);
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return trim((string) preg_replace('/[\r\n\t ]+/', ' ', (string) $str));
    }
}

// Load the unit under test directly — no plugin boot, no WP.
require_once dirname(__DIR__, 2) . '/includes/class-security.php';
