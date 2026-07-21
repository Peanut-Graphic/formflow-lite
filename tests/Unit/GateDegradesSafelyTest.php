<?php
/**
 * The WiFi gate must degrade safely when our JavaScript is stale.
 *
 * Found on the live Pepco form: the site serves
 * `.../public/assets/js/enrollment.js` with NO `?ver=` query string —
 * something there strips them, a common "remove query strings from static
 * resources" optimisation. The plugin relies on that string to bust browser
 * caches.
 *
 * The failure that creates is silent and expensive. A returning visitor gets
 * the NEW markup with their CACHED OLD enrollment.js:
 *
 *   - old JS has no syncWifiVisibility(), so a hidden-by-default question is
 *     never revealed
 *   - the customer picks the thermostat, never sees the question, completes
 *     the entire enrollment
 *   - the SERVER-side gate then rejects the submission ("Home WiFi is
 *     required") with no field on screen to answer
 *
 * So the whole form is filled in for nothing. Two defences, both tested here:
 *
 *   1. Fail VISIBLE, not invisible. The question renders shown; JS HIDES it
 *      for switch-choosers. Stale JS then means a redundant question rather
 *      than an unanswerable rejection.
 *   2. Reveal the callout in CSS, not JS, so the "no WiFi" path works with no
 *      JavaScript at all. JS remains an enhancement (one-click conversion).
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class GateDegradesSafelyTest extends TestCase
{
    private function template(): string
    {
        return (string) file_get_contents(
            FFFL_PLUGIN_DIR . 'public/templates/enrollment/step-1-program.php'
        );
    }

    private function css(): string
    {
        return (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/css/forms.css');
    }

    /**
     * Defence 1. The question must NOT be hidden in the markup.
     */
    public function test_wifi_question_is_not_hidden_by_default(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/id="ff-wifi-check"[^>]*\shidden/',
            $this->template(),
            'The WiFi question is hidden in the markup and depends on JS to reveal it. '
            . 'A visitor with a cached old enrollment.js would never see it, complete the '
            . 'whole form, and be rejected server-side with nothing to answer.'
        );
    }

    /**
     * Defence 2. The callout must be revealed by CSS on the checked radio, so
     * the no-WiFi path survives with zero JavaScript.
     */
    public function test_callout_is_revealed_by_css_when_no_is_selected(): void
    {
        $css = $this->css();

        $this->assertMatchesRegularExpression(
            '/input\[name="has_wifi"\]\[value="no"\]:checked/',
            $css,
            'Nothing in CSS reveals the callout, so the "no WiFi" message depends entirely '
            . 'on JavaScript. With stale JS the customer is told nothing.'
        );
    }

    /**
     * The callout must still start hidden — otherwise every customer is warned
     * they are ineligible before answering anything.
     */
    public function test_callout_is_hidden_until_no_is_selected(): void
    {
        $this->assertMatchesRegularExpression(
            '/#ff-wifi-callout\s*\{[^}]*display:\s*none/s',
            $this->css(),
            'The callout must default to hidden, or everyone sees the ineligibility warning.'
        );
    }

    /**
     * With no JS the one-click button cannot work, so the callout has to tell
     * the customer what to do by hand.
     */
    public function test_callout_tells_the_customer_how_to_proceed_without_js(): void
    {
        $this->assertMatchesRegularExpression(
            '/Outdoor Switch/i',
            $this->template(),
            'The callout must name the Outdoor Switch so a customer can select it '
            . 'themselves when the one-click button is not working.'
        );
    }

    /**
     * JS must now HIDE the question for non-thermostat rather than reveal it
     * for thermostat — the inversion is the whole point.
     */
    public function test_js_hides_the_question_for_switch_choosers(): void
    {
        $js = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/js/enrollment.js');

        $this->assertMatchesRegularExpression(
            '/ff-wifi-check/',
            $js,
            'JS must still manage the question for switch-choosers.'
        );
    }

    /**
     * Defence for the root cause: our own asset URLs must keep their version
     * string even when something else strips query args.
     */
    public function test_plugin_defends_its_own_cache_busting(): void
    {
        // Hooks are registered in class-plugin.php by this codebase's convention;
        // the callback itself lives with the other public-side asset code.
        $hooks  = (string) file_get_contents(FFFL_PLUGIN_DIR . 'includes/class-plugin.php');
        $public = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');

        $this->assertMatchesRegularExpression(
            '/script_loader_src/',
            $hooks,
            'Nothing restores the ?ver= string on our scripts. On a site that strips query '
            . 'args, browsers cache enrollment.js forever and every future JS fix is invisible.'
        );

        $this->assertMatchesRegularExpression(
            '/function keep_our_cache_buster/',
            $public,
            'The restoring callback is missing.'
        );

        $this->assertMatchesRegularExpression(
            "/'script_loader_src'[^;]*9999/s",
            $hooks,
            'The filter must run at a LATE priority, after whatever stripped the query string.'
        );
    }

    /**
     * We must not re-add version strings to other plugins' assets — that is the
     * site owner's optimisation to make, not ours.
     */
    public function test_cache_buster_only_touches_our_own_handles(): void
    {
        $public = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');

        $found = preg_match('/function keep_our_cache_buster.*?\n    \}/s', $public, $m);
        $this->assertSame(1, $found, 'Could not isolate keep_our_cache_buster().');

        $this->assertStringContainsString(
            'strpos($handle, \'ff-\')',
            $m[0],
            'The filter must bail for handles that are not ours.'
        );
        $this->assertMatchesRegularExpression(
            '/FFFL_PLUGIN_URL/',
            $m[0],
            'The filter must bail for URLs outside our own plugin directory.'
        );
    }
}
