<?php
/**
 * Enrollment Step 2: Account Validation
 *
 * User enters utility account number, ZIP code, and participation level for validation.
 */

if (!defined('ABSPATH')) {
    exit;
}

// Import content helper function
use function FFFL\Frontend\fffl_get_content;
use function FFFL\Frontend\fffl_get_support_phone;

$utility_no = $form_data['utility_no'] ?? '';
$zip = $form_data['zip'] ?? '';
$cycling_level = $form_data['cycling_level'] ?? '100';
$validation_error = $form_data['validation_error'] ?? '';

// Get utility name: honor a non-empty content override, otherwise derive the
// brand from the form's utility so Pepco forms don't fall back to "Delmarva
// Power" (the field is not editable in the builder). Empty is treated as absent.
$utility_name = ($instance['settings']['content']['utility_name'] ?? '')
    ?: \FFFL\Utilities::getBrandName($instance['utility'] ?? '');

// Get customizable content
$step_title = fffl_get_content($instance, 'step2_title', __('Verify Your Account', 'formflow-lite'));
$help_account = fffl_get_content($instance, 'help_account', __('Please enter your account number without dashes or spaces.', 'formflow-lite'));
$help_zip = fffl_get_content($instance, 'help_zip', __('The ZIP code where your utility service is located.', 'formflow-lite'));
$btn_back = fffl_get_content($instance, 'btn_back', __('Back', 'formflow-lite'));
$btn_verify = fffl_get_content($instance, 'btn_verify', __('Verify Account', 'formflow-lite'));

// Get account number help image if configured
$account_help_image = $instance['settings']['account_help_image'] ?? '';
?>

<div class="ff-step" data-step="2">
    <h2 class="ff-step-title"><?php echo esc_html($step_title); ?></h2>
    <p class="ff-step-description">
        <?php esc_html_e('Please enter your utility account information to verify your eligibility for the program.', 'formflow-lite'); ?>
    </p>

    <?php if (!empty($validation_error)) : ?>
        <div class="ff-alert ff-alert-error">
            <span class="ff-alert-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                </svg>
            </span>
            <span class="ff-alert-message"><?php echo esc_html($validation_error); ?></span>
            <button type="button" class="ff-alert-close" aria-label="<?php esc_attr_e('Dismiss', 'formflow-lite'); ?>">&times;</button>
        </div>
    <?php endif; ?>

    <form class="ff-step-form" id="ff-step-2-form">
        <!-- Account Validation -->
        <fieldset class="ff-fieldset">
            <legend class="ff-legend"><?php esc_html_e('Account Validation', 'formflow-lite'); ?></legend>

            <div class="ff-form-grid ff-form-grid-2">
                <div class="ff-field ff-field-required">
                    <label for="utility_no" class="ff-label">
                        <?php printf(esc_html__('%s Account Number', 'formflow-lite'), esc_html($utility_name)); ?>
                        <span class="ff-required">*</span>
                        <button type="button" class="ff-help-link" data-popup="account-help">
                            <?php esc_html_e('Where is this?', 'formflow-lite'); ?>
                        </button>
                    </label>
                    <input type="text"
                           name="utility_no"
                           id="utility_no"
                           class="ff-input"
                           value="<?php echo esc_attr($utility_no); ?>"
                           placeholder="<?php esc_attr_e('Enter your account number', 'formflow-lite'); ?>"
                           required
                           autocomplete="off">
                    <p class="ff-field-hint">
                        <?php echo esc_html($help_account); ?>
                    </p>
                </div>

                <div class="ff-field ff-field-required">
                    <label for="zip" class="ff-label">
                        <?php esc_html_e('Service ZIP Code', 'formflow-lite'); ?>
                        <span class="ff-required">*</span>
                    </label>
                    <input type="text"
                           name="zip"
                           id="zip"
                           class="ff-input"
                           value="<?php echo esc_attr($zip); ?>"
                           placeholder="<?php esc_attr_e('Enter 5-digit ZIP code', 'formflow-lite'); ?>"
                           pattern="[0-9]{5}"
                           maxlength="5"
                           required
                           autocomplete="postal-code">
                    <p class="ff-field-hint">
                        <?php echo esc_html($help_zip); ?>
                    </p>
                </div>
            </div>
        </fieldset>

        <!-- Participation Level -->
        <fieldset class="ff-fieldset">
            <legend class="ff-legend">
                <?php esc_html_e('Participation Level', 'formflow-lite'); ?>
                <button type="button" class="ff-help-link" data-popup="cycling-help">
                    <?php esc_html_e('What is cycling?', 'formflow-lite'); ?>
                </button>
            </legend>

            <div class="ff-cycling-options">
                <label class="ff-radio-option <?php echo $cycling_level === '50' ? 'selected' : ''; ?>">
                    <input type="radio"
                           name="cycling_level"
                           value="50"
                           <?php checked($cycling_level, '50'); ?>>
                    <span class="ff-radio-label">
                        <strong>50% Cycling</strong>
                        <span class="ff-radio-desc"><?php esc_html_e('Your AC cycles off for up to 7.5 minutes each half hour', 'formflow-lite'); ?></span>
                    </span>
                </label>

                <label class="ff-radio-option <?php echo $cycling_level === '75' ? 'selected' : ''; ?>">
                    <input type="radio"
                           name="cycling_level"
                           value="75"
                           <?php checked($cycling_level, '75'); ?>>
                    <span class="ff-radio-label">
                        <strong>75% Cycling</strong>
                        <span class="ff-radio-desc"><?php esc_html_e('Your AC cycles off for up to 11.25 minutes each half hour', 'formflow-lite'); ?></span>
                    </span>
                </label>

                <label class="ff-radio-option <?php echo ($cycling_level === '100' || empty($cycling_level)) ? 'selected' : ''; ?>">
                    <input type="radio"
                           name="cycling_level"
                           value="100"
                           <?php checked($cycling_level, '100'); ?>
                           <?php if (empty($cycling_level)) echo 'checked'; ?>>
                    <span class="ff-radio-label">
                        <strong>100% Cycling</strong>
                        <span class="ff-radio-desc"><?php esc_html_e('Your AC cycles off for up to 15 minutes each half hour (Maximum savings)', 'formflow-lite'); ?></span>
                    </span>
                </label>
            </div>
        </fieldset>

        <div class="ff-step-actions">
            <button type="button" class="ff-btn ff-btn-secondary ff-btn-prev">
                <span class="ff-btn-arrow">&larr;</span>
                <?php echo esc_html($btn_back); ?>
            </button>
            <button type="submit" class="ff-btn ff-btn-primary ff-btn-next">
                <span class="ff-btn-text"><?php echo esc_html($btn_verify); ?></span>
                <span class="ff-btn-loading" style="display:none;">
                    <svg class="ff-spinner" viewBox="0 0 24 24" width="20" height="20">
                        <circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3" fill="none" stroke-dasharray="31.4 31.4" />
                    </svg>
                    <?php esc_html_e('Verifying...', 'formflow-lite'); ?>
                </span>
            </button>
        </div>
    </form>
</div>

<!-- Account Number Help Popup -->
<div id="ff-popup-account-help" class="ff-popup" style="display:none;">
    <div class="ff-popup-content ff-popup-lg">
        <button type="button" class="ff-popup-close" aria-label="<?php esc_attr_e('Close', 'formflow-lite'); ?>">&times;</button>
        <h3><?php esc_html_e('Where to Find Your Account Number', 'formflow-lite'); ?></h3>
        <div class="ff-popup-body">
            <p><?php printf(esc_html__('Your %s account number can be found at the top of your monthly utility bill.', 'formflow-lite'), esc_html($utility_name)); ?></p>
            <?php if (!empty($account_help_image)) : ?>
                <img src="<?php echo esc_url($account_help_image); ?>" alt="<?php esc_attr_e('Account number location on bill', 'formflow-lite'); ?>" class="ff-help-image">
            <?php else : ?>
                <div class="ff-help-bill-diagram">
                    <div class="ff-bill-mock">
                        <div class="ff-bill-header"><?php echo esc_html($utility_name); ?></div>
                        <div class="ff-bill-row ff-bill-highlight">
                            <span><?php esc_html_e('Account Number:', 'formflow-lite'); ?></span>
                            <span class="ff-bill-value">1234567890</span>
                            <span class="ff-bill-arrow">&larr; <?php esc_html_e('Here', 'formflow-lite'); ?></span>
                        </div>
                        <div class="ff-bill-row">
                            <span><?php esc_html_e('Service Address:', 'formflow-lite'); ?></span>
                            <span>123 Main St</span>
                        </div>
                        <div class="ff-bill-row">
                            <span><?php esc_html_e('Amount Due:', 'formflow-lite'); ?></span>
                            <span>$XXX.XX</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <p class="ff-help-note">
                <?php esc_html_e('Enter your account number without dashes or spaces.', 'formflow-lite'); ?>
            </p>
            <p class="ff-help-note">
                <?php esc_html_e('If you have multiple accounts, use the account number for the service address where you want the device installed.', 'formflow-lite'); ?>
            </p>
        </div>
    </div>
</div>

<!-- Cycling Help Popup -->
<div id="ff-popup-cycling-help" class="ff-popup" style="display:none;">
    <div class="ff-popup-content">
        <button type="button" class="ff-popup-close" aria-label="<?php esc_attr_e('Close', 'formflow-lite'); ?>">&times;</button>
        <h3><?php esc_html_e('What is Cycling?', 'formflow-lite'); ?></h3>
        <div class="ff-popup-body">
            <p><?php esc_html_e('Cycling refers to how your air conditioning compressor is managed during peak energy demand periods (typically hot summer afternoons).', 'formflow-lite'); ?></p>
            <p><?php esc_html_e('During a cycling event:', 'formflow-lite'); ?></p>
            <ul>
                <li><strong>50% Cycling:</strong> <?php esc_html_e('Your compressor cycles off for up to 7.5 minutes every half hour', 'formflow-lite'); ?></li>
                <li><strong>75% Cycling:</strong> <?php esc_html_e('Your compressor cycles off for up to 11.25 minutes every half hour', 'formflow-lite'); ?></li>
                <li><strong>100% Cycling:</strong> <?php esc_html_e('Your compressor cycles off for up to 15 minutes every half hour', 'formflow-lite'); ?></li>
            </ul>
            <p><?php esc_html_e('Your fan continues to run during cycling, keeping air circulating. Most participants notice little to no change in comfort level.', 'formflow-lite'); ?></p>
            <p><strong><?php esc_html_e('Higher cycling levels = greater energy savings for everyone!', 'formflow-lite'); ?></strong></p>
        </div>
    </div>
</div>
