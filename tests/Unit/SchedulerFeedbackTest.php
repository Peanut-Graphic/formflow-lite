<?php
/**
 * Client feedback on the PHI scheduler (2026-07, Pepco/Delmarva).
 *
 * #1 The 5:00-8:00 PM ("ev") slot must not be offered on Pepco/Delmarva.
 *    Slots come from the IntelliSource API; we render whatever it marks
 *    available. This drops the evening slot on PHI instances only, leaving
 *    every other utility untouched.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use ReflectionMethod;

final class SchedulerFeedbackTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Two dates, each offering all four slots including evening.
     */
    private function slotsWithEvening(): array
    {
        $times = [
            'am' => ['available' => true, 'capacity' => 5, 'label' => '8:00 AM - 11:00 AM'],
            'md' => ['available' => true, 'capacity' => 5, 'label' => '11:00 AM - 2:00 PM'],
            'pm' => ['available' => true, 'capacity' => 5, 'label' => '2:00 PM - 5:00 PM'],
            'ev' => ['available' => true, 'capacity' => 5, 'label' => '5:00 PM - 8:00 PM'],
        ];

        return [
            ['date' => '08/11/2026', 'formatted_date' => 'Tuesday, August 11', 'timestamp' => strtotime('2026-08-11'), 'times' => $times],
            ['date' => '08/12/2026', 'formatted_date' => 'Wednesday, August 12', 'timestamp' => strtotime('2026-08-12'), 'times' => $times],
        ];
    }

    private function applySettings(array $slots, string $utility): array
    {
        $frontend = new \FFFL\Frontend\Frontend();

        $method = new ReflectionMethod($frontend, 'apply_scheduling_settings');
        $method->setAccessible(true);

        $instance = ['id' => 1, 'utility' => $utility, 'settings' => []];

        return $method->invoke($frontend, $slots, $instance);
    }

    /**
     * @dataProvider phiUtilities
     */
    public function test_evening_slot_is_removed_for_phi_instances(string $utility): void
    {
        $result = $this->applySettings($this->slotsWithEvening(), $utility);

        $this->assertNotEmpty($result, 'Removing evening must not remove the dates themselves.');
        foreach ($result as $slot) {
            $this->assertArrayNotHasKey(
                'ev',
                $slot['times'],
                "The 5-8 PM evening slot is still offered on {$utility}."
            );
            // The daytime slots must remain.
            $this->assertArrayHasKey('am', $slot['times']);
            $this->assertArrayHasKey('md', $slot['times']);
            $this->assertArrayHasKey('pm', $slot['times']);
        }
    }

    public static function phiUtilities(): array
    {
        return [
            'pepco md'    => ['pepco_md'],
            'pepco dc'    => ['pepco_dc'],
            'delmarva de' => ['delmarva_de'],
            'delmarva md' => ['delmarva_md'],
        ];
    }

    /**
     * Every other utility keeps its evening slot — this change is PHI-scoped.
     */
    public function test_evening_slot_is_kept_for_non_phi_instances(): void
    {
        $result = $this->applySettings($this->slotsWithEvening(), 'some_other_utility');

        foreach ($result as $slot) {
            $this->assertArrayHasKey(
                'ev',
                $slot['times'],
                'The evening slot was removed for a non-PHI utility; this change must be scoped to PHI.'
            );
        }
    }

    /**
     * A date that ONLY had an evening slot must drop out entirely once evening
     * is removed, rather than render as an empty, unpickable day.
     */
    public function test_a_date_with_only_evening_is_dropped_for_phi(): void
    {
        $slots = [[
            'date' => '08/13/2026',
            'formatted_date' => 'Thursday, August 13',
            'timestamp' => strtotime('2026-08-13'),
            'times' => [
                'ev' => ['available' => true, 'capacity' => 5, 'label' => '5:00 PM - 8:00 PM'],
            ],
        ]];

        $result = $this->applySettings($slots, 'pepco_dc');

        $this->assertSame(
            [],
            $result,
            'A PHI date whose only availability was evening must not survive as an empty day.'
        );
    }

    // -- #2 confirm page for a switch that skipped the scheduler --------------

    private function renderConfirm(array $form_data): string
    {
        $instance = ['id' => 1, 'utility' => 'pepco_dc', 'settings' => []];

        ob_start();
        include FFFL_PLUGIN_DIR . 'public/templates/enrollment/step-5-confirm.php';
        return (string) ob_get_clean();
    }

    public function test_skipped_switch_confirm_does_not_show_an_empty_appointment(): void
    {
        $html = $this->renderConfirm([
            'device_type'   => 'dcu',
            'easy_access'   => 'Yes',
            'schedule_later'=> true,
            // no schedule_date / schedule_time
        ]);

        // It must not present an empty Date/Time pair as if an appointment exists.
        $this->assertDoesNotMatchRegularExpression(
            '/id="review-date"[^>]*>\s*<\/span>/',
            $html,
            'The confirm page shows an empty appointment Date for a switch that skipped '
            . 'the scheduler — it should say no appointment is needed.'
        );
        $this->assertMatchesRegularExpression(
            '/no appointment|will be scheduled|no installation appointment|outdoor unit/i',
            $html,
            'A skipped-switch confirm page must explain that no appointment is needed.'
        );
    }

    public function test_skipped_switch_confirm_edit_does_not_point_at_the_scheduler(): void
    {
        $html = $this->renderConfirm([
            'device_type'   => 'dcu',
            'easy_access'   => 'Yes',
            'schedule_later'=> true,
        ]);

        $this->assertDoesNotMatchRegularExpression(
            '/data-goto-step="4"/',
            $html,
            'The confirm page still links Edit to the scheduler (step 4) the customer skipped.'
        );
    }

    public function test_normal_enrollment_confirm_still_shows_the_appointment(): void
    {
        $html = $this->renderConfirm([
            'device_type'   => 'thermostat',
            'schedule_date' => '08/11/2026',
            'schedule_time' => '11:00 AM - 2:00 PM',
        ]);

        $this->assertStringContainsString('08/11/2026', $html, 'A real appointment must still render.');
        $this->assertMatchesRegularExpression('/data-goto-step="4"/', $html, 'Normal Edit must still reach the scheduler.');
    }
}
