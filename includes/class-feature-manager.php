<?php
namespace FFFL;

if (!defined('ABSPATH')) {
    exit;
}


/**
 * Feature Manager - Lite Version
 *
 * Manages per-instance feature toggles and configurations.
 * Simplified for FormFlow Lite with core features only.
 */


class FeatureManager {

    /**
     * Default feature settings - Lite version features only
     */
    private static array $defaults = [
        // Form Experience
        'inline_validation' => [
            'enabled' => true,
            'show_success_icons' => true,
            'validate_on_blur' => true,
            'validate_on_keyup' => false,
        ],
        'auto_save' => [
            'enabled' => true,
            'interval_seconds' => 60,
            'use_local_storage' => true,
            'show_save_indicator' => true,
        ],
        'spanish_translation' => [
            'enabled' => false,
            'default_language' => 'en',
            'show_language_toggle' => true,
            'auto_detect' => true,
        ],

        // Scheduling
        'appointment_self_service' => [
            'enabled' => false,
            'allow_reschedule' => true,
            'allow_cancel' => true,
            'reschedule_deadline_hours' => 24,
            'cancel_deadline_hours' => 24,
            'require_reason_for_cancel' => true,
            'token_expiry_days' => 30,
        ],
        'capacity_management' => [
            'enabled' => false,
            'daily_cap' => 0,
            'per_slot_cap' => 0,
            'blackout_dates' => [],
            'enable_waitlist' => false,
            'waitlist_notification' => true,
        ],

        // Tracking (basic - full analytics in Peanut Suite)
        'utm_tracking' => [
            'enabled' => true,
            'track_referrer' => true,
            'track_landing_page' => true,
            'pass_to_api' => false,
            'store_in_submission' => true,
        ],
    ];

    /**
     * Get feature settings for an instance
     */
    public static function get_features(array $instance): array {
        $settings = $instance['settings'] ?? [];
        $features = $settings['features'] ?? [];

        // Merge with defaults
        $merged = [];
        foreach (self::$defaults as $feature => $defaults) {
            if (isset($features[$feature]) && is_array($features[$feature])) {
                $merged[$feature] = array_merge($defaults, $features[$feature]);
            } else {
                $merged[$feature] = $defaults;
            }
        }

        return $merged;
    }

    /**
     * Check if a feature is enabled for an instance
     */
    public static function is_enabled(array $instance, string $feature): bool {
        $features = self::get_features($instance);
        return !empty($features[$feature]['enabled']);
    }

    /**
     * Get a specific feature's settings
     */
    public static function get_feature(array $instance, string $feature): array {
        $features = self::get_features($instance);
        return $features[$feature] ?? [];
    }

    /**
     * Get feature setting value
     */
    public static function get_setting(array $instance, string $feature, string $setting, $default = null) {
        $feature_settings = self::get_feature($instance, $feature);
        return $feature_settings[$setting] ?? $default;
    }

    /**
     * Get all available features with their metadata
     */
    public static function get_available_features(): array {
        return [
            // Form Experience
            'inline_validation' => [
                'name' => __('Inline Field Validation', 'formflow-lite'),
                'description' => __('Real-time validation feedback as users type', 'formflow-lite'),
                'category' => 'form_experience',
                'icon' => 'yes-alt',
            ],
            'auto_save' => [
                'name' => __('Auto-Save Drafts', 'formflow-lite'),
                'description' => __('Automatically save form progress', 'formflow-lite'),
                'category' => 'form_experience',
                'icon' => 'backup',
            ],
            'spanish_translation' => [
                'name' => __('Spanish Translation', 'formflow-lite'),
                'description' => __('Full Spanish language support with toggle', 'formflow-lite'),
                'category' => 'form_experience',
                'icon' => 'translation',
            ],

            // Scheduling
            'appointment_self_service' => [
                'name' => __('Appointment Self-Service', 'formflow-lite'),
                'description' => __('Allow customers to reschedule or cancel appointments', 'formflow-lite'),
                'category' => 'scheduling',
                'icon' => 'calendar-alt',
            ],
            'capacity_management' => [
                'name' => __('Capacity Management', 'formflow-lite'),
                'description' => __('Limit appointments per day/slot with blackout dates', 'formflow-lite'),
                'category' => 'scheduling',
                'icon' => 'groups',
            ],

            // Tracking
            'utm_tracking' => [
                'name' => __('UTM Tracking', 'formflow-lite'),
                'description' => __('Capture marketing attribution from URL parameters', 'formflow-lite'),
                'category' => 'tracking',
                'icon' => 'chart-line',
            ],
        ];
    }

    /**
     * Get features grouped by category
     */
    public static function get_features_by_category(): array {
        $features = self::get_available_features();
        $categories = [
            'form_experience' => [
                'label' => __('Form Experience', 'formflow-lite'),
                'features' => [],
            ],
            'scheduling' => [
                'label' => __('Scheduling', 'formflow-lite'),
                'features' => [],
            ],
            'tracking' => [
                'label' => __('Tracking', 'formflow-lite'),
                'features' => [],
            ],
        ];

        foreach ($features as $key => $feature) {
            $category = $feature['category'] ?? 'other';
            if (isset($categories[$category])) {
                $categories[$category]['features'][$key] = $feature;
            }
        }

        // Remove empty categories
        return array_filter($categories, fn($cat) => !empty($cat['features']));
    }

    /**
     * Validate feature configuration
     */
    public static function validate_config(string $feature, array $config): array {
        $errors = [];
        $defaults = self::$defaults[$feature] ?? null;

        if (!$defaults) {
            $errors[] = sprintf(__('Unknown feature: %s', 'formflow-lite'), $feature);
            return $errors;
        }

        return $errors;
    }
}
