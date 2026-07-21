<?php
/**
 * WordPress function mocks for standalone testing.
 *
 * These mocks allow testing of plugin logic without a full WordPress installation.
 * For integration tests, use the WordPress test suite.
 *
 * @package Peanut_Suite
 */

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}

if (!defined('WP_CONTENT_DIR')) {
    define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
}

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
}

// Mock common WordPress functions.

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_attr')) {
    function esc_attr($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return filter_var($url, FILTER_SANITIZE_URL);
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        // Real WordPress VALIDATES and returns '' for a structurally invalid
        // address; FILTER_SANITIZE_EMAIL alone only strips disallowed characters
        // and would hand back 'not-an-email' unchanged, which made the mock
        // disagree with production behaviour.
        $email = filter_var(trim((string) $email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return stripslashes_deep($value);
    }
}

if (!function_exists('stripslashes_deep')) {
    function stripslashes_deep($value) {
        if (is_array($value)) {
            return array_map('stripslashes_deep', $value);
        }
        return stripslashes($value);
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int) $maybeint);
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data, $options = 0, $depth = 512) {
        return json_encode($data, $options, $depth);
    }
}

if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

if (!function_exists('_e')) {
    function _e($text, $domain = 'default') {
        echo $text;
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = 'default') {
        return esc_html($text);
    }
}

if (!function_exists('esc_html_e')) {
    function esc_html_e($text, $domain = 'default') {
        echo esc_html($text);
    }
}

if (!function_exists('wp_hash')) {
    function wp_hash($data, $scheme = 'auth') {
        return hash('sha256', $data . 'test_salt');
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($scheme = 'auth') {
        return 'test_salt_' . $scheme;
    }
}

if (!function_exists('current_time')) {
    function current_time($type, $gmt = 0) {
        if ($type === 'timestamp' || $type === 'U') {
            return time();
        }
        if ($type === 'mysql') {
            return date('Y-m-d H:i:s');
        }
        return date($type);
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $mock_options;
        return $mock_options[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $mock_options;
        $mock_options[$option] = $value;
        return true;
    }
}

if (!function_exists('delete_option')) {
    function delete_option($option) {
        global $mock_options;
        unset($mock_options[$option]);
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($transient) {
        global $mock_transients;
        $data = $mock_transients[$transient] ?? null;
        if ($data && isset($data['expiration']) && $data['expiration'] < time()) {
            unset($mock_transients[$transient]);
            return false;
        }
        return $data['value'] ?? false;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($transient, $value, $expiration = 0) {
        global $mock_transients;
        $mock_transients[$transient] = [
            'value' => $value,
            'expiration' => $expiration > 0 ? time() + $expiration : 0,
        ];
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        global $mock_transients;
        unset($mock_transients[$transient]);
        return true;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $errors = [];
        public $error_data = [];

        public function __construct($code = '', $message = '', $data = '') {
            if (!empty($code)) {
                $this->errors[$code][] = $message;
                if (!empty($data)) {
                    $this->error_data[$code] = $data;
                }
            }
        }

        public function get_error_code() {
            $codes = array_keys($this->errors);
            return $codes[0] ?? '';
        }

        public function get_error_message($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->errors[$code][0] ?? '';
        }

        public function get_error_data($code = '') {
            if (empty($code)) {
                $code = $this->get_error_code();
            }
            return $this->error_data[$code] ?? null;
        }

        public function add($code, $message, $data = '') {
            $this->errors[$code][] = $message;
            if (!empty($data)) {
                $this->error_data[$code] = $data;
            }
        }
    }
}

// Initialize mock storage.
global $mock_options, $mock_transients;
$mock_options = [];
$mock_transients = [];

/*
 * ---------------------------------------------------------------------------
 * Test-state helpers required by FFFL\Tests\TestCase.
 *
 * These were never written: TestCase::setUp() has always called
 * reset_mock_data(), which did not exist, so EVERY test extending it errored
 * before reaching its first assertion. Because no CI job runs the `unit` suite,
 * that went unnoticed and the suite silently rotted.
 *
 * They drive the same globals the WordPress mocks above read, so state set by a
 * test is what the mocked WP functions return.
 * ---------------------------------------------------------------------------
 */

if (!function_exists('reset_mock_data')) {
    /** Reset every piece of mock state between tests. */
    function reset_mock_data(): void {
        global $mock_options, $mock_transients, $mock_wpdb_results, $mock_wpdb_insert_id,
               $mock_emails_sent, $mock_json_response, $mock_current_user_capabilities, $mock_is_ssl;

        $mock_options                   = [];
        $mock_transients                = [];
        $mock_wpdb_results              = [];
        $mock_wpdb_insert_id            = 0;
        $mock_emails_sent               = [];
        $mock_json_response             = null;
        $mock_current_user_capabilities = [];
        $mock_is_ssl                    = false;
    }
}

if (!function_exists('set_mock_option')) {
    function set_mock_option(string $option, $value): void {
        global $mock_options;
        $mock_options[$option] = $value;
    }
}

if (!function_exists('set_mock_wpdb_result')) {
    /** Queue a result for a wpdb method (get_row / get_results / get_var). */
    function set_mock_wpdb_result(string $method, $result): void {
        global $mock_wpdb_results;
        $mock_wpdb_results[$method] = $result;
    }
}

if (!function_exists('get_mock_emails_sent')) {
    function get_mock_emails_sent(): array {
        global $mock_emails_sent;
        return is_array($mock_emails_sent) ? $mock_emails_sent : [];
    }
}

if (!function_exists('get_mock_json_response')) {
    function get_mock_json_response(): ?array {
        global $mock_json_response;
        return is_array($mock_json_response) ? $mock_json_response : null;
    }
}

/* --- Supporting WordPress mocks the helpers imply ------------------------- */

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = []) {
        global $mock_emails_sent;
        if (!is_array($mock_emails_sent)) { $mock_emails_sent = []; }
        $mock_emails_sent[] = compact('to', 'subject', 'message', 'headers', 'attachments');
        return true;
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null) {
        global $mock_json_response;
        $mock_json_response = ['success' => true, 'data' => $data];
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null) {
        global $mock_json_response;
        $mock_json_response = ['success' => false, 'data' => $data];
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability, ...$args) {
        global $mock_current_user_capabilities;
        return (bool) ($mock_current_user_capabilities[$capability] ?? false);
    }
}

if (!function_exists('is_ssl')) {
    function is_ssl(): bool {
        global $mock_is_ssl;
        return (bool) $mock_is_ssl;
    }
}

/* --- Hook + template WordPress mocks -------------------------------------- */

if (!function_exists('apply_filters')) {
    /** No filters are registered under the mock harness — return the value unchanged. */
    function apply_filters($tag, $value, ...$args) { return $value; }
}
if (!function_exists('do_action')) {
    function do_action($tag, ...$args) { return null; }
}
if (!function_exists('add_filter')) {
    function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists('add_action')) {
    function add_action($tag, $callback, $priority = 10, $accepted_args = 1) { return true; }
}
if (!function_exists('has_action')) {
    function has_action($tag, $callback = false) { return false; }
}

if (!function_exists('esc_attr_e')) {
    function esc_attr_e($text, $domain = 'default') { echo esc_attr($text); }
}
if (!function_exists('esc_textarea')) {
    function esc_textarea($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('wp_kses_post')) {
    function wp_kses_post($data) { return $data; }
}

if (!function_exists('checked')) {
    function checked($checked, $current = true, $echo = true) {
        $result = ((string) $checked === (string) $current) ? ' checked="checked"' : '';
        if ($echo) { echo $result; }
        return $result;
    }
}
if (!function_exists('selected')) {
    function selected($selected, $current = true, $echo = true) {
        $result = ((string) $selected === (string) $current) ? ' selected="selected"' : '';
        if ($echo) { echo $result; }
        return $result;
    }
}
if (!function_exists('disabled')) {
    function disabled($disabled, $current = true, $echo = true) {
        $result = ((string) $disabled === (string) $current) ? ' disabled="disabled"' : '';
        if ($echo) { echo $result; }
        return $result;
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title, $fallback = '', $context = 'save') {
        $title = strtolower(remove_accents_stub((string) $title));
        $title = preg_replace('/[^a-z0-9\s\-_]/', '', $title);
        $title = preg_replace('/[\s_]+/', '-', trim($title));
        $title = trim(preg_replace('/-+/', '-', $title), '-');
        return $title !== '' ? $title : $fallback;
    }
    function remove_accents_stub(string $s): string { return $s; }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $key)); }
}

if (!function_exists('wp_create_nonce')) {
    /** Deterministic so wp_verify_nonce() below can round-trip it. */
    function wp_create_nonce($action = -1) { return substr(md5('fffl-mock-nonce|' . $action), 0, 10); }
}
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return hash_equals(wp_create_nonce($action), (string) $nonce) ? 1 : false;
    }
}

/* --- $wpdb mock ------------------------------------------------------------
 * DatabaseTest constructs FFFL\Database\Database, which type-hints wpdb, so
 * without a class of that exact name every one of its tests died with
 * "Cannot assign null to property ... of type wpdb". Results are driven by
 * set_mock_wpdb_result() so tests stay declarative.
 * ------------------------------------------------------------------------- */

if (!class_exists('wpdb')) {
    class wpdb {
        public string $prefix = 'wp_';
        public int $insert_id = 0;
        public string $last_error = '';
        /** @var array<string,mixed> Queries seen, for assertions. */
        public array $queries = [];

        private function result(string $method, $default = null) {
            global $mock_wpdb_results;
            return $mock_wpdb_results[$method] ?? $default;
        }

        /** Mirrors wpdb::prepare's %s/%d/%f substitution closely enough to assert on. */
        public function prepare($query, ...$args) {
            if ($args && is_array($args[0]) && count($args) === 1) {
                $args = $args[0];
            }
            $this->queries[] = $query;
            foreach ($args as $arg) {
                $replacement = is_int($arg) || is_float($arg)
                    ? (string) $arg
                    : "'" . addslashes((string) $arg) . "'";
                $query = preg_replace('/%[sdf]/', str_replace('$', '\\$', $replacement), $query, 1);
            }
            return $query;
        }

        public function get_results($query = null, $output = OBJECT) { return $this->result('get_results', []); }
        public function get_row($query = null, $output = OBJECT, $y = 0)  { return $this->result('get_row'); }
        public function get_var($query = null, $x = 0, $y = 0)            { return $this->result('get_var'); }
        public function get_col($query = null, $x = 0)                    { return $this->result('get_col', []); }

        public function query($query) { $this->queries[] = $query; return $this->result('query', 1); }

        /** Last payload handed to insert()/update(), so tests can assert on writes. */
        public $last_insert_data = null;
        public $last_update_data = null;

        public function insert($table, $data, $format = null) {
            global $mock_wpdb_insert_id;
            $this->last_insert_data = $data;
            $this->insert_id = (int) ($mock_wpdb_insert_id ?: 1);
            return $this->result('insert', 1);
        }
        public function update($table, $data, $where, $format = null, $where_format = null) {
            $this->last_update_data = $data;
            return $this->result('update', 1);
        }
        public function delete($table, $where, $where_format = null) {
            return $this->result('delete', 1);
        }

        public function esc_like($text) { return addcslashes((string) $text, '_%\\'); }
        public function get_charset_collate() { return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'; }
    }
}

if (!defined('OBJECT'))  { define('OBJECT', 'OBJECT'); }
if (!defined('ARRAY_A')) { define('ARRAY_A', 'ARRAY_A'); }
if (!defined('ARRAY_N')) { define('ARRAY_N', 'ARRAY_N'); }

// Make $wpdb available globally, as WordPress does.
if (!isset($GLOBALS['wpdb']) || !$GLOBALS['wpdb'] instanceof wpdb) {
    $GLOBALS['wpdb'] = new wpdb();
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }
}

if (!function_exists('wp_nonce_field')) {
    function wp_nonce_field($action = -1, $name = '_wpnonce', $referer = true, $echo = true) {
        $field = '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr(wp_create_nonce($action)) . '" />';
        if ($echo) { echo $field; }
        return $field;
    }
}
