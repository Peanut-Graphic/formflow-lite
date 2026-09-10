<?php
/**
 * Regression: the Delmarva Delaware program is available for new forms.
 *
 * Enrollment and scheduler instances share this registry. Delaware therefore
 * has to be present both as a utility program and as a served state.
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
    private const EXPECTED_PROGRAMS = ['delmarva_de', 'delmarva_md', 'pepco_md', 'pepco_dc'];

    public function test_getAll_includes_delmarva_delaware(): void
    {
        $all = Utilities::getAll();

        $this->assertArrayHasKey('delmarva_de', $all);
        $this->assertSame(
            self::EXPECTED_PROGRAMS,
            array_keys($all),
            'Program registry should contain exactly the four served programs.'
        );
    }

    public function test_get_delmarva_de_resolves_to_delaware(): void
    {
        $this->assertSame('DE', Utilities::get('delmarva_de')['state']);
    }

    public function test_getOptions_includes_delmarva_delaware(): void
    {
        $options = Utilities::getOptions();

        $this->assertSame('Delmarva Power - Delaware', $options['delmarva_de']);
        $this->assertCount(4, $options);
    }

    public function test_getStates_offers_delaware(): void
    {
        $states = Utilities::getStates();

        $this->assertSame(
            ['DC' => 'District of Columbia', 'DE' => 'Delaware', 'MD' => 'Maryland'],
            $states,
            'Served states should be DC, DE, and MD.'
        );
    }

    public function test_all_programs_resolve(): void
    {
        $this->assertSame('DC', Utilities::get('pepco_dc')['state']);
        $this->assertSame('MD', Utilities::get('pepco_md')['state']);
        $this->assertSame('MD', Utilities::get('delmarva_md')['state']);
        $this->assertSame('DE', Utilities::get('delmarva_de')['state']);
    }
}
