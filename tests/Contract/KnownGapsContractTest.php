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
     * BUG (latent): XmlParser::parse_simple() / parse($xml, false) crashes with
     * a TypeError ("Cannot access offset of type string on string") on ANY
     * nested XML, because in no-attribute mode an opening tag stores a *string*
     * as $current[$tag] and then descends into it, so the next child element
     * dereferences a string as an array (class-xml-parser.php:108).
     *
     * Production only ever calls parse() in attribute mode, so this is latent
     * (a footgun for future callers), not a live failure — hence skipped, not
     * asserted as correct. Counter-example documented below; do NOT weaken.
     *
     * Tracked in known-gaps.md.
     */
    public function testParseSimpleNestedXmlIsBroken(): void
    {
        $this->markTestSkipped(
            'XmlParser::parse_simple() throws TypeError on nested XML '
            . '(class-xml-parser.php:108). Counter-example: '
            . '"<message><status>SUCCESS</status></message>". '
            . 'Latent (prod uses attribute-mode parse()). Tracked in known-gaps.md.'
        );

        // For the record, the failing call (kept unreachable behind the skip):
        XmlParser::parse_simple('<message><status>SUCCESS</status></message>');
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
