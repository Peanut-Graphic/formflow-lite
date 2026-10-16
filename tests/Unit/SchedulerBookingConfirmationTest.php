<?php
namespace FFFL\Tests\Unit;

use FFFL\Database\Database;
use FFFL\Frontend\Frontend;
use FFFL\SessionGuard;
use FFFL\Tests\TestCase;

/** A successful booking must return success after sending its confirmation. */
final class SchedulerBookingConfirmationTest extends TestCase {
    public function test_booking_persists_and_sends_the_confirmation_without_throwing(): void {
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
        $instance = ['id' => 7, 'slug' => 'ewr', 'form_type' => 'scheduler', 'is_active' => 1,
            'settings' => ['demo_mode' => true], 'test_mode' => 0];
        $session = SessionGuard::issue(7);
        $row = ['id' => 1, 'instance_id' => 7, 'session_id' => $session['session_id'],
            'status' => 'in_progress', 'form_data' => ['ca_no' => 'SYNTHETIC-ACCOUNT',
                'fsr_no' => 'SYNTHETIC-FSR', 'confirmation_number' => 'SYNTHETIC-CONFIRMATION',
                'first_name' => 'Caller', 'last_name' => 'Tester', 'email' => 'caller@example.com']];
        $row['form_data'] = \FFFL\PublicFormData::capture($row['form_data'], [
            'first_name' => 'Caller', 'last_name' => 'Tester', 'email' => 'caller@example.com',
        ], $session['session_id'], 7);
        $frontend = new Frontend();
        $db = $this->createMock(Database::class);
        $db->method('get_instance_by_slug')->willReturn($instance);
        $db->method('get_submission_by_session')->willReturnCallback(fn() => $row);
        $db->method('update_submission')->willReturnCallback(function ($id, array $data) use (&$row) {
            $row = array_merge($row, $data);
            return true;
        });
        $db->method('log')->willReturn(1);
        (new \ReflectionProperty(Frontend::class, 'db'))->setValue($frontend, $db);
        $_POST = $session + ['nonce' => wp_create_nonce('fffl_form_nonce'), 'instance' => 'ewr',
            'schedule_date' => '2026-11-02', 'schedule_time' => 'AM'];
        srand(1);
        try {
            $frontend->fffl_book_appointment();
        } finally {
            srand();
            $_POST = [];
        }
        $this->assertTrue(get_mock_json_response()['success']);
        $this->assertSame('completed', $row['status']);
        $this->assertSame('2026-11-02', $row['form_data']['schedule_date']);
        $emails = $this->getSentEmails();
        $this->assertCount(1, $emails);
        $this->assertSame('caller@example.com', $emails[0]['to']);
        $this->assertStringContainsString('SYNTHETIC-CONFIRMATION', $emails[0]['message']);
    }
}
