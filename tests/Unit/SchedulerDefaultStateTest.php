<?php
/**
 * Scheduler service addresses must follow the instance jurisdiction.
 *
 * Shared validation and demo accounts can return an address in a different
 * state. A scheduler belongs to one configured utility program, so its Default
 * State must be used everywhere that validated service address is displayed or
 * persisted.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use function FFFL\Frontend\fffl_apply_default_state_to_address;

final class SchedulerDefaultStateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
    }

    public function test_delaware_default_replaces_validated_dc_state(): void
    {
        $address = fffl_apply_default_state_to_address(
            ['settings' => ['default_state' => 'DE']],
            ['street' => '123 Main Street', 'city' => 'Washington', 'state' => 'DC', 'zip' => '20001']
        );

        $this->assertSame('DE', $address['state']);
    }

    public function test_district_default_replaces_validated_state(): void
    {
        $address = fffl_apply_default_state_to_address(
            ['settings' => ['default_state' => 'dc']],
            ['state' => 'MD']
        );

        $this->assertSame('DC', $address['state']);
    }

    public function test_unconfigured_instance_preserves_validated_state(): void
    {
        $address = fffl_apply_default_state_to_address(
            ['settings' => []],
            ['state' => 'MD']
        );

        $this->assertSame('MD', $address['state']);
    }

    public function test_invalid_default_does_not_replace_validated_state(): void
    {
        $address = fffl_apply_default_state_to_address(
            ['settings' => ['default_state' => 'Delaware']],
            ['state' => 'DC']
        );

        $this->assertSame('DC', $address['state']);
    }
}
