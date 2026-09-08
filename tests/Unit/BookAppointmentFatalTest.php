<?php
/**
 * Regression tests for the scheduler's booking handler.
 *
 * fffl_book_appointment() called send_confirmation_email() with two arguments
 * against a three-parameter signature. ArgumentCountError extends Error, not
 * Exception, so the handler's `catch (\Exception $e)` did not catch it and the
 * request died as an uncaught fatal - a 500 and WordPress's "There has been a
 * critical error on this website."
 *
 * The damage is worse than a failed request: the fatal fires AFTER the API
 * booking succeeds, after the submission is marked completed and after the
 * appointment.scheduled webhook fires. So the appointment was really booked
 * while the customer saw an error page, got no confirmation number and no
 * confirmation email.
 *
 * @package FormFlow_Lite\Tests
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;

final class BookAppointmentFatalTest extends TestCase
{
    private string $source = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->source = (string) file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');
    }

    /**
     * Count the arguments in a call, ignoring commas nested inside brackets.
     */
    private function argCount(string $args): int
    {
        $args = trim($args);
        if ($args === '') {
            return 0;
        }

        $depth = 0;
        $count = 1;
        foreach (str_split($args) as $char) {
            if (in_array($char, ['(', '[',], true)) {
                $depth++;
            } elseif (in_array($char, [')', ']'], true)) {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Every call must supply the arguments the signature requires. This is the
     * bug: the scheduler path passed 2 of 3.
     */
    public function test_every_confirmation_email_call_passes_all_required_args(): void
    {
        preg_match(
            '/private function send_confirmation_email\(([^)]*)\)/',
            $this->source,
            $signature
        );
        $this->assertNotEmpty($signature, 'send_confirmation_email signature not found');

        $required = 0;
        foreach (explode(',', $signature[1]) as $param) {
            // Parameters with a default are optional.
            if (trim($param) !== '' && !str_contains($param, '=')) {
                $required++;
            }
        }
        $this->assertSame(3, $required, 'Signature changed - update this guard');

        preg_match_all(
            '/\$this->send_confirmation_email\(((?:[^()]|\([^()]*\))*)\)/',
            $this->source,
            $calls,
            PREG_SET_ORDER
        );
        $this->assertNotEmpty($calls, 'No send_confirmation_email call sites found');

        foreach ($calls as $call) {
            $this->assertGreaterThanOrEqual(
                $required,
                $this->argCount($call[1]),
                "send_confirmation_email({$call[1]}) passes too few arguments - "
                    . 'ArgumentCountError is an Error, not an Exception, so this fatals'
            );
        }
    }

    /**
     * The booking handler books first and emails second. If anything after the
     * booking throws an Error rather than an Exception, the customer must still
     * get a JSON response instead of a white-screen 500.
     */
    public function test_booking_handler_catches_errors_not_just_exceptions(): void
    {
        $start = strpos($this->source, 'public function fffl_book_appointment(): void');
        $this->assertNotFalse($start, 'fffl_book_appointment not found');

        // Take the handler body up to the next method declaration.
        $next = strpos($this->source, "\n    /**", $start);
        $body = substr($this->source, $start, $next - $start);

        $this->assertStringContainsString(
            'catch (\Throwable',
            $body,
            'fffl_book_appointment must catch Throwable: it runs after the '
                . 'appointment is already booked, so an uncaught Error leaves a '
                . 'booked appointment with an error page in front of the customer'
        );
    }
}
