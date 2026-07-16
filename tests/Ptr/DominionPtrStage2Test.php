<?php
namespace FFFL\Tests\Ptr;

use PHPUnit\Framework\TestCase;
use FFFL\Connectors\DominionPtr\DominionPtrConnector;

/**
 * Stage 2 scaffolding: identity verification (prospect_verifications) and the
 * portal hand-off (create_from_prospect). Pure tests — the connector's HTTP is
 * faked by overriding the protected http_post_json / http_get_json, so no live
 * calls occur. Request shapes are asserted against the reverse-engineered
 * IntelliSource contract.
 */
class DominionPtrStage2Test extends TestCase
{
    private const CFG = ['api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api'];

    /**
     * Connector that records the last POST/GET and returns queued fixtures.
     */
    private function recordingConnector(array $postResp = [], array $getResp = []): DominionPtrConnector
    {
        return new class($postResp, $getResp) extends DominionPtrConnector {
            public array $lastPost = [];
            public array $lastGet = [];
            private array $postResp;
            private array $getResp;
            public function __construct(array $postResp, array $getResp) { $this->postResp = $postResp; $this->getResp = $getResp; }
            protected function http_post_json(string $url, array $data): array {
                $this->lastPost = ['url' => $url, 'data' => $data];
                if (isset($this->postResp['__throw'])) { throw new \Exception('boom'); }
                return $this->postResp;
            }
            protected function http_get_json(string $url, array $query = []): array {
                $this->lastGet = ['url' => $url, 'query' => $query];
                if (isset($this->getResp['__throw'])) { throw new \Exception('boom'); }
                return $this->getResp;
            }
        };
    }

    // --- send_verification -------------------------------------------------

    public function testSendVerificationPostsExpectedPayloadAndParsesId(): void
    {
        $c = $this->recordingConnector(['id' => 4021]);
        $r = $c->send_verification(
            ['email' => 'x@gmail.com', 'mobile_telephone' => '8045551212', 'first_name' => 'ASHOK', 'last_name' => 'RAMASUBBU', 'method' => 'email'],
            self::CFG
        );

        $this->assertTrue($r['sent']);
        $this->assertSame(4021, $r['verification_id']);
        $this->assertStringEndsWith('/prospect_verifications', $c->lastPost['url']);
        $this->assertSame('email', $c->lastPost['data']['method']);
        $this->assertSame('json', $c->lastPost['data']['preferred_format']);
        $this->assertSame('x@gmail.com', $c->lastPost['data']['email']);
        $this->assertSame('ASHOK', $c->lastPost['data']['first_name']);
    }

    public function testSendVerificationFailureIsNonFatal(): void
    {
        $c = $this->recordingConnector(['__throw' => true]);
        $r = $c->send_verification(['email' => 'x@gmail.com'], self::CFG);
        $this->assertFalse($r['sent']);
        $this->assertNull($r['verification_id']);
    }

    // --- check_verification ------------------------------------------------

    public function testCheckVerificationHitsCorrectUrlAndParsesVerified(): void
    {
        $c = $this->recordingConnector([], ['verified' => true]);
        $r = $c->check_verification('4021', '123456', self::CFG);

        $this->assertTrue($r['verified']);
        $this->assertStringEndsWith('/prospect_verifications/4021', $c->lastGet['url']);
        $this->assertSame('123456', $c->lastGet['query']['verification_code']);
    }

    public function testCheckVerificationNegativeWhenNoPassFlag(): void
    {
        $c = $this->recordingConnector([], ['status' => 'pending']);
        $r = $c->check_verification('4021', '000000', self::CFG);
        $this->assertFalse($r['verified']);
    }

    // --- create_portal_handoff (the fully-confirmed one) -------------------

    public function testCreatePortalHandoffParsesTokenAndIdAndPostsMappedFields(): void
    {
        $c = $this->recordingConnector(['portal_user' => ['id' => 5150, 'enrollment_token' => 'tok-abc123']]);
        $r = $c->create_portal_handoff(
            ['premise_id' => 728, 'zip' => '23116', 'utility_no' => '210010506231', 'email' => 'x@gmail.com', 'first_name' => 'ASHOK', 'last_name' => 'RAMASUBBU', 'mobile_telephone' => '8045551212'],
            self::CFG
        );

        $this->assertTrue($r['success']);
        $this->assertSame(5150, $r['portal_user_id']);
        $this->assertSame('tok-abc123', $r['enrollment_token']);
        $this->assertStringEndsWith('/portal_user/create_from_prospect', $c->lastPost['url']);
        $this->assertSame(728, $c->lastPost['data']['premise_id']);
        $this->assertSame('210010506231', $c->lastPost['data']['utility_no']);
        $this->assertSame('json', $c->lastPost['data']['preferred_format']);
    }

    public function testCreatePortalHandoffMissingPortalUserIsFailure(): void
    {
        $c = $this->recordingConnector(['error' => 'nope']);
        $r = $c->create_portal_handoff(['premise_id' => 1, 'utility_no' => 'x', 'email' => 'x@gmail.com'], self::CFG);
        $this->assertFalse($r['success']);
        $this->assertNull($r['enrollment_token']);
    }
}
