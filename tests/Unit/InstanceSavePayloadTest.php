<?php
/**
 * The admin save payload must carry every top-level instance flag.
 *
 * Shipped bug, 3.3.1: "Require WiFi for thermostat" could be ticked and saved
 * and it never took effect. The server-side handler read $_POST['require_wifi']
 * correctly — but admin.js builds its AJAX payload as an explicit WHITELIST:
 *
 *     test_mode: $form.find('input[name="test_mode"]').is(':checked') ? 1 : 0,
 *     demo_mode: $form.find('input[name="demo_mode"]').is(':checked') ? 1 : 0,
 *
 * `require_wifi` was never added to that list, so the browser sent nothing, the
 * server saw an absent field, and the setting stored false forever. The gate
 * could not be switched on by any means.
 *
 * Nothing caught it because the PHP save path and the template were each tested
 * in isolation; no test crossed the browser/server seam. This guards that seam
 * generally: any top-level checkbox the editor renders must appear in the
 * payload admin.js sends, so the next flag added cannot silently do nothing.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class InstanceSavePayloadTest extends TestCase
{
    private function editorSource(): string
    {
        return (string) file_get_contents(FFFL_PLUGIN_DIR . 'admin/views/instance-editor.php');
    }

    private function adminJs(): string
    {
        return (string) file_get_contents(FFFL_PLUGIN_DIR . 'admin/assets/js/admin.js');
    }

    /**
     * Isolate the fffl_save_instance payload so a match elsewhere in admin.js
     * cannot satisfy these assertions.
     */
    private function savePayload(): string
    {
        $js = $this->adminJs();

        $found = preg_match(
            "/action:\s*'fffl_save_instance'(.*?)\}\s*,\s*success/s",
            $js,
            $m
        );

        $this->assertSame(1, $found, 'Could not isolate the fffl_save_instance payload in admin.js.');

        return $m[1];
    }

    /**
     * The specific regression: the WiFi gate could not be enabled at all.
     */
    public function test_require_wifi_is_sent_by_the_save_payload(): void
    {
        $this->assertMatchesRegularExpression(
            '/require_wifi/',
            $this->savePayload(),
            'admin.js does not send require_wifi, so ticking "Require WiFi for thermostat" '
            . 'saves nothing and the gate can never turn on.'
        );
    }

    /**
     * It must be sent as a real boolean-ish value, the same shape as the
     * existing flags, or the server-side !empty() check misreads it.
     */
    public function test_require_wifi_is_sent_as_a_checkbox_state(): void
    {
        $this->assertMatchesRegularExpression(
            "/require_wifi:\s*\\\$form\.find\(\s*'input\[name=\"require_wifi\"\]'\s*\)\.is\(\s*':checked'\s*\)\s*\?\s*1\s*:\s*0/",
            $this->savePayload(),
            'require_wifi must be sent as a 1/0 checked state, matching test_mode and demo_mode.'
        );
    }

    /**
     * The general guard. Every top-level checkbox the editor renders (i.e. not
     * a settings[...] field, which rides along in the JSON blob) has to be in
     * the payload, or it silently does nothing when saved.
     */
    public function test_every_top_level_checkbox_in_the_editor_is_sent(): void
    {
        $editor = $this->editorSource();
        $payload = $this->savePayload();

        // Top-level checkboxes only: name="foo", not name="settings[foo]".
        preg_match_all(
            '/<input[^>]*type=["\']checkbox["\'][^>]*name=["\']([a-z_]+)["\']/i',
            $editor,
            $m
        );

        $names = array_values(array_unique($m[1] ?? []));
        $this->assertNotEmpty($names, 'Parsed no top-level checkboxes — the guard cannot protect anything.');

        $missing = [];
        foreach ($names as $name) {
            if (!preg_match('/\b' . preg_quote($name, '/') . '\b/', $payload)) {
                $missing[] = $name;
            }
        }

        $this->assertSame(
            [],
            $missing,
            "These editor checkboxes are never sent by admin.js, so saving them does nothing: "
            . implode(', ', $missing)
        );
    }

    /**
     * The toggle should live with the other form-behaviour settings, matching
     * FormFlow Pro. It shipped under "API Configuration", where nobody looked.
     */
    public function test_eligibility_toggle_is_in_the_form_fields_panel(): void
    {
        $editor = $this->editorSource();

        $pos = strpos($editor, 'name="require_wifi"');
        $this->assertNotFalse($pos, 'require_wifi checkbox is missing from the editor.');

        $before = substr($editor, 0, $pos);
        preg_match_all('/data-panel="([a-z-]+)"/', $before, $panels);
        $panel = end($panels[1]);

        $this->assertSame(
            'fields',
            $panel,
            'The WiFi eligibility toggle is in the "' . $panel . '" panel. It belongs with the '
            . 'other form-field settings, matching Pro, or nobody can find it.'
        );
    }
}
