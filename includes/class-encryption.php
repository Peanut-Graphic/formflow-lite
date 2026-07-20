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

    private \Peanut\FormCore\Crypto\Encryptor $encryptor;

    /**
     * Constructor
     *
     * The bootstrap in formflow-lite.php refuses to initialise the plugin when
     * the shared Encryptor is unavailable, so reaching this state means some
     * path ran outside that gate (a direct include, WP-CLI, an activation
     * ordering quirk). Fail loudly and specifically rather than with PHP's
     * opaque "Class not found" — and never silently, because the alternative
     * to encrypting is writing secrets to the database in plaintext.
     */
    public function __construct() {
        if (!class_exists('\Peanut\FormCore\Crypto\Encryptor')) {
            throw new \RuntimeException(
                'FormFlow Lite: peanut/formflow-core is missing from vendor/, so data-at-rest '
                . 'encryption is unavailable. Run `composer install --no-dev` in the plugin '
                . 'directory or reinstall from an official release package. Refusing to '
                . 'continue rather than store sensitive data unencrypted.'
            );
        }

        $this->key       = $this->get_encryption_key();
        $this->encryptor = new \Peanut\FormCore\Crypto\Encryptor($this->key);
    }

    /**
     * Get or generate the encryption key
     */
    private function get_encryption_key(): string {
        return \Peanut\FormCore\Crypto\Encryptor::deriveKey(
            defined('FFFL_ENCRYPTION_KEY') ? (string) FFFL_ENCRYPTION_KEY : null,
            (string) wp_salt('auth')
        );
    }

    /**
     * Encrypt data
     */
    public function encrypt(string $data): string {
        return $this->encryptor->encrypt($data);
    }

    /**
     * Decrypt data
     */
    public function decrypt(string $data): string {
        return $this->encryptor->decrypt($data);
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
        return \Peanut\FormCore\Crypto\SensitiveValue::hash($data);
    }

    /**
     * Verify a value against its hash
     */
    public static function verify_hash(string $data, string $hash): bool {
        return \Peanut\FormCore\Crypto\SensitiveValue::verifyHash($data, $hash);
    }

    /**
     * Mask sensitive data for display (e.g., account numbers)
     */
    public static function mask(string $data, int $visible_start = 0, int $visible_end = 4): string {
        return \Peanut\FormCore\Crypto\SensitiveValue::mask($data, $visible_start, $visible_end);
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
        return \Peanut\FormCore\Crypto\EncryptionKeyNotice::keyStatus('FFFL_ENCRYPTION_KEY', 'formflow-lite');
    }

    /**
     * Register admin notices for encryption key issues
     * Call this during plugin initialization
     */
    public static function register_admin_notices(): void {
        (new \Peanut\FormCore\Crypto\EncryptionKeyNotice(
            'FFFL_ENCRYPTION_KEY',
            'FormFlow',
            'formflow-lite',
            ['plugins'],
            'formflow'
        ))->register();
    }

    public static function generate_key(): string {
        return \Peanut\FormCore\Crypto\EncryptionKeyNotice::generateKey();
    }
}
