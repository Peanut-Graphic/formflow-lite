<?php
/**
 * Utilities Configuration
 *
 * Pre-configured settings for supported utilities.
 *
 * @package FormFlow
 */

namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Utilities class
 */
class Utilities
{
    /**
     * Get all supported utilities
     *
     * @return array
     */
    public static function getAll(): array
    {
        // Delmarva Delaware is intentionally omitted: the Delaware Energy Wise
        // Rewards program is not offered (no DE enrollment or scheduler).
        return [
            'delmarva_md' => self::getDelmarvaMD(),
            'pepco_md' => self::getPepcoMD(),
            'pepco_dc' => self::getPepcoDC(),
        ];
    }

    /**
     * Get utility by key
     *
     * @param string $key Utility key.
     * @return array|null
     */
    public static function get(string $key): ?array
    {
        $utilities = self::getAll();
        return $utilities[$key] ?? null;
    }

    /**
     * Get utility display name
     *
     * @param string $key Utility key.
     * @return string
     */
    public static function getName(string $key): string
    {
        $utility = self::get($key);
        return $utility['name'] ?? $key;
    }

    /**
     * Get utility options for select dropdowns
     *
     * @return array
     */
    public static function getOptions(): array
    {
        $options = [];
        foreach (self::getAll() as $key => $utility) {
            $options[$key] = $utility['name'];
        }
        return $options;
    }

    /**
     * Get Delmarva Power - Maryland configuration
     *
     * @return array
     */
    private static function getDelmarvaMD(): array
    {
        return [
            'name' => 'Delmarva Power - Maryland',
            'short_name' => 'Delmarva MD',
            'state' => 'MD',
            'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
            'program_name' => 'Energy Wise Rewards',
            'program_url' => 'https://energywiserewards.delmarva.com',
            'support_phone' => '1-888-818-0075',
            'support_email' => 'support@energywiserewards.com',
            'equipment_types' => [
                'thermostat' => ['05', '10', '15', '20'],
                'dcu' => ['01'],
            ],
            'time_slots' => [
                'AM' => ['label' => 'Morning', 'range' => '8:00 AM - 12:00 PM'],
                'MD' => ['label' => 'Midday', 'range' => '10:00 AM - 2:00 PM'],
                'PM' => ['label' => 'Afternoon', 'range' => '12:00 PM - 5:00 PM'],
                'EV' => ['label' => 'Evening', 'range' => '3:00 PM - 7:00 PM'],
            ],
            'scheduling' => [
                'min_days_out' => 3,
                'max_days_out' => 60,
                'exclude_weekends' => false,
            ],
            'branding' => [
                'primary_color' => '#0066cc',
                'logo_url' => '',
            ],
            'terms_url' => 'https://energywiserewards.delmarva.com/terms',
            'privacy_url' => 'https://energywiserewards.delmarva.com/privacy',
        ];
    }

    /**
     * Get Pepco - Maryland configuration
     *
     * @return array
     */
    private static function getPepcoMD(): array
    {
        return [
            'name' => 'Pepco - Maryland',
            'short_name' => 'Pepco MD',
            'state' => 'MD',
            'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
            'program_name' => 'Energy Wise Rewards',
            'program_url' => 'https://energywiserewards.pepco.com',
            'support_phone' => '1-888-818-0075',
            'support_email' => 'support@energywiserewards.com',
            'equipment_types' => [
                'thermostat' => ['05', '10', '15', '20'],
                'dcu' => ['01'],
            ],
            'time_slots' => [
                'AM' => ['label' => 'Morning', 'range' => '8:00 AM - 12:00 PM'],
                'MD' => ['label' => 'Midday', 'range' => '10:00 AM - 2:00 PM'],
                'PM' => ['label' => 'Afternoon', 'range' => '12:00 PM - 5:00 PM'],
                'EV' => ['label' => 'Evening', 'range' => '3:00 PM - 7:00 PM'],
            ],
            'scheduling' => [
                'min_days_out' => 3,
                'max_days_out' => 60,
                'exclude_weekends' => false,
            ],
            'branding' => [
                'primary_color' => '#00a94f',
                'logo_url' => '',
            ],
            'terms_url' => 'https://energywiserewards.pepco.com/terms',
            'privacy_url' => 'https://energywiserewards.pepco.com/privacy',
        ];
    }

    /**
     * Get Pepco - DC configuration
     *
     * @return array
     */
    private static function getPepcoDC(): array
    {
        return [
            'name' => 'Pepco - Washington DC',
            'short_name' => 'Pepco DC',
            'state' => 'DC',
            'api_endpoint' => 'https://ph.powerportal.com/phiIntelliSOURCE/api',
            'program_name' => 'Energy Wise Rewards',
            'program_url' => 'https://energywiserewards.pepco.com',
            'support_phone' => '1-888-818-0075',
            'support_email' => 'support@energywiserewards.com',
            'equipment_types' => [
                'thermostat' => ['05', '10', '15', '20'],
                'dcu' => ['01'],
            ],
            'time_slots' => [
                'AM' => ['label' => 'Morning', 'range' => '8:00 AM - 12:00 PM'],
                'MD' => ['label' => 'Midday', 'range' => '10:00 AM - 2:00 PM'],
                'PM' => ['label' => 'Afternoon', 'range' => '12:00 PM - 5:00 PM'],
                'EV' => ['label' => 'Evening', 'range' => '3:00 PM - 7:00 PM'],
            ],
            'scheduling' => [
                'min_days_out' => 3,
                'max_days_out' => 60,
                'exclude_weekends' => false,
            ],
            'branding' => [
                'primary_color' => '#00a94f',
                'logo_url' => '',
            ],
            'terms_url' => 'https://energywiserewards.pepco.com/terms',
            'privacy_url' => 'https://energywiserewards.pepco.com/privacy',
        ];
    }

    /**
     * Get equipment type label
     *
     * @param string $code Equipment code.
     * @return string
     */
    public static function getEquipmentLabel(string $code): string
    {
        $labels = [
            '01' => __('Outdoor Cycling Switch (DCU)', 'formflow-lite'),
            '05' => __('Smart Thermostat - Standard', 'formflow-lite'),
            '10' => __('Smart Thermostat - WiFi', 'formflow-lite'),
            '15' => __('Smart Thermostat - Heat Pump', 'formflow-lite'),
            '20' => __('Smart Thermostat - Dual Fuel', 'formflow-lite'),
        ];

        return $labels[$code] ?? sprintf(__('Equipment %s', 'formflow-lite'), $code);
    }

    /**
     * Get time slot label with range
     *
     * @param string $code   Time slot code.
     * @param string $utility Utility key.
     * @return string
     */
    public static function getTimeSlotLabel(string $code, string $utility = ''): string
    {
        $utility_config = !empty($utility) ? self::get($utility) : null;
        $slots = $utility_config['time_slots'] ?? [
            'AM' => ['label' => 'Morning', 'range' => '8:00 AM - 12:00 PM'],
            'MD' => ['label' => 'Midday', 'range' => '10:00 AM - 2:00 PM'],
            'PM' => ['label' => 'Afternoon', 'range' => '12:00 PM - 5:00 PM'],
            'EV' => ['label' => 'Evening', 'range' => '3:00 PM - 7:00 PM'],
        ];

        if (isset($slots[$code])) {
            return sprintf('%s (%s)', $slots[$code]['label'], $slots[$code]['range']);
        }

        return $code;
    }

    /**
     * Get states served by utilities
     *
     * @return array
     */
    public static function getStates(): array
    {
        return [
            'DC' => 'District of Columbia',
            'MD' => 'Maryland',
        ];
    }

    /**
     * Get utilities for a specific state
     *
     * @param string $state State code.
     * @return array
     */
    public static function getByState(string $state): array
    {
        $result = [];
        foreach (self::getAll() as $key => $utility) {
            if ($utility['state'] === $state) {
                $result[$key] = $utility;
            }
        }
        return $result;
    }

    /**
     * Validate utility key
     *
     * @param string $key Utility key.
     * @return bool
     */
    public static function isValid(string $key): bool
    {
        return isset(self::getAll()[$key]);
    }
}
