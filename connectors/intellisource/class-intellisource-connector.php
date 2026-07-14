<?php
/**
 * IntelliSource API Connector
 *
 * Connector for the PowerPortal IntelliSOURCE API used by utility programs
 * like Delmarva Power and Pepco Energy Wise Rewards.
 *
 * @package FormFlow
 * @subpackage Connectors
 * @since 2.0.0
 */

namespace FFFL\Connectors\IntelliSource;

use FFFL\Api\ApiClient;
use FFFL\Api\ApiConnectorInterface;
use FFFL\Api\AccountValidationResult;
use FFFL\Api\EnrollmentResult;
use FFFL\Api\SchedulingResult;
use FFFL\Api\BookingResult;
use FFFL\Api\XmlParser;
use FFFL\Database\Database;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IntelliSourceConnector
 *
 * Implements the API connector interface for IntelliSOURCE/PowerPortal systems.
 */
class IntelliSourceConnector implements ApiConnectorInterface {

    /**
     * Connector identifier
     */
    public const ID = 'intellisource';

    /**
     * Connector version
     */
    public const VERSION = '2.0.0';

    /**
     * Database instance
     */
    private ?Database $db = null;

    /**
     * Get connector identifier
     *
     * @return string
     */
    public function get_id(): string {
        return self::ID;
    }

    /**
     * Get connector display name
     *
     * @return string
     */
    public function get_name(): string {
        return __('IntelliSOURCE / PowerPortal', 'formflow-lite');
    }

    /**
     * Get connector description
     *
     * @return string
     */
    public function get_description(): string {
        return __('API connector for utility demand response programs using the PowerPortal IntelliSOURCE platform.', 'formflow-lite');
    }

    /**
     * Get connector version
     *
     * @return string
     */
    public function get_version(): string {
        return self::VERSION;
    }

    /**
     * Get required configuration fields
     *
     * @return array
     */
    public function get_config_fields(): array {
        return [
            'api_endpoint' => [
                'label' => __('API Endpoint', 'formflow-lite'),
                'type' => 'url',
                'required' => true,
                'description' => __('Base URL for the IntelliSOURCE API (e.g., https://ph.powerportal.com/phiIntelliSOURCE/api)', 'formflow-lite'),
                'default' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
            ],
            'api_password' => [
                'label' => __('API Password', 'formflow-lite'),
                'type' => 'password',
                'required' => true,
                'description' => __('Authentication password for API calls', 'formflow-lite'),
                'encrypted' => true,
            ],
            'test_mode' => [
                'label' => __('Test Mode', 'formflow-lite'),
                'type' => 'checkbox',
                'required' => false,
                'description' => __('Enable test mode to use mock responses instead of live API', 'formflow-lite'),
                'default' => false,
            ],
        ];
    }

    /**
     * Validate configuration
     *
     * @param array $config
     * @return array
     */
    public function validate_config(array $config): array {
        $errors = [];

        if (empty($config['api_endpoint'])) {
            $errors[] = __('API Endpoint is required', 'formflow-lite');
        } elseif (!filter_var($config['api_endpoint'], FILTER_VALIDATE_URL)) {
            $errors[] = __('API Endpoint must be a valid URL', 'formflow-lite');
        }

        if (empty($config['api_password'])) {
            $errors[] = __('API Password is required', 'formflow-lite');
        }

        return $errors;
    }

    /**
     * Test API connection
     *
     * @param array $config
     * @return array
     */
    public function test_connection(array $config): array {
        try {
            $response = $this->make_request(
                $config['api_endpoint'],
                '/promo_codes',
                ['pswd' => $config['api_password']],
                'GET',
                false
            );

            return [
                'success' => true,
                'message' => __('Connection successful', 'formflow-lite'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => sprintf(__('Connection failed: %s', 'formflow-lite'), $e->getMessage()),
            ];
        }
    }

    /**
     * Validate an account
     *
     * @param array $data
     * @param array $config
     * @return AccountValidationResult
     */
    public function validate_account(array $data, array $config): AccountValidationResult {
        // Get account number. Match the legacy form's account routing:
        //  - an 'X'-prefixed entry is a Comverge account -> send as caNo (X removed)
        //  - everything else (plain utility numbers AND 'PHI'-prefixed Comverge
        //    numbers) is sent as utility_no UNCHANGED. Do NOT strip non-digits:
        //    that removes the 'PHI' prefix and IntelliSource then returns a
        //    validation error (Code 01). 3.2.11.
        $account = trim($data['account_number'] ?? $data['utility_no'] ?? '');

        $params = [
            'zip' => preg_replace('/\D/', '', $data['zip'] ?? ''), // ZIP should be digits only
            'pswd' => $config['api_password'],
            'val' => 'submit',
        ];
        if ($account !== '' && strtolower($account[0]) === 'x') {
            $params['caNo'] = preg_replace('/\D/', '', substr($account, 1));
        } else {
            $params['utility_no'] = $account;
        }

        try {
            $response = $this->make_request(
                $config['api_endpoint'],
                '/prospects/validate.xml',
                $params,
                'GET',
                true
            );

            return $this->parse_validation_response($response);
        } catch (\Exception $e) {
            return new AccountValidationResult([
                'is_valid' => false,
                'error_code' => 'connection_error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Submit enrollment
     *
     * @param array $form_data
     * @param array $config
     * @return EnrollmentResult
     */
    public function submit_enrollment(array $form_data, array $config): EnrollmentResult {
        // Map form fields to API format
        $api_params = $this->map_fields($form_data, 'enrollment');

        // Add authentication
        $api_params['pswd'] = $config['api_password'];
        $api_params['val'] = 'submit';

        try {
            $response = $this->make_request(
                $config['api_endpoint'],
                '/prospects/enroll.xml',
                $api_params,
                'GET',
                true
            );

            return $this->parse_enrollment_response($response);
        } catch (\Exception $e) {
            return new EnrollmentResult([
                'success' => false,
                'error_code' => 'connection_error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get available schedule slots
     *
     * @param array $data
     * @param array $config
     * @return SchedulingResult
     */
    public function get_schedule_slots(array $data, array $config): SchedulingResult {
        $params = [
            'startDate' => $data['start_date'] ?? date('m/d/Y'),
            'pswd' => $config['api_password'],
            'val' => 'submit',
        ];

        // Handle account number format (see validate_account note, 3.2.11):
        //  - 'X'-prefixed -> caNo (X removed, digits)
        //  - everything else -> utility_no UNCHANGED (preserve 'PHI' prefix;
        //    stripping it triggers an IntelliSource validation error).
        // For admin view, account might be empty or 'ADMIN-VIEW' - don't send then.
        $account = trim($data['account_number'] ?? $data['utility_no'] ?? '');
        if ($account !== '' && $account !== 'ADMIN-VIEW') {
            if (strtolower($account[0]) === 'x') {
                $params['caNo'] = preg_replace('/\D/', '', substr($account, 1));
            } else {
                $params['utility_no'] = $account;
            }
        }
        // For admin view without account, API should return general availability

        // Add equipment counts
        if (!empty($data['equipment'])) {
            foreach ($data['equipment'] as $type => $eq_config) {
                if (isset($eq_config['count'])) {
                    $params["eqCount-{$type}"] = $eq_config['count'];
                }
                if (isset($eq_config['location'])) {
                    $params["eqLoc-{$type}"] = $eq_config['location'];
                }
            }
        }

        try {
            $response = $this->make_request(
                $config['api_endpoint'],
                '/field_service_requests/scheduling.xml',
                $params,
                'GET',
                true
            );

            return $this->parse_scheduling_response($response);
        } catch (\Exception $e) {
            return new SchedulingResult([
                'success' => false,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Book an appointment
     *
     * @param array $data
     * @param array $config
     * @return BookingResult
     */
    public function book_appointment(array $data, array $config): BookingResult {
        // Map time codes
        $time_map = [
            'MD' => 'Mid-Day',
            'md' => 'Mid-Day',
            'EV' => 'Evening',
            'ev' => 'Evening',
        ];
        $time = $time_map[$data['time']] ?? $data['time'];

        $params = [
            'fsr' => $data['fsr'] ?? '',
            'caNo' => $data['ca_no'] ?? '',
            'schedule_date' => $data['schedule_date'] ?? '',
            'time' => $time,
            'pswd' => $config['api_password'],
            'val' => 'submit',
        ];

        // Add equipment
        if (!empty($data['equipment'])) {
            foreach ($data['equipment'] as $type => $eq_config) {
                if (isset($eq_config['count']) && $eq_config['count'] > 0) {
                    $params["eqCount-{$type}"] = $eq_config['count'];
                    if (isset($eq_config['location'])) {
                        $params["eqLoc-{$type}"] = $eq_config['location'];
                    }
                    if (isset($eq_config['desired_device'])) {
                        $params["dd-{$type}"] = $eq_config['desired_device'];
                    }
                }
            }
        }

        try {
            $response = $this->make_request(
                $config['api_endpoint'],
                '/field_service_requests/schedule',
                $params,
                'GET',
                false
            );

            return $this->parse_booking_response($response, $data);
        } catch (\Exception $e) {
            return new BookingResult([
                'success' => false,
                'error_code' => 'connection_error',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Map form fields to API format
     *
     * @param array $form_data
     * @param string $type
     * @return array
     */
    public function map_fields(array $form_data, string $type = 'enrollment'): array {
        $mapper = new IntelliSourceFieldMapper();

        if ($type === 'scheduling') {
            return $mapper->map_scheduling_data($form_data);
        }

        return $mapper->map_enrollment_data($form_data);
    }

    /**
     * Get supported features
     *
     * @return array
     */
    public function get_supported_features(): array {
        return [
            'account_validation',
            'enrollment',
            'scheduling',
            'promo_codes',
            'equipment_selection',
            'cycling_levels',
        ];
    }

    /**
     * Check if feature is supported
     *
     * @param string $feature
     * @return bool
     */
    public function supports(string $feature): bool {
        return in_array($feature, $this->get_supported_features(), true);
    }

    /**
     * Get preset configurations for utilities
     *
     * @return array
     */
    public function get_presets(): array {
        // Delmarva Delaware omitted: the DE program is not offered.
        return [
            'delmarva_md' => [
                'name' => __('Delmarva Power - Maryland', 'formflow-lite'),
                'short_name' => 'Delmarva MD',
                'state' => 'MD',
                'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
                'program_name' => 'Energy Wise Rewards',
                'program_url' => 'https://energywiserewards.delmarva.com',
                'support_phone' => '1-888-818-0075',
                'support_email' => 'support@energywiserewards.com',
                'branding' => [
                    'primary_color' => '#0066cc',
                    'logo_url' => '',
                ],
            ],
            'pepco_md' => [
                'name' => __('Pepco - Maryland', 'formflow-lite'),
                'short_name' => 'Pepco MD',
                'state' => 'MD',
                'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
                'program_name' => 'Energy Wise Rewards',
                'program_url' => 'https://energywiserewards.pepco.com',
                'support_phone' => '1-888-818-0075',
                'support_email' => 'support@energywiserewards.com',
                'branding' => [
                    'primary_color' => '#00a94f',
                    'logo_url' => '',
                ],
            ],
            'pepco_dc' => [
                'name' => __('Pepco - Washington DC', 'formflow-lite'),
                'short_name' => 'Pepco DC',
                'state' => 'DC',
                'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
                'program_name' => 'Energy Wise Rewards',
                'program_url' => 'https://energywiserewards.pepco.com',
                'support_phone' => '1-888-818-0075',
                'support_email' => 'support@energywiserewards.com',
                'branding' => [
                    'primary_color' => '#00a94f',
                    'logo_url' => '',
                ],
            ],
        ];
    }

    /**
     * Make an API request
     *
     * @param string $endpoint
     * @param string $path
     * @param array $params
     * @param string $method
     * @param bool $parse_xml
     * @return array|string
     */
    private function make_request(
        string $endpoint,
        string $path,
        array $params,
        string $method = 'GET',
        bool $parse_xml = true
    ): array|string {
        $url = rtrim($endpoint, '/') . $path;

        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
            $body = null;
        } else {
            $body = http_build_query($params);
        }

        // SSRF guard: the endpoint is admin-supplied and only validated with
        // FILTER_VALIDATE_URL, which accepts loopback/link-local/private hosts
        // and non-http(s) schemes. Block those before the outbound call so a
        // crafted endpoint can't reach internal services / cloud metadata.
        if (!ApiClient::is_safe_outbound_url($url)) {
            throw new \Exception(
                __('Blocked request to a non-public or unsafe URL', 'formflow-lite')
            );
        }

        $args = [
            'method' => $method,
            'timeout' => 30,
            'sslverify' => true,
            // Defence in depth: WordPress also refuses private/loopback hosts.
            'reject_unsafe_urls' => true,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
        ];

        if ($body) {
            $args['body'] = $body;
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $status = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($status >= 400) {
            throw new \Exception("HTTP {$status}: {$body}");
        }

        if ($parse_xml && !empty($body)) {
            return XmlParser::parse($body);
        }

        return $body;
    }

    /**
     * Parse validation response
     *
     * @param array $response
     * @return AccountValidationResult
     */
    private function parse_validation_response(array $response): AccountValidationResult {
        // Check for error codes
        $error_code = $response['error_cd'] ?? $response['errorCode'] ?? '';
        $error_message = $response['error_message'] ?? $response['error_message'] ?? '';

        // Check validation status
        $valid = ($response['valid'] ?? '') === 'Y'
            || ($response['status'] ?? '') === 'valid';

        if ($error_code === '03') {
            // Already enrolled
            return new AccountValidationResult([
                'is_valid' => false,
                'error_code' => 'already_enrolled',
                'error_message' => __('This account is already enrolled in the program.', 'formflow-lite'),
                'customer_data' => $response,
            ]);
        }

        if ($error_code === '21') {
            // Medical condition flag
            return new AccountValidationResult([
                'is_valid' => true,
                'error_code' => 'medical_condition',
                'error_message' => __('Medical condition acknowledgment required.', 'formflow-lite'),
                'customer_data' => $response,
            ]);
        }

        return new AccountValidationResult([
            'is_valid' => $valid,
            'error_code' => $error_code,
            'error_message' => $error_message,
            'customer_data' => $response,
            'raw_response' => $response,
        ]);
    }

    /**
     * Parse enrollment response
     *
     * @param array $response
     * @return EnrollmentResult
     */
    private function parse_enrollment_response(array $response): EnrollmentResult {
        $success = ($response['status'] ?? '') === 'success'
            || !empty($response['confirmation_no'])
            || !empty($response['caNo']);

        return new EnrollmentResult([
            'success' => $success,
            'confirmation_number' => $response['confirmation_no'] ?? $response['caNo'] ?? '',
            'error_code' => $response['error_cd'] ?? '',
            'error_message' => $response['error_message'] ?? '',
            'data' => $response,
            'raw_response' => $response,
        ]);
    }

    /**
     * Parse scheduling response
     *
     * @param array $response
     * @return SchedulingResult
     */
    private function parse_scheduling_response(array $response): SchedulingResult {
        // Parse available slots from response
        $slots = [];

        if (isset($response['slots']) && is_array($response['slots'])) {
            foreach ($response['slots'] as $slot) {
                $slots[] = [
                    'date' => $slot['date'] ?? '',
                    'time' => $slot['time'] ?? '',
                    'available' => ($slot['available'] ?? 'Y') === 'Y',
                ];
            }
        }

        return new SchedulingResult([
            'success' => !empty($slots) || isset($response['fsr']),
            'fsr' => $response['fsr'] ?? '',
            'ca_no' => $response['caNo'] ?? '',
            'slots' => $slots,
            'raw_response' => $response,
        ]);
    }

    /**
     * Parse booking response
     *
     * @param mixed $response
     * @param array $data
     * @return BookingResult
     */
    private function parse_booking_response($response, array $data): BookingResult {
        // 3.2.5: switched from "negative" check (no "error" string ⇒ success) to
        // a positive-confirmation check. The old check false-positived on
        // "No available slots", HTML error pages, and XML error responses
        // that didn't literally contain the word "error". Booking success
        // requires an explicit confirmation/caNo/fsr token in the response.
        $body = is_string($response) ? $response : '';
        $parsed = is_array($response) ? $response : [];

        if ($parsed === [] && $body !== '' && stripos($body, '<') !== false) {
            try {
                $parsed = XmlParser::parse($body);
            } catch (\Exception $e) {
                $parsed = [];
            }
        }

        // XmlParser returns {root: {child: {value: "..."}}}. Unwrap a single
        // wrapper element if present so we can read leaf tokens directly.
        $inner = $parsed;
        if (is_array($inner) && count($inner) === 1) {
            $only = reset($inner);
            if (is_array($only) && !array_key_exists('value', $only)) {
                $inner = $only;
            }
        }

        $field = static function (array $bag, string ...$keys): string {
            foreach ($keys as $key) {
                if (!isset($bag[$key])) {
                    continue;
                }
                if (is_array($bag[$key]) && isset($bag[$key]['value'])) {
                    return (string)$bag[$key]['value'];
                }
                if (is_scalar($bag[$key])) {
                    return (string)$bag[$key];
                }
            }
            return '';
        };

        $confirmation = $field($inner, 'confirmation', 'confirmationNumber', 'confirmation_number');
        $ca_no        = $field($inner, 'caNo', 'ca_no', 'CaNo');
        $fsr          = $field($inner, 'fsr', 'fsrNo', 'FSR');

        $error_code    = $field($inner, 'error_cd', 'errorCode', 'errCode', 'error_code');
        $error_message = $field($inner, 'error_message', 'errorMessage', 'message', 'desc');

        $has_positive_marker = $confirmation !== '' || $ca_no !== '' || $fsr !== '';
        $has_error_marker    = $error_code !== ''
            || stripos($body, '<error') !== false
            || stripos($body, '<err_') !== false
            || stripos($body, 'no available') !== false
            || stripos($body, 'already scheduled') !== false;

        $success = $has_positive_marker && !$has_error_marker;

        return new BookingResult([
            'success' => $success,
            'confirmation_number' => $confirmation !== '' ? $confirmation : $ca_no,
            'appointment_date' => $data['schedule_date'] ?? '',
            'appointment_time' => $data['time'] ?? '',
            'error_code' => $success ? '' : ($error_code !== '' ? $error_code : 'no_confirmation_marker'),
            'error_message' => $success
                ? ''
                : ($error_message !== ''
                    ? $error_message
                    : 'Booking response did not contain a confirmation, caNo, or fsr marker.'),
            'raw_response' => $parsed !== [] ? $parsed : ['body' => $body],
        ]);
    }

    /**
     * Get database instance
     *
     * @return Database
     */
    private function get_db(): Database {
        if ($this->db === null) {
            $this->db = new Database();
        }
        return $this->db;
    }
}
