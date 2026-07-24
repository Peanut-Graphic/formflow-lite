<?php
/**
 * Client feedback on the PHI scheduler (2026-07) — the JavaScript wiring.
 *
 * This repo has no JS runtime, so these are source-contract guards in the style
 * of WifiEnrollmentJsContractTest: they pin the exact wiring that was wrong, so
 * a regression fails loudly. They prove the handlers reference the right
 * elements and route to the right steps; they do not execute a browser.
 *
 * #4 The standalone scheduler's "Confirm Appointment" button ships `disabled`
 *    and NOTHING re-enabled it — handleTimeSelection only toggled the
 *    enrollment-wizard button (#ff-schedule-continue). So on the standalone
 *    scheduler the button stayed disabled forever. It must enable when a time
 *    is picked and reset when the date changes.
 *
 * #2 A switch (dcu) with no access issue must skip the scheduler entirely.
 *    easy_access = "Yes" means the tech needs no appointment, so after step 3
 *    a dcu+easy_access enrollment must go straight to confirm (step 5), not
 *    the scheduler (step 4).
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class SchedulerJsContractTest extends TestCase
{
    private string $js = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->js = (string) file_get_contents(
            FFFL_PLUGIN_DIR . 'public/assets/js/enrollment.js'
        );
    }

    private function functionBody(string $name): string
    {
        $found = preg_match(
            '/function ' . preg_quote($name, '/') . '\s*\([^)]*\)\s*\{(.*?)\n    \}/s',
            $this->js,
            $m
        );
        $this->assertSame(1, $found, "Could not isolate {$name}().");
        return $m[1];
    }

    // -- #4 confirm button ---------------------------------------------------

    public function test_time_selection_enables_the_standalone_confirm_button(): void
    {
        $body = $this->functionBody('handleTimeSelection');

        $this->assertMatchesRegularExpression(
            "/ff-scheduler-step-2-form[^\n]*\.ff-btn-next[^\n]*\)\.prop\(\s*'disabled',\s*false\s*\)/",
            $body,
            'Selecting a time does not enable the standalone scheduler\'s Confirm button, '
            . 'so it stays disabled forever — the reported blocker.'
        );
    }

    public function test_changing_the_date_resets_the_standalone_confirm_button(): void
    {
        $body = $this->functionBody('handleDateSelection');

        $this->assertMatchesRegularExpression(
            "/ff-scheduler-step-2-form[^\n]*\.ff-btn-next[^\n]*\)\.prop\(\s*'disabled',\s*true\s*\)/",
            $body,
            'Changing the date reloads the time slots and clears the selection, so the '
            . 'Confirm button must return to disabled until a new time is picked.'
        );
    }

    // -- #2 skip scheduler for a no-access-issue switch ----------------------

    public function test_switch_with_easy_access_skips_the_scheduler(): void
    {
        $body = $this->functionBody('handleStep3Submit');

        // The routing decision must consider both the device and the access answer.
        $this->assertMatchesRegularExpression(
            "/device_type\s*===\s*'dcu'/",
            $body,
            'Step 3 does not branch on the device type, so a switch is still sent to the scheduler.'
        );
        $this->assertMatchesRegularExpression(
            '/easy_access/',
            $body,
            'Step 3 does not consider the access answer, so it cannot tell a no-access-issue '
            . 'switch (skip) from one that needs a visit (schedule).'
        );

        // A skipping enrollment must jump past the scheduler to confirm (step 5)
        // and mark itself schedule_later so the server does not expect a slot.
        $this->assertMatchesRegularExpression(
            '/goToStep\(\s*5\s*\)/',
            $body,
            'Nothing routes the no-access switch straight to confirmation (step 5).'
        );
        $this->assertMatchesRegularExpression(
            '/schedule_later\s*=\s*true/',
            $body,
            'A skipped scheduler must set schedule_later so the server does not require a slot.'
        );
    }

    public function test_step3_still_routes_the_scheduling_case_to_step_4(): void
    {
        $body = $this->functionBody('handleStep3Submit');

        // The thermostat / access-issue path must still reach the scheduler.
        $this->assertMatchesRegularExpression(
            '/goToStep\(\s*4\s*\)/',
            $body,
            'The normal path must still reach the scheduler (step 4) — the skip is only for '
            . 'a switch with no access issue.'
        );
    }

    public function test_back_from_confirm_skips_the_scheduler_it_bypassed(): void
    {
        $body = $this->functionBody('handlePrevious');

        // Back from confirm (step 5) must not land a skipped switch on the
        // scheduler (step 4) it was routed past.
        $this->assertMatchesRegularExpression(
            '/goToStep\(\s*3\s*\)/',
            $body,
            'Back from confirmation still steps to 4 for a skipped switch, dropping the '
            . 'customer on the scheduler they were meant to bypass.'
        );
    }
}
