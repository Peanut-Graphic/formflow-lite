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

/*
 * Plugin constants.
 *
 * Normally defined in formflow-lite.php, which is only loaded when the real
 * WordPress test suite is present. Under the mock harness the plugin classes
 * are autoloaded directly, so the constants they reference (table names, paths)
 * must be defined here or every DB-touching test dies on an undefined constant.
 */
if (!defined('FFFL_VERSION')) {
    define('FFFL_VERSION', '0.0.0-test');
    define('FFFL_PLUGIN_FILE', dirname(__DIR__) . '/formflow-lite.php');
    define('FFFL_PLUGIN_DIR', dirname(__DIR__) . '/');
    define('FFFL_PLUGIN_URL', 'https://example.test/wp-content/plugins/formflow-lite/');
    define('FFFL_PLUGIN_BASENAME', 'formflow-lite/formflow-lite.php');
    define('FFFL_CONNECTORS_DIR', dirname(__DIR__) . '/connectors/');
}
if (!defined('FFFL_TABLE_INSTANCES')) {
    define('FFFL_TABLE_INSTANCES', 'fffl_instances');
    define('FFFL_TABLE_SUBMISSIONS', 'fffl_submissions');
    define('FFFL_TABLE_LOGS', 'fffl_logs');
    define('FFFL_TABLE_RESUME_TOKENS', 'fffl_resume_tokens');
    define('FFFL_TABLE_WEBHOOKS', 'fffl_webhooks');
    define('FFFL_TABLE_API_USAGE', 'fffl_api_usage');
}

/*
 * Autoload the plugin's own FFFL\ classes.
 *
 * The plugin registers this in formflow-lite.php, but that file is only loaded
 * when the real WordPress test suite is available. Under the standalone mock
 * harness it never runs, so every test touching a plugin class died with
 * "Class FFFL\... not found". Mirrors the plugin's mapping: CamelCase ->
 * class-kebab-case.php, sub-namespaces -> lowercase sub-directories, with the
 * same interface- fallback.
 */
spl_autoload_register(function ($class) {
    $prefix = 'FFFL\\';
    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $base_dir  = dirname(__DIR__) . '/includes/';
    $class_map = ['FFFL\\UTMTracker' => 'class-utm-tracker.php'];

    if (isset($class_map[$class])) {
        $mapped = $base_dir . $class_map[$class];
        if (file_exists($mapped)) { require_once $mapped; }
        return;
    }

    $parts      = explode('\\', substr($class, strlen($prefix)));
    $class_name = array_pop($parts);
    $kebab      = strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name));
    $sub_dir    = $parts ? strtolower(implode('/', $parts)) . '/' : '';

    foreach (["class-{$kebab}.php", "interface-{$kebab}.php", "trait-{$kebab}.php"] as $candidate) {
        $file = $base_dir . $sub_dir . $candidate;
        if (file_exists($file)) { require_once $file; return; }
    }
});

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
