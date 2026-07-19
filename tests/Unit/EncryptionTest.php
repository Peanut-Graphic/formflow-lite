<?php
/**
 * Encryption Class Unit Tests
 *
 * Tests for the FFFL\Encryption class including encrypt/decrypt,
 * hashing, and masking methods.
 *
 * @package FormFlow_Lite\Tests\Unit
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Encryption;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        parent::setUp();
        $this->encryption = new Encryption();
    }

    // =========================================================================
    // encrypt() and decrypt() Tests
    // =========================================================================

    public function testEncryptReturnsString(): void
    {
        $plaintext = 'Hello, World!';
        $encrypted = $this->encryption->encrypt($plaintext);

        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
    }

    public function testEncryptedDataIsDifferentFromPlaintext(): void
    {
        $plaintext = 'Sensitive data';
        $encrypted = $this->encryption->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
    }

    public function testDecryptReturnsOriginalData(): void
    {
        $plaintext = 'Test data for encryption';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptEmptyStringReturnsEmptyString(): void
    {
        $encrypted = $this->encryption->encrypt('');
        $this->assertEquals('', $encrypted);
    }

    public function testDecryptEmptyStringReturnsEmptyString(): void
    {
        $decrypted = $this->encryption->decrypt('');
        $this->assertEquals('', $decrypted);
    }

    public function testDecryptInvalidDataReturnsEmptyString(): void
    {
        $decrypted = $this->encryption->decrypt('not-valid-encrypted-data');
        $this->assertEquals('', $decrypted);
    }

    public function testEncryptionIsBase64Encoded(): void
    {
        $plaintext = 'Test data';
        $encrypted = $this->encryption->encrypt($plaintext);

        // Should be valid base64
        $decoded = base64_decode($encrypted, true);
        $this->assertNotFalse($decoded);
    }

    public function testSameDataEncryptedTwiceIsDifferent(): void
    {
        $plaintext = 'Test data';
        $encrypted1 = $this->encryption->encrypt($plaintext);
        $encrypted2 = $this->encryption->encrypt($plaintext);

        // Due to random IV, same data should produce different ciphertext
        $this->assertNotEquals($encrypted1, $encrypted2);

        // But both should decrypt to the same value
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted1));
        $this->assertEquals($plaintext, $this->encryption->decrypt($encrypted2));
    }

    public function testEncryptLongData(): void
    {
        $plaintext = str_repeat('A', 10000);
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptSpecialCharacters(): void
    {
        $plaintext = 'Special chars: !@#$%^&*()_+-=[]{}|;:,.<>?/~`';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptUnicodeData(): void
    {
        $plaintext = 'Unicode: Hello World - Bonjour le monde';
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptMultilineData(): void
    {
        $plaintext = "Line 1\nLine 2\nLine 3";
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    // =========================================================================
    // encrypt_array() and decrypt_array() Tests
    // =========================================================================

    public function testEncryptArrayReturnsString(): void
    {
        $data = ['key1' => 'value1', 'key2' => 'value2'];
        $encrypted = $this->encryption->encrypt_array($data);

        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
    }

    public function testDecryptArrayReturnsOriginalArray(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'nested' => [
                'key1' => 'value1',
                'key2' => 'value2',
            ],
        ];

        $encrypted = $this->encryption->encrypt_array($data);
        $decrypted = $this->encryption->decrypt_array($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function testDecryptArrayWithInvalidDataReturnsEmptyArray(): void
    {
        $result = $this->encryption->decrypt_array('invalid-encrypted-data');
        $this->assertEquals([], $result);
    }

    public function testDecryptArrayWithEmptyStringReturnsEmptyArray(): void
    {
        $result = $this->encryption->decrypt_array('');
        $this->assertEquals([], $result);
    }

    public function testEncryptArrayWithEmptyArray(): void
    {
        $encrypted = $this->encryption->encrypt_array([]);
        $decrypted = $this->encryption->decrypt_array($encrypted);

        $this->assertEquals([], $decrypted);
    }

    public function testEncryptArrayWithNumericKeys(): void
    {
        $data = ['first', 'second', 'third'];
        $encrypted = $this->encryption->encrypt_array($data);
        $decrypted = $this->encryption->decrypt_array($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function testEncryptArrayWithMixedData(): void
    {
        $data = [
            'string' => 'text',
            'number' => 42,
            'float' => 3.14,
            'bool' => true,
            'null' => null,
            'array' => [1, 2, 3],
        ];

        $encrypted = $this->encryption->encrypt_array($data);
        $decrypted = $this->encryption->decrypt_array($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    // =========================================================================
    // hash() Tests
    // =========================================================================

    public function testHashReturnsString(): void
    {
        $hash = Encryption::hash('test data');

        $this->assertIsString($hash);
        $this->assertNotEmpty($hash);
    }

    public function testHashReturnsSha256(): void
    {
        $hash = Encryption::hash('test data');

        // SHA-256 produces 64 character hex string
        $this->assertEquals(64, strlen($hash));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $hash);
    }

    public function testHashIsDeterministic(): void
    {
        $data = 'test data';
        $hash1 = Encryption::hash($data);
        $hash2 = Encryption::hash($data);

        $this->assertEquals($hash1, $hash2);
    }

    public function testHashDifferentInputsDifferentOutput(): void
    {
        $hash1 = Encryption::hash('data1');
        $hash2 = Encryption::hash('data2');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testHashEmptyString(): void
    {
        $hash = Encryption::hash('');

        $this->assertIsString($hash);
        $this->assertEquals(64, strlen($hash));
    }

    // =========================================================================
    // verify_hash() Tests
    // =========================================================================

    public function testVerifyHashCorrect(): void
    {
        $data = 'sensitive information';
        $hash = Encryption::hash($data);

        $this->assertTrue(Encryption::verify_hash($data, $hash));
    }

    public function testVerifyHashIncorrect(): void
    {
        $data = 'sensitive information';
        $hash = Encryption::hash($data);

        $this->assertFalse(Encryption::verify_hash('different data', $hash));
    }

    public function testVerifyHashTimingSafe(): void
    {
        // This tests that the comparison is timing-safe
        $data = 'sensitive information';
        $hash = Encryption::hash($data);
        $wrongHash = 'a' . substr($hash, 1);

        // Both should fail, but in a timing-safe manner
        $this->assertFalse(Encryption::verify_hash($data, $wrongHash));
    }

    // =========================================================================
    // mask() Tests
    // =========================================================================

    public function testMaskDefaultParameters(): void
    {
        $result = Encryption::mask('1234567890');

        // Default: 0 visible at start, 4 visible at end
        $this->assertEquals('******7890', $result);
    }

    public function testMaskWithStartAndEnd(): void
    {
        $result = Encryption::mask('1234567890', 2, 2);

        $this->assertEquals('12******90', $result);
    }

    /**
     * Guards the formflow-core migration: a ciphertext blob produced by the
     * PRE-EXTRACTION implementation. Round-tripping the new code against itself
     * would pass even if key derivation had drifted — while every already-stored
     * record became silently unreadable in production. This decrypts a real
     * legacy blob, so drift fails loudly in CI instead.
     */
    public function testDecryptsCiphertextWrittenBeforeTheExtraction(): void
    {
        $stored = 'AgICAgICAgICAgICAgICAh3ae/TO1m5WVgsu3oJaR7rvwlXc4/+6mE9u0CH2ExDz';

        $this->assertEquals(
            'stored-secret-value',
            $this->encryption->decrypt($stored),
            'Records written before the formflow-core extraction must still decrypt.'
        );
    }

    public function testMaskShortString(): void
    {
        $result = Encryption::mask('1234', 0, 4);

        // When the string is no longer than the visible windows it is masked
        // ENTIRELY — failing toward less disclosure. (This assertion previously
        // expected the raw value '1234'; it contradicted the comment directly
        // above it, and had never run. "Fixing" the code to satisfy it would
        // have made mask() return short secrets in full.)
        $this->assertEquals('****', $result);
    }

    public function testMaskVeryShortString(): void
    {
        $result = Encryption::mask('123', 2, 2);

        // String is shorter than visible_start + visible_end
        $this->assertEquals('***', $result);
    }

    public function testMaskEmptyString(): void
    {
        $result = Encryption::mask('');

        $this->assertEquals('', $result);
    }

    public function testMaskAccountNumber(): void
    {
        $result = Encryption::mask('ACCT-123456789', 0, 4);

        $this->assertEquals('**********6789', $result);
    }

    public function testMaskEmail(): void
    {
        $result = Encryption::mask('john.doe@example.com', 3, 4);

        $this->assertEquals('joh*************.com', $result);
    }

    public function testMaskCreditCardStyle(): void
    {
        $result = Encryption::mask('4111111111111111', 0, 4);

        $this->assertEquals('************1111', $result);
    }

    // =========================================================================
    // test() Tests
    // =========================================================================

    public function testEncryptionTest(): void
    {
        $result = $this->encryption->test();

        $this->assertTrue($result);
    }

    // =========================================================================
    // is_using_custom_key() Tests
    // =========================================================================

    public function testIsUsingCustomKeyWhenNotDefined(): void
    {
        // FFFL_ENCRYPTION_KEY is not defined in tests
        $result = Encryption::is_using_custom_key();

        $this->assertFalse($result);
    }

    // =========================================================================
    // get_key_status() Tests
    // =========================================================================

    public function testGetKeyStatusWhenNotDefined(): void
    {
        $status = Encryption::get_key_status();

        $this->assertEquals('warning', $status['status']);
        $this->assertEquals('key_not_defined', $status['code']);
        $this->assertNotEmpty($status['message']);
    }

    // =========================================================================
    // generate_key() Tests
    // =========================================================================

    public function testGenerateKeyLength(): void
    {
        $key = Encryption::generate_key();

        // 16 bytes = 32 hex characters
        $this->assertEquals(32, strlen($key));
    }

    public function testGenerateKeyIsHexadecimal(): void
    {
        $key = Encryption::generate_key();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $key);
    }

    public function testGenerateKeyUnique(): void
    {
        $keys = [];
        for ($i = 0; $i < 100; $i++) {
            $keys[] = Encryption::generate_key();
        }

        $this->assertCount(100, array_unique($keys));
    }

    // =========================================================================
    // Edge Cases & Security Tests
    // =========================================================================

    public function testEncryptionWithNullByte(): void
    {
        $plaintext = "data\x00with\x00nulls";
        $encrypted = $this->encryption->encrypt($plaintext);
        $decrypted = $this->encryption->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testDecryptWithCorruptedData(): void
    {
        $plaintext = 'test data';
        $encrypted = $this->encryption->encrypt($plaintext);

        // Corrupt the encrypted data
        $corrupted = substr($encrypted, 0, -5) . 'XXXXX';
        $decrypted = $this->encryption->decrypt($corrupted);

        $this->assertEquals('', $decrypted);
    }

    public function testDecryptWithTruncatedData(): void
    {
        $plaintext = 'test data';
        $encrypted = $this->encryption->encrypt($plaintext);

        // Truncate the encrypted data
        $truncated = substr($encrypted, 0, 10);
        $decrypted = $this->encryption->decrypt($truncated);

        $this->assertEquals('', $decrypted);
    }

    public function testEncryptionConsistencyAcrossInstances(): void
    {
        $plaintext = 'Consistent data';

        $encryption1 = new Encryption();
        $encryption2 = new Encryption();

        $encrypted = $encryption1->encrypt($plaintext);
        $decrypted = $encryption2->decrypt($encrypted);

        $this->assertEquals($plaintext, $decrypted);
    }

    public function testEncryptArrayWithDeepNesting(): void
    {
        $data = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'value' => 'deep value',
                        ],
                    ],
                ],
            ],
        ];

        $encrypted = $this->encryption->encrypt_array($data);
        $decrypted = $this->encryption->decrypt_array($encrypted);

        $this->assertEquals($data, $decrypted);
        $this->assertEquals('deep value', $decrypted['level1']['level2']['level3']['level4']['value']);
    }
}
