<?php
/**
 * Regression tests for the appointment confirmation screens.
 *
 * The success templates echoed the raw values the API stores straight into the
 * "Time" and "Date" rows, so a customer who booked 11:00 AM - 2:00 PM on the
 * 14th was shown "MD" and "2026-09-14". "MD" is the internal IntelliSOURCE
 * code for Midday, which reads as the state abbreviation for Maryland - which
 * is how it survived on a Maryland program without anyone noticing.
 *
 * @package FormFlow_Lite\Tests
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Utilities;

final class ConfirmationDisplayTest extends TestCase
{
    /**
     * @dataProvider slotProvider
     */
    public function test_code_renders_as_customer_facing_range(string $code, string $expected): void
    {
        $this->assertSame($expected, Utilities::getTimeSlotDisplay($code));
    }

    public static function slotProvider(): array
    {
        return [
            'morning' => ['AM', '8:00 AM - 11:00 AM'],
            'midday' => ['MD', '11:00 AM - 2:00 PM'],
            'afternoon' => ['PM', '2:00 PM - 5:00 PM'],
            'evening' => ['EV', '5:00 PM - 8:00 PM'],
        ];
    }

    /**
     * The API and the front end have both been seen to use lowercase codes.
     */
    public function test_lowercase_codes_resolve(): void
    {
        $this->assertSame('11:00 AM - 2:00 PM', Utilities::getTimeSlotDisplay('md'));
        $this->assertSame('5:00 PM - 8:00 PM', Utilities::getTimeSlotDisplay('ev'));
    }

    /**
     * The bug itself: "MD" must never reach the screen as a bare code.
     */
    public function test_midday_code_is_not_echoed_verbatim(): void
    {
        $this->assertNotSame('MD', Utilities::getTimeSlotDisplay('MD'));
    }

    public function test_unknown_slot_code_falls_back_to_empty_string(): void
    {
        $this->assertSame('', Utilities::getTimeSlotDisplay('ZZ'));
        $this->assertSame('', Utilities::getTimeSlotDisplay(''));
    }

    /**
     * The date row printed the raw Y-m-d. It should match the shape the
     * booking summary already uses.
     */
    public function test_appointment_date_renders_in_long_form(): void
    {
        $this->assertSame(
            'Monday, September 14, 2026',
            Utilities::getAppointmentDateDisplay('2026-09-14')
        );
    }

    public function test_appointment_date_handles_missing_or_junk_values(): void
    {
        $this->assertSame('', Utilities::getAppointmentDateDisplay(''));
        $this->assertSame('', Utilities::getAppointmentDateDisplay('   '));
        $this->assertSame('', Utilities::getAppointmentDateDisplay('not-a-date'));
    }

    /**
     * Pin the templates themselves: neither success screen may echo the raw
     * schedule_time / schedule_date values again.
     */
    public function test_success_templates_do_not_echo_raw_schedule_values(): void
    {
        $templates = [
            'public/templates/enrollment/success.php',
            'public/templates/scheduler/success.php',
            // The review step shows the same two values before the customer
            // commits, and had the same raw-value bug.
            'public/templates/enrollment/step-5-confirm.php',
        ];

        foreach ($templates as $template) {
            $markup = file_get_contents(FFFL_PLUGIN_DIR . $template);

            $this->assertStringNotContainsString(
                "esc_html(\$form_data['schedule_time']",
                $markup,
                "{$template} still echoes the raw time slot code"
            );
            $this->assertStringNotContainsString(
                "esc_html(\$form_data['schedule_date']",
                $markup,
                "{$template} still echoes the raw appointment date"
            );
            $this->assertStringContainsString('getTimeSlotDisplay', $markup);
            $this->assertStringContainsString('getAppointmentDateDisplay', $markup);
        }
    }
}
