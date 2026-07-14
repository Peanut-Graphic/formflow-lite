<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

class DominionPtrEnrollStubTest extends TestCase
{
    public function testTestModeEnrollReturnsStubbedSuccess(): void
    {
        $c = new DominionPtrConnector();
        $r = $c->submit_enrollment(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api', 'test_mode' => true]
        );

        $this->assertTrue($r->is_successful());
        $this->assertStringStartsWith('PTR-DEMO-', $r->get_confirmation_number());
        $this->assertSame('demo-token', $r->toArray()['data']['set_password_token']);
    }

    public function testNonTestModeEnrollIsNotImplemented(): void
    {
        $c = new DominionPtrConnector();
        $r = $c->submit_enrollment(
            ['account_number' => '210010506231', 'zip' => '23116', 'email' => 'x@gmail.com'],
            ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api']
        );

        $this->assertFalse($r->is_successful());
        $this->assertSame('not_implemented', $r->toArray()['error_code']);
    }
}
