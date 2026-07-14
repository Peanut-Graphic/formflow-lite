<?php
/**
 * Bootstrap for the pure Dominion PTR connector test suite.
 *
 * Deliberately WordPress-free: no DB, no hook system, no plugin boot. It loads
 * the connector interface + DTOs and the lightweight WP function shims
 * (__(), esc_html(), wp_json_encode(), …) so connector logic can be unit-tested
 * in isolation. Connector/seeder classes are required guardedly so this
 * bootstrap works both before and after those files exist.
 *
 * Run: vendor/bin/phpunit -c phpunit.ptr.xml
 */

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$root = dirname(__DIR__, 2);

require_once $root . '/vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

// WordPress function shims (__, esc_html, sanitize_*, wp_json_encode, WP_Error…).
require_once $root . '/tests/mocks/wordpress-mocks.php';

// Connector interface + result DTOs (AccountValidationResult, EnrollmentResult…).
require_once $root . '/includes/api/interface-api-connector.php';

// PTR connector + seeder, once they exist (created during Stage 1 tasks).
foreach ([
    '/connectors/dominion-ptr/class-dominion-ptr-connector.php',
    '/connectors/dominion-ptr/class-dominion-ptr-seeder.php',
] as $relative) {
    $path = $root . $relative;
    if (file_exists($path)) {
        require_once $path;
    }
}
