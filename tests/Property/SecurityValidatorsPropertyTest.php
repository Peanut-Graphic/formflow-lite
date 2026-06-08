<?php
/**
 * Property-based tests (net 6) for FFFL\Security pure validators/sanitizers.
 *
 * These functions take a string and return a bool/string using only native PHP
 * (filter_var, preg_*, substr, strtoupper). No WordPress, no I/O, no clock,
 * no randomness inside the code under test — so they are ideal property targets.
 *
 * Determinism: the input *generators* here are seeded (mt_srand) so a failing
 * run reproduces exactly. The functions under test are themselves deterministic.
 *
 * Invariants asserted (real, not tautological):
 *   - validate_phone: equivalent to "10 or 11 digits after stripping non-digits".
 *   - validate_zip:   true  iff string is exactly NNNNN or NNNNN-NNNN.
 *   - validate_email: never accepts a string with no '@'; idempotent w.r.t. itself.
 *   - sanitize_field('state'): output is always 0..2 chars, uppercase A-Z only.
 *   - sanitize_field('zip'):   output contains only [0-9-]; digits are preserved in order.
 *   - sanitize_field('phone'): output contains no alphabetic characters.
 *   - sanitize_field('account_number'): output is alphanumeric + dashes only.
 *
 * @package FormFlow_Lite\Tests\Property
 */

namespace FFFL\Tests\Property;

use FFFL\Security;
use PHPUnit\Framework\TestCase;

final class SecurityValidatorsPropertyTest extends TestCase
{
    /** Number of randomized cases per property. */
    private const CASES = 2000;

    /** Fixed seed so any failure is reproducible. */
    private const SEED = 0x5EAF00D;

    protected function setUp(): void
    {
        parent::setUp();
        mt_srand(self::SEED);
    }

    /** Build a random ASCII-ish string of length 0..maxLen from a char pool. */
    private function randString(string $pool, int $maxLen): string
    {
        $len = mt_rand(0, $maxLen);
        $out = '';
        $hi = strlen($pool) - 1;
        for ($i = 0; $i < $len; $i++) {
            $out .= $pool[mt_rand(0, $hi)];
        }
        return $out;
    }

    public function testPhoneValidationMatchesDigitCountSpec(): void
    {
        $pool = "0123456789-()+ .abcXYZ";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 18);
            $digits = preg_replace('/[^0-9]/', '', $input);
            $expected = (strlen($digits) >= 10 && strlen($digits) <= 11);

            $this->assertSame(
                $expected,
                Security::validate_phone($input),
                "validate_phone disagreed with digit-count spec for input: " . var_export($input, true)
            );
        }
    }

    public function testZipValidationMatchesCanonicalFormats(): void
    {
        $pool = "0123456789-Xabc ";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 12);
            $expected = (preg_match('/^\d{5}(-\d{4})?$/', $input) === 1);

            $this->assertSame(
                $expected,
                Security::validate_zip($input),
                "validate_zip disagreed with canonical-format spec for input: " . var_export($input, true)
            );
        }

        // Anchored, well-formed positives must always pass.
        $this->assertTrue(Security::validate_zip('20001'));
        $this->assertTrue(Security::validate_zip('20001-1234'));
        // Anything with extra leading/trailing content must fail.
        $this->assertFalse(Security::validate_zip(' 20001'));
        $this->assertFalse(Security::validate_zip('20001 '));
        $this->assertFalse(Security::validate_zip('200011234'));
    }

    public function testEmailValidationRejectsMissingAtAndIsIdempotent(): void
    {
        $pool = "abcdefABCDEF0123.@_+-! #";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 24);

            // INVARIANT 1: an address with no '@' can never be valid.
            if (strpos($input, '@') === false) {
                $this->assertFalse(
                    Security::validate_email($input),
                    "validate_email accepted a string with no '@': " . var_export($input, true)
                );
            }

            // INVARIANT 2: determinism — same input, same verdict, twice.
            $this->assertSame(
                Security::validate_email($input),
                Security::validate_email($input),
                "validate_email was non-deterministic for: " . var_export($input, true)
            );
        }
    }

    public function testSanitizeStateAlwaysTwoUppercaseLetters(): void
    {
        $pool = "abcXYZ0123 -_@.!";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 10);
            $out = Security::sanitize_field('state', $input);

            $this->assertLessThanOrEqual(2, strlen($out), "state sanitize exceeded 2 chars: " . var_export($input, true));
            $this->assertSame(
                1,
                preg_match('/^[A-Z]*$/', $out),
                "state sanitize produced non-A-Z output " . var_export($out, true) . " from " . var_export($input, true)
            );
        }
    }

    public function testSanitizeZipKeepsOnlyDigitsAndDashesAndPreservesDigitOrder(): void
    {
        $pool = "0123456789-abcXYZ @.";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 14);
            $out = Security::sanitize_field('zip', $input);

            $this->assertSame(
                1,
                preg_match('/^[0-9-]*$/', $out),
                "zip sanitize leaked disallowed chars: " . var_export($out, true)
            );
            // Digits present in input must survive, in order.
            $inDigits = preg_replace('/[^0-9]/', '', $input);
            $outDigits = preg_replace('/[^0-9]/', '', $out);
            $this->assertSame(
                $inDigits,
                $outDigits,
                "zip sanitize dropped/reordered digits for: " . var_export($input, true)
            );
        }
    }

    public function testSanitizePhoneStripsAllAlphabetics(): void
    {
        $pool = "0123456789-()+ .abcdefXYZ";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 16);
            $out = Security::sanitize_field('phone', $input);

            $this->assertSame(
                0,
                preg_match('/[A-Za-z]/', $out),
                "phone sanitize left alphabetic chars: " . var_export($out, true) . " from " . var_export($input, true)
            );
        }
    }

    public function testSanitizeAccountNumberIsAlphanumericDashOnly(): void
    {
        $pool = "0123456789ABCabc-_ @.#";
        for ($i = 0; $i < self::CASES; $i++) {
            $input = $this->randString($pool, 16);
            $out = Security::sanitize_field('account_number', $input);

            $this->assertSame(
                1,
                preg_match('/^[0-9A-Za-z-]*$/', $out),
                "account_number sanitize leaked disallowed chars: " . var_export($out, true)
            );
        }
    }
}
