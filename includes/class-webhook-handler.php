<?php
namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Webhook Handler
 *
 * Handles sending webhook notifications for enrollment events.
 * Enhanced for Peanut Suite integration with attribution data.
 */


use FFFL\Database\Database;

class WebhookHandler {

    private Database $db;

    /**
     * Available webhook events - Enhanced for Peanut Suite integration
     */
    public const EVENTS = [
        'form.viewed' => 'Form Viewed',
        'form.step_completed' => 'Form Step Completed',
        'account.validated' => 'Account Validated',
        'enrollment.submitted' => 'Enrollment Submitted to API',
        'enrollment.completed' => 'Enrollment Completed',
        'enrollment.failed' => 'Enrollment Failed',
        'appointment.scheduled' => 'Appointment Scheduled',
    ];

    public function __construct() {
        $this->db = new Database();
    }

    /**
     * Trigger webhooks for an event
     *
     * @param string $event Event name
     * @param array $data Event data
     * @param int|null $instance_id Instance ID
     * @return array Results for each webhook
     */
    public function trigger(string $event, array $data, ?int $instance_id = null): array {
        $webhooks = $this->db->get_webhooks_for_event($event, $instance_id);
        $results = [];

        foreach ($webhooks as $webhook) {
            $result = $this->send($webhook, $event, $data);
            $results[$webhook['id']] = $result;

            // Update webhook record
            $this->db->update_webhook_triggered($webhook['id'], $result['success']);

            // Log the webhook call
            $this->db->log(
                $result['success'] ? 'info' : 'warning',
                "Webhook {$webhook['name']}: " . ($result['success'] ? 'Success' : 'Failed'),
                [
                    'webhook_id' => $webhook['id'],
                    'event' => $event,
                    'status_code' => $result['status_code'] ?? null,
                    'error' => $result['error'] ?? null,
                ],
                $instance_id
            );
        }

        return $results;
    }

    /**
     * Send a webhook request
     *
     * @param array $webhook Webhook configuration
     * @param string $event Event name
     * @param array $data Event data
     * @return array Result with success status
     */
    private function send(array $webhook, string $event, array $data): array {
        // Build enhanced payload for Peanut Suite integration
        $payload = $this->build_enhanced_payload($event, $data);

        $json_payload = json_encode($payload);

        $headers = [
            'Content-Type' => 'application/json',
            'X-FFFL-Event' => $event,
            'X-FFFL-Timestamp' => time(),
            'X-FFFL-Source' => 'formflow-lite',
        ];

        // Add signature if secret is configured
        if (!empty($webhook['secret'])) {
            $signature = hash_hmac('sha256', $json_payload, $webhook['secret']);
            $headers['X-FFFL-Signature'] = $signature;
        }

        $args = [
            'method' => 'POST',
            'headers' => $headers,
            'body' => $json_payload,
            'timeout' => 15,
            'sslverify' => true,
        ];

        $response = wp_remote_request($webhook['url'], $args);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'error' => $response->get_error_message(),
            ];
        }

        $status_code = wp_remote_retrieve_response_code($response);

        return [
            'success' => $status_code >= 200 && $status_code < 300,
            'status_code' => $status_code,
            'body' => wp_remote_retrieve_body($response),
        ];
    }

    /**
     * Build enhanced payload for Peanut Suite integration
     *
     * Creates a comprehensive payload structure that includes all
     * relevant data for analytics and attribution in Peanut Suite.
     *
     * @param string $event Event name
     * @param array $data Event data
     * @return array Enhanced payload
     */
    private function build_enhanced_payload(string $event, array $data): array {
        $payload = [
            'event' => $event,
            'timestamp' => current_time('c'),
            'source' => 'formflow-lite',
            'version' => FFFL_VERSION,
        ];

        // Add instance data if available
        if (isset($data['instance_id']) || isset($data['instance'])) {
            $instance_id = $data['instance_id'] ?? $data['instance']['id'] ?? null;
            $payload['instance'] = [
                'id' => $instance_id,
                'slug' => $data['instance_slug'] ?? $data['instance']['slug'] ?? null,
                'utility' => $data['utility'] ?? $data['instance']['utility'] ?? null,
            ];
        }

        // Add submission data if available
        if (isset($data['submission_id']) || isset($data['submission'])) {
            $payload['submission'] = [
                'id' => $data['submission_id'] ?? $data['submission']['id'] ?? null,
                'session_id' => $data['session_id'] ?? null,
                'status' => $data['status'] ?? null,
                'confirmation_number' => $data['confirmation_number'] ?? null,
            ];
        }

        // Add customer data (sanitized for privacy)
        if (isset($data['form_data']) || isset($data['customer'])) {
            $form_data = $data['form_data'] ?? $data['customer'] ?? [];
            $payload['customer'] = [
                'email' => $this->mask_email($form_data['email'] ?? null),
                'zip' => $form_data['zip'] ?? $form_data['zipcode'] ?? null,
                'device_type' => $data['device_type'] ?? $this->detect_device_type(),
            ];

            // Include account number (masked)
            if (!empty($form_data['account_number'])) {
                $payload['customer']['account_masked'] = $this->mask_account($form_data['account_number']);
            }
        }

        // Add scheduling data if available
        if (isset($data['appointment']) || isset($data['schedule'])) {
            $schedule = $data['appointment'] ?? $data['schedule'] ?? [];
            $payload['scheduling'] = [
                'date' => $schedule['date'] ?? null,
                'time_slot' => $schedule['time'] ?? $schedule['time_slot'] ?? null,
                'fsr_number' => $schedule['fsr'] ?? $schedule['fsr_number'] ?? null,
            ];
        }

        // Add step data for step events
        if (isset($data['step'])) {
            $payload['step'] = [
                'number' => $data['step'],
                'name' => $data['step_name'] ?? 'step_' . $data['step'],
            ];
        }

        // Add visitor ID from Peanut integration if available
        $visitor_id = apply_filters(Hooks::GET_VISITOR_ID, $data['visitor_id'] ?? null);
        if ($visitor_id) {
            $payload['visitor_id'] = $visitor_id;
        }

        // Add UTM attribution data
        $attribution = $this->get_attribution_data();
        if (!empty($attribution)) {
            $payload['attribution'] = $attribution;
        }

        // Add any extra data that wasn't categorized
        $handled_keys = ['instance_id', 'instance_slug', 'instance', 'utility', 'submission_id',
                         'submission', 'session_id', 'status', 'confirmation_number',
                         'form_data', 'customer', 'device_type', 'appointment', 'schedule',
                         'step', 'step_name', 'visitor_id'];
        $extra = array_diff_key($data, array_flip($handled_keys));
        if (!empty($extra)) {
            $payload['metadata'] = $extra;
        }

        // Allow filtering of payload
        return apply_filters(Hooks::WEBHOOK_PAYLOAD, $payload, $event, $data['instance_id'] ?? null);
    }

    /**
     * Mask email for privacy
     *
     * @param string|null $email Email address
     * @return string|null Masked email
     */
    private function mask_email(?string $email): ?string {
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $parts = explode('@', $email);
        $name = $parts[0];
        $domain = $parts[1] ?? '';

        if (strlen($name) <= 2) {
            return $name[0] . '*@' . $domain;
        }

        return $name[0] . str_repeat('*', strlen($name) - 2) . substr($name, -1) . '@' . $domain;
    }

    /**
     * Mask account number for privacy
     *
     * @param string $account Account number
     * @return string Masked account
     */
    private function mask_account(string $account): string {
        $length = strlen($account);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }
        return str_repeat('*', $length - 4) . substr($account, -4);
    }

    /**
     * Detect device type from user agent
     *
     * @return string Device type (desktop, mobile, tablet)
     */
    private function detect_device_type(): string {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (preg_match('/tablet|ipad/i', $ua)) {
            return 'tablet';
        }

        if (preg_match('/mobile|android|iphone/i', $ua)) {
            return 'mobile';
        }

        return 'desktop';
    }

    /**
     * Get UTM attribution data from cookies/session for webhook payload
     *
     * @return array Attribution data
     */
    private function get_attribution_data(): array {
        $attribution = [
            'utm_source' => $_COOKIE['fffl_utm_source'] ?? null,
            'utm_medium' => $_COOKIE['fffl_utm_medium'] ?? null,
            'utm_campaign' => $_COOKIE['fffl_utm_campaign'] ?? null,
            'utm_term' => $_COOKIE['fffl_utm_term'] ?? null,
            'utm_content' => $_COOKIE['fffl_utm_content'] ?? null,
            'gclid' => $_COOKIE['fffl_gclid'] ?? null,
            'fbclid' => $_COOKIE['fffl_fbclid'] ?? null,
            'referrer' => $_COOKIE['fffl_referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? null),
            'landing_page' => $_COOKIE['fffl_landing_page'] ?? null,
        ];

        // Remove null values
        return array_filter($attribution, fn($v) => $v !== null);
    }

    /**
     * Get available events for display
     *
     * @return array Event name => Label pairs
     */
    public static function get_available_events(): array {
        return self::EVENTS;
    }
}
