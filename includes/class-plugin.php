<?php
namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Main Plugin Class
 *
 * Orchestrates the plugin by loading dependencies and registering hooks.
 * Lite version - core enrollment functionality only.
 */


class Plugin {

    /**
     * Admin instance
     */
    private ?Admin\Admin $admin = null;

    /**
     * Public (frontend) instance
     */
    private ?Frontend\Frontend $public = null;

    /**
     * Run the plugin
     */
    public function run(): void {
        $this->load_dependencies();
        // Textdomain is loaded in main plugin file before Plugin class is instantiated
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->register_cron_handlers();
    }

    /**
     * Load required dependencies
     */
    private function load_dependencies(): void {
        // Core classes are autoloaded, but we need to ensure they exist
        require_once FFFL_PLUGIN_DIR . 'includes/class-security.php';
        require_once FFFL_PLUGIN_DIR . 'includes/class-encryption.php';
        require_once FFFL_PLUGIN_DIR . 'includes/database/class-database.php';
        require_once FFFL_PLUGIN_DIR . 'includes/class-hooks.php';

        // API classes
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-xml-parser.php';
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-api-client.php';
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-mock-api-client.php';
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-validation-result.php';
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-scheduling-result.php';
        require_once FFFL_PLUGIN_DIR . 'includes/api/class-response-validator.php';

        // Form classes
        require_once FFFL_PLUGIN_DIR . 'includes/forms/class-form-handler.php';
        require_once FFFL_PLUGIN_DIR . 'includes/forms/class-email-handler.php';

        // UTM Tracker for basic attribution (passed to webhooks)
        require_once FFFL_PLUGIN_DIR . 'includes/class-utm-tracker.php';

        // Peanut Suite integration
        require_once FFFL_PLUGIN_DIR . 'includes/class-peanut-integration.php';
        add_action('plugins_loaded', function() {
            PeanutIntegration::instance();
        }, 15);
    }

    /**
     * Register admin-side hooks
     */
    private function define_admin_hooks(): void {
        if (!is_admin()) {
            return;
        }

        require_once FFFL_PLUGIN_DIR . 'admin/class-admin.php';
        $this->admin = new Admin\Admin();

        // Admin notices for security warnings
        add_action('admin_notices', [$this, 'display_admin_notices']);
        add_action('wp_ajax_fffl_dismiss_notice', [$this, 'ajax_dismiss_notice']);

        // Admin menu
        add_action('admin_menu', [$this->admin, 'add_admin_menu']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_styles']);
        add_action('admin_enqueue_scripts', [$this->admin, 'enqueue_scripts']);

        // Instance management AJAX handlers
        add_action('wp_ajax_fffl_save_instance', [$this->admin, 'ajax_save_instance']);
        add_action('wp_ajax_fffl_delete_instance', [$this->admin, 'ajax_delete_instance']);
        add_action('wp_ajax_fffl_duplicate_instance', [$this->admin, 'ajax_duplicate_instance']);
        add_action('wp_ajax_fffl_save_instance_order', [$this->admin, 'ajax_save_instance_order']);

        // API testing AJAX handlers
        add_action('wp_ajax_fffl_test_api', [$this->admin, 'ajax_test_api']);
        add_action('wp_ajax_fffl_test_form', [$this->admin, 'ajax_test_form']);
        add_action('wp_ajax_fffl_check_api_health', [$this->admin, 'ajax_check_api_health']);
        add_action('wp_ajax_fffl_scheduling_diagnostics', [$this->admin, 'ajax_scheduling_diagnostics']);

        // Logs AJAX handlers
        add_action('wp_ajax_fffl_get_logs', [$this->admin, 'ajax_get_logs']);
        add_action('wp_ajax_fffl_bulk_logs_action', [$this->admin, 'ajax_bulk_logs_action']);

        // Submission AJAX handlers
        add_action('wp_ajax_fffl_get_submission_details', [$this->admin, 'ajax_get_submission_details']);
        add_action('wp_ajax_fffl_export_submissions_csv', [$this->admin, 'ajax_export_submissions_csv']);
        add_action('wp_ajax_fffl_bulk_submissions_action', [$this->admin, 'ajax_bulk_submissions_action']);
        add_action('wp_ajax_fffl_mark_test_data', [$this->admin, 'ajax_mark_test_data']);
        add_action('wp_ajax_fffl_delete_test_data', [$this->admin, 'ajax_delete_test_data']);
        add_action('wp_ajax_fffl_get_test_counts', [$this->admin, 'ajax_get_test_counts']);

        // Webhook AJAX handlers
        add_action('wp_ajax_fffl_get_webhook', [$this->admin, 'ajax_get_webhook']);
        add_action('wp_ajax_fffl_save_webhook', [$this->admin, 'ajax_save_webhook']);
        add_action('wp_ajax_fffl_delete_webhook', [$this->admin, 'ajax_delete_webhook']);
        add_action('wp_ajax_fffl_test_webhook', [$this->admin, 'ajax_test_webhook']);

        // API usage/rate limiting AJAX handlers
        add_action('wp_ajax_fffl_get_api_usage', [$this->admin, 'ajax_get_api_usage']);

        // Diagnostics AJAX handlers
        add_action('wp_ajax_fffl_run_diagnostics', [$this->admin, 'ajax_run_diagnostics']);
        add_action('wp_ajax_fffl_quick_health_check', [$this->admin, 'ajax_quick_health_check']);

        // Form Builder AJAX handlers
        add_action('wp_ajax_fffl_builder_save', [$this->admin, 'ajax_builder_save']);
        add_action('wp_ajax_fffl_builder_preview', [$this->admin, 'ajax_builder_preview']);

        // Form preview modal handler
        add_action('wp_ajax_fffl_preview_instance', [$this->admin, 'ajax_preview_instance']);
    }

    /**
     * Register public-facing hooks
     */
    private function define_public_hooks(): void {
        require_once FFFL_PLUGIN_DIR . 'public/class-public.php';
        $this->public = new Frontend\Frontend();

        // Register shortcodes
        add_shortcode('fffl_form', [$this->public, 'render_form_shortcode']);
        add_shortcode('fffl_enroll_button', [$this->public, 'render_enroll_button_shortcode']);

        // Frontend assets
        add_action('wp_enqueue_scripts', [$this->public, 'enqueue_styles']);
        add_action('wp_enqueue_scripts', [$this->public, 'enqueue_scripts']);

        // Public AJAX handlers (both logged in and not logged in)
        $ajax_actions = [
            'fffl_load_step',
            'fffl_validate_account',
            'fffl_enroll_early',       // Submit enrollment at end of step 3
            'fffl_get_schedule_slots',
            'fffl_submit_enrollment',  // Final confirmation (books appointment only)
            'fffl_book_appointment',
            'fffl_save_progress',
            'fffl_save_and_email',
            'fffl_resume_form',
        ];

        foreach ($ajax_actions as $action) {
            add_action("wp_ajax_{$action}", [$this->public, $action]);
            add_action("wp_ajax_nopriv_{$action}", [$this->public, $action]);
        }
    }

    /**
     * Display admin notices for security warnings
     */
    public function display_admin_notices(): void {
        // Only show on plugin pages or dashboard
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }

        $is_plugin_page = strpos($screen->id, 'fffl-') !== false || $screen->id === 'dashboard';
        if (!$is_plugin_page) {
            return;
        }

        // Only show to users who can manage options
        if (!current_user_can('manage_options')) {
            return;
        }

        // Check if notice was dismissed
        $dismissed = get_option('fffl_dismissed_notices', []);

        // Check encryption key status
        $key_status = Encryption::get_key_status();
        if ($key_status['status'] !== 'ok' && !in_array('encryption_key_' . $key_status['code'], $dismissed)) {
            $notice_class = $key_status['status'] === 'error' ? 'notice-error' : 'notice-warning';
            ?>
            <div class="notice <?php echo esc_attr($notice_class); ?> is-dismissible" data-fffl-notice="encryption_key_<?php echo esc_attr($key_status['code']); ?>">
                <p>
                    <strong><?php esc_html_e('FormFlow Lite Security Notice:', 'formflow-lite'); ?></strong>
                    <?php echo esc_html($key_status['message']); ?>
                </p>
                <p>
                    <code>define('FFFL_ENCRYPTION_KEY', '<?php echo esc_html(wp_generate_password(32, false)); ?>');</code>
                </p>
                <p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fffl-tools&tab=settings')); ?>">
                        <?php esc_html_e('View Security Settings', 'formflow-lite'); ?>
                    </a>
                </p>
            </div>
            <script>
            jQuery(document).ready(function($) {
                $('[data-fffl-notice]').on('click', '.notice-dismiss', function() {
                    var notice = $(this).closest('[data-fffl-notice]').data('fffl-notice');
                    $.post(ajaxurl, {
                        action: 'fffl_dismiss_notice',
                        notice: notice,
                        nonce: '<?php echo esc_js(wp_create_nonce('fffl_dismiss_notice')); ?>'
                    });
                });
            });
            </script>
            <?php
        }
    }

    /**
     * AJAX handler for dismissing admin notices
     */
    public function ajax_dismiss_notice(): void {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'fffl_dismiss_notice')) {
            wp_send_json_error(['message' => 'Invalid security token']);
        }

        // Verify permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions']);
        }

        // Sanitize notice ID
        $notice = isset($_POST['notice']) ? sanitize_key($_POST['notice']) : '';
        if (empty($notice)) {
            wp_send_json_error(['message' => 'Invalid notice ID']);
        }

        // Get current dismissed notices and add this one
        $dismissed = get_option('fffl_dismissed_notices', []);
        if (!is_array($dismissed)) {
            $dismissed = [];
        }

        if (!in_array($notice, $dismissed)) {
            $dismissed[] = $notice;
            update_option('fffl_dismissed_notices', $dismissed);
        }

        wp_send_json_success(['dismissed' => $notice]);
    }

    /**
     * Register cron event handlers
     */
    private function register_cron_handlers(): void {
        // Add custom cron schedules
        add_filter('cron_schedules', [$this, 'add_cron_schedules']);

        // Register cron handlers
        add_action('fffl_cleanup_sessions', [$this, 'cleanup_abandoned_sessions']);
        add_action('fffl_cleanup_logs', [$this, 'cleanup_old_logs']);
        add_action('fffl_process_retry_queue', [$this, 'process_retry_queue']);

        // Ensure all scheduled events are registered
        add_action('init', [$this, 'ensure_cron_events_scheduled']);
    }

    /**
     * Add custom cron schedules
     */
    public function add_cron_schedules($schedules) {
        $schedules['five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes', 'formflow-lite')
        ];
        $schedules['weekly'] = [
            'interval' => 604800,
            'display' => __('Once Weekly', 'formflow-lite')
        ];
        return $schedules;
    }

    /**
     * Ensure all cron events are scheduled
     */
    public function ensure_cron_events_scheduled(): void {
        // Only run once per day
        $last_check = get_transient('fffl_cron_check');
        if ($last_check) {
            return;
        }
        set_transient('fffl_cron_check', true, DAY_IN_SECONDS);

        // Reschedule missing events
        if (!wp_next_scheduled('fffl_cleanup_sessions')) {
            wp_schedule_event(time(), 'daily', 'fffl_cleanup_sessions');
        }

        if (!wp_next_scheduled('fffl_cleanup_logs')) {
            wp_schedule_event(time(), 'weekly', 'fffl_cleanup_logs');
        }

        if (!wp_next_scheduled('fffl_process_retry_queue')) {
            wp_schedule_event(time(), 'five_minutes', 'fffl_process_retry_queue');
        }
    }

    /**
     * Clean up abandoned form sessions
     */
    public function cleanup_abandoned_sessions(): void {
        $settings = get_option('fffl_settings', []);
        $hours = $settings['cleanup_abandoned_hours'] ?? 24;

        $db = new Database\Database();
        $db->mark_abandoned_sessions($hours);
    }

    /**
     * Clean up old log entries
     */
    public function cleanup_old_logs(): void {
        $settings = get_option('fffl_settings', []);
        $days = $settings['log_retention_days'] ?? 90;

        $db = new Database\Database();
        $db->delete_old_logs($days);
    }

    /**
     * Process the retry queue for failed submissions
     */
    public function process_retry_queue(): void {
        require_once FFFL_PLUGIN_DIR . 'includes/class-retry-processor.php';

        $processor = new RetryProcessor();
        $processor->process();
    }

    /**
     * Get admin instance
     */
    public function get_admin(): ?Admin\Admin {
        return $this->admin;
    }

    /**
     * Get public instance
     */
    public function get_public(): ?Frontend\Frontend {
        return $this->public;
    }
}
