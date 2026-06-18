<?php
/**
 * Standalone bootstrap for the Reliability test suite.
 *
 * These tests pin the schema-drift guard and the upgrade/self-heal mechanism.
 * They run as PURE PHP — no WordPress is booted. A minimal set of WP shims
 * (option store, transient store, a recording `wpdb`, dbDelta, etc.) is defined
 * here so the Activator migration path and the Database table-exists guard can
 * be exercised in isolation.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fffl-reliability/');
}
if (!is_dir(ABSPATH . 'wp-admin/includes')) {
    @mkdir(ABSPATH . 'wp-admin/includes', 0777, true);
}
// dbDelta is required via ABSPATH . 'wp-admin/includes/upgrade.php' inside the
// Activator. Provide a stub file there so the require_once succeeds.
$__upgrade_stub = ABSPATH . 'wp-admin/includes/upgrade.php';
if (!file_exists($__upgrade_stub)) {
    file_put_contents($__upgrade_stub, "<?php\n");
}

// ---------------------------------------------------------------------------
// Plugin constants (mirrors formflow-lite.php — the 7 real tables).
// ---------------------------------------------------------------------------
if (!defined('FFFL_VERSION')) {
    define('FFFL_VERSION', '9.9.9-test');
}
foreach ([
    'FFFL_TABLE_INSTANCES'     => 'fffl_instances',
    'FFFL_TABLE_SUBMISSIONS'   => 'fffl_submissions',
    'FFFL_TABLE_LOGS'          => 'fffl_logs',
    'FFFL_TABLE_RESUME_TOKENS' => 'fffl_resume_tokens',
    'FFFL_TABLE_WEBHOOKS'      => 'fffl_webhooks',
    'FFFL_TABLE_API_USAGE'     => 'fffl_api_usage',
] as $__const => $__val) {
    if (!defined($__const)) {
        define($__const, $__val);
    }
}
if (!defined('MINUTE_IN_SECONDS')) {
    define('MINUTE_IN_SECONDS', 60);
}
if (!defined('HOUR_IN_SECONDS')) {
    define('HOUR_IN_SECONDS', 3600);
}

// ---------------------------------------------------------------------------
// Option + transient stores (in-memory, reset between tests via helpers).
// ---------------------------------------------------------------------------
$GLOBALS['__fffl_options']    = [];
$GLOBALS['__fffl_transients'] = [];

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $GLOBALS['__fffl_options'][$name] ?? $default;
    }
}
if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        $GLOBALS['__fffl_options'][$name] = $value;
        return true;
    }
}
if (!function_exists('add_option')) {
    function add_option($name, $value = '', $deprecated = '', $autoload = 'yes') {
        if (isset($GLOBALS['__fffl_options'][$name])) {
            return false;
        }
        $GLOBALS['__fffl_options'][$name] = $value;
        return true;
    }
}
if (!function_exists('get_transient')) {
    function get_transient($name) {
        return $GLOBALS['__fffl_transients'][$name] ?? false;
    }
}
if (!function_exists('set_transient')) {
    function set_transient($name, $value, $exp = 0) {
        $GLOBALS['__fffl_transients'][$name] = $value;
        return true;
    }
}
if (!function_exists('delete_transient')) {
    function delete_transient($name) {
        unset($GLOBALS['__fffl_transients'][$name]);
        return true;
    }
}

// ---------------------------------------------------------------------------
// Misc WP shims used along the migration / guard paths.
// ---------------------------------------------------------------------------
if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules($hard = true) {}
}
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled($hook, $args = []) { return time(); }
}
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event($ts, $rec, $hook, $args = []) { return true; }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') { return $text; }
}
if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') { return 'test-salt-do-not-use'; }
}
if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') { return hash_hmac('md5', (string) $data, wp_salt($scheme)); }
}
if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) { return $type === 'timestamp' ? time() : gmdate('Y-m-d H:i:s'); }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) { return is_string($str) ? trim(strip_tags($str)) : ''; }
}
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user() {
        $u = new \stdClass();
        $u->ID         = $GLOBALS['__fffl_current_user_id'] ?? 1;
        $u->user_login = 'tester';
        $u->user_email = 'tester@example.com';
        return $u;
    }
}

// dbDelta records the SQL it was handed so the upgrade test can prove the
// Activator re-ran create_tables() on a stale version.
if (!function_exists('dbDelta')) {
    function dbDelta($queries, $execute = true) {
        $GLOBALS['__fffl_dbdelta_calls'][] = $queries;
        return [];
    }
}

/**
 * Minimal recording wpdb double.
 *
 * - Records insert()/update() table + data so guard tests can assert a write
 *   was (or was not) attempted.
 * - get_var() answers INFORMATION_SCHEMA column/table existence queries from a
 *   configurable map of "tables that exist".
 */
class wpdb {
    public $prefix = 'wp_';
    public $dbname = 'fffl_test_db';
    public $insert_id = 0;
    public $last_error = '';

    /** @var string[] table names (with prefix) that "exist" in the DB */
    public array $existing_tables = [];
    /** @var array<int,array{table:string,data:array}> */
    public array $inserts = [];
    /** @var array<int,array{table:string,data:array}> */
    public array $updates = [];
    /** @var array<int,string> raw queries asked of get_var */
    public array $get_var_queries = [];

    public function get_charset_collate(): string {
        return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
    }

    public function prepare($query, ...$args) {
        // Flatten a single array arg (wpdb behaviour).
        if (count($args) === 1 && is_array($args[0])) {
            $args = $args[0];
        }
        // Good-enough substitution for assertions; not used for real SQL here.
        $i = 0;
        return preg_replace_callback('/%[sdf]/', function ($m) use (&$i, $args) {
            $v = $args[$i] ?? '';
            $i++;
            return is_numeric($v) ? (string) $v : "'" . $v . "'";
        }, (string) $query);
    }

    public function get_var($query) {
        $this->get_var_queries[] = (string) $query;
        // Answer table-exists checks: SHOW TABLES LIKE 'wp_xyz'
        if (preg_match("/SHOW TABLES LIKE '([^']+)'/i", (string) $query, $m)) {
            return in_array($m[1], $this->existing_tables, true) ? $m[1] : null;
        }
        return null;
    }

    public function insert($table, $data, $format = null) {
        $this->inserts[] = ['table' => $table, 'data' => $data];
        $this->insert_id = count($this->inserts);
        return 1;
    }

    public function update($table, $data, $where, $f = null, $wf = null) {
        $this->updates[] = ['table' => $table, 'data' => $data];
        return 1;
    }

    public function get_results($query, $output = OBJECT) {
        return [];
    }

    public function get_row($query, $output = OBJECT) {
        return null;
    }
}

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}

/**
 * Reset the in-memory WP state and install a fresh fake wpdb.
 */
function fffl_reliability_reset(array $existing_tables = []): wpdb {
    global $wpdb;
    $GLOBALS['__fffl_options']        = [];
    $GLOBALS['__fffl_transients']     = [];
    $GLOBALS['__fffl_dbdelta_calls']  = [];
    $wpdb = new wpdb();
    $wpdb->existing_tables = $existing_tables;

    // Reset the Database table-exists static cache between tests.
    if (class_exists(\FFFL\Database\Database::class)) {
        // Private static; accessible without setAccessible() on PHP 8.1+.
        (new \ReflectionProperty(\FFFL\Database\Database::class, 'table_exists_cache'))
            ->setValue(null, []);
    }

    return $wpdb;
}

require_once dirname(__DIR__, 2) . '/includes/class-activator.php';
require_once dirname(__DIR__, 2) . '/includes/class-encryption.php';
require_once dirname(__DIR__, 2) . '/includes/database/class-database.php';
