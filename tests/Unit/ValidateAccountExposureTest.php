<?php
/**
 * ValidateAccountExposureTest — account-validation PII oracle (audit 2026-10, LOW).
 *
 * BEFORE: fffl_validate_account answered any account number + ZIP with the
 * account holder's full name, email and service address (plus the household
 * medical-condition flag), throttled only by the shared 120 requests/minute.
 * AFTER: the response carries a masked summary only (first name, last-name
 * initial, masked email, city/state/ZIP), the full values stay in the
 * server-side session, the JS stops copying customer data into its form data,
 * and validation has its own much tighter per-IP budget.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Database\Database;
use FFFL\Frontend\Frontend;
use FFFL\Security;
use FFFL\SessionGuard;
use FFFL\Tests\TestCase;

final class ValidateAccountExposureTest extends TestCase
{
    private const INSTANCE = [
        'id' => 7, 'slug' => 'ewr', 'form_type' => 'enrollment', 'test_mode' => 0, 'is_active' => 1,
        'settings' => ['demo_mode' => true],
    ];

    private array $rows = [];
    private Frontend $frontend;

    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $this->rows = [];

        $this->frontend = new Frontend();
        $db = $this->createMock(Database::class);
        $db->method('get_instance_by_slug')->willReturn(self::INSTANCE);
        $db->method('get_submission_by_session')->willReturnCallback(function ($sid) {
            foreach ($this->rows as $row) { if ($row['session_id'] === $sid) { return $row; } }
            return null;
        });
        $db->method('create_submission')->willReturnCallback(function (array $d) {
            $this->rows[] = array_merge(['id' => count($this->rows) + 1, 'status' => 'in_progress'], $d);
            return count($this->rows);
        });
        $db->method('update_submission')->willReturn(true);
        $db->method('log')->willReturn(1);
        (new \ReflectionProperty(Frontend::class, 'db'))->setValue($this->frontend, $db);
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    private function validate(): array
    {
        $GLOBALS['mock_json_response'] = null;
        $session = SessionGuard::issue(self::INSTANCE['id']);
        $_POST = [
            'nonce' => wp_create_nonce('fffl_form_nonce'), 'instance' => self::INSTANCE['slug'],
            'session_id' => $session['session_id'], 'session_token' => $session['session_token'],
            'utility_no' => '1234567890', 'zip' => '20001',
        ];
        $this->frontend->fffl_validate_account();
        $sent = get_mock_json_response();
        $this->assertNotNull($sent);
        return $sent;
    }

    public function test_response_carries_only_a_masked_customer_summary(): void
    {
        $sent = $this->validate();
        $this->assertTrue($sent['success'], json_encode($sent));

        $json = json_encode($sent['data']);
        $this->assertStringNotContainsString('john.smith@example.com', $json, 'Full email must not be returned.');
        $this->assertStringNotContainsString('Smith', $json, 'Full last name must not be returned.');
        $this->assertStringNotContainsString('123 Main Street', $json, 'Street address must not be returned.');

        $customer = $sent['data']['customer'];
        $this->assertSame('John', $customer['first_name']);
        $this->assertSame('S.', $customer['last_name']);
        $this->assertMatchesRegularExpression('/^j\*+@example\.com$/', $customer['email']);
        $this->assertSame(['city' => 'Washington', 'state' => 'DC', 'zip' => '20001'], $customer['address']);
    }

    public function test_full_values_stay_in_the_server_session(): void
    {
        $this->validate();
        $stored = $this->rows[0]['form_data'];
        $this->assertSame('Smith', $stored['last_name']);
        $this->assertSame('john.smith@example.com', $stored['email']);
        $this->assertSame('123 Main Street', $stored['address']['street']);
    }

    public function test_validation_has_its_own_tight_per_ip_throttle(): void
    {
        $limit = Security::VALIDATE_RATE_LIMIT_DEFAULT;
        $this->assertLessThanOrEqual(30, $limit);

        for ($i = 0; $i < $limit; $i++) {
            $this->assertTrue($this->validate()['success'], "attempt {$i} should pass");
        }
        $blocked = $this->validate();
        $this->assertFalse($blocked['success'], 'Validation must be throttled well below the shared 120/min limit.');
        $this->assertSame('rate_limited', $blocked['data']['code'] ?? null);
    }

    public function test_mask_email(): void
    {
        $this->assertSame('j*********@example.com', Security::mask_email('john.smith@example.com'));
        $this->assertSame('', Security::mask_email('not-an-email'));
    }

    public function test_frontend_does_not_copy_customer_details_into_form_data(): void
    {
        $js = file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/js/enrollment.js');
        $this->assertDoesNotMatchRegularExpression('/formData\.[a-z_]+\s*=\s*response\.data\.customer\./', $js,
            'Masked customer values must never be written back into formData (they would overwrite the session).');
    }
}
