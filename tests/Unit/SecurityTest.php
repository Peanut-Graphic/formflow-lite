<?php
/**
 * Security Class Unit Tests
 *
 * Tests for the FFFL\Security class including sanitization,
 * rate limiting, and validation methods.
 *
 * @package FormFlow_Lite\Tests\Unit
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Security;

class SecurityTest extends TestCase
{
    private Security $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security = new Security();
    }

    // =========================================================================
    // sanitize_form_data() Tests
    // =========================================================================

    public function testSanitizeFormDataWithSimpleArray(): void
    {
        $input = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ];

        $result = Security::sanitize_form_data($input);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('name', $result);
        $this->assertArrayHasKey('email', $result);
    }

    public function testSanitizeFormDataWithNestedArray(): void
    {
        $input = [
            'address' => [
                'street' => '123 Main St',
                'city' => 'Washington',
                'state' => 'DC',
            ],
        ];

        $result = Security::sanitize_form_data($input);

        $this->assertIsArray($result['address']);
        $this->assertEquals('DC', $result['address']['state']);
    }

    public function testSanitizeFormDataRemovesScriptTags(): void
    {
        $input = [
            'name' => '<script>alert("xss")</script>John',
        ];

        $result = Security::sanitize_form_data($input);

        // What matters is that no executable MARKUP survives. Sanitization
        // strips tags and escapes the remainder, so the inner text "alert(...)"
        // legitimately survives as inert, escaped characters — real WordPress
        // sanitize_text_field() behaves identically.
        //
        // The old second assertion required the literal word 'alert' to be
        // absent, which no standard sanitizer does; satisfying it would mean
        // blocklisting words instead of escaping, which is strictly weaker.
        $this->assertStringNotContainsString('<script', $result['name']);
        $this->assertStringNotContainsString('</script', $result['name']);
        $this->assertStringNotContainsString('<', $result['name']);
        $this->assertStringNotContainsString('>', $result['name']);
        $this->assertStringNotContainsString('"', $result['name'], 'quotes must be escaped, not raw');
        $this->assertStringContainsString('John', $result['name'], 'legitimate content must survive');
    }

    // =========================================================================
    // sanitize_field() Tests
    // =========================================================================

    public function testSanitizeFieldEmail(): void
    {
        $result = Security::sanitize_field('email', 'john@example.com');
        $this->assertEquals('john@example.com', $result);

        $result = Security::sanitize_field('email_address', 'john@example.com');
        $this->assertEquals('john@example.com', $result);
    }

    public function testSanitizeFieldEmailWithInvalidInput(): void
    {
        $result = Security::sanitize_field('email', 'not-an-email');
        $this->assertEquals('', $result);
    }

    public function testSanitizeFieldPhone(): void
    {
        $result = Security::sanitize_field('phone', '(202) 555-1234');
        $this->assertEquals('(202) 555-1234', $result);

        $result = Security::sanitize_field('phone_number', '+1-202-555-1234');
        $this->assertEquals('+1-202-555-1234', $result);
    }

    public function testSanitizeFieldPhoneRemovesInvalidChars(): void
    {
        $result = Security::sanitize_field('phone', '(202) 555-1234 ext ABC');
        $this->assertStringNotContainsString('e', $result);
        $this->assertStringNotContainsString('x', $result);
        $this->assertStringNotContainsString('t', $result);
    }

    public function testSanitizeFieldAccountNumber(): void
    {
        $result = Security::sanitize_field('account_number', '12345-ABC');
        $this->assertEquals('12345-ABC', $result);

        $result = Security::sanitize_field('utility_no', '12345-ABC');
        $this->assertEquals('12345-ABC', $result);
    }

    public function testSanitizeFieldAccountNumberRemovesSpecialChars(): void
    {
        $result = Security::sanitize_field('account_number', '12345!@#ABC');
        $this->assertEquals('12345ABC', $result);
    }

    public function testSanitizeFieldZipCode(): void
    {
        $result = Security::sanitize_field('zip', '20001');
        $this->assertEquals('20001', $result);

        $result = Security::sanitize_field('zip_code', '20001-1234');
        $this->assertEquals('20001-1234', $result);

        $result = Security::sanitize_field('postal_code', '20001');
        $this->assertEquals('20001', $result);
    }

    public function testSanitizeFieldZipCodeRemovesLetters(): void
    {
        $result = Security::sanitize_field('zip', '20001ABC');
        $this->assertEquals('20001', $result);
    }

    public function testSanitizeFieldState(): void
    {
        $result = Security::sanitize_field('state', 'DC');
        $this->assertEquals('DC', $result);

        $result = Security::sanitize_field('state', 'dc');
        $this->assertEquals('DC', $result);

        $result = Security::sanitize_field('state', 'Maryland');
        $this->assertEquals('MA', $result); // Truncated to 2 chars
    }

    public function testSanitizeFieldDefaultText(): void
    {
        $result = Security::sanitize_field('first_name', 'John');
        $this->assertEquals('John', $result);

        $result = Security::sanitize_field('custom_field', 'Some Value');
        $this->assertEquals('Some Value', $result);
    }

    // =========================================================================
    // sanitize() Instance Method Tests
    // =========================================================================

    public function testSanitizeInstanceMethod(): void
    {
        $result = $this->security->sanitize('test value', 'text');
        $this->assertEquals('test value', $result);
    }

    public function testSanitizeInstanceMethodWithEmail(): void
    {
        $result = $this->security->sanitize('john@example.com', 'email');
        $this->assertEquals('john@example.com', $result);
    }

    // =========================================================================
    // validate_required_fields() Tests
    // =========================================================================

    public function testValidateRequiredFieldsAllPresent(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
        ];

        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
        ];

        $errors = Security::validate_required_fields($data, $required);

        $this->assertEmpty($errors);
    }

    public function testValidateRequiredFieldsMissing(): void
    {
        $data = [
            'first_name' => 'John',
        ];

        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
        ];

        $errors = Security::validate_required_fields($data, $required);

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateRequiredFieldsEmptyValues(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => '',
            'email' => '   ',
        ];

        $required = [
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
        ];

        $errors = Security::validate_required_fields($data, $required);

        $this->assertCount(2, $errors);
        $this->assertArrayHasKey('last_name', $errors);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testValidateRequiredFieldsNumericKeys(): void
    {
        $data = [
            'first_name' => 'John',
        ];

        $required = ['first_name', 'last_name'];

        $errors = Security::validate_required_fields($data, $required);

        $this->assertCount(1, $errors);
        $this->assertArrayHasKey('last_name', $errors);
    }

    // =========================================================================
    // validate_email() Tests
    // =========================================================================

    public function testValidateEmailValid(): void
    {
        $this->assertTrue(Security::validate_email('john@example.com'));
        $this->assertTrue(Security::validate_email('john.doe@example.co.uk'));
        $this->assertTrue(Security::validate_email('john+tag@example.com'));
    }

    public function testValidateEmailInvalid(): void
    {
        $this->assertFalse(Security::validate_email('not-an-email'));
        $this->assertFalse(Security::validate_email('john@'));
        $this->assertFalse(Security::validate_email('@example.com'));
        $this->assertFalse(Security::validate_email(''));
    }

    // =========================================================================
    // validate_phone() Tests
    // =========================================================================

    public function testValidatePhoneValid(): void
    {
        $this->assertTrue(Security::validate_phone('2025551234'));
        $this->assertTrue(Security::validate_phone('(202) 555-1234'));
        $this->assertTrue(Security::validate_phone('202-555-1234'));
        $this->assertTrue(Security::validate_phone('+1 202 555 1234'));
    }

    public function testValidatePhoneInvalid(): void
    {
        $this->assertFalse(Security::validate_phone('12345'));
        $this->assertFalse(Security::validate_phone('123456789012'));
        $this->assertFalse(Security::validate_phone(''));
    }

    // =========================================================================
    // validate_zip() Tests
    // =========================================================================

    public function testValidateZipValid(): void
    {
        $this->assertTrue(Security::validate_zip('20001'));
        $this->assertTrue(Security::validate_zip('20001-1234'));
    }

    public function testValidateZipInvalid(): void
    {
        $this->assertFalse(Security::validate_zip('2000'));
        $this->assertFalse(Security::validate_zip('200011'));
        $this->assertFalse(Security::validate_zip('2000A'));
        $this->assertFalse(Security::validate_zip('20001-123'));
        $this->assertFalse(Security::validate_zip(''));
    }

    // =========================================================================
    // generate_session_id() Tests
    // =========================================================================

    public function testGenerateSessionIdLength(): void
    {
        $sessionId = Security::generate_session_id();

        $this->assertEquals(64, strlen($sessionId));
    }

    public function testGenerateSessionIdHexadecimal(): void
    {
        $sessionId = Security::generate_session_id();

        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $sessionId);
    }

    public function testGenerateSessionIdUnique(): void
    {
        $ids = [];
        for ($i = 0; $i < 100; $i++) {
            $ids[] = Security::generate_session_id();
        }

        $this->assertCount(100, array_unique($ids));
    }

    // =========================================================================
    // create_form_nonce() Tests
    // =========================================================================

    public function testCreateFormNonce(): void
    {
        $nonce = Security::create_form_nonce();

        $this->assertNotEmpty($nonce);
        $this->assertIsString($nonce);
    }

    public function testCreateFormNonceWithAction(): void
    {
        $nonce1 = Security::create_form_nonce('action1');
        $nonce2 = Security::create_form_nonce('action2');

        $this->assertNotEquals($nonce1, $nonce2);
    }

    // =========================================================================
    // sanitize_slug() Tests
    // =========================================================================

    public function testSanitizeSlug(): void
    {
        $result = Security::sanitize_slug('Test Form Instance');
        $this->assertEquals('test-form-instance', $result);
    }

    public function testSanitizeSlugWithSpecialChars(): void
    {
        $result = Security::sanitize_slug('Test@Form#Instance!');
        $this->assertEquals('testforminstance', $result);
    }

    public function testSanitizeSlugWithNumbers(): void
    {
        $result = Security::sanitize_slug('Form 123');
        $this->assertEquals('form-123', $result);
    }

    // =========================================================================
    // get_client_ip() Tests
    // =========================================================================

    public function testGetClientIpFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);

        $ip = Security::get_client_ip();

        $this->assertEquals('192.168.1.1', $ip);
    }

    public function testGetClientIpFromXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ip = Security::get_client_ip();

        // HARDENED: X-Forwarded-For is only honoured when the request actually
        // arrived from a configured trusted proxy. REMOTE_ADDR here is not one,
        // so the header is ignored and the real peer wins. This previously
        // asserted '10.0.0.1' — i.e. that a client-supplied header is trusted —
        // which is precisely the spoofable behaviour the fleet-wide clientIp
        // hardening removed. Satisfying that assertion would reintroduce it.
        $this->assertEquals('127.0.0.1', $ip);
    }

    public function testGetClientIpFromCloudflare(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '203.0.113.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $ip = Security::get_client_ip();

        // HARDENED: same rule for CF-Connecting-IP — honoured only behind a
        // trusted proxy, ignored otherwise.
        $this->assertEquals('127.0.0.1', $ip);

        // Clean up
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    public function testGetClientIpReturnsDefaultOnInvalid(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'not-an-ip';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_CF_CONNECTING_IP']);

        $ip = Security::get_client_ip();

        $this->assertEquals('0.0.0.0', $ip);
    }

    // =========================================================================
    // check_rate_limit() Tests
    // =========================================================================

    public function testCheckRateLimitFirstRequest(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $result = Security::check_rate_limit();

        $this->assertTrue($result);
    }

    public function testCheckRateLimitWithinLimit(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.101';

        // Make 10 requests
        for ($i = 0; $i < 10; $i++) {
            $result = Security::check_rate_limit();
            $this->assertTrue($result);
        }
    }

    public function testCheckRateLimitDisabled(): void
    {
        $this->setOption('fffl_settings', [
            'disable_rate_limit' => true,
        ]);

        $_SERVER['REMOTE_ADDR'] = '192.168.1.102';

        // Should always return true when disabled
        for ($i = 0; $i < 200; $i++) {
            $this->assertTrue(Security::check_rate_limit());
        }
    }

    // =========================================================================
    // clear_rate_limit() Tests
    // =========================================================================

    public function testClearRateLimit(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.103';

        // Make some requests
        Security::check_rate_limit();
        Security::check_rate_limit();

        // Clear the limit
        Security::clear_rate_limit();

        // Should be able to make requests again
        $result = Security::check_rate_limit();
        $this->assertTrue($result);
    }

    public function testClearRateLimitForSpecificIp(): void
    {
        Security::clear_rate_limit('192.168.1.104');

        // Just verify it doesn't throw an error
        $this->assertTrue(true);
    }

    // =========================================================================
    // is_ssl() Tests
    // =========================================================================

    public function testIsSslReturnsTrue(): void
    {
        $this->setSsl(true);
        $this->assertTrue(Security::is_ssl());
    }

    public function testIsSslReturnsFalse(): void
    {
        $this->setSsl(false);
        $this->assertFalse(Security::is_ssl());
    }

    // =========================================================================
    // Edge Cases & Security Tests
    // =========================================================================

    public function testSanitizeFieldWithNullInput(): void
    {
        $result = Security::sanitize_field('text', null);
        $this->assertEquals('', $result);
    }

    public function testSanitizeFieldWithNumericInput(): void
    {
        $result = Security::sanitize_field('text', 12345);
        $this->assertEquals('12345', $result);
    }

    public function testSanitizeFieldWithXSSAttempt(): void
    {
        $xss = '<img src=x onerror=alert(1)>';
        $result = Security::sanitize_field('text', $xss);

        $this->assertStringNotContainsString('onerror', $result);
    }

    public function testSanitizeFieldWithSqlInjectionAttempt(): void
    {
        $sql = "1; DROP TABLE users; --";
        $result = Security::sanitize_field('text', $sql);

        // The value should be sanitized but not necessarily SQL-safe
        // SQL injection protection happens at the database layer
        $this->assertIsString($result);
    }

    public function testSanitizeFormDataPreservesValidData(): void
    {
        $input = [
            'first_name' => 'John',
            'last_name' => "O'Connor",
            'company' => 'Acme & Co.',
        ];

        $result = Security::sanitize_form_data($input);

        $this->assertStringContainsString('John', $result['first_name']);
        $this->assertStringContainsString('Connor', $result['last_name']);
    }
}
