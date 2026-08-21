<?php
/**
 * Real-WordPress contract test: features registered during the plugin's
 * `init`-hook boot must actually RUN, not merely be registered.
 *
 * The bug family (shared with peanut-connect's dead migration hook): this
 * plugin boots via fffl_init on `init`, which fires AFTER plugins_loaded.
 * Any add_action('plugins_loaded', ...) issued from the boot path registers
 * on a hook that has already finished firing -- a silent no-op. Two features
 * were dead this way:
 *
 *   1. PeanutIntegration::instance() (suite integration) -- never
 *      instantiated on any web request.
 *   2. ConnectorRegistry::init_connectors() -- never ran, so
 *      fffl_register_connectors never fired and the bundled IntelliSource
 *      connector was never registered; every get('intellisource') was null.
 *
 * (The identical PeanutIntegration line in full FormFlow works, because that
 * plugin boots on plugins_loaded@10 and a same-hook @15 registration still
 * runs. Copying the line into an init-boot plugin changed its meaning.)
 */

namespace FFFL\Tests\ContractWp;

use WP_UnitTestCase;

class BootHookLivenessContractTest extends WP_UnitTestCase
{
    public function test_premise_plugins_loaded_has_already_fired(): void
    {
        $this->assertGreaterThan(
            0,
            did_action('plugins_loaded'),
            'test premise: by the time an init-boot plugin runs, plugins_loaded is history'
        );
    }

    public function test_connector_registry_initializes_despite_the_dead_hook(): void
    {
        $registry = \FFFL\Api\ConnectorRegistry::instance();

        // The broken constructor only registered a callback that could never
        // fire; fffl_register_connectors therefore never ran. The fixed path
        // must have fired it by the time the singleton exists.
        $this->assertGreaterThan(
            0,
            did_action('fffl_register_connectors'),
            'fffl_register_connectors must fire during boot -- it is how every bundled and third-party connector registers'
        );

        $this->assertNotNull(
            $registry->get('intellisource'),
            'the bundled IntelliSource connector must be registered; null here means init_connectors never ran (the dead plugins_loaded@5 self-hook)'
        );
    }
}
