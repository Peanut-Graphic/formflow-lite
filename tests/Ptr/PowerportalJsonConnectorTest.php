<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\PowerportalJson\PowerportalJsonConnector;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

/**
 * The shared base connector is utility-agnostic: everything is driven by the
 * configured api_endpoint, and it ships no named presets. A utility is a thin
 * subclass (DominionPtrConnector). These tests prove the base stands alone and
 * that the Dominion specialization still overrides id/prefix/preset correctly.
 */
class PowerportalJsonConnectorTest extends TestCase
{
    private function baseReturning(array $byPath): PowerportalJsonConnector
    {
        return new class($byPath) extends PowerportalJsonConnector {
            private array $byPath;
            public function __construct(array $byPath) { $this->byPath = $byPath; }
            protected function http_get_json(string $url, array $query = []): array {
                foreach ($this->byPath as $needle => $resp) {
                    if (strpos($url, $needle) !== false) { return $resp; }
                }
                throw new \Exception("no fixture for {$url}");
            }
        };
    }

    public function testBaseIsUtilityAgnostic(): void
    {
        $c = new PowerportalJsonConnector();
        $this->assertSame('powerportal-json', $c->get_id());
        $this->assertSame([], $c->get_presets(), 'base ships no utility presets');
        $this->assertSame(['enrollment'], $c->get_supported_features());
    }

    public function testValidateWorksAgainstAnyEndpoint(): void
    {
        $c = $this->baseReturning([
            'prospect/validate' => ['status' => 'found', 'data' => [
                'prospect_id' => 12, 'first_name' => 'A', 'last_name' => 'B', 'name' => 'A B',
                'email' => 'a@b.com', 'utility_no' => '999', 'enrollable_premises' => [['id' => 1, 'address' => 'X', 'zip' => '00000']],
            ]],
            'portal_user_emails' => ['available' => true, 'has_login_history' => false],
        ]);
        $r = $c->validate_account(
            ['account_number' => '999', 'zip' => '00000', 'email' => 'a@b.com'],
            ['api_endpoint' => 'https://some-other-utility.powerportal.com/x/api']
        );
        $this->assertTrue($r->is_valid());
        $this->assertSame(12, $r->get_customer_data()['prospect_id']);
    }

    public function testBaseDemoEnrollUsesGenericPrefix(): void
    {
        $c = new PowerportalJsonConnector();
        $r = $c->submit_enrollment(['account_number' => '999', 'email' => 'a@b.com'], ['api_endpoint' => 'https://x/api', 'test_mode' => true]);
        $this->assertTrue($r->is_successful());
        $this->assertStringStartsWith('DEMO-', $r->get_confirmation_number());
    }

    public function testDominionSpecializationOverridesIdPrefixAndPreset(): void
    {
        $d = new DominionPtrConnector();
        $this->assertInstanceOf(PowerportalJsonConnector::class, $d);
        $this->assertSame('dominion-ptr', $d->get_id());
        $this->assertArrayHasKey('dominion_ptr', $d->get_presets());

        $r = $d->submit_enrollment(['account_number' => '210010506231', 'email' => 'x@gmail.com'], ['api_endpoint' => 'https://x/api', 'test_mode' => true]);
        $this->assertStringStartsWith('PTR-DEMO-', $r->get_confirmation_number());
    }
}
