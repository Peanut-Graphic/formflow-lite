<?php
/**
 * Regression guard for the admin submission-detail stored-XSS +
 * CSV formula-injection cluster (port of the merged FormFlow Pro fixes).
 *
 * Two vulnerabilities are covered:
 *
 *   1. STORED XSS (anon -> admin). admin/views/data.php renderSubmissionDetails()
 *      interpolated decrypted, attacker-submitted form_data straight into
 *      innerHTML. Only ip_address / user_agent went through escapeHtml(); every
 *      other submitted field (name, email, phone, address, promo_code,
 *      confirmation_number, schedule_date/time, account_number, the raw JSON
 *      dump, ...) was raw. Fix: route EVERY rendered value through escapeHtml()
 *      (hardened to also escape single + double quotes), and render the email as
 *      a mailto: link only when it matches an email regex.
 *
 *   2. CSV FORMULA INJECTION (two export sites). admin/class-admin.php submissions
 *      export + analytics export only did RFC-4180 quote-doubling. A cell such as
 *      `=HYPERLINK("http://evil")` opened as a live formula in Excel/Sheets. Fix:
 *      sanitize_csv_cell() prefixes an apostrophe on a leading = + - @ TAB CR,
 *      applied to every cell (headers included), and \r joins the wrap triggers.
 *
 * This test is intentionally SELF-CONTAINED (filesystem + a WordPress-free load
 * of the one class under test) so it runs under phpunit.regression.xml, which
 * has no WordPress bootstrap. Mirrors tests/Regression/AbspathGuardOrderTest.php.
 *
 * @package FormFlow_Lite\Tests\Regression
 */

namespace FFFL\Tests\Regression;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

class AdminXssAndCsvInjectionTest extends PHPUnitTestCase
{
    /** @return string absolute repo root */
    private static function repoRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * Load the Admin class file in a WordPress-free process so we can exercise
     * the pure static sanitize_csv_cell() helper directly via reflection.
     */
    private static function invokeSanitize(string $value): string
    {
        // The class file `exit`s unless ABSPATH is defined; declaring the class
        // itself executes no WP-coupled code (constructor is never called).
        if (!defined('ABSPATH')) {
            define('ABSPATH', sys_get_temp_dir() . '/fffl-regression/');
        }

        if (!class_exists('FFFL\\Admin\\Admin', false)) {
            require_once self::repoRoot() . '/admin/class-admin.php';
        }

        // Since PHP 8.1 reflection can invoke private methods without an
        // explicit setAccessible() call (which is a deprecated no-op on 8.5+).
        $method = new \ReflectionMethod('FFFL\\Admin\\Admin', 'sanitize_csv_cell');

        return $method->invoke(null, $value);
    }

    /**
     * The headline attack: a HYPERLINK formula must come back neutralized with a
     * leading apostrophe so no spreadsheet treats it as a formula.
     */
    public function testHyperlinkFormulaIsNeutralized(): void
    {
        $this->assertSame(
            "'=HYPERLINK(\"http://evil\")",
            self::invokeSanitize('=HYPERLINK("http://evil")'),
            'A leading "=" formula cell must be prefixed with an apostrophe.'
        );
    }

    /**
     * Every spreadsheet formula-trigger prefix (= + - @ TAB CR) must be defused;
     * benign values must pass through unchanged.
     */
    public function testAllFormulaTriggersAreDefusedAndBenignUntouched(): void
    {
        $triggers = ['=cmd', '+1+1', '-2+3', '@SUM(A1)', "\tTabbed", "\rCarriage"];
        foreach ($triggers as $bad) {
            $out = self::invokeSanitize($bad);
            $this->assertSame(
                "'" . $bad,
                $out,
                sprintf('Formula-trigger cell %s must be apostrophe-prefixed.', json_encode($bad))
            );
        }

        foreach (['john@doe is a name', 'Doe, John', 'normal value', '12345', '', '5551234567'] as $ok) {
            // Note: an address/email whose FIRST char is @ is a trigger and is
            // handled above; here we assert non-leading-metachar values are safe.
            $this->assertSame(
                $ok,
                self::invokeSanitize($ok),
                sprintf('Benign cell %s must be left unchanged.', json_encode($ok))
            );
        }
    }

    /**
     * Both CSV export sites must run every cell through sanitize_csv_cell() and
     * include \r in their quote-wrap trigger. A source scan keeps the guard on
     * BOTH loops (submissions + analytics), so neither can silently regress.
     */
    public function testBothExportSitesSanitizeAndWrapOnCarriageReturn(): void
    {
        $src = file_get_contents(self::repoRoot() . '/admin/class-admin.php');
        $this->assertNotFalse($src);

        // sanitize_csv_cell must be defined and invoked at least twice (once per
        // export loop). The definition line contains "function sanitize_csv_cell".
        $callCount = preg_match_all('/self::sanitize_csv_cell\s*\(/', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $callCount,
            'Both export loops (submissions + analytics) must call self::sanitize_csv_cell().'
        );

        // Each RFC-4180 escaper closure must also wrap when the field contains a
        // carriage return, not just a newline. Count the \r wrap triggers.
        $crWrap = preg_match_all('/strpos\(\$field,\s*"\\\\r"\)\s*!==\s*false/', $src);
        $this->assertGreaterThanOrEqual(
            2,
            $crWrap,
            'Both export escapers must add a \\r trigger to the quote-wrap condition.'
        );
    }

    /**
     * Guard the XSS fix: in renderSubmissionDetails(), no submitted form_data
     * (fd.*) or submission (s.*) field may be interpolated into the HTML string
     * raw. Every dynamic insertion must be wrapped in escapeHtml() or the
     * dedicated renderEmailCell() helper. This scans the JS block in data.php.
     */
    public function testSubmissionDetailFieldsAreEscaped(): void
    {
        $src = file_get_contents(self::repoRoot() . '/admin/views/data.php');
        $this->assertNotFalse($src);

        // Hardened escapeHtml must escape both quote styles (attribute-safe).
        $this->assertStringContainsString("replace(/\"/g, '&quot;')", $src, 'escapeHtml must escape double quotes.');
        $this->assertStringContainsString("replace(/'/g, '&#039;')", $src, 'escapeHtml must escape single quotes.');

        // The email cell must be routed through renderEmailCell (regex-gated
        // mailto), never interpolated raw into a mailto: href.
        $this->assertStringContainsString('renderEmailCell(fd.email)', $src, 'Email must go through renderEmailCell().');
        $this->assertDoesNotMatchRegularExpression(
            '/mailto:\'\s*\+\s*fd\.email/',
            $src,
            'The raw fd.email must never be concatenated straight into a mailto: href.'
        );

        // Spot-check that the previously-raw fields are now wrapped. Each of
        // these MUST appear inside an escapeHtml(...) / renderEmailCell(...) call.
        $mustBeEscaped = [
            'fd.phone',
            'fd.promo_code',
            'fd.confirmation_number',
            'fd.schedule_date',
            's.account_number',
            's.session_id',
        ];
        foreach ($mustBeEscaped as $field) {
            $this->assertMatchesRegularExpression(
                '/escapeHtml\([^;]*' . preg_quote($field, '/') . '/',
                $src,
                sprintf('Submitted field %s must be rendered through escapeHtml().', $field)
            );
        }

        // The raw JSON dump must also be escaped before hitting innerHTML.
        $this->assertStringContainsString(
            'escapeHtml(JSON.stringify(fd, null, 2))',
            $src,
            'The raw form-data JSON dump must be escaped before innerHTML insertion.'
        );

        // Belt-and-braces: the old raw interpolations must be gone.
        foreach (["+ fd.phone +", "+ fd.promo_code +", "+ fd.confirmation_number +"] as $rawBad) {
            $this->assertStringNotContainsString(
                $rawBad,
                $src,
                sprintf('Raw unescaped interpolation "%s" must no longer exist.', $rawBad)
            );
        }
    }
}
