<?php
/**
 * Pins the fail-closed behaviour when peanut/formflow-core is absent.
 *
 * Context: FFFL\Encryption delegates to \Peanut\FormCore\Crypto\Encryptor
 * (adopted in #26/#28). Before this guard, a vendor-less install fataled with
 * an opaque "Class not found" on the FIRST form save or submission —
 * Database, Public, Diagnostics, the queue and the embed handler all construct
 * Encryption. formflow-lite.php guarded only the require_once and then kept
 * loading.
 *
 * The important property is NOT merely "it errors". It is that it errors
 * rather than DEGRADING: continuing without the encryptor would write
 * api_password and submission payloads to the database in plaintext, which is
 * strictly worse than refusing to run. This test would fail if someone
 * "helpfully" made Encryption fall back to a no-op or to storing raw values.
 *
 * Runs in a subprocess with NO composer autoloader, because the class under
 * test is autoloaded in-process and cannot be un-declared.
 *
 * @package FormFlow_Lite\Tests\Unit
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

class EncryptionVendorGuardTest extends TestCase
{
    /**
     * Boot Encryption in a clean PHP process with no autoloader.
     *
     * @return array{code:int,output:string}
     */
    private function runWithoutVendor(): array
    {
        $pluginRoot = dirname(__DIR__, 2);
        $script = <<<'PHP'
<?php
define('ABSPATH', '/tmp/');
function wp_salt($scheme = 'auth') { return str_repeat('x', 64); }
require_once %s . '/includes/class-encryption.php';
try {
    new \FFFL\Encryption();
    echo "CONSTRUCTED_OK";
} catch (\RuntimeException $e) {
    echo "RUNTIME_EXCEPTION:" . $e->getMessage();
} catch (\Throwable $t) {
    echo "OTHER_THROWABLE:" . get_class($t) . ':' . $t->getMessage();
}
PHP;
        $script = sprintf($script, var_export($pluginRoot, true));

        $tmp = tempnam(sys_get_temp_dir(), 'fffl_guard_') . '.php';
        file_put_contents($tmp, $script);

        $output = [];
        $code = 0;
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($tmp).' 2>&1', $output, $code);
        @unlink($tmp);

        return ['code' => $code, 'output' => implode("\n", $output)];
    }

    public function testRefusesToConstructWhenFormcoreIsMissing(): void
    {
        $result = $this->runWithoutVendor();

        $this->assertStringNotContainsString(
            'CONSTRUCTED_OK',
            $result['output'],
            'Encryption constructed without the encryptor available — it must never '
            . 'degrade, because the fallback would be storing secrets in plaintext.'
        );

        $this->assertStringContainsString(
            'RUNTIME_EXCEPTION:',
            $result['output'],
            'Expected an explicit RuntimeException, not an opaque "Class not found" fatal. '
            . "Got:\n" . $result['output']
        );
    }

    public function testFailureMessageIsActionable(): void
    {
        $output = $this->runWithoutVendor()['output'];

        // A maintainer hitting this at 2am should learn what to run.
        $this->assertStringContainsString('formflow-core', $output);
        $this->assertStringContainsString('composer install', $output);
        $this->assertStringContainsString('vendor/', $output);
    }

    public function testEncryptionStillWorksNormallyWhenFormcoreIsPresent(): void
    {
        // The guard must not have broken the ordinary path.
        $this->assertTrue(class_exists('\Peanut\FormCore\Crypto\Encryptor'));

        $encryption = new \FFFL\Encryption();
        $this->assertSame('round-trip', $encryption->decrypt($encryption->encrypt('round-trip')));
    }
}
