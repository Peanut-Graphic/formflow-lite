<?php
namespace FFFL\Tests\Regression;
use PHPUnit\Framework\TestCase;
if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
require_once dirname(__DIR__, 2) . '/includes/api/class-api-client.php';
final class PhiTrustedOriginTest extends TestCase {
    public function testFixedVendorOperationsWorkWithoutPublicDns(): void {
        foreach (['ph.powerportal.com', 'intellisource.powerportal.pepcoholdings.com'] as $host) {
            foreach (['prospects/validate.xml', 'prospects/enroll.xml', 'field_service_requests/scheduling.xml', 'field_service_requests/schedule', 'promo_codes'] as $path) {
                $url = 'https://' . $host . '/phiIntelliSOURCE/api/' . $path . '?val=submit';
                self::assertTrue(\FFFL\Api\ApiClient::is_trusted_intellisource_url($url));
                self::assertTrue(\FFFL\Api\ApiClient::is_safe_outbound_url($url));
            }
        }
    }
    public function testExceptionCannotEscapeTheVendorApi(): void {
        foreach ([
            'http://ph.powerportal.com/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://ph.powerportal.com:8443/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://user:pass@ph.powerportal.com/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://ph.powerportal.com.evil.test/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://ph.powerportal.com./phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://ph.powerportal.com/phiIntelliSOURCE/api/../admin',
            'https://ph.powerportal.com/phiIntelliSOURCE/api/prospects/enroll.xml#fragment',
            'https://127.0.0.1/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://169.254.169.254/phiIntelliSOURCE/api/prospects/enroll.xml',
            'https://ph.powerportal.com/admin',
        ] as $url) {
            self::assertFalse(\FFFL\Api\ApiClient::is_trusted_intellisource_url($url), $url);
        }
    }
    public function testBothTransportsRefuseRedirectsAndKeepTlsVerification(): void {
        foreach (['includes/api/class-api-client.php', 'connectors/intellisource/class-intellisource-connector.php'] as $file) {
            $source = file_get_contents(dirname(__DIR__, 2) . '/' . $file);
            self::assertStringContainsString("'redirection' => 0", $source);
            self::assertStringContainsString("'sslverify' => true", $source);
            self::assertStringContainsString("'reject_unsafe_urls' => !", $source);
        }
    }
}
