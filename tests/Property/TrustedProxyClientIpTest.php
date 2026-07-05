<?php
/**
 * Regression: trusted-proxy client-IP resolver (Security::get_client_ip).
 *
 * Guards the CLUSTER-2 fix. The earlier resolver trusted the FIRST value of
 * attacker-settable forwarded headers (CF-Connecting-IP, X-Forwarded-For)
 * BEFORE REMOTE_ADDR, so a logged-out enrollment submitter could rotate the
 * rate-limit bucket key (`md5($ip)`) on every request by sending a fresh
 * spoofed header — defeating the sole throttle on the public nopriv submit.
 *
 * The fix defaults to REMOTE_ADDR (the unspoofable TCP peer) and only honors
 * forwarded headers when REMOTE_ADDR is an admin-configured trusted proxy;
 * then it takes the RIGHT-MOST hop that is not itself a trusted proxy.
 *
 * The Property bootstrap defines ABSPATH, a get_option passthrough stub, and
 * loads includes/class-security.php. This file adds a fixed FFFL_TRUSTED_PROXIES
 * constant so the allowlist path is exercised without a WordPress boot.
 * $_SERVER is snapshotted per test.
 *
 * @package FormFlow_Lite\Tests\Property
 */

namespace FFFL\Tests\Property;

use FFFL\Security;
use PHPUnit\Framework\TestCase;

// Fixed trusted reverse-proxy address for the allowlist. Read at call-time by
// Security::trusted_proxies(), so defining it here (before any test runs) is
// sufficient; the Security class is already loaded by the Property bootstrap.
if (!defined('FFFL_TRUSTED_PROXIES')) {
    define('FFFL_TRUSTED_PROXIES', '203.0.113.7');
}

final class TrustedProxyClientIpTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $serverBackup = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->serverBackup = $_SERVER;
        // Start from a clean header slate each test.
        unset(
            $_SERVER['REMOTE_ADDR'],
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR']
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->serverBackup;
        parent::tearDown();
    }

    /**
     * From an UNTRUSTED peer, a spoofed X-Forwarded-For is IGNORED — the key is
     * the real REMOTE_ADDR, so the attacker cannot rotate the bucket.
     */
    public function testSpoofedXffIgnoredFromUntrustedPeer(): void
    {
        $_SERVER['REMOTE_ADDR']          = '198.51.100.9'; // untrusted client
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';      // attacker-supplied

        $this->assertSame('198.51.100.9', Security::get_client_ip());
    }

    /**
     * Same for a spoofed CF-Connecting-IP from an untrusted peer.
     */
    public function testSpoofedCfConnectingIpIgnoredFromUntrustedPeer(): void
    {
        $_SERVER['REMOTE_ADDR']            = '198.51.100.9';
        $_SERVER['HTTP_CF_CONNECTING_IP']  = '9.9.9.9';

        $this->assertSame('198.51.100.9', Security::get_client_ip());
    }

    /**
     * Rotating the spoofed header does NOT change the resolved IP — the whole
     * point of the fix (the rate-limit key stays pinned to the real peer).
     */
    public function testRotatingSpoofedHeaderDoesNotChangeKey(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.9';

        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1';
        $first = Security::get_client_ip();
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.2';
        $second = Security::get_client_ip();

        $this->assertSame('198.51.100.9', $first);
        $this->assertSame($first, $second);
    }

    /**
     * When the request genuinely arrives THROUGH the trusted proxy, the
     * forwarded X-Forwarded-For client value is honored.
     */
    public function testTrustedProxyHonorsForwardedXff(): void
    {
        $_SERVER['REMOTE_ADDR']          = '203.0.113.7'; // trusted proxy
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.55';

        $this->assertSame('203.0.113.55', Security::get_client_ip());
    }

    /**
     * Trusted proxy also honors CF-Connecting-IP (single unambiguous value).
     */
    public function testTrustedProxyHonorsCfConnectingIp(): void
    {
        $_SERVER['REMOTE_ADDR']           = '203.0.113.7';
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '8.8.8.8';

        $this->assertSame('8.8.8.8', Security::get_client_ip());
    }

    /**
     * X-Forwarded-For is "client, proxy..." — with the closest hop on the RIGHT.
     * The resolver must return the RIGHT-MOST hop that is not itself a trusted
     * proxy, never the left-most (attacker-influenced) entry.
     */
    public function testTrustedProxyTakesRightmostUntrustedHop(): void
    {
        $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
        // Left-most is attacker-supplied; the real client the proxy saw is the
        // last non-proxy hop before the trusted proxy appended itself.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.1.1.1, 198.51.100.23, 203.0.113.7';

        $this->assertSame('198.51.100.23', Security::get_client_ip());
    }

    /**
     * A malformed / empty REMOTE_ADDR yields the sentinel, never a header value.
     */
    public function testMissingRemoteAddrFallsBackToSentinelNotHeader(): void
    {
        // No REMOTE_ADDR set at all.
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '1.2.3.4';

        $this->assertSame('0.0.0.0', Security::get_client_ip());
    }
}
