<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\Seeder;

/**
 * Pure-build test for the dominion-ptr instance row. Only exercises
 * Seeder::build_instance_row() (no DB) — create_instance() wraps the
 * plugin's Database::create_instance() and needs a real DB, so it is
 * covered outside this WordPress-free suite.
 */
class DominionPtrSeederTest extends TestCase
{
    public function testBuildsEnrollmentInstanceRowBoundToConnector(): void
    {
        $row = Seeder::build_instance_row();

        $this->assertSame('enrollment', $row['form_type']);
        $this->assertSame('dominion-ptr', $row['slug']);
        $this->assertSame('dominion', $row['utility']);
        $this->assertSame(1, $row['is_active']);
        $this->assertSame(1, $row['test_mode']);
        $this->assertStringContainsString('/ptr/residential/api', $row['api_endpoint']);

        $settings = json_decode($row['settings'], true);
        $this->assertSame('dominion-ptr', $settings['connector']);
        $this->assertTrue($settings['disable_device']);
        $this->assertTrue($settings['disable_scheduling']);
        $this->assertSame('Peak Time Rebates', $settings['program']['name']);
    }
}
