<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

/**
 * Verifies the dominion-ptr connector skeleton is wired correctly, without
 * going through the WordPress-backed ConnectorRegistry (this suite is
 * WordPress-free). Instantiates the connector directly.
 */
class DominionPtrConnectorRegistrationTest extends TestCase
{
    public function testConnectorIdAndFeatures(): void
    {
        $connector = new DominionPtrConnector();

        $this->assertSame('dominion-ptr', $connector->get_id());
        $this->assertSame(['enrollment'], $connector->get_supported_features());
    }

    public function testExposesDominionPtrPreset(): void
    {
        $connector = new DominionPtrConnector();
        $presets = $connector->get_presets();

        $this->assertArrayHasKey('dominion_ptr', $presets);
        $this->assertSame(
            'https://www.dominionenergyptr.com/ptr/residential/api',
            $presets['dominion_ptr']['api_endpoint']
        );
    }
}
