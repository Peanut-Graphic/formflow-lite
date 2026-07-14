<?php
/**
 * Regression: the Delmarva Delaware program is not offered.
 *
 * The Delaware Energy Wise Rewards program was retired from the plugin (no DE
 * enrollment or scheduler). Before the fix, Utilities::getAll() included a
 * 'delmarva_de' program and Utilities::getStates() offered 'DE' as a served
 * state — so this test's assertions would have FAILED on the pre-fix code
 * (red), and pass now (green). It guards against Delaware silently returning
 * to the utility registry or the served-state list.
 *
 * Pure PHP: Utilities has no WordPress dependency at load beyond the ABSPATH
 * guard and __() (both provided by the Property bootstrap). The class is loaded
 * here directly so no plugin boot is required.
 *
 * @package FormFlow_Lite\Tests\Property
 */

namespace FFFL\Tests\Property;

use FFFL\Utilities;
use PHPUnit\Framework\TestCase;

// Utilities::getEquipmentLabel() references __(); the Property bootstrap already
// stubs it. The class file `exit`s unless ABSPATH is defined (bootstrap does).
require_once dirname(__DIR__, 2) . '/includes/class-utilities.php';

final class UtilitiesProgramRegistryTest extends TestCase
{
    /** The three — and only three — programs the plugin serves after DE removal. */
    private const EXPECTED_PROGRAMS = ['delmarva_md', 'pepco_md', 'pepco_dc'];

    public function test_getAll_excludes_delmarva_delaware(): void
    {
        $all = Utilities::getAll();

        $this->assertArrayNotHasKey('delmarva_de', $all, 'Delmarva Delaware must not be a registered program.');
        $this->assertSame(
            self::EXPECTED_PROGRAMS,
            array_keys($all),
            'Program registry should contain exactly the three served programs.'
        );
    }

    public function test_get_delmarva_de_returns_null(): void
    {
        $this->assertNull(Utilities::get('delmarva_de'), 'Resolving the removed DE program must return null.');
    }

    public function test_getOptions_excludes_delmarva_delaware(): void
    {
        $options = Utilities::getOptions();

        $this->assertArrayNotHasKey('delmarva_de', $options);
        $this->assertCount(3, $options);
    }

    public function test_getStates_no_longer_offers_delaware(): void
    {
        $states = Utilities::getStates();

        $this->assertArrayNotHasKey('DE', $states, '"DE" must not be a selectable served state.');
        $this->assertSame(
            ['DC' => 'District of Columbia', 'MD' => 'Maryland'],
            $states,
            'Served states should be DC and MD only.'
        );
    }

    /** Sanity: removing Delaware must not disturb the programs that remain. */
    public function test_remaining_programs_still_resolve(): void
    {
        $this->assertSame('DC', Utilities::get('pepco_dc')['state']);
        $this->assertSame('MD', Utilities::get('pepco_md')['state']);
        $this->assertSame('MD', Utilities::get('delmarva_md')['state']);
    }
}
