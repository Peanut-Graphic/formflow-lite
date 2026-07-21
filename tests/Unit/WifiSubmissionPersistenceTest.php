<?php
/**
 * WifiSubmissionPersistenceTest — the WiFi answer and the conversion flag have
 * to reach their own columns.
 *
 * They cannot ride along inside form_data: that column is encrypted at rest,
 * so anything stored there is invisible to reporting. These tests pin the
 * write path to the real columns.
 *
 * @package FormFlow_Lite
 */

namespace FFFL\Tests\Unit;

use FFFL\Tests\TestCase;
use FFFL\Database\Database;

final class WifiSubmissionPersistenceTest extends TestCase
{
    private function captureInsert(array $data): array
    {
        global $wpdb;
        $wpdb->last_insert_data = null;

        (new Database())->create_submission($data);

        $this->assertIsArray($wpdb->last_insert_data, 'create_submission never called insert().');

        return $wpdb->last_insert_data;
    }

    private function captureUpdate(array $data): array
    {
        global $wpdb;
        $wpdb->last_update_data = null;

        (new Database())->update_submission(42, $data);

        return is_array($wpdb->last_update_data) ? $wpdb->last_update_data : [];
    }

    public function test_create_submission_persists_the_wifi_answer(): void
    {
        $insert = $this->captureInsert([
            'instance_id' => 1,
            'session_id'  => 'abc',
            'device_type' => 'thermostat',
            'has_wifi'    => 'yes',
        ]);

        $this->assertArrayHasKey('has_wifi', $insert, 'has_wifi never reaches the insert.');
        $this->assertSame('yes', $insert['has_wifi']);
    }

    public function test_create_submission_persists_the_conversion_flag(): void
    {
        $insert = $this->captureInsert([
            'instance_id'      => 1,
            'session_id'       => 'abc',
            'device_type'      => 'dcu',
            'has_wifi'         => 'no',
            'device_converted' => 1,
        ]);

        $this->assertArrayHasKey('device_converted', $insert);
        $this->assertSame(1, $insert['device_converted']);
    }

    /**
     * NULL is load-bearing: it is how "never asked" stays distinguishable from
     * a customer who actually answered.
     */
    public function test_unanswered_wifi_persists_as_null_not_empty_string(): void
    {
        $insert = $this->captureInsert([
            'instance_id' => 1,
            'session_id'  => 'abc',
            'device_type' => 'dcu',
        ]);

        $this->assertArrayHasKey('has_wifi', $insert);
        $this->assertNull($insert['has_wifi'], '"Never asked" must be NULL, not an empty string.');
    }

    public function test_conversion_flag_defaults_to_zero(): void
    {
        $insert = $this->captureInsert([
            'instance_id' => 1,
            'session_id'  => 'abc',
            'device_type' => 'thermostat',
            'has_wifi'    => 'yes',
        ]);

        $this->assertSame(0, $insert['device_converted']);
    }

    public function test_update_submission_persists_wifi_answer_and_conversion(): void
    {
        $update = $this->captureUpdate([
            'device_type'      => 'dcu',
            'has_wifi'         => 'no',
            'device_converted' => 1,
        ]);

        $this->assertSame('no', $update['has_wifi'] ?? null, 'has_wifi never reaches the update.');
        $this->assertSame(1, $update['device_converted'] ?? null, 'device_converted never reaches the update.');
    }

    public function test_update_submission_leaves_wifi_fields_alone_when_not_supplied(): void
    {
        $update = $this->captureUpdate(['status' => 'completed']);

        $this->assertArrayNotHasKey('has_wifi', $update);
        $this->assertArrayNotHasKey('device_converted', $update);
    }
}
