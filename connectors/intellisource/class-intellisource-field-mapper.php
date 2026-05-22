<?php
/**
 * IntelliSource Field Mapper
 *
 * Maps internal form field names to PowerPortal IntelliSOURCE API parameter names.
 *
 * @package FormFlow
 * @subpackage Connectors
 * @since 2.0.0
 */

namespace FFFL\Connectors\IntelliSource;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class IntelliSourceFieldMapper
 */
class IntelliSourceFieldMapper {

    /**
     * Contract type codes based on cycling level and device type
     */
    private const CONTRACT_CODES = [
        '50%-Pro-VHF' => '01',
        '50%-Pro-Z' => '02',
        '50%-IT900' => '03',
        '50%-DCU' => '04',
        '75%-Pro-VHF' => '05',
        '75%-Pro-Z' => '06',
        '75%-IT900' => '07',
        '75%-DCU' => '08',
        '100%-Pro-VHF' => '09',
        '100%-Pro-Z' => '10',
        '100%-IT900' => '11',
        '100%-DCU' => '12',
        '50%-Business-DCU' => '13',
        '50%-Business-IT900' => '14',
        '50%-Business-Pro-Z' => '15',
        '50%-MMA-IT900' => '16',
        '75%-MMA-IT900' => '17',
        '100%-MMA-IT900' => '18',
        '50%-MMA-DCU' => '19',
        '75%-MMA-DCU' => '20',
        '100%-MMA-DCU' => '21',
    ];

    /**
     * Map of internal field names to API parameter names for enrollment
     */
    private const ENROLLMENT_FIELD_MAP = [
        // Account Information
        'utility_no' => 'utility_no',
        'account_number' => 'utility_no',
        'cycling_level' => 'level',

        // Personal Information
        'first_name' => 'fname',
        'last_name' => 'lname',

        // Address
        'street' => 'address',
        'street2' => 'address2',
        'city' => 'city',
        'state' => 'state',
        'zip' => 'zip',
        'zip_confirm' => 'zip',

        // Contact Information
        'phone' => 'dayPhone',
        'alt_phone' => 'evePhone',
        'email' => 'email',

        // Property Information
        'ownership' => 'ownsPrem',
        'thermostat_count' => 'eqCount-15',

        // Additional
        'promo_code' => 'pCode',

        // DCU-specific
        'easy_access' => 'easyAccess',
        'install_time' => 'installTime',
    ];

    /**
     * Map of internal field names to API parameter names for scheduling
     */
    private const SCHEDULING_FIELD_MAP = [
        'account_number' => 'caNo',
        'utility_no' => 'utility_no',
        'schedule_date' => 'schedule_date',
        'schedule_time' => 'time',
    ];

    /**
     * Required fields for enrollment API call
     */
    private const REQUIRED_ENROLLMENT_FIELDS = [
        'utility_no',
        'level',
        'fname',
        'lname',
        'zip',
        'dayPhone',
        'email',
        'ownsPrem',
        'eqCount-15',
    ];

    /**
     * Promo codes to include in dropdown
     */
    private const PROMO_INCLUDE_LIST = [
        'WEB', 'RADIO', 'BLOG', 'BROCHURE', 'BUS', 'STOP', 'EVENT',
        'FACEBOOK', 'FRIEND', 'INSTALLER', 'NEWSPAPER', 'OTHER',
    ];

    /**
     * Transform form data to API parameters for enrollment
     *
     * @param array $form_data
     * @return array
     */
    public function map_enrollment_data(array $form_data): array {
        $api_params = [];

        // Map each field using the enrollment map
        foreach (self::ENROLLMENT_FIELD_MAP as $internal_name => $api_name) {
            if (isset($form_data[$internal_name]) && $form_data[$internal_name] !== '') {
                if (!isset($api_params[$api_name]) || $api_params[$api_name] === '') {
                    $api_params[$api_name] = $this->transform_value($internal_name, $form_data[$internal_name]);
                }
            }
        }

        // Handle account number - sanitize non-digits and handle Comverge format (starts with X)
        $account = $form_data['utility_no'] ?? $form_data['account_number'] ?? '';
        if (strtolower(substr($account, 0, 1)) === 'x') {
            // Comverge format: X followed by digits
            unset($api_params['utility_no']);
            $api_params['caNo'] = preg_replace('/\D/', '', substr($account, 1));
        } else {
            // Standard utility account - strip all non-digits
            $api_params['utility_no'] = preg_replace('/\D/', '', $account);
        }

        // Set partType (01 = residential)
        $api_params['partType'] = '01';

        // Calculate contract code
        $level = $form_data['cycling_level'] ?? '100';
        $device_type = $form_data['device_type'] ?? 'thermostat';
        $api_params['contract'] = $this->get_contract_code($level, $device_type);

        // Handle ownership
        $ownership = strtolower($form_data['ownership'] ?? 'own');
        $api_params['ownsPrem'] = ($ownership === 'lease' || $ownership === 'rent') ? '02' : '01';

        // Handle landlord info when renting
        if ($api_params['ownsPrem'] === '02') {
            $fname = str_replace(' ', '', $form_data['first_name'] ?? '');
            $lname = str_replace(' ', '', $form_data['last_name'] ?? '');
            $api_params['llordName'] = 'Landlord,' . $fname . $lname;
            $api_params['llordPhone'] = '1234567890';
            $api_params['llordAuth'] = '02';
        } else {
            $api_params['llordName'] = '';
            $api_params['llordPhone'] = '';
            $api_params['llordAuth'] = '02';
        }

        // Phone - strip non-numeric
        if (isset($api_params['dayPhone'])) {
            $api_params['dayPhone'] = preg_replace('/\D/', '', $api_params['dayPhone']);
        }
        if (isset($api_params['evePhone'])) {
            $api_params['evePhone'] = preg_replace('/\D/', '', $api_params['evePhone']);
        }
        $api_params['dayPhoneExt'] = '';

        // Equipment location and count
        $api_params['eqLoc-15'] = '05'; // Interior
        if (!isset($api_params['eqCount-15'])) {
            $api_params['eqCount-15'] = $form_data['thermostat_count'] ?? '1';
        }

        // Desired device based on type.
        // Device codes (must match the values IntelliSource maps to its device
        // catalog): '02' = DCU (outdoor switch), '03' = Sensei WiFi thermostat.
        // NOTE: 3.2.10 — was '05' here, which IntelliSource maps to IntelliTemp,
        // not Sensei WiFi. The legacy production enrollment form sent '03' for
        // thermostats; restored to match so enrollments register as Sensei WiFi.
        if ($device_type === 'dcu') {
            $api_params['dd-15'] = '02';
            $easy_access = $form_data['easy_access'] ?? 'Yes';
            $install_time = $form_data['install_time'] ?? 'Anytime';
            $api_params['mustSchedule'] = ($easy_access === 'No' || $install_time === 'Appointment') ? 'Y' : 'N';
        } else {
            $api_params['dd-15'] = '03'; // Sensei WiFi thermostat
        }

        // Additional required fields
        $api_params['overrideFlag'] = '01';
        $api_params['noStories'] = '0';
        $api_params['gated'] = 'No';
        $api_params['mktSrc'] = 'WEB';
        $api_params['val'] = 'val';

        return $api_params;
    }

    /**
     * Transform form data to API parameters for scheduling
     *
     * @param array $form_data
     * @return array
     */
    public function map_scheduling_data(array $form_data): array {
        $api_params = [];

        foreach (self::SCHEDULING_FIELD_MAP as $internal_name => $api_name) {
            if (isset($form_data[$internal_name]) && $form_data[$internal_name] !== '') {
                $api_params[$api_name] = $this->transform_value($internal_name, $form_data[$internal_name]);
            }
        }

        // Handle account number format
        if (isset($form_data['account_number'])) {
            $account = $form_data['account_number'];
            if (strtolower(substr($account, 0, 1)) === 'x') {
                $api_params['caNo'] = substr($account, 1);
            } else {
                $api_params['utility_no'] = $account;
            }
        }

        return $api_params;
    }

    /**
     * Get contract code based on cycling level and device type
     *
     * @param string $level
     * @param string $device_type
     * @return string
     */
    public function get_contract_code(string $level, string $device_type): string {
        if ($device_type === 'dcu') {
            $key = $level . '%-DCU';
        } else {
            $key = $level . '%-Pro-VHF';
        }

        return self::CONTRACT_CODES[$key] ?? '09';
    }

    /**
     * Transform a value based on field type
     *
     * @param string $field_name
     * @param mixed $value
     * @return mixed
     */
    private function transform_value(string $field_name, mixed $value): mixed {
        switch ($field_name) {
            case 'cycling_level':
                return (string) $value;

            case 'state':
                return strtoupper($value);

            case 'phone':
            case 'alt_phone':
                return preg_replace('/\D/', '', $value);

            case 'email':
                return strtolower(trim($value));

            case 'zip':
            case 'zip_confirm':
                return preg_replace('/[^0-9\-]/', '', $value);

            case 'thermostat_count':
                return (string) max(1, (int) $value);

            default:
                return is_string($value) ? trim($value) : $value;
        }
    }

    /**
     * Filter promo codes
     *
     * @param array $promo_codes
     * @param string $utility_prefix
     * @return array
     */
    public static function filter_promo_codes(array $promo_codes, string $utility_prefix = 'DE'): array {
        $filtered = [];

        foreach ($promo_codes as $code) {
            $code = trim($code);
            if (empty($code)) {
                continue;
            }

            if (in_array(strtoupper($code), self::PROMO_INCLUDE_LIST)) {
                $filtered[] = $code;
                continue;
            }

            if (strtoupper(substr($code, 0, strlen($utility_prefix) + 1)) === $utility_prefix . 'C') {
                $filtered[] = $code;
            }
        }

        return $filtered;
    }

    /**
     * Validate account number for utility
     *
     * @param string $account_number
     * @param string $utility
     * @return array
     */
    public static function validate_account_for_utility(string $account_number, string $utility): array {
        $account = preg_replace('/\D/', '', $account_number);
        $errors = [];

        // Check for Pepco account in Delmarva form
        if (strpos(strtolower($utility), 'delmarva') !== false) {
            if (strlen($account) === 10) {
                $errors[] = [
                    'code' => 'pepco_account',
                    'message' => __("This appears to be a Pepco account number. Please visit the Pepco enrollment page.", 'formflow-lite'),
                ];
            }
        }

        // Check for Delmarva account in Pepco form
        if (strpos(strtolower($utility), 'pepco') !== false) {
            if (strlen($account) !== 10) {
                $errors[] = [
                    'code' => 'delmarva_account',
                    'message' => __("This appears to be a Delmarva Power account number. Please visit the Delmarva enrollment page.", 'formflow-lite'),
                ];
            }
        }

        return $errors;
    }

    /**
     * Get field label for API field name
     *
     * @param string $api_field
     * @return string
     */
    public static function get_field_label(string $api_field): string {
        $labels = [
            'accountnumber' => __('Account Number', 'formflow-lite'),
            'utility_no' => __('Account Number', 'formflow-lite'),
            'level' => __('Participation Level', 'formflow-lite'),
            'fname' => __('First Name', 'formflow-lite'),
            'lname' => __('Last Name', 'formflow-lite'),
            'address' => __('Street Address', 'formflow-lite'),
            'address2' => __('Address Line 2', 'formflow-lite'),
            'city' => __('City', 'formflow-lite'),
            'state' => __('State', 'formflow-lite'),
            'zip' => __('ZIP Code', 'formflow-lite'),
            'dayPhone' => __('Primary Phone', 'formflow-lite'),
            'evePhone' => __('Secondary Phone', 'formflow-lite'),
            'email' => __('Email Address', 'formflow-lite'),
            'ownsPrem' => __('Lease or Own', 'formflow-lite'),
            'eqCount-15' => __('Number of Thermostats/Units', 'formflow-lite'),
            'pCode' => __('Promo Code', 'formflow-lite'),
            'schedule_date' => __('Appointment Date', 'formflow-lite'),
            'time' => __('Appointment Time', 'formflow-lite'),
            'contract' => __('Contract Type', 'formflow-lite'),
        ];

        return $labels[$api_field] ?? ucwords(str_replace(['_', '-'], ' ', $api_field));
    }
}
