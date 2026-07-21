<?php
/**
 * WifiStepOneTemplateTest — renders the real Step 1 template.
 *
 * Fragment-string tests can pass while the shipped file is broken, so this
 * includes `step-1-program.php` itself and asserts against its actual output.
 *
 * The accessibility assertions are not decoration. The callout is the moment a
 * customer is told they cannot have the thing they just chose, and WCAG AA is
 * this codebase's baseline rather than a feature: meaning carried only by the
 * colour red is invisible to a screen reader and to anyone who cannot
 * distinguish it, so the callout has to say what is wrong in words and
 * announce itself.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class WifiStepOneTemplateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
    }

    /**
     * Render the shipped template with the given instance settings.
     */
    private function render(array $settings = [], array $form_data = []): string
    {
        $instance = ['id' => 1, 'settings' => $settings];

        ob_start();
        include FFFL_PLUGIN_DIR . 'public/templates/enrollment/step-1-program.php';
        return (string) ob_get_clean();
    }

    public function test_gated_instance_renders_the_wifi_question(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertStringContainsString('name="has_wifi"', $html, 'The WiFi question is missing entirely.');
        $this->assertStringContainsString('value="yes"', $html);
        $this->assertStringContainsString('value="no"', $html);
    }

    /**
     * An instance that never opted in must render exactly what it rendered
     * before this feature existed.
     */
    public function test_ungated_instance_renders_no_wifi_question(): void
    {
        $html = $this->render([]);

        $this->assertStringNotContainsString(
            'name="has_wifi"',
            $html,
            'A utility that never asked for this gate is now being shown it.'
        );
    }

    public function test_ungated_instance_still_renders_both_device_options(): void
    {
        $html = $this->render([]);

        $this->assertStringContainsString('value="thermostat"', $html);
        $this->assertStringContainsString('value="dcu"', $html);
    }

    public function test_callout_announces_itself_to_assistive_technology(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertMatchesRegularExpression(
            '/role=["\']alert["\']/',
            $html,
            'The conversion callout must be announced. A silent colour change tells a '
            . 'screen-reader user nothing about why they cannot continue.'
        );
    }

    public function test_callout_states_the_problem_in_words_not_only_colour(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertMatchesRegularExpression(
            '/WiFi is required/i',
            $html,
            'The callout carries no text explaining the requirement, so its meaning '
            . 'depends entirely on being able to see that it is red.'
        );
    }

    public function test_callout_offers_the_switch_conversion_action(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertStringContainsString(
            'ff-convert-to-dcu',
            $html,
            'There is no conversion button, so a customer without WiFi has nowhere to go.'
        );
    }

    public function test_callout_reassures_that_the_program_is_the_same(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertMatchesRegularExpression(
            '/same participation levels/i',
            $html,
            'The client asked customers to be told the program is unchanged. Without it '
            . 'the callout reads as a downgrade and people abandon.'
        );
    }

    /**
     * The requirement is unchanged — nobody should see the ineligibility
     * warning before answering — but as of the fail-visible work it is
     * guaranteed by CSS rather than a `hidden` attribute, so that the callout
     * can be revealed by :checked without JavaScript. Assert the guarantee
     * where it now lives.
     */
    public function test_callout_is_hidden_until_the_customer_answers_no(): void
    {
        $html = $this->render(['require_wifi' => true]);

        $this->assertStringContainsString(
            'id="ff-wifi-callout"',
            $html,
            'The callout must still be rendered so CSS can reveal it.'
        );

        $css = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/css/forms.css');

        $this->assertMatchesRegularExpression(
            '/#ff-wifi-callout\s*\{[^}]*display:\s*none/s',
            $css,
            'The callout is not hidden by default, so everyone sees the ineligibility '
            . 'warning before answering.'
        );

        $this->assertMatchesRegularExpression(
            '/input\[name="has_wifi"\]\[value="no"\]:checked/',
            $css,
            'Nothing reveals the callout when "No" is chosen.'
        );
    }

    public function test_callout_copy_is_instance_overridable(): void
    {
        $html = $this->render([
            'require_wifi' => true,
            'content'      => [
                'wifi_question'        => 'Custom question text',
                'wifi_callout_heading' => 'Custom heading',
                'wifi_callout_body'    => 'Custom body',
                'wifi_convert_button'  => 'Custom button',
            ],
        ]);

        foreach (['Custom question text', 'Custom heading', 'Custom body', 'Custom button'] as $custom) {
            $this->assertStringContainsString(
                $custom,
                $html,
                "Instance content override '{$custom}' was ignored; the copy is hardcoded."
            );
        }
    }
}
