<?php
/**
 * IntelliSource Connector Loader
 *
 * Registers the IntelliSource connector with the core plugin.
 *
 * @package FormFlow
 * @subpackage Connectors
 * @since 2.0.0
 */

namespace FFFL\Connectors\IntelliSource;

if (!defined('ABSPATH')) {
    exit;
}

// Define connector path.
//
// FFFL_ prefix, not the ISF one: that namespace belongs to FormFlow Pro, which
// ships its own IntelliSource connector and defines its path constant
// unguarded. With both plugins active the second to load kept the FIRST
// plugin's path, so Lite would require Pro's connector files - which declare
// Pro's classes, never Lite's FFFL ones. A !defined() guard would only hide the
// warning while leaving the path wrong. PHP 9 turns the redefinition into a
// fatal. Guarded by NoProNamespaceCollisionTest.
define('FFFL_INTELLISOURCE_PATH', __DIR__);

/**
 * Load connector classes
 */
function load_connector(): void {
    // 3.2.5: the IntelliSource-specific xml parser file does not exist in this
    // build. The connector uses \FFFL\Api\XmlParser (loaded by class-plugin.php)
    // instead. Keeping this comment as a tombstone in case a future build
    // reintroduces a connector-specific parser.
    require_once FFFL_INTELLISOURCE_PATH . '/class-intellisource-field-mapper.php';
    require_once FFFL_INTELLISOURCE_PATH . '/class-intellisource-connector.php';
}

/**
 * Register the IntelliSource connector
 *
 * @param \FFFL\Api\ConnectorRegistry $registry
 */
function register_connector($registry): void {
    load_connector();

    $connector = new IntelliSourceConnector();
    $registry->register($connector);
}

// Hook into connector registration
add_action('fffl_register_connectors', __NAMESPACE__ . '\\register_connector');

/**
 * Provide backward compatibility with legacy Utilities class
 *
 * This filter allows the old Utilities::getAll() pattern to still work
 * by pulling data from the connector presets.
 */
add_filter('fffl_legacy_utilities', function ($utilities) {
    $connector = new IntelliSourceConnector();
    $presets = $connector->get_presets();

    foreach ($presets as $key => $preset) {
        $utilities[$key] = array_merge($preset, [
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
            'terms_url' => $preset['program_url'] . '/terms',
            'privacy_url' => $preset['program_url'] . '/privacy',
        ]);
    }

    return $utilities;
});
