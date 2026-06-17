<?php
/**
 * Migration-on-upgrade / schema self-heal (fix A).
 *
 * The bug: dbDelta only ran on register_activation_hook, run_migrations() was
 * an empty stub, and the stored `fffl_version` option was written but never
 * compared — so a schema change shipped via auto-update never reached an
 * already-active install. This is the exact structural gap behind the
 * peanut-connect "Unknown column" outage.
 *
 * The fix adds Activator::maybe_upgrade() (cheap option gate on every
 * plugins_loaded) that re-runs create_tables() (dbDelta is idempotent/
 * additive) and bumps the stored version when stale OR when the live schema
 * has drifted from what the current version expects.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use FFFL\Activator;
use PHPUnit\Framework\TestCase;

final class UpgradeMigrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        fffl_reliability_reset();
    }

    public function testMaybeUpgradeMethodExists(): void
    {
        $this->assertTrue(
            method_exists(Activator::class, 'maybe_upgrade'),
            'Activator::maybe_upgrade() must exist so upgrades reach active installs.'
        );
    }

    public function testStaleVersionTriggersMigrationAndBumpsVersion(): void
    {
        // Simulate an existing install on an older schema version.
        update_option('fffl_version', '3.0.0');
        $GLOBALS['__fffl_dbdelta_calls'] = [];

        Activator::maybe_upgrade();

        $this->assertNotEmpty(
            $GLOBALS['__fffl_dbdelta_calls'],
            'A stale fffl_version must re-run create_tables() (dbDelta).'
        );
        $this->assertSame(
            FFFL_VERSION,
            get_option('fffl_version'),
            'After upgrade the stored version must be bumped to FFFL_VERSION.'
        );
    }

    public function testCurrentVersionWithIntactSchemaIsNoOp(): void
    {
        // Install is already current AND every real table exists.
        update_option('fffl_version', FFFL_VERSION);
        $this->primeAllRealTablesExist();
        $GLOBALS['__fffl_dbdelta_calls'] = [];

        Activator::maybe_upgrade();

        $this->assertSame(
            [],
            $GLOBALS['__fffl_dbdelta_calls'],
            'When version is current and schema is intact, no migration should run.'
        );
    }

    public function testCurrentVersionButMissingTableSelfHeals(): void
    {
        // Drift case: option says current, but a core table is missing — the
        // option must NOT be trusted blindly (peanut-connect lesson).
        update_option('fffl_version', FFFL_VERSION);
        // Prime all but one real table as existing.
        global $wpdb;
        $wpdb->existing_tables = [
            $wpdb->prefix . 'fffl_instances',
            $wpdb->prefix . 'fffl_submissions',
            $wpdb->prefix . 'fffl_logs',
            $wpdb->prefix . 'fffl_retry_queue',
            $wpdb->prefix . 'fffl_webhooks',
            $wpdb->prefix . 'fffl_api_usage',
            // fffl_resume_tokens intentionally absent (drift).
        ];
        $GLOBALS['__fffl_dbdelta_calls'] = [];

        Activator::maybe_upgrade();

        $this->assertNotEmpty(
            $GLOBALS['__fffl_dbdelta_calls'],
            'Missing core table must self-heal even when the version option looks current.'
        );
    }

    public function testFreshInstallWithNoVersionOptionMigrates(): void
    {
        // No fffl_version option at all (never activated / new site).
        $GLOBALS['__fffl_dbdelta_calls'] = [];

        Activator::maybe_upgrade();

        $this->assertNotEmpty(
            $GLOBALS['__fffl_dbdelta_calls'],
            'A missing version option must trigger a migration.'
        );
        $this->assertSame(FFFL_VERSION, get_option('fffl_version'));
    }

    private function primeAllRealTablesExist(): void
    {
        global $wpdb;
        foreach ([
            'fffl_instances', 'fffl_submissions', 'fffl_logs', 'fffl_retry_queue',
            'fffl_webhooks', 'fffl_api_usage', 'fffl_resume_tokens',
        ] as $t) {
            $wpdb->existing_tables[] = $wpdb->prefix . $t;
        }
    }
}
