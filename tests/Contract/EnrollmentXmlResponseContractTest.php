<?php
/**
 * Contract tests (net 7) for the PowerPortal-API <-> plugin XML seam.
 *
 * The upstream enrollment API speaks XML. The plugin consumes it via
 * FFFL\Api\XmlParser::parse() (attribute mode — the mode used everywhere in
 * production: see XmlParser::parse() calls in class-api-client.php and the
 * IntelliSource connector) and then validates the parsed shape with
 * FFFL\Api\ResponseValidator. These tests PIN that consumer contract so an
 * upstream- or parser-shape change is caught here, not in production.
 *
 * No WordPress is booted (both classes are pure PHP).
 *
 * @package FormFlow_Lite\Tests\Contract
 */

namespace FFFL\Tests\Contract;

use FFFL\Api\XmlParser;
use FFFL\Api\ResponseValidator;
use PHPUnit\Framework\TestCase;

final class EnrollmentXmlResponseContractTest extends TestCase
{
    /** A representative successful enrollment response from the upstream API. */
    private const SUCCESS_XML = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<message>
    <status>SUCCESS</status>
    <messagetype>enrollment</messagetype>
    <caNo>1234567890</caNo>
    <fname>John</fname>
    <lname>Doe</lname>
    <email>john.doe@example.com</email>
    <confirmation>ENR-998877</confirmation>
</message>
XML;

    protected function setUp(): void
    {
        parent::setUp();
        // Silence PHP 8.5's xml_parser_free() deprecation (no behavioural effect)
        // so failOnWarning in the suite config reflects real contract drift only.
        error_reporting(E_ALL & ~E_DEPRECATED);
    }

    public function testParsedShapeIsPinned(): void
    {
        $parsed = XmlParser::parse(self::SUCCESS_XML);

        // CONTRACT: every leaf is wrapped as ['value' => <string>] under a single
        // <message> root. This is exactly what ResponseValidator::extract_value
        // expects. If the parser shape drifts, this assertion fails first.
        $this->assertSame(
            [
                'message' => [
                    'status'        => ['value' => 'SUCCESS'],
                    'messagetype'   => ['value' => 'enrollment'],
                    'caNo'          => ['value' => '1234567890'],
                    'fname'         => ['value' => 'John'],
                    'lname'         => ['value' => 'Doe'],
                    'email'         => ['value' => 'john.doe@example.com'],
                    'confirmation'  => ['value' => 'ENR-998877'],
                ],
            ],
            $parsed,
            'Parsed enrollment XML shape drifted from the pinned consumer contract.'
        );
    }

    public function testGetValueNavigatesPinnedShape(): void
    {
        $parsed = XmlParser::parse(self::SUCCESS_XML);

        $this->assertSame('SUCCESS', XmlParser::get_value($parsed, 'message.status.value'));
        $this->assertSame('ENR-998877', XmlParser::get_value($parsed, 'message.confirmation.value'));
        $this->assertNull(XmlParser::get_value($parsed, 'message.nonexistent.value'));
    }

    public function testValidatorAcceptsTheContractShape(): void
    {
        $parsed = XmlParser::parse(self::SUCCESS_XML);

        $this->assertTrue(
            ResponseValidator::validate_validation_response($parsed),
            'ResponseValidator rejected a well-formed enrollment response: '
            . implode('; ', ResponseValidator::get_errors())
        );
    }

    public function testValidatorRejectsResponseMissingMessageRoot(): void
    {
        // CONTRACT (negative): a payload with no <message> root must be rejected.
        $this->assertFalse(ResponseValidator::validate_validation_response(['notmessage' => []]));
        $this->assertContains('Missing root message element', ResponseValidator::get_errors());
    }

    public function testValidatorRejectsResponseMissingStatusIndicator(): void
    {
        // CONTRACT (negative): a <message> with no status / messagetype / enroll-status
        // must be rejected so the plugin never treats an ambiguous reply as success.
        $parsed = XmlParser::parse('<message><caNo>1</caNo></message>');
        $this->assertFalse(ResponseValidator::validate_validation_response($parsed));
        $this->assertContains('Missing status indicator in response', ResponseValidator::get_errors());
    }
}
