<?php
/**
 * Regression tests for demo-mode account selection.
 *
 * The demo banner hardcoded account 1234567890 / ZIP 20001 on every form
 * instance. That is the Washington, DC record, so following the banner's own
 * instructions on a Maryland program produced a scheduler screen headed
 * "Delmarva Maryland Residential Scheduler" above the address
 * "123 Main Street, Washington, DC 20001".
 *
 * @package FormFlow_Lite\Tests
 */

namespace FFFL\Tests\Unit;

use FFFL\Api\MockApiClient;
use FFFL\Tests\TestCase;

final class DemoAccountForStateTest extends TestCase
{
    /**
     * @dataProvider stateProvider
     */
    public function test_demo_account_matches_requested_state(string $state, string $expected_account): void
    {
        $account = MockApiClient::get_demo_account_for_state($state);

        $this->assertIsArray($account);
        $this->assertSame($expected_account, $account['account']);
        $this->assertSame($state, $account['state']);
    }

    public static function stateProvider(): array
    {
        return [
            'maryland' => ['MD', '9876543210'],
            'delaware' => ['DE', '5555555555'],
            'district of columbia' => ['DC', '1234567890'],
        ];
    }

    /**
     * The bug: a Maryland instance must not be handed the DC record.
     */
    public function test_maryland_is_not_given_the_dc_account(): void
    {
        $account = MockApiClient::get_demo_account_for_state('MD');

        $this->assertNotSame('1234567890', $account['account']);
        $this->assertNotSame('Washington', $account['city']);
    }

    /**
     * The wildcard pattern is not a dialable account number and must never be
     * advertised.
     */
    public function test_wildcard_pattern_is_never_advertised(): void
    {
        foreach (['MD', 'DE', 'DC', 'ZZ'] as $state) {
            $account = MockApiClient::get_demo_account_for_state($state);
            $this->assertStringNotContainsString('*', $account['account']);
        }
    }

    /**
     * An unrecognised state should still yield a usable account rather than
     * blanking the banner.
     */
    public function test_unknown_state_falls_back_to_a_usable_account(): void
    {
        $account = MockApiClient::get_demo_account_for_state('ZZ');

        $this->assertNotEmpty($account['account']);
        $this->assertNotEmpty($account['zip']);
    }

    /**
     * Pin the banner itself - it must not go back to a hardcoded number.
     */
    public function test_demo_banner_does_not_hardcode_an_account(): void
    {
        $markup = file_get_contents(FFFL_PLUGIN_DIR . 'public/class-public.php');

        $this->assertStringNotContainsString(
            'Try account: 1234567890 with ZIP: 20001',
            $markup,
            'Demo banner still hardcodes the DC demo account'
        );
        $this->assertStringContainsString('get_demo_account_for_state', $markup);
    }
}
