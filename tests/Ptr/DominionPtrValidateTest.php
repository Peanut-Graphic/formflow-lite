<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

/**
 * Live-response shape verified out-of-band against the real endpoint on
 * 2026-07-14; the fixtures below are sourced from that real
 * `prospect/validate` / `portal_user_emails` JSON.
 */
class DominionPtrValidateTest extends TestCase
{
    private function connectorReturning(array $byPath): DominionPtrConnector
    {
        // Anonymous subclass injects fixtures keyed by the last path segment.
        return new class($byPath) extends DominionPtrConnector {
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

    public function testEligibleAccountReturnsValidWithPremises(): void
    {
        $c = $this->connectorReturning([
            'prospect/validate' => ['status' => 'found', 'data' => [
                'prospect_id' => 728, 'first_name' => 'ASHOK', 'last_name' => 'RAMASUBBU',
                'name' => 'ASHOK RAMASUBBU', 'email' => 'X@GMAIL.COM', 'utility_no' => '210010506231',
                'enrollable_premises' => [['id' => 728, 'address' => '9593 SYCAMORE GROVE WAY, MECHANICSVILLE, VA 23116', 'zip' => '23116']],
            ]],
            'portal_user_emails' => ['available' => false, 'has_login_history' => false],
        ]);

        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertTrue($r->is_valid());
        $this->assertSame(728, $r->get_customer_data()['prospect_id']);
        $this->assertCount(1, $r->get_customer_data()['enrollable_premises']);
        $this->assertFalse($r->get_customer_data()['portal_available']);
        $this->assertSame('ASHOK', $r->get_customer_data()['first_name']);
        $this->assertSame('RAMASUBBU', $r->get_customer_data()['last_name']);
        $this->assertSame('ASHOK RAMASUBBU', $r->get_customer_data()['name']);
        $this->assertSame('X@GMAIL.COM', $r->get_customer_data()['email']);
        $this->assertSame('210010506231', $r->get_customer_data()['utility_no']);
    }

    public function testIneligibleAccountReturnsInvalid(): void
    {
        $c = $this->connectorReturning([
            'prospect/validate' => ['status' => 'not_found', 'data' => null],
            'portal_user_emails' => ['available' => true, 'has_login_history' => false],
        ]);

        $r = $c->validate_account(
            ['account_number' => '000000000000', 'zip' => '00000', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertFalse($r->is_valid());
        $this->assertSame('not_found', $r->get_error_code());
    }

    public function testConnectionErrorWhenValidateThrows(): void
    {
        $c = $this->connectorReturning([]);

        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertFalse($r->is_valid());
        $this->assertSame('connection_error', $r->get_error_code());
    }

    public function testPortalLookupFailureIsNonFatal(): void
    {
        $c = $this->connectorReturning([
            'prospect/validate' => ['status' => 'found', 'data' => [
                'prospect_id' => 728, 'first_name' => 'ASHOK', 'last_name' => 'RAMASUBBU',
                'name' => 'ASHOK RAMASUBBU', 'email' => 'X@GMAIL.COM', 'utility_no' => '210010506231',
                'enrollable_premises' => [['id' => 728, 'address' => '9593 SYCAMORE GROVE WAY, MECHANICSVILLE, VA 23116', 'zip' => '23116']],
            ]],
            // No fixture for portal_user_emails, so that GET throws.
        ]);

        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertTrue($r->is_valid());
        $this->assertCount(1, $r->get_customer_data()['enrollable_premises']);
        $this->assertNull($r->get_customer_data()['portal_available']);
        $this->assertNull($r->get_customer_data()['has_login_history']);
    }
}
