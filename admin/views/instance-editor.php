<?php
/**
 * Admin Instance Editor View
 *
 * Multi-step wizard form for creating/editing form instances.
 * Features: Wizard navigation, Quick-edit mode, WYSIWYG editors
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include help tooltip helper
require_once FFFL_PLUGIN_DIR . 'admin/views/partials/help-tooltip.php';

$is_edit = !empty($instance);
$page_title = $is_edit
    ? __('Edit Form Instance', 'formflow-lite')
    : __('Add New Form Instance', 'formflow-lite');

// Get settings
$content = $instance['settings']['content'] ?? [];
$fields_config = $instance['settings']['fields'] ?? [];
$scheduling = $instance['settings']['scheduling'] ?? [];
$blocked_dates = $scheduling['blocked_dates'] ?? [];
$capacity_limits = $scheduling['capacity_limits'] ?? [];
$maintenance = $instance['settings']['maintenance'] ?? [];

// States for dropdown
$states = [
    '' => __('-- Select State --', 'formflow-lite'),
    'DC' => 'District of Columbia',
    'MD' => 'Maryland',
];

// Available fields for form
$available_fields = [
    'phone' => __('Phone Number', 'formflow-lite'),
    'email' => __('Email Address', 'formflow-lite'),
    'street' => __('Street Address', 'formflow-lite'),
    'city' => __('City', 'formflow-lite'),
    'state' => __('State', 'formflow-lite'),
    'zip' => __('ZIP Code (Customer Info)', 'formflow-lite'),
    'promo_code' => __('Promo Code', 'formflow-lite'),
];

// Get saved field order or use default
$field_order = $instance['settings']['field_order'] ?? array_keys($available_fields);
foreach (array_keys($available_fields) as $key) {
    if (!in_array($key, $field_order)) {
        $field_order[] = $key;
    }
}

// Wizard steps configuration
$wizard_steps = [
    'basics' => [
        'title' => __('Basics', 'formflow-lite'),
        'icon' => 'admin-settings',
        'description' => __('Name, utility, and form type', 'formflow-lite'),
    ],
    'api' => [
        'title' => __('API', 'formflow-lite'),
        'icon' => 'admin-site',
        'description' => __('API endpoint and credentials', 'formflow-lite'),
    ],
    'fields' => [
        'title' => __('Fields', 'formflow-lite'),
        'icon' => 'forms',
        'description' => __('Form fields and validation', 'formflow-lite'),
    ],
    'scheduling' => [
        'title' => __('Scheduling', 'formflow-lite'),
        'icon' => 'calendar-alt',
        'description' => __('Blocked dates and capacity', 'formflow-lite'),
    ],
    'content' => [
        'title' => __('Content', 'formflow-lite'),
        'icon' => 'edit',
        'description' => __('Text, labels, and messages', 'formflow-lite'),
    ],
    'email' => [
        'title' => __('Email', 'formflow-lite'),
        'icon' => 'email',
        'description' => __('Email settings and templates', 'formflow-lite'),
    ],
    'features' => [
        'title' => __('Features', 'formflow-lite'),
        'icon' => 'admin-plugins',
        'description' => __('Advanced features', 'formflow-lite'),
    ],
];
?>

<div class="wrap ff-admin-wrap ff-editor-wrap">
    <div class="ff-editor-header">
        <div class="ff-editor-header-left">
            <a href="<?php echo esc_url(admin_url('admin.php?page=fffl-dashboard')); ?>" class="ff-back-link">
                <span class="dashicons dashicons-arrow-left-alt2"></span>
                <?php esc_html_e('Dashboard', 'formflow-lite'); ?>
            </a>
            <h1><?php echo esc_html($page_title); ?></h1>
        </div>
        <div class="ff-editor-header-right">
            <div class="ff-editor-mode-toggle">
                <label class="ff-toggle-switch">
                    <input type="checkbox" id="ff-quick-edit-toggle" <?php checked(isset($_GET['mode']) && $_GET['mode'] === 'quick'); ?>>
                    <span class="ff-toggle-slider"></span>
                </label>
                <span class="ff-toggle-label"><?php esc_html_e('Quick Edit', 'formflow-lite'); ?></span>
                <?php fffl_help_tooltip(__('Quick Edit mode shows all settings on one page for power users.', 'formflow-lite')); ?>
            </div>
        </div>
    </div>

    <form id="ff-instance-form" class="ff-form ff-wizard-form" method="post">
        <input type="hidden" name="id" value="<?php echo esc_attr($instance['id'] ?? 0); ?>">
        <input type="hidden" name="current_step" id="ff-current-step" value="basics">

        <div class="ff-editor-layout">
            <!-- Wizard Navigation (left sidebar) -->
            <div class="ff-wizard-nav" id="ff-wizard-nav">
                <ul class="ff-wizard-steps">
                    <?php foreach ($wizard_steps as $step_id => $step) : ?>
                    <li class="ff-wizard-step <?php echo $step_id === 'basics' ? 'active' : ''; ?>" data-step="<?php echo esc_attr($step_id); ?>">
                        <span class="ff-step-icon">
                            <span class="dashicons dashicons-<?php echo esc_attr($step['icon']); ?>"></span>
                        </span>
                        <span class="ff-step-content">
                            <span class="ff-step-title"><?php echo esc_html($step['title']); ?></span>
                            <span class="ff-step-desc"><?php echo esc_html($step['description']); ?></span>
                        </span>
                        <span class="ff-step-status">
                            <span class="dashicons dashicons-yes-alt"></span>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <!-- Main Content Area -->
            <div class="ff-editor-main">
                <!-- Wizard Panels -->
                <div class="ff-wizard-panels">

                    <!-- Step 1: Basics -->
                    <div class="ff-wizard-panel active" data-panel="basics">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Basic Settings', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Configure the fundamental settings for this form instance.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- Identity Pod -->
                                <div class="ff-pod ff-pod-primary">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-nametag"></span><?php esc_html_e('Identity', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="name" class="ff-field-label">
                                                <?php esc_html_e('Form Name', 'formflow-lite'); ?>
                                                <span class="required">*</span>
                                            </label>
                                            <input type="text" id="name" name="name" class="ff-field-input"
                                                   value="<?php echo esc_attr($instance['name'] ?? ''); ?>" required
                                                   placeholder="<?php esc_attr_e('e.g., Delmarva MD Enrollment', 'formflow-lite'); ?>">
                                            <p class="ff-field-help"><?php esc_html_e('A descriptive name for this form instance.', 'formflow-lite'); ?></p>
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="slug" class="ff-field-label">
                                                <?php esc_html_e('Slug', 'formflow-lite'); ?>
                                                <span class="required">*</span>
                                            </label>
                                            <div class="ff-field-with-prefix">
                                                <span class="ff-field-prefix">[fffl_form instance="</span>
                                                <input type="text" id="slug" name="slug" class="ff-field-input ff-slug-input"
                                                       value="<?php echo esc_attr($instance['slug'] ?? ''); ?>" required
                                                       pattern="[a-z0-9\-]+"
                                                       placeholder="delmarva-md"
                                                       <?php echo $is_edit ? 'readonly' : ''; ?>>
                                                <span class="ff-field-suffix">"]</span>
                                            </div>
                                            <p class="ff-field-help"><?php esc_html_e('URL-friendly identifier used in the shortcode.', 'formflow-lite'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Configuration Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-admin-settings"></span><?php esc_html_e('Configuration', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="utility" class="ff-field-label">
                                                <?php esc_html_e('Utility', 'formflow-lite'); ?>
                                                <span class="required">*</span>
                                            </label>
                                            <select id="utility" name="utility" class="ff-field-select" required>
                                                <option value=""><?php esc_html_e('Select a utility...', 'formflow-lite'); ?></option>
                                                <?php foreach ($utilities as $key => $utility) : ?>
                                                    <option value="<?php echo esc_attr($key); ?>"
                                                            data-endpoint="<?php echo esc_attr($utility['api_endpoint']); ?>"
                                                            data-email-from="<?php echo esc_attr($utility['support_email_from']); ?>"
                                                            data-email-to="<?php echo esc_attr($utility['support_email_to']); ?>"
                                                            <?php selected($instance['utility'] ?? '', $key); ?>>
                                                        <?php echo esc_html($utility['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="form_type" class="ff-field-label">
                                                <?php esc_html_e('Form Type', 'formflow-lite'); ?>
                                                <?php fffl_help_tooltip(__('Enrollment Form includes all steps. Scheduler Only skips enrollment. External Enrollment tracks handoffs to external platforms.', 'formflow-lite')); ?>
                                            </label>
                                            <select id="form_type" name="form_type" class="ff-field-select">
                                                <option value="enrollment" <?php selected($instance['form_type'] ?? '', 'enrollment'); ?>>
                                                    <?php esc_html_e('Enrollment Form', 'formflow-lite'); ?>
                                                </option>
                                                <option value="scheduler" <?php selected($instance['form_type'] ?? '', 'scheduler'); ?>>
                                                    <?php esc_html_e('Scheduler Only', 'formflow-lite'); ?>
                                                </option>
                                                <option value="external" <?php selected($instance['form_type'] ?? '', 'external'); ?>>
                                                    <?php esc_html_e('External Enrollment', 'formflow-lite'); ?>
                                                </option>
                                            </select>
                                        </div>

                                        <div class="ff-pod-fields">
                                            <div class="ff-field-group">
                                                <label for="default_state" class="ff-field-label">
                                                    <?php esc_html_e('Default State', 'formflow-lite'); ?>
                                                </label>
                                                <select id="default_state" name="settings[default_state]" class="ff-field-select">
                                                    <?php foreach ($states as $abbr => $state_name) : ?>
                                                        <option value="<?php echo esc_attr($abbr); ?>" <?php selected($instance['settings']['default_state'] ?? '', $abbr); ?>>
                                                            <?php echo esc_html($state_name); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

                                            <div class="ff-field-group">
                                                <label for="support_phone" class="ff-field-label">
                                                    <?php esc_html_e('Support Phone', 'formflow-lite'); ?>
                                                </label>
                                                <input type="text" id="support_phone" name="settings[support_phone]" class="ff-field-input"
                                                       value="<?php echo esc_attr($instance['settings']['support_phone'] ?? '1-866-353-5799'); ?>"
                                                       placeholder="1-866-353-5799">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- External URL Pod (shown only for external form type) -->
                                <div class="ff-pod ff-pod-full ff-external-settings" id="ff-external-settings" style="display: <?php echo ($instance['form_type'] ?? '') === 'external' ? 'block' : 'none'; ?>;">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-external"></span><?php esc_html_e('External Enrollment', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-pod-info-box">
                                            <p><span class="dashicons dashicons-info"></span>
                                            <?php esc_html_e('Users will be tracked for attribution, then redirected to the external platform.', 'formflow-lite'); ?></p>
                                        </div>
                                        <div class="ff-pod-fields">
                                            <div class="ff-field-group ff-pod-field-full">
                                                <label for="external_url" class="ff-field-label">
                                                    <?php esc_html_e('External Enrollment URL', 'formflow-lite'); ?>
                                                    <span class="required">*</span>
                                                </label>
                                                <input type="url" id="external_url" name="settings[external_url]" class="ff-field-input"
                                                       value="<?php echo esc_url($instance['settings']['external_url'] ?? ''); ?>"
                                                       placeholder="https://www.dominionenergyptr.com/ptr/residential/">
                                            </div>

                                            <div class="ff-field-group">
                                                <label for="external_button_text" class="ff-field-label">
                                                    <?php esc_html_e('Button Text', 'formflow-lite'); ?>
                                                </label>
                                                <input type="text" id="external_button_text" name="settings[external_button_text]" class="ff-field-input"
                                                       value="<?php echo esc_attr($instance['settings']['external_button_text'] ?? ''); ?>"
                                                       placeholder="<?php esc_attr_e('Enroll Now', 'formflow-lite'); ?>">
                                            </div>

                                            <div class="ff-field-group" style="display: flex; align-items: center; padding-top: 24px;">
                                                <label class="ff-checkbox-label">
                                                    <input type="checkbox" name="settings[external_new_tab]" value="1"
                                                           <?php checked($instance['settings']['external_new_tab'] ?? false); ?>>
                                                    <?php esc_html_e('Open in new tab', 'formflow-lite'); ?>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: API Configuration -->
                    <div class="ff-wizard-panel" data-panel="api">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('API Configuration', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Configure the PowerPortal IntelliSOURCE API connection.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- API Connection Pod -->
                                <div class="ff-pod ff-pod-primary">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-admin-site"></span><?php esc_html_e('API Connection', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="api_endpoint" class="ff-field-label">
                                                <?php esc_html_e('API Endpoint', 'formflow-lite'); ?>
                                                <span class="required">*</span>
                                                <?php fffl_help_tooltip(__('Automatically set when you select a utility.', 'formflow-lite')); ?>
                                            </label>
                                            <input type="url" id="api_endpoint" name="api_endpoint" class="ff-field-input"
                                                   value="<?php echo esc_url($instance['api_endpoint'] ?? ''); ?>" required
                                                   placeholder="https://ph.powerportal.com/phiIntelliSOURCE/api/">
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="api_password" class="ff-field-label">
                                                <?php esc_html_e('API Password', 'formflow-lite'); ?>
                                                <?php echo !$is_edit ? '<span class="required">*</span>' : ''; ?>
                                                <?php fffl_help_tooltip(__('Securely encrypted before storage.', 'formflow-lite')); ?>
                                            </label>
                                            <div class="ff-password-wrap" style="position: relative;">
                                                <input type="password" id="api_password" name="api_password" class="ff-field-input"
                                                       value="<?php echo esc_attr($instance['api_password'] ?? ''); ?>"
                                                       style="padding-right: 38px;"
                                                       <?php echo $is_edit ? '' : 'required'; ?>>
                                                <button type="button"
                                                        class="ff-password-toggle"
                                                        aria-label="<?php esc_attr_e('Show password', 'formflow-lite'); ?>"
                                                        aria-pressed="false"
                                                        data-target="api_password"
                                                        style="position: absolute; right: 4px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 4px 8px; color: #6b7280;">
                                                    <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
                                                </button>
                                            </div>
                                            <?php if ($is_edit) : ?>
                                                <p class="ff-field-help"><?php esc_html_e('Leave blank to keep existing password.', 'formflow-lite'); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="ff-pod-footer">
                                        <button type="button" id="fffl-test-api" class="button button-secondary">
                                            <span class="dashicons dashicons-yes-alt"></span>
                                            <?php esc_html_e('Test Connection', 'formflow-lite'); ?>
                                        </button>
                                        <span id="ff-api-status" class="ff-api-status"></span>
                                    </div>
                                </div>

                                <!-- Mode Settings Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-admin-tools"></span><?php esc_html_e('Mode Settings', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-mode-cards">
                                            <div class="ff-mode-card">
                                                <label class="ff-mode-card-label">
                                                    <input type="checkbox" name="test_mode" value="1" <?php checked($instance['test_mode'] ?? false); ?>>
                                                    <span class="ff-mode-card-content">
                                                        <span class="ff-mode-icon"><span class="dashicons dashicons-visibility"></span></span>
                                                        <span class="ff-mode-title"><?php esc_html_e('Test Mode', 'formflow-lite'); ?></span>
                                                        <span class="ff-mode-desc"><?php esc_html_e('Marked as test. API calls made.', 'formflow-lite'); ?></span>
                                                    </span>
                                                </label>
                                            </div>

                                            <div class="ff-mode-card">
                                                <label class="ff-mode-card-label">
                                                    <input type="checkbox" name="demo_mode" value="1" <?php checked($instance['settings']['demo_mode'] ?? false); ?>>
                                                    <span class="ff-mode-card-content">
                                                        <span class="ff-mode-icon"><span class="dashicons dashicons-admin-generic"></span></span>
                                                        <span class="ff-mode-title"><?php esc_html_e('Demo Mode', 'formflow-lite'); ?></span>
                                                        <span class="ff-mode-desc"><?php esc_html_e('Mock data. No API calls.', 'formflow-lite'); ?></span>
                                                    </span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Eligibility Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-shield"></span><?php esc_html_e('Eligibility', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <label class="ff-checkbox-row">
                                            <input type="checkbox" name="require_wifi" value="1" <?php checked($instance['settings']['require_wifi'] ?? false); ?>>
                                            <span><strong><?php esc_html_e('Require WiFi for thermostat', 'formflow-lite'); ?></strong></span>
                                        </label>
                                        <p class="ff-field-help">
                                            <?php esc_html_e('Asks customers who choose the Web-Programmable Thermostat whether their home has WiFi. Answering "No" blocks the thermostat and offers to enroll them in the Outdoor Switch program instead. Leave off for utilities that have not requested this check.', 'formflow-lite'); ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- IntelliSource Device & Location Codes Pod -->
                                <?php
                                $cfg_dd_thermostat = $instance['settings']['device_code_thermostat'] ?? '03';
                                $cfg_dd_dcu        = $instance['settings']['device_code_dcu'] ?? '02';
                                $cfg_eq_location   = $instance['settings']['equipment_location'] ?? '05';
                                ?>
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-admin-generic"></span><?php esc_html_e('IntelliSource Device Codes', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <p class="description" style="margin: 0 0 14px;">
                                            <?php esc_html_e('Codes sent to IntelliSource that determine which device is provisioned and where. These vary by program — confirm the current values with your Comverge / IntelliSource contact. Defaults: thermostat 03, outdoor switch (DCU) 02, location 05.', 'formflow-lite'); ?>
                                        </p>
                                        <div class="ff-field">
                                            <label for="device_code_thermostat" class="ff-field-label"><?php esc_html_e('Thermostat device code (dd-15)', 'formflow-lite'); ?></label>
                                            <input type="text" id="device_code_thermostat" name="settings[device_code_thermostat]" class="ff-field-input"
                                                   value="<?php echo esc_attr($cfg_dd_thermostat); ?>" placeholder="03">
                                            <p class="description"><?php esc_html_e('e.g. the code for Sensi WiFi. (03 currently resolves to IntelliTemp on this deployment.)', 'formflow-lite'); ?></p>
                                        </div>
                                        <div class="ff-field">
                                            <label for="device_code_dcu" class="ff-field-label"><?php esc_html_e('Outdoor switch / DCU device code (dd-15)', 'formflow-lite'); ?></label>
                                            <input type="text" id="device_code_dcu" name="settings[device_code_dcu]" class="ff-field-input"
                                                   value="<?php echo esc_attr($cfg_dd_dcu); ?>" placeholder="02">
                                        </div>
                                        <div class="ff-field">
                                            <label for="equipment_location" class="ff-field-label"><?php esc_html_e('Equipment location code (eqLoc-15)', 'formflow-lite'); ?></label>
                                            <input type="text" id="equipment_location" name="settings[equipment_location]" class="ff-field-input"
                                                   value="<?php echo esc_attr($cfg_eq_location); ?>" placeholder="05">
                                            <p class="description"><?php esc_html_e('Install location code (e.g. Interior). 05 currently resolves to "roof-multi-story" — update once Comverge confirms the correct code.', 'formflow-lite'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Demo Accounts Pod (shown only in demo mode) -->
                                <?php $demo_accounts = \FFFL\Api\MockApiClient::get_demo_accounts_info(); ?>
                                <div class="ff-pod ff-pod-full ff-demo-accounts" id="ff-demo-accounts" style="display: <?php echo ($instance['settings']['demo_mode'] ?? false) ? 'block' : 'none'; ?>;">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-clipboard"></span><?php esc_html_e('Demo Test Accounts', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <table class="ff-demo-table widefat striped">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e('Account #', 'formflow-lite'); ?></th>
                                                    <th><?php esc_html_e('ZIP', 'formflow-lite'); ?></th>
                                                    <th><?php esc_html_e('Note', 'formflow-lite'); ?></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($demo_accounts as $account) : ?>
                                                <tr>
                                                    <td><code><?php echo esc_html($account['account']); ?></code></td>
                                                    <td><code><?php echo esc_html($account['zip']); ?></code></td>
                                                    <td><?php echo esc_html($account['description']); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Form Fields -->
                    <div class="ff-wizard-panel" data-panel="fields">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Form Fields', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Configure which fields appear on the form and their order.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- Customer Info Fields Pod -->
                                <div class="ff-pod ff-pod-primary ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-forms"></span><?php esc_html_e('Customer Info Fields', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-fields-builder">
                                            <div class="ff-fields-header">
                                                <span class="ff-fields-header-drag"><?php esc_html_e('Order', 'formflow-lite'); ?></span>
                                                <span class="ff-fields-header-field"><?php esc_html_e('Field', 'formflow-lite'); ?></span>
                                                <span class="ff-fields-header-visible"><?php esc_html_e('Show', 'formflow-lite'); ?></span>
                                                <span class="ff-fields-header-required"><?php esc_html_e('Required', 'formflow-lite'); ?></span>
                                            </div>
                                            <div id="ff-sortable-fields" class="ff-sortable-fields">
                                                <?php foreach ($field_order as $field_key) :
                                                    if (!isset($available_fields[$field_key])) continue;
                                                    $field_label = $available_fields[$field_key];
                                                    $is_visible = $fields_config[$field_key]['visible'] ?? true;
                                                    $is_required = $fields_config[$field_key]['required'] ?? true;
                                                ?>
                                                <div class="ff-field-toggle-row ff-sortable-item" data-field="<?php echo esc_attr($field_key); ?>">
                                                    <span class="ff-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'formflow-lite'); ?>">
                                                        <span class="dashicons dashicons-menu"></span>
                                                    </span>
                                                    <input type="hidden" name="settings[field_order][]" value="<?php echo esc_attr($field_key); ?>">
                                                    <span class="ff-field-name"><?php echo esc_html($field_label); ?></span>
                                                    <label class="ff-toggle-mini">
                                                        <input type="checkbox" name="settings[fields][<?php echo esc_attr($field_key); ?>][visible]" value="1" <?php checked($is_visible); ?>>
                                                        <span class="ff-toggle-mini-slider"></span>
                                                    </label>
                                                    <label class="ff-toggle-mini">
                                                        <input type="checkbox" name="settings[fields][<?php echo esc_attr($field_key); ?>][required]" value="1" <?php checked($is_required); ?>>
                                                        <span class="ff-toggle-mini-slider"></span>
                                                    </label>
                                                </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                        <p class="ff-field-help"><?php esc_html_e('Drag fields to reorder. Toggle visibility and required status using the switches.', 'formflow-lite'); ?></p>
                                    </div>
                                </div>

                                <!-- Promo Code Filtering Pod -->
                                <div class="ff-pod ff-pod-primary ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-tag"></span><?php esc_html_e('Promo Code Filtering', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <p class="ff-field-help" style="margin-top: 0;"><?php esc_html_e('Control which promo codes appear in the "How did you hear about us?" dropdown.', 'formflow-lite'); ?></p>

                                        <?php
                                        $promo_codes_allowed = $settings['promo_codes_allowed'] ?? [];
                                        $promo_codes_hidden = $settings['promo_codes_hidden'] ?? [];
                                        ?>

                                        <table class="form-table ff-compact-table">
                                            <tr>
                                                <th scope="row">
                                                    <label for="promo_codes_allowed"><?php esc_html_e('Show Only These Codes', 'formflow-lite'); ?></label>
                                                </th>
                                                <td>
                                                    <textarea name="settings[promo_codes_allowed]" id="promo_codes_allowed" class="large-text code" rows="3" placeholder="<?php esc_attr_e('WEB, RADIO, FRIEND, OTHER', 'formflow-lite'); ?>"><?php echo esc_textarea(is_array($promo_codes_allowed) ? implode(', ', $promo_codes_allowed) : $promo_codes_allowed); ?></textarea>
                                                    <p class="description"><?php esc_html_e('Enter comma-separated list of codes to show. Leave empty to show all codes from API.', 'formflow-lite'); ?></p>
                                                </td>
                                            </tr>
                                            <tr>
                                                <th scope="row">
                                                    <label for="promo_codes_hidden"><?php esc_html_e('Hide These Codes', 'formflow-lite'); ?></label>
                                                </th>
                                                <td>
                                                    <textarea name="settings[promo_codes_hidden]" id="promo_codes_hidden" class="large-text code" rows="3" placeholder="<?php esc_attr_e('INTERNAL, TEST, DEBUG', 'formflow-lite'); ?>"><?php echo esc_textarea(is_array($promo_codes_hidden) ? implode(', ', $promo_codes_hidden) : $promo_codes_hidden); ?></textarea>
                                                    <p class="description"><?php esc_html_e('Enter comma-separated list of codes to hide. These codes will not appear in the dropdown.', 'formflow-lite'); ?></p>
                                                </td>
                                            </tr>
                                        </table>

                                        <p class="ff-field-help">
                                            <strong><?php esc_html_e('Common codes:', 'formflow-lite'); ?></strong>
                                            WEB, RADIO, FRIEND, FACEBOOK, TWITTER, NEWSPAPER, BROCHURE, EVENT, OTHER
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 4: Scheduling -->
                    <div class="ff-wizard-panel" data-panel="scheduling">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Scheduling Settings', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Configure scheduling restrictions and capacity limits.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- Blocked Dates Pod -->
                                <div class="ff-pod ff-pod-primary">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-calendar-alt"></span><?php esc_html_e('Blocked Dates', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <p class="ff-field-help" style="margin-top: 0;"><?php esc_html_e('Block specific dates (holidays, closures) from scheduling.', 'formflow-lite'); ?></p>

                                        <div class="ff-blocked-dates-container">
                                            <div id="ff-blocked-dates-list" class="ff-dates-list ff-sortable-dates">
                                                <?php if (!empty($blocked_dates)) : ?>
                                                    <?php foreach ($blocked_dates as $index => $blocked) : ?>
                                                        <div class="ff-blocked-date-row ff-sortable-item" data-index="<?php echo esc_attr($index); ?>">
                                                            <span class="ff-drag-handle" title="<?php esc_attr_e('Drag to reorder', 'formflow-lite'); ?>">
                                                                <span class="dashicons dashicons-menu"></span>
                                                            </span>
                                                            <input type="date" name="settings[scheduling][blocked_dates][<?php echo esc_attr($index); ?>][date]"
                                                                   value="<?php echo esc_attr($blocked['date'] ?? ''); ?>"
                                                                   class="ff-date-input" required>
                                                            <input type="text" name="settings[scheduling][blocked_dates][<?php echo esc_attr($index); ?>][label]"
                                                                   value="<?php echo esc_attr($blocked['label'] ?? ''); ?>"
                                                                   placeholder="<?php esc_attr_e('e.g., Christmas Day', 'formflow-lite'); ?>"
                                                                   class="regular-text">
                                                            <button type="button" class="button ff-remove-blocked-date" title="<?php esc_attr_e('Remove', 'formflow-lite'); ?>">
                                                                <span class="dashicons dashicons-trash"></span>
                                                            </button>
                                                        </div>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="ff-pod-footer">
                                        <button type="button" id="ff-add-blocked-date" class="button">
                                            <span class="dashicons dashicons-plus-alt2"></span>
                                            <?php esc_html_e('Add Blocked Date', 'formflow-lite'); ?>
                                        </button>
                                    </div>
                                </div>

                                <!-- Capacity Limits Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-groups"></span><?php esc_html_e('Capacity Limits', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-capacity-limits">
                                            <label class="ff-checkbox-label" style="margin-bottom: 15px;">
                                                <input type="checkbox" name="settings[scheduling][capacity_limits][enabled]" value="1"
                                                    <?php checked($capacity_limits['enabled'] ?? false); ?>>
                                                <?php esc_html_e('Enable custom capacity limits (override API values)', 'formflow-lite'); ?>
                                            </label>
                                            <div class="ff-capacity-inputs" style="<?php echo ($capacity_limits['enabled'] ?? false) ? '' : 'display: none;'; ?>">
                                                <p class="ff-field-help"><?php esc_html_e('Set to 0 to block a time slot. Leave blank to use API values.', 'formflow-lite'); ?></p>
                                                <div class="ff-capacity-grid">
                                                    <div class="ff-capacity-item">
                                                        <label for="capacity_am"><?php esc_html_e('Morning', 'formflow-lite'); ?></label>
                                                        <span class="ff-capacity-time">8-11 AM</span>
                                                        <input type="number" id="capacity_am" name="settings[scheduling][capacity_limits][am]"
                                                               value="<?php echo esc_attr($capacity_limits['am'] ?? ''); ?>"
                                                               min="0" max="99" class="small-text" placeholder="—">
                                                    </div>
                                                    <div class="ff-capacity-item">
                                                        <label for="capacity_md"><?php esc_html_e('Mid-Day', 'formflow-lite'); ?></label>
                                                        <span class="ff-capacity-time">11 AM-2 PM</span>
                                                        <input type="number" id="capacity_md" name="settings[scheduling][capacity_limits][md]"
                                                               value="<?php echo esc_attr($capacity_limits['md'] ?? ''); ?>"
                                                               min="0" max="99" class="small-text" placeholder="—">
                                                    </div>
                                                    <div class="ff-capacity-item">
                                                        <label for="capacity_pm"><?php esc_html_e('Afternoon', 'formflow-lite'); ?></label>
                                                        <span class="ff-capacity-time">2-5 PM</span>
                                                        <input type="number" id="capacity_pm" name="settings[scheduling][capacity_limits][pm]"
                                                               value="<?php echo esc_attr($capacity_limits['pm'] ?? ''); ?>"
                                                               min="0" max="99" class="small-text" placeholder="—">
                                                    </div>
                                                    <div class="ff-capacity-item">
                                                        <label for="capacity_ev"><?php esc_html_e('Evening', 'formflow-lite'); ?></label>
                                                        <span class="ff-capacity-time">5-8 PM</span>
                                                        <input type="number" id="capacity_ev" name="settings[scheduling][capacity_limits][ev]"
                                                               value="<?php echo esc_attr($capacity_limits['ev'] ?? ''); ?>"
                                                               min="0" max="99" class="small-text" placeholder="—">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Scheduled Maintenance Pod -->
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-hammer"></span><?php esc_html_e('Scheduled Maintenance', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <label class="ff-checkbox-label" style="margin-bottom: 15px;">
                                            <input type="checkbox" name="settings[maintenance][enabled]" value="1" <?php checked($maintenance['enabled'] ?? false); ?>>
                                            <?php esc_html_e('Schedule a maintenance window', 'formflow-lite'); ?>
                                        </label>

                                        <div class="ff-maintenance-inputs" style="<?php echo ($maintenance['enabled'] ?? false) ? '' : 'display: none;'; ?>">
                                            <div class="ff-pod-fields">
                                                <div class="ff-field-group">
                                                    <label for="maintenance_start" class="ff-field-label"><?php esc_html_e('Start', 'formflow-lite'); ?></label>
                                                    <input type="datetime-local" id="maintenance_start" name="settings[maintenance][start]" class="ff-field-input"
                                                           value="<?php echo esc_attr($maintenance['start'] ?? ''); ?>">
                                                </div>
                                                <div class="ff-field-group">
                                                    <label for="maintenance_end" class="ff-field-label"><?php esc_html_e('End', 'formflow-lite'); ?></label>
                                                    <input type="datetime-local" id="maintenance_end" name="settings[maintenance][end]" class="ff-field-input"
                                                           value="<?php echo esc_attr($maintenance['end'] ?? ''); ?>">
                                                </div>
                                                <div class="ff-field-group ff-pod-field-full">
                                                    <label for="maintenance_message" class="ff-field-label"><?php esc_html_e('Message', 'formflow-lite'); ?></label>
                                                    <textarea id="maintenance_message" name="settings[maintenance][message]" class="ff-field-textarea" rows="2"
                                                              placeholder="<?php esc_attr_e('This form is temporarily unavailable for scheduled maintenance.', 'formflow-lite'); ?>"><?php echo esc_textarea($maintenance['message'] ?? ''); ?></textarea>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 5: Content -->
                    <div class="ff-wizard-panel" data-panel="content">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Form Content', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Customize the text displayed on the form. Leave blank to use defaults.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- General Content Pod -->
                                <div class="ff-pod ff-pod-primary">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-text"></span><?php esc_html_e('General Content', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="content_form_title" class="ff-field-label"><?php esc_html_e('Form Title', 'formflow-lite'); ?></label>
                                            <input type="text" id="content_form_title" name="settings[content][form_title]" class="ff-field-input"
                                                   value="<?php echo esc_attr($content['form_title'] ?? ''); ?>"
                                                   placeholder="<?php esc_attr_e('Energy Wise Rewards Enrollment', 'formflow-lite'); ?>">
                                        </div>
                                        <div class="ff-field-group">
                                            <label for="content_form_description" class="ff-field-label"><?php esc_html_e('Form Description', 'formflow-lite'); ?></label>
                                            <textarea id="content_form_description" name="settings[content][form_description]" class="ff-field-textarea" rows="2"
                                                      placeholder="<?php esc_attr_e('Join the Energy Wise Rewards program and start saving today.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['form_description'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="ff-field-group">
                                            <label for="content_program_name" class="ff-field-label"><?php esc_html_e('Program Name', 'formflow-lite'); ?></label>
                                            <input type="text" id="content_program_name" name="settings[content][program_name]" class="ff-field-input"
                                                   value="<?php echo esc_attr($content['program_name'] ?? ''); ?>"
                                                   placeholder="<?php esc_attr_e('Energy Wise Rewards', 'formflow-lite'); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Button Labels Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-button"></span><?php esc_html_e('Button Labels', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-pod-fields">
                                            <div class="ff-field-group">
                                                <label for="content_btn_next" class="ff-field-label"><?php esc_html_e('Next', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_btn_next" name="settings[content][btn_next]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['btn_next'] ?? ''); ?>" placeholder="<?php esc_attr_e('Continue', 'formflow-lite'); ?>">
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_btn_back" class="ff-field-label"><?php esc_html_e('Back', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_btn_back" name="settings[content][btn_back]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['btn_back'] ?? ''); ?>" placeholder="<?php esc_attr_e('Back', 'formflow-lite'); ?>">
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_btn_submit" class="ff-field-label"><?php esc_html_e('Submit', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_btn_submit" name="settings[content][btn_submit]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['btn_submit'] ?? ''); ?>" placeholder="<?php esc_attr_e('Complete Enrollment', 'formflow-lite'); ?>">
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_btn_verify" class="ff-field-label"><?php esc_html_e('Verify', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_btn_verify" name="settings[content][btn_verify]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['btn_verify'] ?? ''); ?>" placeholder="<?php esc_attr_e('Verify Account', 'formflow-lite'); ?>">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Step Titles Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-editor-ol"></span><?php esc_html_e('Step Titles', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <?php for ($i = 1; $i <= 5; $i++) :
                                            $step_placeholders = [
                                                1 => __('Select Your Device', 'formflow-lite'),
                                                2 => __('Verify Your Account', 'formflow-lite'),
                                                3 => __('Your Information', 'formflow-lite'),
                                                4 => __('Schedule Installation', 'formflow-lite'),
                                                5 => __('Review & Confirm', 'formflow-lite'),
                                            ];
                                        ?>
                                        <div class="ff-field-group ff-field-compact">
                                            <label for="content_step<?php echo $i; ?>_title" class="ff-field-label">
                                                <?php printf(__('Step %d', 'formflow-lite'), $i); ?>
                                            </label>
                                            <input type="text" id="content_step<?php echo $i; ?>_title" name="settings[content][step<?php echo $i; ?>_title]" class="ff-field-input"
                                                   value="<?php echo esc_attr($content['step' . $i . '_title'] ?? ''); ?>"
                                                   placeholder="<?php echo esc_attr($step_placeholders[$i]); ?>">
                                        </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <!-- Help Text Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-editor-help"></span><?php esc_html_e('Help Text', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="content_help_account" class="ff-field-label"><?php esc_html_e('Account Number Help', 'formflow-lite'); ?></label>
                                            <textarea id="content_help_account" name="settings[content][help_account]" class="ff-field-textarea" rows="2"
                                                      placeholder="<?php esc_attr_e('Your account number can be found on your utility bill.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['help_account'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="ff-field-group">
                                            <label for="content_help_zip" class="ff-field-label"><?php esc_html_e('ZIP Code Help', 'formflow-lite'); ?></label>
                                            <textarea id="content_help_zip" name="settings[content][help_zip]" class="ff-field-textarea" rows="2"
                                                      placeholder="<?php esc_attr_e('Enter the 5-digit ZIP code for your service address.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['help_zip'] ?? ''); ?></textarea>
                                        </div>
                                        <div class="ff-field-group">
                                            <label for="content_help_scheduling" class="ff-field-label"><?php esc_html_e('Scheduling Help', 'formflow-lite'); ?></label>
                                            <textarea id="content_help_scheduling" name="settings[content][help_scheduling]" class="ff-field-textarea" rows="2"
                                                      placeholder="<?php esc_attr_e('Select an available date and time for your installation.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['help_scheduling'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Error Messages Pod -->
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-warning"></span><?php esc_html_e('Error Messages', 'formflow-lite'); ?></h3>
                                        <span class="ff-pod-badge"><?php esc_html_e('Use {phone} for support number', 'formflow-lite'); ?></span>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-pod-fields">
                                            <div class="ff-field-group">
                                                <label for="content_error_validation" class="ff-field-label"><?php esc_html_e('Account Validation', 'formflow-lite'); ?></label>
                                                <textarea id="content_error_validation" name="settings[content][error_validation]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('We could not verify your account. Please check your account number and ZIP code.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['error_validation'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_error_scheduling" class="ff-field-label"><?php esc_html_e('Scheduling', 'formflow-lite'); ?></label>
                                                <textarea id="content_error_scheduling" name="settings[content][error_scheduling]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('Unable to load available appointments. Please try again.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['error_scheduling'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_error_submission" class="ff-field-label"><?php esc_html_e('Submission', 'formflow-lite'); ?></label>
                                                <textarea id="content_error_submission" name="settings[content][error_submission]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('There was a problem submitting your enrollment.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['error_submission'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_error_general" class="ff-field-label"><?php esc_html_e('General Error', 'formflow-lite'); ?></label>
                                                <textarea id="content_error_general" name="settings[content][error_general]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('An error occurred. Please try again.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['error_general'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Terms & Conditions Pod -->
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-media-text"></span><?php esc_html_e('Terms & Conditions', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-pod-fields">
                                            <div class="ff-field-group">
                                                <label for="content_terms_title" class="ff-field-label"><?php esc_html_e('Title', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_terms_title" name="settings[content][terms_title]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['terms_title'] ?? ''); ?>"
                                                       placeholder="<?php esc_attr_e('Terms and Conditions', 'formflow-lite'); ?>">
                                            </div>
                                            <div class="ff-field-group">
                                                <label for="content_terms_checkbox" class="ff-field-label"><?php esc_html_e('Checkbox Label', 'formflow-lite'); ?></label>
                                                <input type="text" id="content_terms_checkbox" name="settings[content][terms_checkbox]" class="ff-field-input"
                                                       value="<?php echo esc_attr($content['terms_checkbox'] ?? ''); ?>"
                                                       placeholder="<?php esc_attr_e('I have read and agree to the Terms and Conditions', 'formflow-lite'); ?>">
                                            </div>
                                            <div class="ff-field-group ff-pod-field-full">
                                                <label for="content_terms_intro" class="ff-field-label"><?php esc_html_e('Introduction', 'formflow-lite'); ?></label>
                                                <textarea id="content_terms_intro" name="settings[content][terms_intro]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('By enrolling in the program, you agree to the following terms:', 'formflow-lite'); ?>"><?php echo esc_textarea($content['terms_intro'] ?? ''); ?></textarea>
                                            </div>
                                            <div class="ff-field-group ff-pod-field-full">
                                                <label for="content_terms_content" class="ff-field-label">
                                                    <?php esc_html_e('Content', 'formflow-lite'); ?>
                                                    <span class="ff-label-badge"><?php esc_html_e('HTML', 'formflow-lite'); ?></span>
                                                </label>
                                                <?php
                                                $terms_editor_id = 'content_terms_content';
                                                $terms_content = $content['terms_content'] ?? '';
                                                wp_editor($terms_content, $terms_editor_id, [
                                                    'textarea_name' => 'settings[content][terms_content]',
                                                    'textarea_rows' => 8,
                                                    'media_buttons' => false,
                                                    'teeny' => true,
                                                    'quicktags' => ['buttons' => 'strong,em,ul,ol,li,link'],
                                                ]);
                                                ?>
                                            </div>
                                            <div class="ff-field-group ff-pod-field-full">
                                                <label for="content_terms_footer" class="ff-field-label"><?php esc_html_e('Footer', 'formflow-lite'); ?></label>
                                                <textarea id="content_terms_footer" name="settings[content][terms_footer]" class="ff-field-textarea" rows="2"
                                                          placeholder="<?php esc_attr_e('For complete program details, please visit our website.', 'formflow-lite'); ?>"><?php echo esc_textarea($content['terms_footer'] ?? ''); ?></textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 6: Email Settings -->
                    <div class="ff-wizard-panel" data-panel="email">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Email Settings', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Configure email notifications and templates.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <!-- Email Configuration Pod -->
                                <div class="ff-pod ff-pod-primary">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-email-alt"></span><?php esc_html_e('Email Configuration', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label class="ff-checkbox-label">
                                                <input type="checkbox" name="settings[send_confirmation_email]" value="1"
                                                       <?php checked($instance['settings']['send_confirmation_email'] ?? true); ?>>
                                                <?php esc_html_e('Send confirmation email from this site', 'formflow-lite'); ?>
                                            </label>
                                            <p class="ff-field-help"><?php esc_html_e('Uncheck if IntelliSOURCE sends confirmation emails automatically.', 'formflow-lite'); ?></p>
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="support_email_from" class="ff-field-label"><?php esc_html_e('From Email', 'formflow-lite'); ?></label>
                                            <input type="email" id="support_email_from" name="support_email_from" class="ff-field-input"
                                                   value="<?php echo esc_attr($instance['support_email_from'] ?? ''); ?>"
                                                   placeholder="noreply@example.com">
                                        </div>
                                        <div class="ff-field-group">
                                            <label for="support_email_to" class="ff-field-label"><?php esc_html_e('CC Emails', 'formflow-lite'); ?></label>
                                            <input type="text" id="support_email_to" name="support_email_to" class="ff-field-input"
                                                   value="<?php echo esc_attr($instance['support_email_to'] ?? ''); ?>"
                                                   placeholder="admin@example.com, support@example.com">
                                        </div>
                                    </div>
                                </div>

                                <!-- Template Settings Pod -->
                                <div class="ff-pod">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-welcome-write-blog"></span><?php esc_html_e('Template Settings', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="content_email_subject" class="ff-field-label"><?php esc_html_e('Subject', 'formflow-lite'); ?></label>
                                            <input type="text" id="content_email_subject" name="settings[content][email_subject]" class="ff-field-input"
                                                   value="<?php echo esc_attr($content['email_subject'] ?? ''); ?>"
                                                   placeholder="<?php esc_attr_e('Your {program_name} Enrollment Confirmation', 'formflow-lite'); ?>">
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="content_email_heading" class="ff-field-label"><?php esc_html_e('Heading', 'formflow-lite'); ?></label>
                                            <input type="text" id="content_email_heading" name="settings[content][email_heading]" class="ff-field-input"
                                                   value="<?php echo esc_attr($content['email_heading'] ?? ''); ?>"
                                                   placeholder="<?php esc_attr_e('Thank You for Enrolling!', 'formflow-lite'); ?>">
                                        </div>

                                        <div class="ff-field-group">
                                            <label for="content_email_footer" class="ff-field-label"><?php esc_html_e('Footer', 'formflow-lite'); ?></label>
                                            <textarea id="content_email_footer" name="settings[content][email_footer]" class="ff-field-textarea" rows="2"
                                                      placeholder="<?php esc_attr_e('Thank you for helping us build a more reliable energy grid!', 'formflow-lite'); ?>"><?php echo esc_textarea($content['email_footer'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Email Body Pod -->
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-text-page"></span><?php esc_html_e('Email Body', 'formflow-lite'); ?></h3>
                                        <span class="ff-pod-badge"><?php esc_html_e('{name}, {email}, {phone}, {device}, {date}, {time}, {confirmation_number}', 'formflow-lite'); ?></span>
                                    </div>
                                    <div class="ff-pod-body">
                                        <div class="ff-field-group">
                                            <label for="content_email_body" class="ff-field-label">
                                                <?php esc_html_e('Body Content', 'formflow-lite'); ?>
                                                <span class="ff-label-badge"><?php esc_html_e('HTML', 'formflow-lite'); ?></span>
                                            </label>
                                            <?php
                                            $email_editor_id = 'content_email_body';
                                            $email_content = $content['email_body'] ?? '';
                                            wp_editor($email_content, $email_editor_id, [
                                                'textarea_name' => 'settings[content][email_body]',
                                                'textarea_rows' => 10,
                                                'media_buttons' => false,
                                                'teeny' => true,
                                                'quicktags' => ['buttons' => 'strong,em,ul,ol,li,link'],
                                            ]);
                                            ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 7: Features -->
                    <div class="ff-wizard-panel" data-panel="features">
                        <div class="ff-panel-header">
                            <h2><?php esc_html_e('Advanced Features', 'formflow-lite'); ?></h2>
                            <p><?php esc_html_e('Enable and configure additional features.', 'formflow-lite'); ?></p>
                        </div>

                        <div class="ff-panel-body">
                            <div class="ff-pods-grid">
                                <div class="ff-pod ff-pod-full">
                                    <div class="ff-pod-header">
                                        <h3><span class="dashicons dashicons-admin-plugins"></span><?php esc_html_e('Feature Toggles', 'formflow-lite'); ?></h3>
                                    </div>
                                    <div class="ff-pod-body ff-pod-body-features">
                                        <?php include FFFL_PLUGIN_DIR . 'admin/views/partials/features-settings.php'; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Wizard Navigation Buttons -->
                <div class="ff-wizard-footer">
                    <div class="ff-wizard-footer-left">
                        <button type="button" id="ff-wizard-prev" class="button button-secondary" style="display: none;">
                            <span class="dashicons dashicons-arrow-left-alt2"></span>
                            <?php esc_html_e('Previous', 'formflow-lite'); ?>
                        </button>
                    </div>
                    <div class="ff-wizard-footer-center">
                        <span class="ff-wizard-progress">
                            <span id="ff-wizard-progress-text"><?php esc_html_e('Step 1 of 7', 'formflow-lite'); ?></span>
                        </span>
                    </div>
                    <div class="ff-wizard-footer-right">
                        <button type="button" id="ff-wizard-next" class="button button-primary">
                            <?php esc_html_e('Next', 'formflow-lite'); ?>
                            <span class="dashicons dashicons-arrow-right-alt2"></span>
                        </button>
                        <button type="submit" id="ff-wizard-save" class="button button-primary" style="display: none;">
                            <span class="dashicons dashicons-saved"></span>
                            <?php echo $is_edit ? esc_html__('Save Changes', 'formflow-lite') : esc_html__('Create Form', 'formflow-lite'); ?>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="ff-editor-sidebar">
                <div class="ff-sidebar-card ff-publish-card">
                    <h3><?php esc_html_e('Publish', 'formflow-lite'); ?></h3>

                    <div class="ff-publish-status">
                        <label class="ff-status-toggle">
                            <input type="checkbox" name="is_active" value="1" <?php checked($instance['is_active'] ?? true); ?>>
                            <span class="ff-status-slider"></span>
                            <span class="ff-status-label"><?php esc_html_e('Active', 'formflow-lite'); ?></span>
                        </label>
                    </div>

                    <div class="ff-publish-actions">
                        <button type="submit" class="button button-primary button-large ff-save-btn">
                            <?php echo $is_edit ? esc_html__('Save Changes', 'formflow-lite') : esc_html__('Create Form', 'formflow-lite'); ?>
                        </button>
                        <?php if ($is_edit) : ?>
                        <button type="button" class="button button-link-delete ff-delete-instance" data-id="<?php echo esc_attr($instance['id']); ?>">
                            <?php esc_html_e('Delete', 'formflow-lite'); ?>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($is_edit) : ?>
                <div class="ff-sidebar-card">
                    <h3><?php esc_html_e('Shortcode', 'formflow-lite'); ?></h3>
                    <code class="ff-shortcode-display" onclick="navigator.clipboard.writeText(this.innerText)" title="<?php esc_attr_e('Click to copy', 'formflow-lite'); ?>">
                        [fffl_form instance="<?php echo esc_attr($instance['slug']); ?>"]
                    </code>
                    <p class="ff-card-help"><?php esc_html_e('Click to copy', 'formflow-lite'); ?></p>
                </div>

                <div class="ff-sidebar-card">
                    <h3><?php esc_html_e('Quick Actions', 'formflow-lite'); ?></h3>
                    <div class="ff-quick-actions">
                        <button type="button" id="ff-preview-form" class="button">
                            <span class="dashicons dashicons-visibility"></span>
                            <?php esc_html_e('Preview', 'formflow-lite'); ?>
                        </button>
                        <button type="button" id="ff-duplicate-form" class="button" data-id="<?php echo esc_attr($instance['id']); ?>">
                            <span class="dashicons dashicons-admin-page"></span>
                            <?php esc_html_e('Duplicate', 'formflow-lite'); ?>
                        </button>
                    </div>
                </div>

                <div class="ff-sidebar-card">
                    <h3><?php esc_html_e('Statistics', 'formflow-lite'); ?></h3>
                    <?php
                    $db = new \FFFL\Database\Database();
                    $instance_stats = $db->get_statistics($instance['id']);
                    ?>
                    <div class="ff-mini-stats">
                        <div class="ff-mini-stat">
                            <span class="ff-mini-stat-value"><?php echo esc_html($instance_stats['total']); ?></span>
                            <span class="ff-mini-stat-label"><?php esc_html_e('Total', 'formflow-lite'); ?></span>
                        </div>
                        <div class="ff-mini-stat">
                            <span class="ff-mini-stat-value"><?php echo esc_html($instance_stats['completed']); ?></span>
                            <span class="ff-mini-stat-label"><?php esc_html_e('Completed', 'formflow-lite'); ?></span>
                        </div>
                        <div class="ff-mini-stat">
                            <span class="ff-mini-stat-value"><?php echo esc_html($instance_stats['completion_rate']); ?>%</span>
                            <span class="ff-mini-stat-label"><?php esc_html_e('Rate', 'formflow-lite'); ?></span>
                        </div>
                    </div>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=fffl-data&instance_id=' . $instance['id'])); ?>" class="ff-view-all-link">
                        <?php esc_html_e('View All Submissions', 'formflow-lite'); ?> &rarr;
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Form Preview Modal -->
<?php if (!empty($instance['id'])) : ?>
<div id="ff-preview-modal" class="ff-modal" style="display: none;">
    <div class="ff-modal-overlay"></div>
    <div class="ff-modal-container">
        <div class="ff-modal-header">
            <h2><?php esc_html_e('Form Preview', 'formflow-lite'); ?></h2>
            <button type="button" class="ff-modal-close" aria-label="<?php esc_attr_e('Close', 'formflow-lite'); ?>">
                <span class="dashicons dashicons-no-alt"></span>
            </button>
        </div>
        <div class="ff-modal-body">
            <div class="ff-modal-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading preview...', 'formflow-lite'); ?>
            </div>
            <div class="ff-modal-content"></div>
        </div>
    </div>
</div>
<style>
    .ff-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 100050;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .ff-modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
    }
    .ff-modal-container {
        position: relative;
        background: #fff;
        border-radius: 8px;
        width: 90%;
        max-width: 800px;
        max-height: 90vh;
        display: flex;
        flex-direction: column;
        box-shadow: 0 5px 30px rgba(0, 0, 0, 0.3);
    }
    .ff-modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px 20px;
        border-bottom: 1px solid #ddd;
    }
    .ff-modal-header h2 {
        margin: 0;
        font-size: 18px;
    }
    .ff-modal-close {
        background: none;
        border: none;
        cursor: pointer;
        padding: 5px;
        color: #666;
    }
    .ff-modal-close:hover {
        color: #d63638;
    }
    .ff-modal-body {
        padding: 20px;
        overflow-y: auto;
        flex: 1;
    }
    .ff-modal-loading {
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px;
        gap: 10px;
    }
    .ff-modal-loading .spinner {
        float: none;
        margin: 0;
    }
    .ff-modal-content {
        display: none;
    }
</style>
<?php endif; ?>

<script>
jQuery(document).ready(function($) {
    // 3.2.7: password show/hide toggle
    $(document).on('click', '.ff-password-toggle', function() {
        var $btn = $(this);
        var $input = $('#' + $btn.data('target'));
        var $icon = $btn.find('.dashicons');
        var showing = $input.attr('type') === 'text';
        $input.attr('type', showing ? 'password' : 'text');
        $icon.toggleClass('dashicons-visibility', showing);
        $icon.toggleClass('dashicons-hidden', !showing);
        $btn.attr('aria-pressed', !showing);
        $btn.attr('aria-label', showing ? 'Show password' : 'Hide password');
    });

    var wizardSteps = ['basics', 'api', 'fields', 'scheduling', 'content', 'email', 'features'];
    var currentStepIndex = 0;
    var isQuickEditMode = $('#ff-quick-edit-toggle').is(':checked');

    // Initialize wizard
    function initWizard() {
        updateWizardUI();

        // Handle quick edit toggle
        $('#ff-quick-edit-toggle').on('change', function() {
            isQuickEditMode = $(this).is(':checked');
            $('.ff-wizard-form').toggleClass('ff-quick-edit-mode', isQuickEditMode);

            if (isQuickEditMode) {
                // Show all panels
                $('.ff-wizard-panel').addClass('active');
                $('.ff-wizard-nav').hide();
                $('.ff-wizard-footer').hide();
            } else {
                // Show only current panel
                $('.ff-wizard-panel').removeClass('active');
                $('.ff-wizard-panel[data-panel="' + wizardSteps[currentStepIndex] + '"]').addClass('active');
                $('.ff-wizard-nav').show();
                $('.ff-wizard-footer').show();
            }
            updateWizardUI();
        });

        // Initialize on load
        if (isQuickEditMode) {
            $('#ff-quick-edit-toggle').trigger('change');
        }
    }

    // Update wizard UI based on current step
    function updateWizardUI() {
        if (isQuickEditMode) return;

        var currentStep = wizardSteps[currentStepIndex];

        // Update step navigation
        $('.ff-wizard-step').removeClass('active completed');
        wizardSteps.forEach(function(step, index) {
            var $step = $('.ff-wizard-step[data-step="' + step + '"]');
            if (index < currentStepIndex) {
                $step.addClass('completed');
            } else if (index === currentStepIndex) {
                $step.addClass('active');
            }
        });

        // Update panels
        $('.ff-wizard-panel').removeClass('active');
        $('.ff-wizard-panel[data-panel="' + currentStep + '"]').addClass('active');

        // Update buttons
        $('#ff-wizard-prev').toggle(currentStepIndex > 0);
        $('#ff-wizard-next').toggle(currentStepIndex < wizardSteps.length - 1);
        $('#ff-wizard-save').toggle(currentStepIndex === wizardSteps.length - 1);

        // Update progress text
        $('#ff-wizard-progress-text').text('<?php esc_html_e('Step', 'formflow-lite'); ?> ' + (currentStepIndex + 1) + ' <?php esc_html_e('of', 'formflow-lite'); ?> ' + wizardSteps.length);

        // Update hidden input
        $('#ff-current-step').val(currentStep);
    }

    // Navigate to step
    function goToStep(stepIndex) {
        if (stepIndex >= 0 && stepIndex < wizardSteps.length) {
            currentStepIndex = stepIndex;
            updateWizardUI();

            // Scroll to top of editor
            $('.ff-editor-main').scrollTop(0);
        }
    }

    // Next button
    $('#ff-wizard-next').on('click', function() {
        // Validate current step before proceeding
        if (validateCurrentStep()) {
            goToStep(currentStepIndex + 1);
        }
    });

    // Previous button
    $('#ff-wizard-prev').on('click', function() {
        goToStep(currentStepIndex - 1);
    });

    // Click on step navigation
    $('.ff-wizard-step').on('click', function() {
        var stepId = $(this).data('step');
        var stepIndex = wizardSteps.indexOf(stepId);
        if (stepIndex !== -1) {
            goToStep(stepIndex);
        }
    });

    // Basic validation for current step
    function validateCurrentStep() {
        var currentStep = wizardSteps[currentStepIndex];
        var $panel = $('.ff-wizard-panel[data-panel="' + currentStep + '"]');
        var isValid = true;

        $panel.find('[required]').each(function() {
            if (!$(this).val()) {
                $(this).addClass('ff-field-error');
                isValid = false;
            } else {
                $(this).removeClass('ff-field-error');
            }
        });

        if (!isValid) {
            $panel.find('.ff-field-error').first().focus();
        }

        return isValid;
    }

    // Auto-fill settings when utility changes
    $('#utility').on('change', function() {
        var $selected = $(this).find(':selected');
        var endpoint = $selected.data('endpoint');
        var emailFrom = $selected.data('email-from');
        var emailTo = $selected.data('email-to');

        if (endpoint) $('#api_endpoint').val(endpoint);
        if (emailFrom) $('#support_email_from').val(emailFrom);
        if (emailTo) $('#support_email_to').val(emailTo);
    });

    // Auto-generate slug from name
    <?php if (!$is_edit) : ?>
    $('#name').on('input', function() {
        var slug = $(this).val()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
        $('#slug').val(slug);
    });
    <?php endif; ?>

    // Toggle demo accounts display
    $('input[name="demo_mode"]').on('change', function() {
        $('#ff-demo-accounts').toggle(this.checked);
    });

    // Toggle external settings based on form type
    $('#form_type').on('change', function() {
        var isExternal = $(this).val() === 'external';
        $('#ff-external-settings').toggle(isExternal);

        // Make external URL required only when external type is selected
        $('#external_url').prop('required', isExternal);

        // Hide API and Fields steps for external type (they're not needed)
        if (isExternal) {
            $('.ff-wizard-step[data-step="api"], .ff-wizard-step[data-step="fields"], .ff-wizard-step[data-step="scheduling"]').addClass('ff-step-disabled');
        } else {
            $('.ff-wizard-step[data-step="api"], .ff-wizard-step[data-step="fields"], .ff-wizard-step[data-step="scheduling"]').removeClass('ff-step-disabled');
        }
    }).trigger('change');

    // Blocked dates management
    var blockedDateIndex = <?php echo !empty($blocked_dates) ? max(array_keys($blocked_dates)) + 1 : 0; ?>;

    $('#ff-add-blocked-date').on('click', function() {
        var html = '<div class="ff-blocked-date-row ff-sortable-item" data-index="' + blockedDateIndex + '">' +
            '<span class="ff-drag-handle" title="<?php echo esc_js(__('Drag to reorder', 'formflow-lite')); ?>">' +
            '<span class="dashicons dashicons-menu"></span></span>' +
            '<input type="date" name="settings[scheduling][blocked_dates][' + blockedDateIndex + '][date]" class="ff-date-input" required>' +
            '<input type="text" name="settings[scheduling][blocked_dates][' + blockedDateIndex + '][label]" placeholder="<?php echo esc_js(__('e.g., Christmas Day', 'formflow-lite')); ?>" class="regular-text">' +
            '<button type="button" class="button ff-remove-blocked-date"><span class="dashicons dashicons-trash"></span></button>' +
            '</div>';
        $('#ff-blocked-dates-list').append(html);
        blockedDateIndex++;
    });

    $(document).on('click', '.ff-remove-blocked-date', function() {
        $(this).closest('.ff-blocked-date-row').remove();
    });

    // Toggle capacity limits inputs
    $('input[name="settings[scheduling][capacity_limits][enabled]"]').on('change', function() {
        $('.ff-capacity-inputs').toggle(this.checked);
    });

    // Toggle maintenance inputs
    $('input[name="settings[maintenance][enabled]"]').on('change', function() {
        $('.ff-maintenance-inputs').toggle(this.checked);
    });

    <?php if ($is_edit) : ?>
    // Duplicate form handler
    $('#ff-duplicate-form').on('click', function() {
        var $btn = $(this);
        var instanceId = $btn.data('id');

        if (!confirm('<?php echo esc_js(__('Create a copy of this form?', 'formflow-lite')); ?>')) {
            return;
        }

        $btn.prop('disabled', true);

        $.ajax({
            url: fffl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'fffl_duplicate_instance',
                nonce: fffl_admin.nonce,
                id: instanceId
            },
            success: function(response) {
                if (response.success) {
                    window.location.href = '<?php echo esc_url(admin_url('admin.php?page=fffl-instance-editor&id=')); ?>' + response.data.new_id;
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Failed to duplicate form.', 'formflow-lite')); ?>');
                    $btn.prop('disabled', false);
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Failed to duplicate form.', 'formflow-lite')); ?>');
                $btn.prop('disabled', false);
            }
        });
    });

    // Preview form handler - opens modal
    $('#ff-preview-form').on('click', function() {
        var $modal = $('#ff-preview-modal');
        var $loading = $modal.find('.ff-modal-loading');
        var $content = $modal.find('.ff-modal-content');

        // Show modal with loading state
        $modal.show();
        $loading.show();
        $content.hide().empty();

        // Load form via AJAX
        $.post(fffl_admin.ajax_url, {
            action: 'fffl_preview_instance',
            nonce: fffl_admin.nonce,
            instance_id: <?php echo (int) $instance['id']; ?>
        }, function(response) {
            $loading.hide();
            if (response.success) {
                $content.html(response.data.html).show();
            } else {
                $content.html('<div class="notice notice-error"><p>' + (response.data.message || '<?php echo esc_js(__('Failed to load preview.', 'formflow-lite')); ?>') + '</p></div>').show();
            }
        }).fail(function() {
            $loading.hide();
            $content.html('<div class="notice notice-error"><p><?php echo esc_js(__('Failed to load preview.', 'formflow-lite')); ?></p></div>').show();
        });
    });

    // Close modal handlers
    $('#ff-preview-modal .ff-modal-close, #ff-preview-modal .ff-modal-overlay').on('click', function() {
        $('#ff-preview-modal').hide();
    });

    // Close on escape key
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $('#ff-preview-modal').is(':visible')) {
            $('#ff-preview-modal').hide();
        }
    });
    <?php endif; ?>

    // Initialize
    initWizard();
});
</script>
