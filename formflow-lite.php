<?php
/**
 * Plugin Name: FormFlow Lite
 * Plugin URI: https://formflow.dev
 * Description: Lightweight API-integrated enrollment and scheduling forms for utility demand response programs
 * Version: 3.2.11
 * Author: Peanut Graphic
 * Author URI: https://peanutgraphic.com
 * Text Domain: formflow-lite
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('FFFL_VERSION', '3.2.11');
define('FFFL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('FFFL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('FFFL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('FFFL_PLUGIN_FILE', __FILE__);
define('FFFL_CONNECTORS_DIR', FFFL_PLUGIN_DIR . 'connectors/');

// Database table names (without prefix) - Core tables only
define('FFFL_TABLE_INSTANCES', 'fffl_instances');
define('FFFL_TABLE_SUBMISSIONS', 'fffl_submissions');
define('FFFL_TABLE_LOGS', 'fffl_logs');
define('FFFL_TABLE_RESUME_TOKENS', 'fffl_resume_tokens');
define('FFFL_TABLE_WEBHOOKS', 'fffl_webhooks');
define('FFFL_TABLE_API_USAGE', 'fffl_api_usage');

// Autoloader
spl_autoload_register(function ($class) {
    // Check if the class is in our namespace
    $prefix = 'FFFL\\';
    $base_dir = FFFL_PLUGIN_DIR . 'includes/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Class map for special cases (acronyms, unusual naming)
    $class_map = [
        'FFFL\\UTMTracker' => 'class-utm-tracker.php',
    ];

    // Check class map first
    if (isset($class_map[$class])) {
        $file = $base_dir . $class_map[$class];
        if (file_exists($file)) {
            require $file;
            return;
        }
    }

    // Get the relative class name
    $relative_class = substr($class, $len);

    // Map namespace to directory structure
    $path_parts = explode('\\', $relative_class);
    $class_name = array_pop($path_parts);

    // Convert class name to file name (CamelCase to kebab-case)
    $file_name = 'class-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name)) . '.php';

    // Build the file path
    if (!empty($path_parts)) {
        $sub_dir = strtolower(implode('/', $path_parts)) . '/';
        $file = $base_dir . $sub_dir . $file_name;
    } else {
        $file = $base_dir . $file_name;
    }

    if (file_exists($file)) {
        require $file;
        return;
    }

    // Also check connectors directory for connector classes
    if (count($path_parts) >= 2 && $path_parts[0] === 'Connectors') {
        $connector_name = strtolower($path_parts[1]);
        $connector_file = FFFL_CONNECTORS_DIR . $connector_name . '/' . $file_name;
        if (file_exists($connector_file)) {
            require $connector_file;
            return;
        }
    }

    // Check for interface files
    $interface_name = 'interface-' . strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $class_name)) . '.php';
    if (!empty($path_parts)) {
        $interface_file = $base_dir . $sub_dir . $interface_name;
    } else {
        $interface_file = $base_dir . $interface_name;
    }
    if (file_exists($interface_file)) {
        require $interface_file;
    }
});

/**
 * Activation hook
 */
function fffl_activate() {
    require_once FFFL_PLUGIN_DIR . 'includes/class-activator.php';
    FFFL\Activator::activate();
}
register_activation_hook(__FILE__, 'fffl_activate');

/**
 * Deactivation hook
 */
function fffl_deactivate() {
    require_once FFFL_PLUGIN_DIR . 'includes/class-deactivator.php';
    FFFL\Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'fffl_deactivate');

/**
 * Initialize plugin
 *
 * Uses 'init' hook instead of 'plugins_loaded' to ensure translations
 * are properly loaded before any translation functions are called.
 * WordPress 6.7+ enforces strict timing on textdomain loading.
 */
function fffl_init() {
    // Load textdomain first
    load_plugin_textdomain(
        'formflow-lite',
        false,
        dirname(FFFL_PLUGIN_BASENAME) . '/languages'
    );

    // Check PHP version
    if (version_compare(PHP_VERSION, '8.0', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo esc_html__('FormFlow Lite requires PHP 8.0 or higher.', 'formflow-lite');
            echo '</p></div>';
        });
        return;
    }

    // Load core classes
    require_once FFFL_PLUGIN_DIR . 'includes/api/interface-api-connector.php';
    require_once FFFL_PLUGIN_DIR . 'includes/api/class-connector-registry.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-branding.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-cache-manager.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-queue-manager.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-embed-handler.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-hooks.php';
    require_once FFFL_PLUGIN_DIR . 'includes/class-encryption.php';

    // Register encryption key admin notices (warns if not configured)
    FFFL\Encryption::register_admin_notices();

    // Initialize singletons
    FFFL\Api\ConnectorRegistry::instance();
    FFFL\CacheManager::instance();

    // Initialize queue manager
    $queue = new FFFL\QueueManager();
    $queue->init();

    // Initialize embed handler
    $embed = new FFFL\EmbedHandler();
    $embed->init();

    // Load bundled connectors
    fffl_load_bundled_connectors();

    // Load plugin
    require_once FFFL_PLUGIN_DIR . 'includes/class-plugin.php';
    $plugin = new FFFL\Plugin();
    $plugin->run();
}
add_action('init', 'fffl_init', 0);

/**
 * Load bundled connectors from the connectors directory
 */
function fffl_load_bundled_connectors() {
    $connectors_dir = FFFL_CONNECTORS_DIR;

    if (!is_dir($connectors_dir)) {
        return;
    }

    // Scan for connector directories
    $directories = glob($connectors_dir . '*', GLOB_ONLYDIR);

    foreach ($directories as $dir) {
        $loader = $dir . '/loader.php';
        if (file_exists($loader)) {
            require_once $loader;
        }
    }

    /**
     * Action: After bundled connectors are loaded
     *
     * External plugins can hook here to register additional connectors.
     */
    do_action('fffl_connectors_loaded');
}

/**
 * Get plugin instance (for external access)
 */
function fffl() {
    static $instance = null;
    if ($instance === null) {
        require_once FFFL_PLUGIN_DIR . 'includes/class-plugin.php';
        $instance = new FFFL\Plugin();
    }
    return $instance;
}

/**
 * Get connector registry instance
 *
 * @return FFFL\Api\ConnectorRegistry
 */
function fffl_connectors() {
    return FFFL\Api\ConnectorRegistry::instance();
}

/**
 * Get a specific connector by ID
 *
 * @param string $id Connector ID
 * @return FFFL\Api\ApiConnectorInterface|null
 */
function fffl_get_connector(string $id) {
    return fffl_connectors()->get($id);
}

/**
 * Get branding instance
 *
 * @return FFFL\Branding
 */
function fffl_branding() {
    return FFFL\Branding::instance();
}

/**
 * Get a branding setting
 *
 * @param string $key Setting key
 * @param mixed $default Default value
 * @return mixed
 */
function fffl_brand(string $key, $default = null) {
    return fffl_branding()->get($key, $default);
}

/**
 * Get plugin name (from branding or default)
 *
 * @return string
 */
function fffl_plugin_name() {
    return fffl_branding()->get_plugin_name();
}

/**
 * Get cache manager instance
 *
 * @return FFFL\CacheManager
 */
function fffl_cache() {
    return FFFL\CacheManager::instance();
}

/**
 * Queue manager instance holder
 */
global $fffl_queue_manager;

/**
 * Get queue manager instance
 *
 * @return FFFL\QueueManager
 */
function fffl_queue() {
    global $fffl_queue_manager;
    if ($fffl_queue_manager === null) {
        $fffl_queue_manager = new FFFL\QueueManager();
    }
    return $fffl_queue_manager;
}

/**
 * Embed handler instance holder
 */
global $fffl_embed_handler;

/**
 * Get embed handler instance
 *
 * @return FFFL\EmbedHandler
 */
function fffl_embed() {
    global $fffl_embed_handler;
    if ($fffl_embed_handler === null) {
        $fffl_embed_handler = new FFFL\EmbedHandler();
    }
    return $fffl_embed_handler;
}
