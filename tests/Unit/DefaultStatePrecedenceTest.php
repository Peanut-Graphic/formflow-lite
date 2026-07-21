<?php
/**
 * The configured Default State must win on the public form.
 *
 * Reported from the live Pepco DC form: Default State was set to DC in the
 * backend and the public form still defaulted to MD.
 *
 * Cause: the step-3 template ranked API-validated address data ABOVE the
 * instance's configured default:
 *
 *     $state = $form_data['state'] ?: $form_data['validated_state'] ?: default
 *
 * PR #20 ("honor Default State") had already moved in this direction, but only
 * for the case where validation returned an EMPTY state. A non-empty validated
 * state — which is what test/demo data returns — still shadowed the setting.
 *
 * These forms are program-specific: a DC instance serves a DC-only programme,
 * so what an operator configures in the backend is the authority for what the
 * form shows. A customer can still change the field; this is about the default.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class DefaultStatePrecedenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Resolve the state exactly as the shipped template does.
     */
    private function resolvedState(array $settings, array $form_data): string
    {
        $instance = ['id' => 1, 'settings' => $settings];

        $src = (string) file_get_contents(
            FFFL_PLUGIN_DIR . 'public/templates/enrollment/step-3-info.php'
        );

        $found = preg_match('/^\$state = (.+);$/m', $src, $m);
        $this->assertSame(1, $found, 'Could not locate the $state resolution line in the template.');

        $fffl_get_default_state = 'FFFL\Frontend\fffl_get_default_state';
        $expr = str_replace(
            'fffl_get_default_state($instance)',
            '$fffl_get_default_state($instance)',
            $m[1]
        );

        return (string) eval("return {$expr};");
    }

    /**
     * The reported bug. Backend says DC, validation (test data) says MD.
     */
    public function test_configured_default_wins_over_validated_state(): void
    {
        $state = $this->resolvedState(
            ['default_state' => 'DC'],
            ['validated_state' => 'MD']
        );

        $this->assertSame(
            'DC',
            $state,
            'The form ignored the configured Default State and used the validated one. '
            . 'On a DC-only programme that shows the wrong district to every customer.'
        );
    }

    /**
     * What the customer actually picked always wins — we are setting a default,
     * not overriding a person.
     */
    public function test_customer_selection_always_wins(): void
    {
        $state = $this->resolvedState(
            ['default_state' => 'DC'],
            ['state' => 'MD', 'validated_state' => 'MD']
        );

        $this->assertSame('MD', $state, 'A state the customer chose must never be overridden.');
    }

    /**
     * With no default configured, validated data is still useful prefill.
     */
    public function test_validated_state_is_used_when_no_default_is_configured(): void
    {
        $state = $this->resolvedState([], ['validated_state' => 'MD']);

        $this->assertSame('MD', $state, 'Without a configured default, validation should prefill.');
    }

    /**
     * The case PR #20 fixed must keep working: empty validation, use default.
     */
    public function test_empty_validated_state_falls_through_to_default(): void
    {
        $state = $this->resolvedState(['default_state' => 'DC'], ['validated_state' => '']);

        $this->assertSame('DC', $state, 'PR #20 regression: empty validation must not shadow the default.');
    }

    public function test_nothing_configured_and_nothing_validated_is_empty(): void
    {
        $this->assertSame('', $this->resolvedState([], []));
    }
}
