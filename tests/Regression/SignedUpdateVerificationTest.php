<?php
/**
 * Regression guard for audit finding L1 — FormFlow Lite installed plugin updates
 * with NO signature verification.
 *
 * Lite has no updater of its own; the license-server mu-plugin hands it a
 * package and, before this, WordPress installed it on transport trust alone. As
 * the free tier it is also the widest install base in the fleet, which made it
 * the softest supply-chain target we had.
 *
 * These pin BOTH halves of the fix: the shared verifier is actually bundled, and
 * the plugin actually registers the gate.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Regression;

use PHPUnit\Framework\TestCase;
use Peanut\FormCore\Update\PackageVerifier;

final class SignedUpdateVerificationTest extends TestCase
{
    private function pluginSource(): string
    {
        return (string) file_get_contents(dirname(__DIR__, 2) . '/formflow-lite.php');
    }

    public function test_shared_verifier_is_bundled(): void
    {
        $this->assertTrue(
            class_exists(PackageVerifier::class),
            'peanut/formflow-core must be a dependency — without it the update gate cannot run.'
        );
        $this->assertTrue(class_exists(\Peanut\FormCore\Update\SignedUpdateGate::class));
    }

    public function test_plugin_registers_the_signed_update_gate(): void
    {
        $src = $this->pluginSource();
        $this->assertStringContainsString('SignedUpdateGate', $src, 'Lite must register the update gate.');
        $this->assertStringContainsString('fffl_register_update_gate', $src);
        $this->assertMatchesRegularExpression(
            '/add_action\(\s*[\'"]plugins_loaded[\'"]\s*,\s*[\'"]fffl_register_update_gate[\'"]/',
            $src,
            'The gate must be hooked, or it never runs.'
        );
    }

    public function test_plugin_pins_the_peanut_signing_key_and_hosts(): void
    {
        $src = $this->pluginSource();
        $this->assertStringContainsString('FFFL_SIGNING_PUBKEY', $src);
        // The Ed25519 key the release pipeline signs manifests against.
        $this->assertStringContainsString('NtHnWTBLVzCBKMAq9CO8LHDSD9ZfpGV0UloQdgToIwM=', $src);
        $this->assertStringContainsString("'peanutgraphic.com'", $src);
    }

    public function test_composer_requires_formflow_core(): void
    {
        $composer = json_decode((string) file_get_contents(dirname(__DIR__, 2) . '/composer.json'), true);
        $this->assertArrayHasKey('peanut/formflow-core', $composer['require'] ?? [], 'core dependency missing');
    }

    /**
     * The behaviour L1 was about: an unsigned / unverifiable package must not
     * be accepted. Exercised against the bundled primitive.
     */
    public function test_unsigned_and_tampered_packages_are_refused(): void
    {
        if (!function_exists('sodium_crypto_sign_keypair')) {
            $this->markTestSkipped('libsodium unavailable');
        }
        $kp    = sodium_crypto_sign_keypair();
        $pub   = base64_encode(sodium_crypto_sign_publickey($kp));
        $sk    = sodium_crypto_sign_secretkey($kp);
        $bytes = 'PK' . str_repeat('lite', 100);

        $signed = [
            'sha256'    => hash('sha256', $bytes),
            'signature' => base64_encode(sodium_crypto_sign_detached($bytes, $sk)),
        ];

        $this->assertTrue(PackageVerifier::verifyBytes($bytes, $signed, $pub), 'a correctly signed package must install');
        $this->assertFalse(PackageVerifier::verifyBytes($bytes, [], $pub), 'unsigned package must be refused');
        $this->assertFalse(PackageVerifier::verifyBytes($bytes . 'evil', $signed, $pub), 'tampered package must be refused');

        // And an attacker-controlled host must never be trusted.
        $hosts = ['peanutgraphic.com', 'github.com'];
        $this->assertTrue(PackageVerifier::isTrustedPackageUrl('https://peanutgraphic.com/x.zip', $hosts));
        $this->assertFalse(PackageVerifier::isTrustedPackageUrl('https://evilpeanutgraphic.com/x.zip', $hosts));
        $this->assertFalse(PackageVerifier::isTrustedPackageUrl('http://peanutgraphic.com/x.zip', $hosts));
    }
}
