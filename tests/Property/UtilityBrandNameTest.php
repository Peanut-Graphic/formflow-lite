<?php
/**
 * Regression: the account-number label resolves the correct utility brand.
 *
 * Bug: the enrollment "Verify Account" step derived the utility name as
 * `content['utility_name'] ?? 'Delmarva Power'` — and that content field is not
 * editable in the builder, so every Pepco form fell back to "Delmarva Power
 * Account Number", the wrong utility. The fix derives the brand from the form's
 * utility key via Utilities::getBrandName(). This test pins that resolver:
 * pepco_* => Pepco, delmarva_* => Delmarva Power. It is red on the pre-fix code
 * (getBrandName did not exist) and green now.
 *
 * Pure PHP: Utilities has no WordPress dependency at load beyond ABSPATH and
 * __(), both provided by the Property bootstrap.
 *
 * @package FormFlow_Lite\Tests\Property
 */

namespace FFFL\Tests\Property;

use FFFL\Utilities;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 2) . '/includes/class-utilities.php';

final class UtilityBrandNameTest extends TestCase
{
    public function test_pepco_keys_resolve_to_pepco(): void
    {
        $this->assertSame('Pepco', Utilities::getBrandName('pepco_dc'));
        $this->assertSame('Pepco', Utilities::getBrandName('pepco_md'));
    }

    public function test_delmarva_keys_resolve_to_delmarva_power(): void
    {
        $this->assertSame('Delmarva Power', Utilities::getBrandName('delmarva_md'));
        $this->assertSame('Delmarva Power', Utilities::getBrandName('delmarva_de'));
    }

    public function test_pepco_form_does_not_fall_back_to_delmarva(): void
    {
        // The exact regression: a Pepco form must never surface "Delmarva Power".
        $this->assertNotSame('Delmarva Power', Utilities::getBrandName('pepco_dc'));
    }

    public function test_unknown_utility_uses_the_generic_fallback(): void
    {
        $this->assertSame('your utility', Utilities::getBrandName(''));
        $this->assertSame('your utility', Utilities::getBrandName('unknown_key'));
        $this->assertSame('Energy Wise Rewards', Utilities::getBrandName('', 'Energy Wise Rewards'));
    }
}
