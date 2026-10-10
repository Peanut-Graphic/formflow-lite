<?php
/**
 * EnrollmentSessionBindingTest — cached-page session sharing (audit 2026-10, HIGH)
 * and the client-trusted account number at enrollment (same class as FormFlow
 * Pro's MEDIUM finding; found in Lite while verifying the session fix).
 *
 * BEFORE the fix the shortcode rendered a random session id into the page
 * HTML (data-session) and every public fffl_* handler looked the submission up
 * by that client-sent id alone. Behind a full-page cache every visitor of the
 * cached copy shared one id: visitor B's step 3 (step-3-info.php) rendered
 * visitor A's name / email / phone / address, and B's posts overwrote A's row.
 * Completed sessions stayed writable. fffl_enroll_early / fffl_submit_enrollment
 * merged the client's form_data over the session, so the account sent to the
 * IntelliSource enroll API (and the enrollment_completed flag) was whatever the
 * client posted.
 *
 * These tests drive the real handlers with an in-memory submissions store.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Database\Database;
use FFFL\Frontend\Frontend;
use FFFL\SessionGuard;
use FFFL\Tests\TestCase;

/** Thrown from the fake store to stop a handler at a chosen write. */
final class HaltAtWrite extends \Error
{
    public array $data;

    public function __construct(array $data)
    {
        parent::__construct('halt');
        $this->data = $data;
    }
}

final class EnrollmentSessionBindingTest extends TestCase
{
    private const INSTANCE = [
        'id'        => 7,
        'slug'      => 'ewr',
        'form_type' => 'enrollment',
        'test_mode' => 0,
        'is_active' => 1,
        'settings'  => ['demo_mode' => true],
    ];

    /** @var array<int, array> */
    private array $rows = [];
    private ?string $haltOnStatus = null;
    private Frontend $frontend;

    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';

        $_POST = [];
        $_SERVER['REMOTE_ADDR'] = '203.0.113.10';
        $this->rows = [];
        $this->haltOnStatus = null;

        $this->frontend = new Frontend();
        (new \ReflectionProperty(Frontend::class, 'db'))->setValue($this->frontend, $this->fakeDatabase());
    }

    protected function tearDown(): void
    {
        $_POST = [];
        parent::tearDown();
    }

    private function fakeDatabase(): Database
    {
        $db = $this->createMock(Database::class);
        $db->method('get_instance_by_slug')->willReturnCallback(
            fn($slug) => $slug === self::INSTANCE['slug'] ? self::INSTANCE : null
        );
        $db->method('get_instance')->willReturnCallback(
            fn($id) => (int) $id === self::INSTANCE['id'] ? self::INSTANCE : null
        );
        $db->method('get_submission_by_session')->willReturnCallback(function ($session_id, $instance_id) {
            foreach (array_reverse($this->rows, true) as $row) {
                if ($row['session_id'] === $session_id && (int) $row['instance_id'] === (int) $instance_id) {
                    return $row;
                }
            }
            return null;
        });
        $db->method('create_submission')->willReturnCallback(function (array $data) {
            $id = count($this->rows) + 1;
            $this->rows[$id] = array_merge(['id' => $id, 'status' => 'in_progress', 'form_data' => []], $data);
            return $id;
        });
        $db->method('update_submission')->willReturnCallback(function ($id, array $data) {
            if ($this->haltOnStatus !== null && ($data['status'] ?? null) === $this->haltOnStatus) {
                throw new HaltAtWrite($data);
            }
            $this->rows[$id] = array_merge($this->rows[$id], $data);
            return true;
        });
        $db->method('log')->willReturn(1);

        return $db;
    }

    /** Run a public handler and return the JSON envelope it sent. */
    private function call(string $handler, array $post): array
    {
        $GLOBALS['mock_json_response'] = null;
        $_POST = array_merge([
            'nonce'    => wp_create_nonce('fffl_form_nonce'),
            'instance' => self::INSTANCE['slug'],
        ], $post);
        $this->frontend->{$handler}();
        $sent = get_mock_json_response();
        $this->assertNotNull($sent, "{$handler} sent no JSON response.");
        return $sent;
    }

    private function startSession(): array
    {
        $sent = $this->call('fffl_start_session', []);
        $this->assertTrue($sent['success'], 'fffl_start_session must succeed for an active instance.');
        $this->assertArrayHasKey('session_id', $sent['data']);
        $this->assertArrayHasKey('session_token', $sent['data']);
        return $sent['data'];
    }

    private function seedRow(string $session_id, array $form_data, string $status = 'in_progress'): int
    {
        $id = count($this->rows) + 1;
        $this->rows[$id] = [
            'id'          => $id,
            'instance_id' => self::INSTANCE['id'],
            'session_id'  => $session_id,
            'status'      => $status,
            'step'        => 3,
            'form_data'   => $form_data,
        ];
        return $id;
    }

    private function assertRefusedWith(string $code, array $sent, string $why): void
    {
        $this->assertFalse($sent['success'], $why);
        $this->assertSame($code, $sent['data']['code'] ?? null, $why);
    }

    private const ALL_SESSION_WRITERS = [
        'fffl_save_progress', 'fffl_save_and_email', 'fffl_validate_account', 'fffl_enroll_early',
        'fffl_get_schedule_slots', 'fffl_submit_enrollment', 'fffl_book_appointment',
    ];

    private function writerPost(array $session_pair): array
    {
        return array_merge($session_pair, [
            'step'          => 3,
            'email'         => 'mallory@example.com',
            'utility_no'    => '1234567890',
            'zip'           => '20001',
            'schedule_date' => '2026-11-02',
            'schedule_time' => 'AM',
            'form_data'     => json_encode(['email' => 'mallory@example.com']),
        ]);
    }

    // ------------------------------------------------------------------
    // Cached HTML carries no session
    // ------------------------------------------------------------------

    public function test_shortcode_does_not_render_session_material_into_html(): void
    {
        $source = file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');
        preg_match('/function render_form_shortcode\(.*?\n    }\n/s', $source, $m);
        $this->assertNotEmpty($m);
        $this->assertStringNotContainsString('data-session', $m[0],
            'The form container must not carry a session id: a page cache would hand it to every visitor.');
        $this->assertStringNotContainsString('generate_session_id', $m[0]);
        $this->assertStringContainsString('SessionGuard::mark_page_uncacheable()', $m[0],
            'Rendering the form must ask page caches not to store the page.');

        $plugin = file_get_contents(FFFL_PLUGIN_DIR . 'includes/class-plugin.php');
        $this->assertStringContainsString("'fffl_start_session'", $plugin);
        $this->assertMatchesRegularExpression("/'template_redirect'\s*,\s*\[\s*\\\$this->public\s*,\s*'maybe_disable_page_cache'\s*\]/", $plugin);
    }

    public function test_frontend_js_bootstraps_session_and_sends_token(): void
    {
        $js = file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/js/enrollment.js');
        $this->assertStringContainsString("action: 'fffl_start_session'", $js);
        $this->assertDoesNotMatchRegularExpression("/sessionId\s*=\s*\\\$container\.data\('session'\)/", $js);

        $ids    = preg_match_all('/(?<![a-z_])session_id: FFEnrollment\.sessionId,/', $js);
        $tokens = preg_match_all('/session_token: FFEnrollment\.sessionToken,/', $js);
        $this->assertGreaterThan(0, $ids);
        $this->assertSame($ids, $tokens, 'Every AJAX payload that sends session_id must also send session_token.');
        $this->assertDoesNotMatchRegularExpression('/ff_session_id: FFEnrollment\.sessionId,\s*\n\s*session_token/', $js,
            'The session token must never be pushed to the GTM dataLayer.');

        $autosave = file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/js/auto-save.js');
        $this->assertStringContainsString("session_token: \$container.data('sessionToken')", $autosave);
    }

    // ------------------------------------------------------------------
    // Two visitors of one cached page
    // ------------------------------------------------------------------

    public function test_two_visitors_get_distinct_instance_bound_sessions(): void
    {
        $a = $this->startSession();
        $b = $this->startSession();

        $this->assertNotSame($a['session_id'], $b['session_id']);
        $this->assertTrue(SessionGuard::verify($a['session_id'], $a['session_token'], self::INSTANCE['id']));
        $this->assertFalse(SessionGuard::verify($a['session_id'], $b['session_token'], self::INSTANCE['id']));
        $this->assertFalse(SessionGuard::verify($a['session_id'], $a['session_token'], 8));
        $source = file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');
        $this->assertMatchesRegularExpression('/function fffl_start_session\(\): void \{\s*SessionGuard::send_private_headers\(\);/', $source,
            'The session bootstrap response must be sent with private, no-store headers.');
    }

    public function test_visitor_b_cannot_read_visitor_a_step_three(): void
    {
        $a = $this->startSession();
        $b = $this->startSession();
        $this->seedRow($a['session_id'], ['first_name' => 'Ada', 'email' => 'ada@example.com']);

        $this->assertRefusedWith(SessionGuard::ERROR_INVALID,
            $this->call('fffl_load_step', ['session_id' => $a['session_id'], 'step' => 3]),
            'load_step must refuse a bare session id (what the cached HTML used to carry).');
        $this->assertRefusedWith(SessionGuard::ERROR_INVALID,
            $this->call('fffl_load_step', ['session_id' => $a['session_id'], 'session_token' => $b['session_token'], 'step' => 3]),
            "load_step must refuse A's id with B's token.");
    }

    public function test_visitor_b_cannot_overwrite_visitor_a(): void
    {
        $a = $this->startSession();
        $b = $this->startSession();
        $rowId = $this->seedRow($a['session_id'], ['first_name' => 'Ada', 'email' => 'ada@example.com']);

        foreach (self::ALL_SESSION_WRITERS as $handler) {
            $this->assertRefusedWith(SessionGuard::ERROR_INVALID,
                $this->call($handler, $this->writerPost(['session_id' => $a['session_id'], 'session_token' => $b['session_token']])),
                "{$handler} must refuse A's id with B's token.");
        }

        $this->assertSame('ada@example.com', $this->rows[$rowId]['form_data']['email']);
        $this->assertCount(1, $this->rows);
    }

    public function test_client_chosen_session_ids_are_refused(): void
    {
        $this->assertRefusedWith(SessionGuard::ERROR_INVALID,
            $this->call('fffl_save_progress', ['session_id' => str_repeat('a', 64), 'step' => 2, 'form_data' => '{"first_name":"Eve"}']),
            'A session id the server never issued must not create a row.');
        $this->assertSame([], $this->rows);
    }

    public function test_owner_with_valid_pair_can_save_progress(): void
    {
        $a = $this->startSession();
        $sent = $this->call('fffl_save_progress', [
            'session_id' => $a['session_id'], 'session_token' => $a['session_token'],
            'step' => 1, 'form_data' => json_encode(['device_type' => 'thermostat']),
        ]);
        $this->assertTrue($sent['success']);
        $this->assertSame('thermostat', reset($this->rows)['form_data']['device_type']);
    }

    // ------------------------------------------------------------------
    // Completed sessions are frozen
    // ------------------------------------------------------------------

    public function test_completed_session_refuses_writes_and_earlier_steps(): void
    {
        $a = $this->startSession();
        $rowId = $this->seedRow($a['session_id'], ['email' => 'ada@example.com'], 'completed');
        $pair = ['session_id' => $a['session_id'], 'session_token' => $a['session_token']];

        foreach (self::ALL_SESSION_WRITERS as $handler) {
            $this->assertRefusedWith(SessionGuard::ERROR_COMPLETED, $this->call($handler, $this->writerPost($pair)),
                "{$handler} must refuse a completed session.");
        }
        $this->assertRefusedWith(SessionGuard::ERROR_COMPLETED,
            $this->call('fffl_load_step', $pair + ['step' => 3]),
            'A completed session must not re-open earlier (PII-bearing) steps.');
        $this->assertSame('ada@example.com', $this->rows[$rowId]['form_data']['email']);
    }

    // ------------------------------------------------------------------
    // Enrollment uses only the server-validated account
    // ------------------------------------------------------------------

    private function clientData(array $overrides = []): array
    {
        return array_merge([
            'has_ac' => 'yes', 'device_type' => 'thermostat', 'cycling_level' => '100',
            'utility_no' => '1234567890', 'zip' => '20001', 'zip_confirm' => '20001',
            'first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@example.com',
            'phone' => '2025550123', 'street' => '1 Main St', 'city' => 'Washington', 'state' => 'DC',
            'ownership' => 'own', 'thermostat_count' => '1', 'agree_terms' => true, 'agree_adult' => true,
            'schedule_later' => true,
        ], $overrides);
    }

    /** Run enroll_early; return the form_data it tried to persist as 'enrolled', or null + refusal. */
    private function enrollEarly(array $pair, array $client, ?array &$refusal = null): ?array
    {
        $this->haltOnStatus = 'enrolled';
        try {
            $refusal = $this->call('fffl_enroll_early', $pair + ['form_data' => json_encode($client)]);
        } catch (HaltAtWrite $halt) {
            return $halt->data['form_data'];
        } finally {
            $this->haltOnStatus = null;
        }
        return null;
    }

    public function test_enroll_refuses_a_session_whose_account_was_never_validated(): void
    {
        $a = $this->startSession();
        $pair = ['session_id' => $a['session_id'], 'session_token' => $a['session_token']];
        $this->call('fffl_save_progress', $pair + ['step' => 3, 'form_data' => json_encode($this->clientData([
            'account_number' => '5555555555', 'account_validated' => true, 'enrollment_completed' => true,
        ]))]);

        $refusal = null;
        $this->assertNull($this->enrollEarly($pair, $this->clientData(), $refusal),
            'An account never validated server-side reached the enroll API.');
        $this->assertSame('account_not_validated', $refusal['data']['code'] ?? null);

        $this->haltOnStatus = 'completed';
        try {
            $sent = $this->call('fffl_submit_enrollment', $pair + ['form_data' => json_encode($this->clientData(['enrollment_completed' => true]))]);
        } catch (HaltAtWrite $halt) {
            $this->fail('submit_enrollment completed a never-validated session.');
        } finally {
            $this->haltOnStatus = null;
        }
        $this->assertSame('account_not_validated', $sent['data']['code'] ?? null);
    }

    public function test_save_progress_cannot_seed_server_owned_keys(): void
    {
        $a = $this->startSession();
        $this->call('fffl_save_progress', [
            'session_id' => $a['session_id'], 'session_token' => $a['session_token'], 'step' => 2,
            'form_data' => json_encode([
                'first_name' => 'Eve', 'account_number' => '5', 'utility_no' => '5', 'account_validated' => true,
                'ca_no' => 'X', 'comverge_no' => 'X', 'fsr_no' => 'F', 'enrollment_completed' => true,
            ]),
        ]);
        $stored = reset($this->rows)['form_data'];
        $this->assertSame('Eve', $stored['first_name']);
        foreach (['account_number', 'utility_no', 'account_validated', 'ca_no', 'comverge_no', 'fsr_no', 'enrollment_completed'] as $key) {
            $this->assertArrayNotHasKey($key, $stored, "save_progress must not let the client write {$key}.");
        }
    }

    public function test_enroll_uses_the_validated_account_not_the_posted_one(): void
    {
        $a = $this->startSession();
        $pair = ['session_id' => $a['session_id'], 'session_token' => $a['session_token']];
        $this->seedRow($a['session_id'], [
            'account_validated' => true, 'account_number' => '1234567890', 'utility_no' => '1234567890', 'zip_code' => '20001',
        ]);

        $refusal = null;
        $enrolled = $this->enrollEarly($pair, $this->clientData([
            'utility_no' => '9999999999', 'account_number' => '9999999999',
        ]), $refusal);

        $this->assertNotNull($enrolled, 'A validated session must still enroll: ' . json_encode($refusal));
        $this->assertSame('1234567890', $enrolled['account_number']);
        $this->assertSame('1234567890', $enrolled['utility_no']);
        $this->assertSame('1234567890', \FFFL\Api\FieldMapper::mapEnrollmentData($enrolled)['utility_no'] ?? null);
    }

    public function test_validate_account_records_server_owned_validation(): void
    {
        $source = file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');
        preg_match('/function fffl_validate_account\(.*?\n    }\n/s', $source, $m);
        $this->assertNotEmpty($m);
        $this->assertStringContainsString("\$form_data['account_validated'] = true;", $m[0]);
        $this->assertStringContainsString("\$form_data['utility_no'] = \$account_number;", $m[0]);
    }
}
