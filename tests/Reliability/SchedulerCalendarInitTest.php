<?php
/**
 * Regression: the scheduler's appointment calendar actually initializes.
 *
 * The scheduler step-2 template renders a `#ff-calendar-grid` that starts with a
 * static "Loading available dates..." spinner, which the JS replaces once it
 * fetches slots. But the slot fetch (initScheduleCalendar -> loadScheduleSlots)
 * was only triggered when `step === 4` — the ENROLLMENT form's scheduling step.
 * The standalone SCHEDULER reaches scheduling at step 2, so the fetch never
 * fired and the calendar spun forever (in demo and production alike).
 *
 * The fix keys the calendar init on the presence of `#ff-calendar-grid` in the
 * freshly loaded step rather than a hard-coded enrollment step number. These
 * assertions fail on the pre-fix code (which had no element check) and pass now.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use PHPUnit\Framework\TestCase;

final class SchedulerCalendarInitTest extends TestCase
{
    private function js(): string
    {
        return file_get_contents(dirname(__DIR__, 2) . '/public/assets/js/enrollment.js');
    }

    public function test_scheduler_step2_template_renders_the_calendar_grid(): void
    {
        $tpl = file_get_contents(dirname(__DIR__, 2) . '/public/templates/scheduler/step-2-schedule.php');
        $this->assertStringContainsString(
            'id="ff-calendar-grid"',
            $tpl,
            'The scheduler scheduling step must render the calendar grid that the JS populates.'
        );
    }

    public function test_calendar_init_is_keyed_on_the_grid_element_not_a_step_number(): void
    {
        $js = $this->js();

        // The fix: initialize whenever the loaded step contains the calendar.
        $this->assertMatchesRegularExpression(
            '/find\(\s*[\'"]#ff-calendar-grid[\'"]\s*\)[^\n]*\n\s*initScheduleCalendar\(\)/',
            $js,
            'Calendar init must be gated on the #ff-calendar-grid element so the scheduler (step 2) initializes it too, not only enrollment step 4.'
        );

        // Guard against a regression back to the step-number-only gate.
        $this->assertStringNotContainsString(
            'if (step === 4) {' . "\n" . '                        initScheduleCalendar();',
            $js,
            'Calendar init must not be gated solely on step === 4 (breaks the scheduler).'
        );
    }
}
