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
        $base = rtrim($config['api_endpoint'], '/');
        $mapped = $this->map_fields($data);

        try {
            $validate = $this->http_get_json($base . '/prospect/validate', [
                'email' => $mapped['email'],
                'zip' => $mapped['zip'],
                'utility_no' => $mapped['utility_no'],
            ]);
        } catch (\Exception $e) {
            return new AccountValidationResult(['is_valid' => false, 'error_code' => 'connection_error', 'error_message' => $e->getMessage()]);
        }

        return $this->parse_validate($validate, $base, $mapped);
    }

    /**
     * Parses the /prospect/validate response and, on a found status,
     * enriches it with portal-availability data from /portal_user_emails
     * (non-fatal if that lookup fails).
     */
    private function parse_validate(array $validate, string $base, array $mapped): AccountValidationResult {
        $status = $validate['status'] ?? 'error';
        if ($status !== 'found' || empty($validate['data'])) {
            return new AccountValidationResult(['is_valid' => false, 'error_code' => (string) $status, 'raw_response' => $validate]);
        }

        // Existing-account check (non-fatal if it fails).
        $portal = ['available' => null, 'has_login_history' => null];
        try {
            $portal = $this->http_get_json($base . '/portal_user_emails', ['email' => $mapped['email']]);
        } catch (\Exception $e) { /* leave nulls */ }

        $d = $validate['data'];
        return new AccountValidationResult([
            'is_valid' => true,
            'customer_data' => [
                'prospect_id' => $d['prospect_id'] ?? null,
                'first_name' => $d['first_name'] ?? '',
                'last_name' => $d['last_name'] ?? '',
                'name' => $d['name'] ?? '',
                'email' => $d['email'] ?? $mapped['email'],
                'utility_no' => $d['utility_no'] ?? $mapped['utility_no'],
                'enrollable_premises' => $d['enrollable_premises'] ?? [],
                'portal_available' => $portal['available'] ?? null,
                'has_login_history' => $portal['has_login_history'] ?? null,
            ],
            'raw_response' => $validate,
        ]);
    }

    public function submit_enrollment(array $form_data, array $config): EnrollmentResult {
        if (!empty($config['test_mode'])) {
            $mapped = $this->map_fields($form_data);
            return new EnrollmentResult([
                'success' => true,
                'confirmation_number' => 'PTR-DEMO-' . substr(md5($mapped['utility_no'] . $mapped['email']), 0, 8),
                'data' => [
                    'account_id' => 0,
                    'set_password_token' => 'demo-token',
                ],
            ]);
        }
        // Live path implemented in Stage 2 (gated on Itron).
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

    /**
     * Ordered enrollment step keys for a PTR-style flow. Pure: derives the
     * step list from the instance settings' disable flags. Consumed by the
     * form-render engine (wiring deferred to integration).
     *
     * @param array $settings Instance settings (uses 'disable_device', 'disable_scheduling').
     * @return string[] Ordered step keys.
     */
    public function enrollment_steps(array $settings): array {
        $steps = ['validate'];
        if (empty($settings['disable_device'])) {
            $steps[] = 'device';
        }
        $steps[] = 'address_confirm';
        if (empty($settings['disable_scheduling'])) {
            $steps[] = 'scheduling';
        }
        $steps[] = 'terms';
        $steps[] = 'enroll';
        return $steps;
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

    /**
     * POST a JSON endpoint (form-encoded body, JSON response). Protected so
     * tests can override with a fixture. Mirrors http_get_json.
     *
     * @return array Decoded JSON as an associative array.
     * @throws \Exception on transport error, non-2xx, or invalid JSON.
     */
    protected function http_post_json(string $url, array $data): array {
        if (!ApiClient::is_safe_outbound_url($url)) {
            throw new \Exception(__('Blocked request to a non-public or unsafe URL', 'formflow-lite'));
        }
        $response = wp_remote_request($url, [
            'method' => 'POST',
            'timeout' => 30,
            'sslverify' => true,
            'reject_unsafe_urls' => true,
            'body' => $data,
        ]);
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

    // ------------------------------------------------------------------
    // Stage 2 scaffolding — request/response shapes reverse-engineered from
    // the IntelliSource SPA client code (see
    // Peanut-meta/dominion-ptr-intellisource-api-contract.md). CONFIRMED from
    // their client except where marked. None of these are wired to fire from
    // the live form yet; that happens once Itron confirms the open questions
    // (byot-vs-full enroll, whether verification is required, IP allowlist).
    // ------------------------------------------------------------------

    /**
     * Send an identity-verification code (email or SMS).
     * CONFIRMED: POST api/prospect_verifications with the payload below; the
     * response carries the new verification record id (SPA reads `t.id`).
     *
     * @param array $data   Requires: email; optional: mobile_telephone,
     *                      first_name, last_name, method ('email'|'sms'), byot_only.
     * @param array $config Requires: api_endpoint.
     * @return array {sent: bool, verification_id: ?int, method: string, raw_response: array}
     */
    public function send_verification(array $data, array $config): array {
        $url = rtrim($config['api_endpoint'], '/') . '/prospect_verifications';
        $payload = [
            'method' => $data['method'] ?? 'email',
            'preferred_format' => 'json',
            'email' => $data['email'] ?? '',
            'mobile_telephone' => $data['mobile_telephone'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'byot_only' => $data['byot_only'] ?? true,
        ];
        try {
            $resp = $this->http_post_json($url, $payload);
        } catch (\Exception $e) {
            return ['sent' => false, 'verification_id' => null, 'method' => $payload['method'], 'raw_response' => ['error' => $e->getMessage()]];
        }
        return [
            'sent' => isset($resp['id']),
            'verification_id' => $resp['id'] ?? null,
            'method' => $payload['method'],
            'raw_response' => $resp,
        ];
    }

    /**
     * Check a verification code the customer entered.
     * CONFIRMED: GET api/prospect_verifications/{id}?verification_code=…
     * INFERRED: the exact "passed" field in the response — confirm against a
     * live test. We treat an explicit truthy `verified`/`confirmed` as passed.
     *
     * @return array {verified: bool, raw_response: array}
     */
    public function check_verification(string $verification_id, string $code, array $config): array {
        $url = rtrim($config['api_endpoint'], '/') . '/prospect_verifications/' . rawurlencode($verification_id);
        try {
            $resp = $this->http_get_json($url, ['verification_code' => $code, 'preferred_format' => 'json']);
        } catch (\Exception $e) {
            return ['verified' => false, 'raw_response' => ['error' => $e->getMessage()]];
        }
        $verified = ! empty($resp['verified']) || ! empty($resp['confirmed']) || (($resp['status'] ?? null) === 'verified');
        return ['verified' => $verified, 'raw_response' => $resp];
    }

    /**
     * Provision the portal account for a freshly enrolled prospect and get the
     * hand-off token. CONFIRMED: POST api/portal_user/create_from_prospect with
     * the payload below; the SPA reads `portal_user.id` and
     * `portal_user.enrollment_token` from the response and logs the customer in.
     *
     * @param array $data   Requires: premise_id, zip, utility_no, email,
     *                      first_name, last_name, mobile_telephone.
     * @param array $config Requires: api_endpoint.
     * @return array {success: bool, portal_user_id: ?int, enrollment_token: ?string, raw_response: array}
     */
    public function create_portal_handoff(array $data, array $config): array {
        $url = rtrim($config['api_endpoint'], '/') . '/portal_user/create_from_prospect';
        $payload = [
            'premise_id' => $data['premise_id'] ?? null,
            'zip' => preg_replace('/\D/', '', (string) ($data['zip'] ?? '')),
            'preferred_format' => 'json',
            'utility_no' => $data['utility_no'] ?? '',
            'email' => $data['email'] ?? '',
            'first_name' => $data['first_name'] ?? '',
            'last_name' => $data['last_name'] ?? '',
            'mobile_telephone' => $data['mobile_telephone'] ?? '',
        ];
        try {
            $resp = $this->http_post_json($url, $payload);
        } catch (\Exception $e) {
            return ['success' => false, 'portal_user_id' => null, 'enrollment_token' => null, 'raw_response' => ['error' => $e->getMessage()]];
        }
        $pu = $resp['portal_user'] ?? [];
        return [
            'success' => isset($pu['id'], $pu['enrollment_token']),
            'portal_user_id' => $pu['id'] ?? null,
            'enrollment_token' => $pu['enrollment_token'] ?? null,
            'raw_response' => $resp,
        ];
    }
}
