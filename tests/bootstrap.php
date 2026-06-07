<?php
/**
 * PHPUnit bootstrap file for FormFlow Lite tests.
 *
 * @package FormFlow_Lite
 */

// Define test mode.
define('FORMFLOW_LITE_TESTING', true);

// Composer autoloader.
$composer_autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($composer_autoload)) {
    require_once $composer_autoload;
}

// Try to load WordPress test environment.
$_tests_dir = getenv('WP_TESTS_DIR');

if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

// Check if WordPress test suite is available.
if (file_exists($_tests_dir . '/includes/functions.php')) {
    require_once $_tests_dir . '/includes/functions.php';

    function _manually_load_plugin() {
        require dirname(__DIR__) . '/formflow-lite.php';
    }
    tests_add_filter('muplugins_loaded', '_manually_load_plugin');

    require $_tests_dir . '/includes/bootstrap.php';
} else {
    // Load mocks for standalone testing.
    require_once __DIR__ . '/mocks/wordpress-mocks.php';
}

// Net 6 (property) + net 7 (contract) target PURE PHP classes that must be
// loadable without a full plugin boot. Require them here (guarded) so the
// Property/ and Contract/ suites are collectable under this top-level config
// as well as via their dedicated phpunit.property.xml / phpunit.contract.xml.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fffl-tests/');
}
foreach ([
    '/includes/class-security.php',
    '/includes/api/class-xml-parser.php',
    '/includes/api/class-response-validator.php',
] as $__fffl_pure_class) {
    $__fffl_path = dirname(__DIR__) . $__fffl_pure_class;
    if (file_exists($__fffl_path)) {
        require_once $__fffl_path;
    }
}
unset($__fffl_pure_class, $__fffl_path);

/**
 * Base test case class for FormFlow Lite.
 */
abstract class FormFlow_Lite_TestCase extends \PHPUnit\Framework\TestCase {

    protected function setUp(): void {
        parent::setUp();
    }

    protected function tearDown(): void {
        parent::tearDown();
    }
}
