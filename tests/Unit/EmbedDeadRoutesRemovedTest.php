<?php
/**
 * EmbedDeadRoutesRemovedTest — unreachable / broken embed REST routes (audit 2026-10, LOW).
 *
 * BEFORE: /fffl/v1/embed/submit, /embed/validate and /embed/schedule were
 * public (__return_true) routes keyed only by an embed token. Lite has no UI
 * that issues embed tokens, /embed/submit called a FormHandler method that
 * does not exist (fatal), and /embed/validate had no nonce and — without
 * Peanut Suite's rate-limit filter — no throttle, returning the connector's
 * full validation result (account holder PII) for any account + ZIP.
 * AFTER: the three routes and their handlers are gone; the embed widget always
 * renders the iframe (the real, session-bound form) and no longer advertises
 * those endpoints.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\EmbedHandler;
use FFFL\Tests\TestCase;

final class EmbedDeadRoutesRemovedTest extends TestCase
{
    public function test_only_the_config_route_is_registered(): void
    {
        $GLOBALS['mock_rest_routes'] = [];
        $handler = (new \ReflectionClass(EmbedHandler::class))->newInstanceWithoutConstructor();
        $handler->register_rest_routes();

        $routes = array_column($GLOBALS['mock_rest_routes'], 'route');
        $this->assertSame(['/embed/config/(?P<token>[a-zA-Z0-9]+)'], $routes,
            'Only the embed config route may remain public.');
    }

    public function test_dead_handlers_are_gone(): void
    {
        foreach (['handle_embed_submission', 'handle_embed_validation', 'handle_embed_schedule'] as $method) {
            $this->assertFalse(method_exists(EmbedHandler::class, $method), "{$method} must be removed.");
        }
        $src = file_get_contents(FFFL_PLUGIN_DIR . 'includes/class-embed-handler.php');
        $this->assertStringNotContainsString("'endpoints'", $src, 'The embed config must not advertise removed endpoints.');
    }

    public function test_widget_no_longer_posts_to_removed_endpoints(): void
    {
        $js = file_get_contents(FFFL_PLUGIN_DIR . 'public/assets/js/embed.js');
        $this->assertStringNotContainsString('endpoints.submit', $js);
        $this->assertStringNotContainsString('createInlineForm', $js);
    }
}
