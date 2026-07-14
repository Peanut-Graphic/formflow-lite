<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

class DominionPtrFlowStepsTest extends TestCase
{
    public function testPtrFlowOmitsDeviceAndScheduling(): void
    {
        $connector = new DominionPtrConnector();
        $steps = $connector->enrollment_steps([
            'disable_device' => true,
            'disable_scheduling' => true,
        ]);

        $this->assertContains('validate', $steps);
        $this->assertContains('address_confirm', $steps);
        $this->assertContains('terms', $steps);
        $this->assertContains('enroll', $steps);
        $this->assertNotContains('device', $steps);
        $this->assertNotContains('scheduling', $steps);

        $this->assertSame(['validate', 'address_confirm', 'terms', 'enroll'], $steps);
    }

    public function testFlowIncludesDeviceAndSchedulingWhenNotDisabled(): void
    {
        $connector = new DominionPtrConnector();
        $steps = $connector->enrollment_steps([]);

        $this->assertContains('device', $steps);
        $this->assertContains('scheduling', $steps);

        $this->assertSame(
            ['validate', 'device', 'address_confirm', 'scheduling', 'terms', 'enroll'],
            $steps
        );
    }
}
