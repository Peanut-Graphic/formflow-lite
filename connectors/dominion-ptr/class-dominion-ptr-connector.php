<?php
namespace FFFL\Connectors\DominionPtr;

use FFFL\Api\ApiConnectorInterface;
use FFFL\Api\AccountValidationResult;
use FFFL\Api\EnrollmentResult;
use FFFL\Api\SchedulingResult;
use FFFL\Api\BookingResult;
use FFFL\Api\ApiClient;

if (!defined('ABSPATH')) { exit; }

/**
 * Dominion Peak Time Rebates connector.
 *
 * Speaks Dominion's IntelliSource JSON API (prospect/validate,
 * portal_user_emails, prospect/enroll) under /ptr/residential/api. Distinct
 * from the XML `intellisource` connector, which serves the Energy Wise
 * programs and must not be touched.
 */
class DominionPtrConnector implements ApiConnectorInterface {

    public function get_id(): string { return 'dominion-ptr'; }
    public function get_name(): string { return __('Dominion Peak Time Rebates', 'formflow-lite'); }
    public function get_description(): string {
        return __('Dominion PTR enrollment via the IntelliSource JSON API.', 'formflow-lite');
    }
    public function get_version(): string { return '1.0.0'; }

    public function get_config_fields(): array {
        return [
            'api_endpoint' => [
                'label' => __('API Endpoint', 'formflow-lite'),
                'type' => 'url',
                'required' => true,
                'description' => __('Base URL for the Dominion PTR JSON API (…/ptr/residential/api)', 'formflow-lite'),
                'default' => 'https://www.dominionenergyptr.com/ptr/residential/api',
            ],
            'test_mode' => [
                'label' => __('Test Mode', 'formflow-lite'),
                'type' => 'checkbox',
                'required' => false,
                'description' => __('Stub enrollment (validation stays live).', 'formflow-lite'),
                'default' => false,
            ],
        ];
    }

    public function validate_config(array $config): array {
        $errors = [];
        if (empty($config['api_endpoint']) || !filter_var($config['api_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('A valid API Endpoint is required', 'formflow-lite');
        }
        return $errors;
    }

    public function test_connection(array $config): array {
        // cep_configurations is a lightweight unauthenticated GET.
        try {
            $this->http_get_json(rtrim($config['api_endpoint'], '/') . '/cep_configurations');
            return ['success' => true, 'message' => __('Connection successful', 'formflow-lite')];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function validate_account(array $data, array $config): AccountValidationResult {
        // Implemented in Task 2.
        return new AccountValidationResult(['is_valid' => false, 'error_code' => 'not_implemented']);
    }

    public function submit_enrollment(array $form_data, array $config): EnrollmentResult {
        // Implemented in Task 5 (mock) / Task 7 (live).
        return new EnrollmentResult(['success' => false, 'error_code' => 'not_implemented']);
    }

    public function get_schedule_slots(array $data, array $config): SchedulingResult {
        return new SchedulingResult(['success' => false, 'error_code' => 'unsupported']);
    }

    public function book_appointment(array $data, array $config): BookingResult {
        return new BookingResult(['success' => false, 'error_code' => 'unsupported']);
    }

    public function map_fields(array $form_data, string $type = 'enrollment'): array {
        return [
            'utility_no' => trim($form_data['account_number'] ?? $form_data['utility_no'] ?? ''),
            'zip' => preg_replace('/\D/', '', $form_data['zip'] ?? ''),
            'email' => trim($form_data['email'] ?? ''),
        ];
    }

    public function get_supported_features(): array { return ['enrollment']; }
    public function supports(string $feature): bool { return in_array($feature, $this->get_supported_features(), true); }

    public function get_presets(): array {
        return [
            'dominion_ptr' => [
                'name' => __('Dominion Energy — Peak Time Rebates', 'formflow-lite'),
                'short_name' => 'Dominion PTR',
                'state' => 'VA',
                'api_endpoint' => 'https://www.dominionenergyptr.com/ptr/residential/api',
                'program_name' => 'Peak Time Rebates',
                'program_url' => 'https://www.dominionenergyptr.com',
                'connector' => 'dominion-ptr',
                'disable_device' => true,
                'disable_scheduling' => true,
                'branding' => ['primary_color' => '#0072ce', 'logo_url' => ''],
            ],
        ];
    }

    /**
     * GET a JSON endpoint. Protected so tests can override with a fixture.
     *
     * @return array Decoded JSON as an associative array.
     * @throws \Exception on transport error, non-2xx, or invalid JSON.
     */
    protected function http_get_json(string $url, array $query = []): array {
        if ($query) {
            $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($query);
        }
        if (!ApiClient::is_safe_outbound_url($url)) {
            throw new \Exception(__('Blocked request to a non-public or unsafe URL', 'formflow-lite'));
        }
        $response = wp_remote_request($url, ['method' => 'GET', 'timeout' => 30, 'sslverify' => true, 'reject_unsafe_urls' => true]);
        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }
        $status = (int) wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($status >= 400) {
            throw new \Exception("HTTP {$status}");
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new \Exception(__('Invalid JSON response', 'formflow-lite'));
        }
        return $decoded;
    }
}
