<?php
/**
 * Regression guard: IntelliSource SSRF guard + embed CORS credentials safety.
 *
 * Two ported/lite-specific security fixes are pinned here:
 *
 *   1) SSRF (MED, admin-gated): the IntelliSource `api_endpoint` was only
 *      validated with FILTER_VALIDATE_URL, which accepts loopback/link-local/
 *      private hosts and non-http(s) schemes. Both outbound paths
 *      (ApiClient::do_request and IntelliSourceConnector::make_request) now
 *      call ApiClient::is_safe_outbound_url() before wp_remote_request().
 *      -> A request to an internal-IP endpoint must be blocked.
 *
 *   2) CORS (MED, lite-specific): EmbedHandler::add_cors_headers() defaulted
 *      the allowlist to '*' and REFLECTED the request Origin together with
 *      `Access-Control-Allow-Credentials: true` — reflecting an arbitrary
 *      origin WITH credentials is unsafe. The decision is now pure
 *      (EmbedHandler::cors_headers_for) and never combines a wildcard/reflected
 *      origin with credentials.
 *      -> With the default '*' allowlist the response must NOT set
 *         Allow-Credentials alongside a reflected origin.
 *
 * SELF-CONTAINED: this test defines ABSPATH + a couple of thin WP shims and
 * requires the two class files directly. No WordPress boot, no network — the
 * guard is exercised with IP-literal hosts so it stays deterministic. Runs
 * under phpunit.regression.xml (which has no bootstrap by design).
 *
 * @package FormFlow_Lite\Tests\Regression
 */

namespace FFFL\Tests\Regression;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/fffl-regression/');
}
if (!function_exists('wp_parse_url')) {
    function wp_parse_url($url, $component = -1) {
        return parse_url((string) $url);
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($str) {
        return is_string($str) ? trim(strip_tags($str)) : '';
    }
}
if (!function_exists('__')) {
    function __($text, $domain = 'default') {
        return $text;
    }
}

require_once dirname(__DIR__, 2) . '/includes/api/class-api-client.php';
require_once dirname(__DIR__, 2) . '/includes/class-embed-handler.php';

final class IntelliSourceSsrfAndEmbedCorsTest extends PHPUnitTestCase
{
    /**
     * Internal / non-public / non-http(s) endpoints must be rejected by the
     * shared SSRF guard that both the ApiClient and the connector call.
     *
     * @return array<string,array{0:string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'ipv4 loopback'        => ['http://127.0.0.1/phiIntelliSOURCE/api'],
            'ipv4 loopback https'  => ['https://127.0.0.1/api'],
            'ipv6 loopback'        => ['http://[::1]/api'],
            'localhost name'       => ['http://localhost/api'],
            'private 10/8'         => ['http://10.0.0.5/api'],
            'private 172.16/12'    => ['https://172.16.10.10/api'],
            'private 192.168/16'   => ['http://192.168.1.1/api'],
            'link-local 169.254'   => ['http://169.254.169.254/latest/meta-data'],
            'non-http scheme file' => ['file:///etc/passwd'],
            'non-http scheme ftp'  => ['ftp://10.0.0.1/x'],
            'no host'              => ['/relative/path'],
        ];
    }

    /**
     * @dataProvider blockedUrls
     */
    public function testInternalOrUnsafeEndpointsAreBlocked(string $url): void
    {
        $this->assertFalse(
            \FFFL\Api\ApiClient::is_safe_outbound_url($url),
            "Expected SSRF guard to BLOCK: {$url}"
        );
    }

    /**
     * Public IP-literal / https vendor-shaped endpoints stay allowed so the
     * legitimate vendor endpoint keeps working.
     *
     * @return array<string,array{0:string}>
     */
    public static function allowedUrls(): array
    {
        return [
            'public ipv4'       => ['https://8.8.8.8/phiIntelliSOURCE/api'],
            'public ipv4 http'  => ['http://93.184.216.34/api'],
        ];
    }

    /**
     * @dataProvider allowedUrls
     */
    public function testPublicEndpointsAreAllowed(string $url): void
    {
        $this->assertTrue(
            \FFFL\Api\ApiClient::is_safe_outbound_url($url),
            "Expected SSRF guard to ALLOW: {$url}"
        );
    }

    /**
     * The connector's outbound path must route through the shared guard, not
     * call wp_remote_request() unguarded.
     */
    public function testConnectorInvokesSharedSsrfGuard(): void
    {
        $src = file_get_contents(
            dirname(__DIR__, 2)
            . '/connectors/intellisource/class-intellisource-connector.php'
        );
        $this->assertIsString($src);
        $guardPos = strpos($src, 'ApiClient::is_safe_outbound_url');
        $callPos  = strpos($src, 'wp_remote_request');
        $this->assertNotFalse($guardPos, 'Connector must call the SSRF guard.');
        $this->assertNotFalse($callPos, 'Connector must still perform the request.');
        $this->assertLessThan(
            $callPos,
            $guardPos,
            'SSRF guard must run BEFORE wp_remote_request().'
        );
    }

    /**
     * Default ('*') allowlist: emit `Allow-Origin: *` but NEVER credentials,
     * and never reflect the caller's Origin.
     */
    public function testWildcardAllowlistNeverSetsCredentials(): void
    {
        $headers = \FFFL\EmbedHandler::cors_headers_for(
            'https://evil.example.com',
            ['*']
        );

        $joined = strtolower(implode("\n", $headers));

        $this->assertStringContainsString('access-control-allow-origin: *', $joined);
        $this->assertStringNotContainsString('access-control-allow-credentials', $joined);
        $this->assertStringNotContainsString(
            'evil.example.com',
            $joined,
            'Wildcard mode must not reflect the request Origin.'
        );
    }

    /**
     * An Origin NOT on an explicit allowlist gets no CORS grant at all
     * (so it certainly cannot obtain credentials).
     */
    public function testUnlistedOriginGetsNoCredentialedGrant(): void
    {
        $headers = \FFFL\EmbedHandler::cors_headers_for(
            'https://evil.example.com',
            ['https://good.example.com']
        );

        $this->assertSame(
            [],
            $headers,
            'Unlisted origin must receive no CORS headers.'
        );
    }

    /**
     * Only an explicitly allowlisted Origin may be reflected together with
     * credentials — that pairing is the legitimate embed case.
     */
    public function testExplicitlyAllowlistedOriginMayUseCredentials(): void
    {
        $headers = \FFFL\EmbedHandler::cors_headers_for(
            'https://good.example.com',
            ['https://good.example.com']
        );

        $joined = strtolower(implode("\n", $headers));

        $this->assertStringContainsString(
            'access-control-allow-origin: https://good.example.com',
            $joined
        );
        $this->assertStringContainsString('access-control-allow-credentials: true', $joined);
        // Never a wildcard when credentials are in play.
        $this->assertStringNotContainsString('access-control-allow-origin: *', $joined);
    }
}
