<?php
/**
 * Regression tests for the confirmation email's date and time placeholders.
 *
 * The success screens were fixed to render the arrival window and a long-form
 * date, but send_confirmation_email() kept substituting {time} with the raw
 * IntelliSOURCE slot code and {date} with the raw Y-m-d. The email is the
 * artifact the customer keeps, so it was telling them:
 *
 *     Date: 2026-09-14
 *     Time: MD
 *
 * The booking handler already computes schedule_time_display, but the email
 * never read it - that field is written and never used anywhere.
 *
 * @package FormFlow_Lite\Tests
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class ConfirmationEmailDisplayTest extends TestCase
{
    private function emailBody(): string
    {
        $source = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');
        $start = strpos($source, 'private function send_confirmation_email(');
        $this->assertNotFalse($start, 'send_confirmation_email not found');

        // Up to the placeholder array, which is all this guard cares about.
        $end = strpos($source, '// Get customizable subject', $start);

        return substr($source, $start, $end - $start);
    }

    /**
     * The email must not hand the raw slot code to the {time} placeholder.
     */
    public function test_time_placeholder_is_not_the_raw_slot_code(): void
    {
        $body = $this->emailBody();

        $this->assertMatchesRegularExpression(
            '/\$schedule_time\s*=\s*.*getTimeSlotDisplay/',
            $body,
            'Confirmation email still substitutes {time} with the raw slot code'
        );
    }

    /**
     * Nor the raw Y-m-d to {date}.
     */
    public function test_date_placeholder_is_not_the_raw_ymd(): void
    {
        $body = $this->emailBody();

        $this->assertMatchesRegularExpression(
            '/\$schedule_date\s*=\s*.*getAppointmentDateDisplay/',
            $body,
            'Confirmation email still substitutes {date} with the raw Y-m-d'
        );
    }

    /**
     * The unscheduled fallback must survive: an enrollment that skipped
     * scheduling still needs "To be scheduled" rather than a blank row.
     */
    public function test_unscheduled_fallback_is_preserved(): void
    {
        $this->assertStringContainsString(
            'To be scheduled',
            $this->emailBody(),
            'The skip-scheduling fallback copy was lost'
        );
    }
}
