<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Api\AccountValidationResult;
use FFFL\Api\EnrollmentResult;

/**
 * Proves the pure PTR test harness loads the DTO foundation and WP shims.
 * Connector-specific tests are added by the Stage 1 tasks.
 */
class PtrHarnessSmokeTest extends TestCase
{
    public function testDtoFoundationLoads(): void
    {
        $v = new AccountValidationResult(['is_valid' => true, 'customer_data' => ['prospect_id' => 728]]);
        $this->assertTrue($v->is_valid());
        $this->assertSame(728, $v->get_customer_data()['prospect_id']);

        $e = new EnrollmentResult(['success' => true, 'confirmation_number' => 'PTR-DEMO-abc']);
        $this->assertTrue($e->is_successful());
        $this->assertSame('PTR-DEMO-abc', $e->get_confirmation_number());
    }

    public function testWordpressShimsAvailable(): void
    {
        $this->assertSame('hello', __('hello', 'formflow-lite'));
        $this->assertSame('{"a":1}', wp_json_encode(['a' => 1]));
    }
}
