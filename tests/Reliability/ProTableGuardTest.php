<?php
/**
 * Pro-table access guards (fix B).
 *
 * The Lite build deliberately does NOT create the Pro-feature tables
 * (fffl_audit_log, fffl_scheduled_reports, fffl_gdpr_requests). But the
 * Database class still has live read/write methods against them, reachable
 * from 16+ admin actions and several AJAX/render paths — every call hit a
 * missing table and spammed the error log.
 *
 * The fix guards each access with a cached table_exists() check so the methods
 * degrade cleanly (write = no-op/false, read = empty) WITHOUT ever issuing a
 * query against a non-existent table.
 *
 * @package FormFlow_Lite\Tests\Reliability
 */

namespace FFFL\Tests\Reliability;

use FFFL\Database\Database;
use PHPUnit\Framework\TestCase;

final class ProTableGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        fffl_reliability_reset();
    }

    private function db(): Database
    {
        return new Database();
    }

    // ---- table_exists() helper -------------------------------------------

    public function testTableExistsHelperExists(): void
    {
        $this->assertTrue(
            method_exists(Database::class, 'table_exists'),
            'Database::table_exists() guard helper must exist.'
        );
    }

    public function testTableExistsTrueWhenPresentFalseWhenAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [$wpdb->prefix . 'fffl_audit_log'];

        $db = $this->db();
        $this->assertTrue($db->table_exists($wpdb->prefix . 'fffl_audit_log'));
        $this->assertFalse($db->table_exists($wpdb->prefix . 'fffl_gdpr_requests'));
    }

    public function testTableExistsResultIsCached(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [$wpdb->prefix . 'fffl_audit_log'];
        $db = $this->db();

        $db->table_exists($wpdb->prefix . 'fffl_audit_log');
        $countAfterFirst = count($wpdb->get_var_queries);
        $db->table_exists($wpdb->prefix . 'fffl_audit_log');
        $countAfterSecond = count($wpdb->get_var_queries);

        $this->assertSame(
            $countAfterFirst,
            $countAfterSecond,
            'Repeat table_exists() checks must be served from cache, not re-query.'
        );
    }

    // ---- audit log --------------------------------------------------------

    public function testLogAuditNoOpsWhenTableAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = []; // audit table not created in Lite
        $GLOBALS['__fffl_current_user_id'] = 7;

        $result = $this->db()->log_audit('update', 'instance', 1, 'Form A');

        $this->assertFalse($result, 'log_audit() must return false when the table is absent.');
        $this->assertSame([], $wpdb->inserts, 'No INSERT may be issued against a missing audit table.');
    }

    public function testLogAuditWritesWhenTablePresent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [$wpdb->prefix . 'fffl_audit_log'];
        $GLOBALS['__fffl_current_user_id'] = 7;

        $result = $this->db()->log_audit('update', 'instance', 1, 'Form A');

        $this->assertNotFalse($result, 'log_audit() must write when the table exists.');
        $this->assertCount(1, $wpdb->inserts);
        $this->assertSame($wpdb->prefix . 'fffl_audit_log', $wpdb->inserts[0]['table']);
    }

    public function testGetAuditLogEmptyWhenTableAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [];
        $this->assertSame([], $this->db()->get_audit_log());
        $this->assertSame(0, $this->db()->get_audit_log_count());
    }

    // ---- scheduled reports ------------------------------------------------

    public function testScheduledReportsDegradeToEmptyWhenAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [];
        $db = $this->db();

        $this->assertSame([], $db->get_scheduled_reports());
        $this->assertNull($db->get_scheduled_report(1));
        $this->assertFalse($db->create_scheduled_report([
            'name' => 'Weekly', 'frequency' => 'weekly', 'recipients' => ['a@b.c'],
            'instance_id' => 0, 'settings' => [],
        ]));
        $this->assertSame([], $wpdb->inserts, 'No INSERT against a missing scheduled_reports table.');
    }

    // ---- disabled analytics surface --------------------------------------

    public function testTrackStepEventNoOpsWhenAnalyticsTableAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = []; // fffl_analytics_disabled never created

        $result = $this->db()->track_step_event([
            'instance_id' => 1, 'session_id' => 's', 'step' => 1, 'action' => 'enter',
        ]);

        $this->assertFalse($result, 'track_step_event() must no-op when the analytics table is absent.');
        $this->assertSame([], $wpdb->inserts, 'No INSERT against a missing analytics table.');
    }

    // ---- gdpr requests ----------------------------------------------------

    public function testGdprRequestsDegradeToEmptyWhenAbsent(): void
    {
        global $wpdb;
        $wpdb->existing_tables = [];
        $db = $this->db();

        $this->assertSame([], $db->get_gdpr_requests());
        $this->assertSame(0, $db->get_gdpr_requests_count());
        $this->assertFalse($db->create_gdpr_request([
            'request_type' => 'export', 'email' => 'a@b.c',
        ]));
        $this->assertSame([], $wpdb->inserts, 'No INSERT against a missing gdpr_requests table.');
    }
}
