<?php
namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Encryption Utilities
 *
 * Handles AES-256-CBC encryption/decryption for sensitive data storage.
 */


class Encryption {

    private const METHOD = 'AES-256-CBC';
    private const IV_LENGTH = 16;

    private string $key;

    /**
     * Constructor
     */
    public function __construct() {
        $this->key = $this->get_encryption_key();
    }

    /**
     * Get or generate the encryption key
     */
    private function get_encryption_key(): string {
        // First, check for defined constant
        if (defined('FFFL_ENCRYPTION_KEY') && strlen(FFFL_ENCRYPTION_KEY) >= 32) {
            return substr(FFFL_ENCRYPTION_KEY, 0, 32);
        }

        // Fall back to WordPress auth salt
        $key = wp_salt('auth');

        // Ensure key is exactly 32 bytes
        return substr(hash('sha256', $key), 0, 32);
    }

    /**
     * Encrypt data
     */
    public function encrypt(string $data): string {
        if (empty($data)) {
            return '';
        }

        // Generate random IV
        $iv = openssl_random_pseudo_bytes(self::IV_LENGTH);

        // Encrypt
        $encrypted = openssl_encrypt(
            $data,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        if ($encrypted === false) {
            throw new \RuntimeException('Encryption failed');
        }

        // Combine IV and encrypted data, then base64 encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt data
     */
    public function decrypt(string $data): string {
        if (empty($data)) {
            return '';
        }

        // Decode base64
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return '';
        }

        // Extract IV and encrypted data
        $iv = substr($decoded, 0, self::IV_LENGTH);
        $encrypted = substr($decoded, self::IV_LENGTH);

        if (strlen($iv) !== self::IV_LENGTH) {
            return '';
        }

        // Decrypt
        $decrypted = openssl_decrypt(
            $encrypted,
            self::METHOD,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );

        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Encrypt an array (converts to JSON first)
     */
    public function encrypt_array(array $data): string {
        return $this->encrypt(json_encode($data));
    }

    /**
     * Decrypt to array
     */
    public function decrypt_array(string $data): array {
        $decrypted = $this->decrypt($data);
        if (empty($decrypted)) {
            return [];
        }

        $array = json_decode($decrypted, true);
        return is_array($array) ? $array : [];
    }

    /**
     * Hash sensitive data for comparison (one-way)
     */
    public static function hash(string $data): string {
        return hash('sha256', $data);
    }

    /**
     * Verify a value against its hash
     */
    public static function verify_hash(string $data, string $hash): bool {
        return hash_equals($hash, self::hash($data));
    }

    /**
     * Mask sensitive data for display (e.g., account numbers)
     */
    public static function mask(string $data, int $visible_start = 0, int $visible_end = 4): string {
        $length = strlen($data);
        if ($length <= ($visible_start + $visible_end)) {
            return str_repeat('*', $length);
        }

        $start = substr($data, 0, $visible_start);
        $end = substr($data, -$visible_end);
        $middle = str_repeat('*', $length - $visible_start - $visible_end);

        return $start . $middle . $end;
    }

    /**
     * Test if encryption is working properly
     */
    public function test(): bool {
        $test_data = 'FormFlow Encryption Test ' . time();

        try {
            $encrypted = $this->encrypt($test_data);
            $decrypted = $this->decrypt($encrypted);
            return $decrypted === $test_data;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if using custom encryption key (not WordPress fallback)
     */
    public static function is_using_custom_key(): bool {
        return defined('FFFL_ENCRYPTION_KEY') && strlen(FFFL_ENCRYPTION_KEY) >= 32;
    }

    /**
     * Check if encryption key is properly configured
     */
    public static function get_key_status(): array {
        if (!defined('FFFL_ENCRYPTION_KEY')) {
            return [
                'status' => 'warning',
                'message' => __('FFFL_ENCRYPTION_KEY is not defined. Using WordPress auth salt as fallback. For better security, add a custom encryption key to wp-config.php.', 'formflow-lite'),
                'code' => 'key_not_defined'
            ];
        }

        if (strlen(FFFL_ENCRYPTION_KEY) < 32) {
            return [
                'status' => 'error',
                'message' => __('FFFL_ENCRYPTION_KEY is too short. It must be at least 32 characters for AES-256 encryption.', 'formflow-lite'),
                'code' => 'key_too_short'
            ];
        }

        return [
            'status' => 'ok',
            'message' => __('Custom encryption key is properly configured.', 'formflow-lite'),
            'code' => 'key_ok'
        ];
    }

    /**
     * Register admin notices for encryption key issues
     * Call this during plugin initialization
     */
    public static function register_admin_notices(): void {
        add_action('admin_notices', [self::class, 'display_key_notice']);
    }

    /**
     * Display admin notice if encryption key is not properly configured
     */
    public static function display_key_notice(): void {
        // Only show to administrators
        if (!current_user_can('manage_options')) {
            return;
        }

        // Only show on FormFlow admin pages or the plugins page
        $screen = get_current_screen();
        $show_on = ['plugins', 'toplevel_page_formflow', 'formflow_page_formflow-settings'];
        if ($screen && !in_array($screen->id, $show_on, true) && strpos($screen->id, 'formflow') === false) {
            return;
        }

        $status = self::get_key_status();

        if ($status['status'] === 'ok') {
            return;
        }

        $notice_class = $status['status'] === 'error' ? 'notice-error' : 'notice-warning';
        $generated_key = bin2hex(random_bytes(16)); // Generate a sample key

        ?>
        <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible">
            <p>
                <strong><?php esc_html_e('FormFlow Security Notice:', 'formflow-lite'); ?></strong>
                <?php echo esc_html($status['message']); ?>
            </p>
            <p>
                <?php esc_html_e('Add the following line to your wp-config.php file:', 'formflow-lite'); ?>
            </p>
            <p>
                <code>define('FFFL_ENCRYPTION_KEY', '<?php echo esc_html($generated_key); ?>');</code>
            </p>
            <p>
                <em><?php esc_html_e('Note: Once set, do not change this key or encrypted data will become unreadable.', 'formflow-lite'); ?></em>
            </p>
        </div>
        <?php
    }

    /**
     * Generate a secure encryption key
     *
     * @return string A 32-character hexadecimal key
     */
    public static function generate_key(): string {
        return bin2hex(random_bytes(16));
    }
}
