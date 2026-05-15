<?php
namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Plugin Activator
 *
 * Handles plugin activation, including database table creation.
 * Lite version - core tables only.
 */


class Activator {

    /**
     * Activate the plugin
     */
    public static function activate(): void {
        self::create_tables();
        self::run_migrations();
        self::set_default_options();
        self::schedule_cron_events();

        // Store version for future updates
        update_option('fffl_version', FFFL_VERSION);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Run database migrations for version upgrades
     */
    private static function run_migrations(): void {
        global $wpdb;

        $current_version = get_option('fffl_version', '1.0.0');

        // Migration for v3.0.0: Initial lite version - no migrations needed yet
    }

    /**
     * Create database tables - Core tables only
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Instances table
        $table_instances = $wpdb->prefix . FFFL_TABLE_INSTANCES;
        $sql_instances = "CREATE TABLE {$table_instances} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            utility VARCHAR(50) NOT NULL,
            form_type ENUM('enrollment','scheduler','external') DEFAULT 'enrollment',
            api_endpoint VARCHAR(500) NOT NULL,
            api_password VARCHAR(500),
            support_email_from VARCHAR(255),
            support_email_to TEXT,
            settings JSON,
            is_active TINYINT(1) DEFAULT 1,
            test_mode TINYINT(1) DEFAULT 0,
            embed_token VARCHAR(64) NULL,
            display_order INT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY slug (slug),
            UNIQUE KEY embed_token (embed_token),
            KEY utility (utility),
            KEY form_type_active (form_type, is_active),
            KEY display_order (display_order)
        ) {$charset_collate};";

        // Submissions table
        // Note: Foreign key removed - dbDelta() doesn't handle FK constraints well
        // Referential integrity is managed at application level
        $table_submissions = $wpdb->prefix . FFFL_TABLE_SUBMISSIONS;
        $sql_submissions = "CREATE TABLE {$table_submissions} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            instance_id INT UNSIGNED NOT NULL,
            session_id VARCHAR(64) NOT NULL,
            account_number VARCHAR(50),
            customer_name VARCHAR(255),
            device_type ENUM('thermostat','dcu'),
            form_data MEDIUMBLOB,
            api_response MEDIUMBLOB,
            status ENUM('in_progress','completed','failed','abandoned') DEFAULT 'in_progress',
            step TINYINT UNSIGNED DEFAULT 1,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            is_test TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            KEY session_id (session_id),
            KEY status_date (status, created_at),
            KEY instance_status (instance_id, status),
            KEY is_test (is_test)
        ) {$charset_collate};";

        // Logs table
        $table_logs = $wpdb->prefix . FFFL_TABLE_LOGS;
        $sql_logs = "CREATE TABLE {$table_logs} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            instance_id INT UNSIGNED,
            submission_id INT UNSIGNED,
            log_type ENUM('info','warning','error','api_call','security') NOT NULL,
            message TEXT NOT NULL,
            details JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY type_date (log_type, created_at),
            KEY instance_id (instance_id),
            KEY submission_id (submission_id)
        ) {$charset_collate};";

        // Failed submissions retry queue
        $table_retry_queue = $wpdb->prefix . 'fffl_retry_queue';
        $sql_retry_queue = "CREATE TABLE {$table_retry_queue} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            submission_id INT UNSIGNED NOT NULL,
            instance_id INT UNSIGNED NOT NULL,
            retry_count TINYINT UNSIGNED DEFAULT 0,
            max_retries TINYINT UNSIGNED DEFAULT 3,
            last_error TEXT,
            next_retry_at TIMESTAMP NULL,
            status ENUM('pending','processing','completed','failed') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY status_retry (status, next_retry_at),
            KEY submission_id (submission_id),
            KEY instance_id (instance_id)
        ) {$charset_collate};";

        // Webhook notifications table
        $table_webhooks = $wpdb->prefix . FFFL_TABLE_WEBHOOKS;
        $sql_webhooks = "CREATE TABLE {$table_webhooks} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            instance_id INT UNSIGNED,
            name VARCHAR(255) NOT NULL,
            url VARCHAR(500) NOT NULL,
            events JSON NOT NULL,
            secret VARCHAR(255),
            is_active TINYINT(1) DEFAULT 1,
            last_triggered_at TIMESTAMP NULL,
            success_count INT UNSIGNED DEFAULT 0,
            failure_count TINYINT UNSIGNED DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY instance_active (instance_id, is_active)
        ) {$charset_collate};";

        // API usage tracking table for rate limit monitoring
        $table_api_usage = $wpdb->prefix . FFFL_TABLE_API_USAGE;
        $sql_api_usage = "CREATE TABLE {$table_api_usage} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            instance_id INT UNSIGNED NOT NULL,
            endpoint VARCHAR(100) NOT NULL,
            method VARCHAR(50) NOT NULL,
            status_code SMALLINT UNSIGNED,
            response_time_ms INT UNSIGNED,
            success TINYINT(1) DEFAULT 1,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY instance_endpoint (instance_id, endpoint),
            KEY created_at (created_at),
            KEY instance_date (instance_id, created_at),
            KEY endpoint_date (endpoint, created_at)
        ) {$charset_collate};";

        // Resume tokens table for "save and continue later" feature
        $table_resume_tokens = $wpdb->prefix . FFFL_TABLE_RESUME_TOKENS;
        $sql_resume_tokens = "CREATE TABLE {$table_resume_tokens} (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            session_id VARCHAR(64) NOT NULL,
            instance_id INT UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            email VARCHAR(255) NOT NULL,
            expires_at TIMESTAMP NOT NULL,
            used_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY token (token),
            KEY session_instance (session_id, instance_id),
            KEY expires_at (expires_at),
            KEY instance_id (instance_id)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        dbDelta($sql_instances);
        dbDelta($sql_submissions);
        dbDelta($sql_logs);
        dbDelta($sql_retry_queue);
        dbDelta($sql_webhooks);
        dbDelta($sql_api_usage);
        dbDelta($sql_resume_tokens);
    }

    /**
     * Set default plugin options
     */
    private static function set_default_options(): void {
        $default_settings = [
            'log_retention_days' => 90,
            'session_timeout_minutes' => 30,
            'rate_limit_requests' => 120,
            'rate_limit_window' => 60,
            'cleanup_abandoned_hours' => 24,
        ];

        if (!get_option('fffl_settings')) {
            add_option('fffl_settings', $default_settings);
        }
    }

    /**
     * Schedule cron events for maintenance tasks
     */
    private static function schedule_cron_events(): void {
        // Clean up abandoned sessions daily
        if (!wp_next_scheduled('fffl_cleanup_sessions')) {
            wp_schedule_event(time(), 'daily', 'fffl_cleanup_sessions');
        }

        // Clean up old logs weekly
        if (!wp_next_scheduled('fffl_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'fffl_cleanup_logs');
        }

        // Process retry queue every 5 minutes
        if (!wp_next_scheduled('fffl_process_retry_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'fffl_process_retry_queue');
        }
    }

    /**
     * Add custom cron schedules
     */
    public static function add_cron_schedules(array $schedules): array {
        $schedules['five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every 5 Minutes', 'formflow-lite')
        ];
        return $schedules;
    }
}
