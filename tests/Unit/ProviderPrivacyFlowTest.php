<?php
namespace FFFL\Tests\Unit;

use FFFL\Api\FieldMapper;
use FFFL\Database\Database;
use FFFL\Frontend\Frontend;
use FFFL\SessionGuard;
use FFFL\Tests\TestCase;

/** Exercises the real public handlers against synthetic provider accounts. */
final class ProviderPrivacyFlowTest extends TestCase {
    private array $instance;
    private array $rows = [];
    private array $resumeTokens = [];
    private array $logs = [];
    private array $session;
    private Frontend $frontend;

    protected function setUp(): void {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
        $this->instance = ['id' => 7, 'slug' => 'ewr', 'form_type' => 'enrollment', 'test_mode' => 0,
            'is_active' => 1, 'settings' => ['demo_mode' => true]];
        $this->session = SessionGuard::issue(7);
        $_SERVER['REMOTE_ADDR'] = '203.0.113.30';
        $this->frontend = new Frontend();
        $db = $this->createMock(Database::class);
        $db->method('get_instance_by_slug')->willReturnCallback(fn() => $this->instance);
        $db->method('get_submission_by_session')->willReturnCallback(function ($session, $instance) {
            foreach ($this->rows as $row) {
                if ($row['session_id'] === $session && $row['instance_id'] === $instance) {
                    return $row;
                }
            }
            return null;
        });
        $db->method('create_submission')->willReturnCallback(function (array $data) {
            $id = count($this->rows) + 1;
            $this->rows[$id] = array_merge(['id' => $id, 'status' => 'in_progress'], $data);
            return $id;
        });
        $db->method('update_submission')->willReturnCallback(function ($id, array $data) {
            $this->rows[$id] = array_merge($this->rows[$id], $data);
            return true;
        });
        $db->method('save_resume_token')->willReturnCallback(function ($session, $instance, $token, $email) {
            $this->resumeTokens[$token] = ['session_id' => $session, 'instance_id' => $instance, 'email' => $email];
            return true;
        });
        $db->method('get_resume_token')->willReturnCallback(fn($token) => $this->resumeTokens[$token] ?? null);
        $db->method('mark_resume_token_used')->willReturn(true);
        $db->method('log')->willReturnCallback(function ($level, $message) { $this->logs[] = $message; return 1; });
        (new \ReflectionProperty(Frontend::class, 'db'))->setValue($this->frontend, $db);
    }

    protected function tearDown(): void {
        $_POST = [];
        parent::tearDown();
    }

    private function call(string $handler, array $post = []): array {
        $_POST = array_merge($this->session, ['nonce' => wp_create_nonce('fffl_form_nonce'), 'instance' => 'ewr'], $post);
        $GLOBALS['mock_json_response'] = null;
        $this->frontend->{$handler}();
        $response = get_mock_json_response();
        $this->assertNotNull($response, $handler);
        return $response;
    }

    private function validate(): void {
        $response = $this->call('fffl_validate_account', ['utility_no' => '1234567890', 'zip' => '20001']);
        $this->assertTrue($response['success'], json_encode($response));
    }

    private function assertNoProviderContact(array $response): void {
        $serialized = json_encode($response);
        foreach (['Smith', 'john.smith@example.com', '123 Main Street', 'validation_result', '_provider_validation', '_public_input', '_legacy_form_data'] as $secret) {
            $this->assertStringNotContainsString($secret, $serialized, 'Provider record/private state leaked: ' . $secret);
        }
    }

    public function test_provider_details_are_absent_from_every_enrollment_and_scheduler_render(): void {
        foreach (['enrollment' => [1, 2, 3, 4, 5, 'success'], 'scheduler' => [1, 2, 'success']] as $type => $steps) {
            $this->instance['form_type'] = $type;
            $this->session = SessionGuard::issue(7);
            $this->validate();
            foreach ($steps as $step) {
                $response = $this->call('fffl_load_step', ['step' => $step]);
                $this->assertTrue($response['success'], $type . '/' . $step . ': ' . json_encode($response));
                $this->assertNoProviderContact($response);
            }
        }
    }

    public function test_save_email_and_resume_cannot_disclose_provider_contact_to_another_mailbox(): void {
        $this->validate();
        $saved = $this->call('fffl_save_and_email', ['step' => 3, 'email' => 'caller@example.com', 'form_data' => '{}']);
        $this->assertTrue($saved['success'], json_encode([$saved, $this->logs]));
        $token = array_key_first($this->resumeTokens);
        $this->assertNotNull($token);
        $resumed = $this->call('fffl_resume_form', ['resume_token' => $token]);
        $this->assertTrue($resumed['success']);
        $this->assertNoProviderContact($resumed);
        foreach (['ca_no', 'comverge_no', 'fsr_no', 'enrollment_response', 'scheduling_result'] as $private) {
            $this->assertArrayNotHasKey($private, $resumed['data']['form_data']);
        }
    }

    private function callerInput(): array {
        return ['first_name' => 'Alice', 'last_name' => 'Tester', 'email' => 'alice.input@example.com',
            'email_confirm' => 'alice.input@example.com', 'phone' => '2025550101', 'street' => '9 Caller Lane',
            'city' => 'Washington', 'state' => 'DC', 'zip' => '20001', 'zip_confirm' => '20001',
            'device_type' => 'thermostat', 'thermostat_count' => '1', 'cycling_level' => '100',
            'ownership' => 'own', 'has_ac' => 'yes', 'agree_terms' => true, 'agree_adult' => true,
            'agree_contact' => true, 'schedule_later' => true];
    }

    public function test_caller_input_survives_save_navigation_resume_and_enrollment_mapping(): void {
        $this->validate();
        $input = $this->callerInput();
        $saved = $this->call('fffl_save_progress', ['step' => 3, 'form_data' => json_encode($input)]);
        $this->assertTrue($saved['success']);
        $rendered = $this->call('fffl_load_step', ['step' => 3]);
        $this->assertTrue($rendered['success']);
        $this->assertStringContainsString('alice.input@example.com', $rendered['data']['html']);
        $this->assertNoProviderContact($rendered);
        $saved = $this->call('fffl_save_and_email', ['step' => 3, 'email' => 'caller@example.com', 'form_data' => '{}']);
        $this->assertTrue($saved['success']);
        $resumed = $this->call('fffl_resume_form', ['resume_token' => array_key_first($this->resumeTokens)]);
        $this->assertSame('Alice', $resumed['data']['form_data']['first_name']);
        $this->assertSame('alice.input@example.com', $resumed['data']['form_data']['email']);
        $this->assertNoProviderContact($resumed);
        $enrolled = $this->call('fffl_enroll_early', ['form_data' => '{}']);
        $this->assertTrue($enrolled['success'], json_encode($enrolled));
        $mapped = FieldMapper::mapEnrollmentData(reset($this->rows)['form_data']);
        $this->assertSame('Alice', $mapped['fname']);
        $this->assertSame('Tester', $mapped['lname']);
        $this->assertSame('alice.input@example.com', $mapped['email']);
        $this->assertSame('1234567890', $mapped['utility_no']);
    }

    public function test_legacy_contact_requires_explicit_reentry_and_injected_provenance_does_not_help(): void {
        $this->rows[1] = ['id' => 1, 'status' => 'in_progress', 'instance_id' => 7,
            'session_id' => $this->session['session_id'], 'step' => 3, 'form_data' => [
                'first_name' => 'John', 'last_name' => 'Smith', 'email' => 'john.smith@example.com',
                'address' => ['street' => '123 Main Street'], 'validation_result' => ['email' => 'john.smith@example.com'],
                '_public_input' => ['version' => 1, 'fields' => ['email' => 'john.smith@example.com'], 'signature' => 'forged'],
            ]];
        $response = $this->call('fffl_load_step', ['step' => 3, 'form_data' => json_encode([
            '_public_input' => ['version' => 1, 'fields' => ['email' => 'john.smith@example.com'], 'signature' => 'forged'],
            '_provider_validation' => ['email' => 'john.smith@example.com'],
        ])]);
        $this->assertTrue($response['success']);
        $this->assertNoProviderContact($response);
        $this->call('fffl_save_progress', ['step' => 3, 'form_data' => json_encode(['email' => 'caller@example.com'])]);
        $rendered = $this->call('fffl_load_step', ['step' => 3]);
        $this->assertStringContainsString('caller@example.com', $rendered['data']['html']);
        $this->assertNoProviderContact($rendered);
    }

    public function test_scheduler_still_gets_slots_books_and_renders_a_private_confirmation(): void {
        $this->instance['form_type'] = 'scheduler';
        $this->validate();
        $slots = $this->call('fffl_get_schedule_slots');
        $this->assertTrue($slots['success'], json_encode($slots));
        $this->assertNotEmpty($slots['data']['slots']);
        srand(1);
        try {
            $booked = $this->call('fffl_book_appointment', ['schedule_date' => '2026-11-02', 'schedule_time' => 'AM']);
        } finally {
            srand();
        }
        $this->assertTrue($booked['success'], json_encode($booked));
        $emails = $this->getSentEmails();
        $this->assertCount(1, $emails);
        $this->assertSame('john.smith@example.com', $emails[0]['to']);
        $confirmation = $this->call('fffl_load_step', ['step' => 'success']);
        $this->assertTrue($confirmation['success']);
        $this->assertStringContainsString('2026-11-02', $confirmation['data']['html']);
        $this->assertNoProviderContact($confirmation);
    }
    public function test_revalidation_clears_old_scheduling_references_and_requires_fresh_slots(): void {
        $this->instance['form_type'] = 'scheduler';
        $this->validate();
        $this->assertTrue($this->call('fffl_get_schedule_slots')['success']);
        $this->assertNotEmpty($this->rows[1]['form_data']['fsr_no']);
        $this->validate();
        $this->assertArrayNotHasKey('fsr_no', $this->rows[1]['form_data']);
        $this->assertArrayNotHasKey('scheduling_result', $this->rows[1]['form_data']);
        $this->assertFalse($this->call('fffl_book_appointment', ['schedule_date' => '2026-11-02', 'schedule_time' => 'AM'])['success']);
    }

    public function test_enrolled_session_cannot_rebind_its_account(): void {
        $this->validate();
        $this->rows[1]['form_data']['enrollment_completed'] = true;
        $before = $this->rows[1]['form_data'];
        $response = $this->call('fffl_validate_account', ['utility_no' => '9999999999', 'zip' => '20002']);
        $this->assertFalse($response['success']);
        $this->assertSame('account_already_enrolled', $response['data']['code']);
        $this->assertSame($before, $this->rows[1]['form_data']);
    }

}
