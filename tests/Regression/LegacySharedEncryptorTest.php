<?php
namespace FFFL\Tests\Regression {
    final class LegacySharedEncryptorTest extends \PHPUnit\Framework\TestCase {
        /** @runInSeparateProcess
         * @preserveGlobalState disabled
         */
        public function testOlderSharedCoreCanLoadFirstWithoutBreakingEncryptedStorage(): void {
            if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
            require dirname(__DIR__) . '/Fixtures/legacy-shared-encryptor.php';
            require dirname(__DIR__, 2) . '/includes/class-encryption.php';
            self::assertFalse(method_exists(\Peanut\FormCore\Crypto\Encryptor::class, 'fromKeyMaterial'));
            $legacy = new \Peanut\FormCore\Crypto\Encryptor(
                \Peanut\FormCore\Crypto\Encryptor::deriveKey(null, wp_salt('auth'))
            );
            $adapter = new \FFFL\Encryption();
            $plain = '{"verification":"legacy-compatible"}';
            self::assertSame($plain, $adapter->decrypt($legacy->encrypt($plain)));
            $encrypted = $adapter->encrypt($plain);
            self::assertNotSame($plain, $encrypted);
            self::assertSame($plain, $legacy->decrypt($encrypted));
        }
    }
}

namespace {
    if (!function_exists('wp_salt')) {
        function wp_salt($scheme = 'auth') { return 'local-legacy-salt-for-encryption-test'; }
    }
}
