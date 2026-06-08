<?php
/**
 * Real-WordPress REST contract test (net 7) for the public embed-config route.
 *
 * Pins the REAL `GET /fffl/v1/embed/config/{token}` route registered by
 * \FFFL\EmbedHandler::register_rest_routes(). The route is
 * `permission_callback => '__return_true'` (public by design — the embeddable
 * widget fetches its config before any sign-in), so it is the stable, gettable
 * surface to lock down.
 *
 * Documented response contract (see EmbedHandler::get_embed_config):
 *   - token must match /^[a-zA-Z0-9]{16,64}$/ (route `validate_callback`)
 *   - unknown (valid-format) token => HTTP 403, body ['error' => 'Invalid token']
 *
 * A valid-format but nonexistent token is deterministic with NO DB seeding:
 * `get_instance_by_embed_token` finds nothing, so the callback returns the
 * documented 403. This boots a real WordPress and dispatches through the real
 * REST server — NO mocks. If the route or shape regresses, this fails.
 */

namespace FFFL\Tests\ContractWp;

use WP_UnitTestCase;
use WP_REST_Request;

class EmbedConfigRouteContractTest extends WP_UnitTestCase {

    public function set_up(): void {
        parent::set_up();

        // The plugin boots on `init` (fffl_init, priority 0), where EmbedHandler
        // wires its routes onto `rest_api_init`. Rebuild the REST server so the
        // route is live for this test.
        global $wp_rest_server;
        $wp_rest_server = null;
        do_action('rest_api_init');
    }

    public function test_embed_config_route_is_registered(): void {
        $routes = rest_get_server()->get_routes();

        $this->assertArrayHasKey(
            '/fffl/v1/embed/config/(?P<token>[a-zA-Z0-9]+)',
            $routes,
            'Public embed-config route must be registered on a real WordPress.'
        );
    }

    public function test_unknown_token_returns_documented_403_contract(): void {
        // Valid format per the route validate_callback (16-64 alnum), but no
        // matching instance exists -> deterministic documented 403.
        $token = str_repeat('a', 32);

        $request  = new WP_REST_Request('GET', '/fffl/v1/embed/config/' . $token);
        $response = rest_get_server()->dispatch($request);

        // Real status from the real callback.
        $this->assertSame(
            403,
            $response->get_status(),
            'Unknown embed token must return the documented HTTP 403.'
        );

        $data = $response->get_data();

        $this->assertIsArray($data);
        $this->assertArrayHasKey('error', $data);
        $this->assertSame('Invalid token', $data['error']);
    }
}
