<?php
/**
 * Honest-state placeholders for contract seams that are BLOCKED or carry a
 * known bug. These tests do not assert broken behaviour as correct — they are
 * skipped with a reason so the gap is counted, not hidden.
 *
 * @package FormFlow_Lite\Tests\Contract
 */

namespace FFFL\Tests\Contract;

use FFFL\Api\XmlParser;
use PHPUnit\Framework\TestCase;

final class KnownGapsContractTest extends TestCase
{
    /**
     * REGRESSION: XmlParser::parse_simple() / parse($xml, false) previously
     * crashed with a TypeError ("Cannot access offset of type string on
     * string") on ANY nested XML, because in no-attribute mode an opening tag
     * stored a *string* as $current[$tag] and then descended into it, so the
     * next child element dereferenced a string as an array
     * (class-xml-parser.php, 'open' case).
     *
     * Fixed by promoting the no-attribute leaf to an array container when a tag
     * opens (mirrors attribute-mode descent). This test pins the correct nested
     * structure so the gap cannot silently reopen.
     */
    public function testParseSimpleParsesNestedXml(): void
    {
        // Single level of nesting (the documented counter-example).
        $this->assertSame(
            ['message' => ['status' => 'SUCCESS']],
            XmlParser::parse_simple('<message><status>SUCCESS</status></message>')
        );

        // Deeper nesting must also descend correctly, not throw.
        $this->assertSame(
            ['a' => ['b' => ['c' => 'X']]],
            XmlParser::parse_simple('<a><b><c>X</c></b></a>')
        );
    }

    /**
     * BLOCKED: the /wp-json/fffl/v1/* HTTP route contract (FFFL\Embed_Handler,
     * FFFL\Builder\FormBuilder register_rest_route) needs a booting WordPress
     * REST harness (wp-phpunit / WP_TESTS_DIR). That harness is not provisioned
     * in this environment, so the route response shape cannot be pinned here.
     *
     * Tracked in known-gaps.md.
     */
    public function testWpRestRouteContractRequiresWpHarness(): void
    {
        $this->markTestSkipped(
            'WP REST route contract (/wp-json/fffl/v1/*) requires wp-phpunit / '
            . 'WP_TESTS_DIR, which is not provisioned. Tracked in known-gaps.md.'
        );
    }
}
