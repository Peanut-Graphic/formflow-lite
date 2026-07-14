<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

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

    public function testLiveValidateReadOnly(): void
    {
        if (getenv('FFFL_LIVE_TESTS') !== '1') {
            $this->markTestSkipped('Set FFFL_LIVE_TESTS=1 to hit the live read-only validate endpoint.');
        }

        if (!class_exists(\FFFL\Api\ApiClient::class)) {
            require_once dirname(__DIR__, 2) . '/includes/api/class-api-client.php';
        }

        $c = new DominionPtrConnector();
        $r = $c->validate_account(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'Rspectesting123@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );
        // Read-only. NEVER call submit_enrollment with this account.
        $this->assertTrue($r->is_valid());
        $this->assertNotEmpty($r->get_customer_data()['enrollable_premises']);
    }
}
