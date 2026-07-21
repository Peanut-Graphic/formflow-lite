<?php
/**
 * WiFi gate columns reach existing installs.
 *
 * The columns are declared in create_tables(), which only covers *fresh*
 * installs. Every live Pepco/Delmarva site is an existing install that will
 * receive 3.3.0 by auto-update, and the code writes `has_wifi` and
 * `device_converted` on every completed enrollment. If the ALTERs do not run,
 * those writes hit columns that do not exist — the "Unknown column" outage
 * this whole Reliability suite was built after.
 *
 * dbDelta cannot be relied on here, so the migration issues explicit ALTERs
 * and these tests pin that behaviour.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use FFFL\Activator;
use PHPUnit\Framework\TestCase;

final class WifiColumnsUpgradeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        fffl_reliability_reset();
    }

    /**
     * All ALTER statements the upgrade issued.
     *
     * @return string[]
     */
    private function altersOnUpgradeFrom(string $stored_version, array $existing_columns = []): array
    {
        global $wpdb;
        $wpdb->existing_columns = $existing_columns;

        update_option('fffl_version', $stored_version);
        Activator::maybe_upgrade();

        return array_values(array_filter(
            $wpdb->queries,
            static fn($q) => stripos($q, 'ALTER TABLE') !== false
        ));
    }

    public function test_upgrade_from_an_older_version_adds_has_wifi(): void
    {
        $alters = $this->altersOnUpgradeFrom('3.2.24');

        $this->assertNotEmpty($alters, 'The upgrade issued no ALTER at all.');
        $this->assertTrue(
            (bool) array_filter($alters, static fn($q) => stripos($q, 'ADD COLUMN has_wifi') !== false),
            'Existing installs never gain has_wifi, so every completed enrollment writes '
            . 'to a column that does not exist.'
        );
    }

    public function test_upgrade_from_an_older_version_adds_device_converted(): void
    {
        $alters = $this->altersOnUpgradeFrom('3.2.24');

        $this->assertTrue(
            (bool) array_filter($alters, static fn($q) => stripos($q, 'ADD COLUMN device_converted') !== false),
            'Existing installs never gain device_converted.'
        );
    }

    /**
     * maybe_upgrade() runs on every plugins_loaded and re-runs on version
     * drift, so a migration that is not guarded would try to add the same
     * column repeatedly and error.
     */
    public function test_migration_is_idempotent_when_the_columns_already_exist(): void
    {
        $alters = $this->altersOnUpgradeFrom('3.2.24', ['has_wifi', 'device_converted']);

        $this->assertSame(
            [],
            $alters,
            'The migration re-added columns that already exist. It re-runs on every '
            . 'version drift, so it has to check before altering.'
        );
    }

    /**
     * A half-applied upgrade — one column added, then the request died — must
     * be completed on the next run rather than skipped or repeated.
     */
    public function test_partially_applied_upgrade_adds_only_the_missing_column(): void
    {
        $alters = $this->altersOnUpgradeFrom('3.2.24', ['has_wifi']);

        $this->assertCount(1, $alters, 'Exactly the missing column should be added.');
        $this->assertStringContainsStringIgnoringCase('device_converted', $alters[0]);
    }

    /**
     * NULL is load-bearing: it distinguishes "never asked" from an answer, so
     * the added column must not be NOT NULL or carry a non-null default.
     */
    public function test_has_wifi_is_added_as_a_nullable_enum(): void
    {
        $alters = $this->altersOnUpgradeFrom('3.2.24');

        $has_wifi = array_values(array_filter(
            $alters,
            static fn($q) => stripos($q, 'ADD COLUMN has_wifi') !== false
        ));

        $this->assertNotEmpty($has_wifi);
        $this->assertMatchesRegularExpression(
            "/ENUM\(\s*'yes'\s*,\s*'no'\s*\)\s+DEFAULT NULL/i",
            $has_wifi[0],
            'has_wifi must be added nullable, or "never asked" becomes indistinguishable '
            . 'from an answer on every pre-existing row.'
        );
    }
}
