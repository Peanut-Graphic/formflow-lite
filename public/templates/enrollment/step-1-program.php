<?php
/**
 * Enrollment Step 1: Program Selection
 *
 * User selects their device type (thermostat or outdoor switch).
 */

if (!defined('ABSPATH')) {
    exit;
}

// Import content helper function
use function FFFL\Frontend\fffl_get_content;
use function FFFL\Frontend\fffl_requires_wifi;

// 3.2.17: pre-fill Step 1 from URL parameters. The program's intro pages
// link people here as e.g. /md-schedule/?has_ac=yes&device_type=thermostat,
// having already asked "Do you have central AC?" and "Which device?". Honor
// those so the customer isn't forced to re-answer — otherwise the fields sit
// unchecked, and clicking Continue trips the browser's required-field
// validation, which looks like the page is just refreshing.
$device_type = $form_data['device_type'] ?? '';

// A Web-Programmable Thermostat cannot be installed without home WiFi. Only
// instances that opted in ask about it; everywhere else this whole block is
// absent and the step renders exactly as it always has.
$requires_wifi = fffl_requires_wifi($instance);
$has_wifi      = $form_data['has_wifi'] ?? '';
if ($device_type === '' && isset($_GET['device_type'])) {
    $param_device = sanitize_text_field(wp_unslash($_GET['device_type']));
    if (in_array($param_device, ['thermostat', 'dcu'], true)) {
        $device_type = $param_device;
    }
}

$has_ac_checked = !empty($form_data['has_ac']);
if (!$has_ac_checked && isset($_GET['has_ac'])) {
    $param_has_ac = strtolower(sanitize_text_field(wp_unslash($_GET['has_ac'])));
    $has_ac_checked = in_array($param_has_ac, ['yes', '1', 'true', 'on'], true);
}

// Get customizable content
$step_title = fffl_get_content($instance, 'step1_title', __('Choose Your Energy-Saving Device', 'formflow-lite'));
$form_description = fffl_get_content($instance, 'form_description', __('Select the device you would like installed to participate in the Energy Wise Rewards program.', 'formflow-lite'));
$program_name = fffl_get_content($instance, 'program_name', __('Energy Wise Rewards', 'formflow-lite'));
$btn_next = fffl_get_content($instance, 'btn_next', __('Continue', 'formflow-lite'));
?>

<div class="ff-step" data-step="1">
    <h2 class="ff-step-title"><?php echo esc_html($step_title); ?></h2>
    <p class="ff-step-description">
        <?php echo esc_html($form_description); ?>
    </p>

    <form class="ff-step-form" id="ff-step-1-form">
        <div class="ff-field ff-field-required">
            <label class="ff-label">
                <input type="checkbox" name="has_ac" id="has_ac" value="yes" required
                       <?php checked($has_ac_checked, true); ?>>
                <?php esc_html_e('I have a Central Air Conditioner or Heat Pump and I am a customer of this utility.', 'formflow-lite'); ?>
                <span class="ff-required">*</span>
            </label>
        </div>

        <div class="ff-device-options">
            <label class="ff-device-option <?php echo $device_type === 'thermostat' ? 'selected' : ''; ?>">
                <input type="radio" name="device_type" value="thermostat" required
                       <?php checked($device_type, 'thermostat'); ?>>
                <div class="ff-device-card">
                    <div class="ff-device-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="4" y="2" width="16" height="20" rx="2"/>
                            <circle cx="12" cy="11" r="4"/>
                            <path d="M12 7v1M12 15v1M8 11h1M15 11h1"/>
                        </svg>
                    </div>
                    <div class="ff-device-content">
                        <h3><?php esc_html_e('Web-Programmable Thermostat', 'formflow-lite'); ?></h3>
                        <p><?php esc_html_e('A smart thermostat that lets you control your home temperature from anywhere, helping you save energy and money.', 'formflow-lite'); ?></p>
                    </div>
                    <a href="#" class="ff-device-info" data-popup="thermostat">
                        <?php esc_html_e('Learn More', 'formflow-lite'); ?>
                    </a>
                </div>
            </label>

            <label class="ff-device-option <?php echo $device_type === 'dcu' ? 'selected' : ''; ?>">
                <input type="radio" name="device_type" value="dcu" required
                       <?php checked($device_type, 'dcu'); ?>>
                <div class="ff-device-card">
                    <div class="ff-device-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <circle cx="12" cy="12" r="3"/>
                            <path d="M12 3v3M12 18v3M3 12h3M18 12h3"/>
                        </svg>
                    </div>
                    <div class="ff-device-content">
                        <h3><?php esc_html_e('Outdoor Switch', 'formflow-lite'); ?></h3>
                        <p><?php esc_html_e('A simple device installed on your outdoor AC unit that helps reduce strain on the power grid during peak demand.', 'formflow-lite'); ?></p>
                    </div>
                    <a href="#" class="ff-device-info" data-popup="dcu">
                        <?php esc_html_e('Learn More', 'formflow-lite'); ?>
                    </a>
                </div>
            </label>
        </div>

        <?php if ($requires_wifi) : ?>
            <?php
            $wifi_question = fffl_get_content($instance, 'wifi_question', __('Does your home have WiFi?', 'formflow-lite'));
            $wifi_help     = fffl_get_content($instance, 'wifi_help', __('A wireless internet connection from a router in your home.', 'formflow-lite'));
            $wifi_heading  = fffl_get_content($instance, 'wifi_callout_heading', __('Home WiFi is required for the thermostat', 'formflow-lite'));
            $wifi_body     = fffl_get_content($instance, 'wifi_callout_body', __('The Web-Programmable Thermostat connects to your home WiFi to receive schedule changes and take part in energy-saving events. Without it, it cannot be installed.', 'formflow-lite'));
            $wifi_reassure = fffl_get_content($instance, 'wifi_callout_reassurance', __('The Outdoor Switch gets you the same program. Same bill credits, same participation levels, same enrollment - it is simply a different device, installed outside on your AC unit instead of on your wall. No WiFi required.', 'formflow-lite'));
            $wifi_convert  = fffl_get_content($instance, 'wifi_convert_button', __('Yes, enroll me in the Outdoor Switch program', 'formflow-lite'));
            ?>
            <!--
                Shown only once the thermostat is selected. Hidden on load so
                nobody is warned about ineligibility before they have answered.
            -->
            <fieldset class="ff-field ff-wifi-check" id="ff-wifi-check" hidden>
                <legend class="ff-label">
                    <?php echo esc_html($wifi_question); ?>
                    <span class="ff-required">*</span>
                </legend>

                <div class="ff-wifi-options">
                    <label class="ff-radio-option">
                        <input type="radio" name="has_wifi" value="yes"
                               <?php checked($has_wifi, 'yes'); ?>>
                        <span class="ff-radio-label"><?php esc_html_e('Yes', 'formflow-lite'); ?></span>
                    </label>

                    <label class="ff-radio-option">
                        <input type="radio" name="has_wifi" value="no"
                               <?php checked($has_wifi, 'no'); ?>>
                        <span class="ff-radio-label"><?php esc_html_e('No', 'formflow-lite'); ?></span>
                    </label>
                </div>

                <p class="ff-field-help" id="ff-wifi-help"><?php echo esc_html($wifi_help); ?></p>
            </fieldset>

            <!--
                role="alert" so the callout is announced rather than silently
                appearing. Meaning must not depend on the red treatment alone:
                the icon and the heading state the problem in words, per WCAG AA.
            -->
            <div class="ff-wifi-callout" id="ff-wifi-callout" role="alert" hidden>
                <div class="ff-wifi-callout-icon" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                        <path d="M12 9v4M12 17h.01"/>
                    </svg>
                </div>

                <div class="ff-wifi-callout-content">
                    <h3 class="ff-wifi-callout-heading"><?php echo esc_html($wifi_heading); ?></h3>
                    <p><?php echo esc_html($wifi_body); ?></p>
                    <p class="ff-wifi-callout-reassurance"><?php echo esc_html($wifi_reassure); ?></p>

                    <div class="ff-wifi-callout-actions">
                        <button type="button" class="ff-btn ff-btn-primary ff-convert-to-dcu">
                            <?php echo esc_html($wifi_convert); ?>
                            <span class="ff-btn-arrow">&rarr;</span>
                        </button>
                        <a href="#" class="ff-device-info" data-popup="dcu">
                            <?php esc_html_e("What's the Outdoor Switch?", 'formflow-lite'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="ff-step-actions">
            <button type="submit" class="ff-btn ff-btn-primary ff-btn-next">
                <?php echo esc_html($btn_next); ?>
                <span class="ff-btn-arrow">&rarr;</span>
            </button>
        </div>
    </form>
</div>

<!-- Device Info Popups -->
<div class="ff-popup" id="ff-popup-thermostat" style="display:none;">
    <div class="ff-popup-content">
        <button type="button" class="ff-popup-close">&times;</button>
        <h3><?php esc_html_e('Web-Programmable Thermostat', 'formflow-lite'); ?></h3>
        <p><?php esc_html_e('The Energy Wise Rewards web-programmable thermostat allows you to:', 'formflow-lite'); ?></p>
        <ul>
            <li><?php esc_html_e('Control your home temperature remotely via web or mobile app', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Set schedules to automatically adjust temperature when you\'re away', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Receive energy-saving tips and usage insights', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Participate in demand response events to earn rewards', 'formflow-lite'); ?></li>
        </ul>
        <p><?php esc_html_e('Installation is free and performed by a certified technician.', 'formflow-lite'); ?></p>
    </div>
</div>

<div class="ff-popup" id="ff-popup-dcu" style="display:none;">
    <div class="ff-popup-content">
        <button type="button" class="ff-popup-close">&times;</button>
        <h3><?php esc_html_e('Outdoor Switch (Cycling Device)', 'formflow-lite'); ?></h3>
        <p><?php esc_html_e('The outdoor switch is a simple device that:', 'formflow-lite'); ?></p>
        <ul>
            <li><?php esc_html_e('Connects directly to your outdoor AC or heat pump unit', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Briefly cycles your unit during peak demand periods', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Operates automatically - no action required from you', 'formflow-lite'); ?></li>
            <li><?php esc_html_e('Has minimal impact on your home comfort', 'formflow-lite'); ?></li>
        </ul>
        <p><?php esc_html_e('Installation is free and typically takes less than 30 minutes.', 'formflow-lite'); ?></p>
    </div>
</div>
