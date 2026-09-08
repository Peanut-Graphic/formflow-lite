<?php
/**
 * Enrollment Success Page
 *
 * Displayed after successful enrollment completion.
 */

if (!defined('ABSPATH')) {
    exit;
}

$confirmation_number = $form_data['confirmation_number'] ?? '';
$device_type = $form_data['device_type'] ?? 'thermostat';
$device_name = $device_type === 'thermostat'
    ? __('Web-Programmable Thermostat', 'formflow-lite')
    : __('Outdoor Switch', 'formflow-lite');
?>

<div class="ff-step ff-step-success" data-step="success">
    <div class="ff-success-icon">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="64" height="64">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
        </svg>
    </div>

    <h2 class="ff-success-title"><?php esc_html_e('Enrollment Complete!', 'formflow-lite'); ?></h2>
    <p class="ff-success-message">
        <?php esc_html_e('Thank you for enrolling in the Energy Wise Rewards program. Your installation has been scheduled.', 'formflow-lite'); ?>
    </p>

    <?php if (!empty($confirmation_number)) : ?>
    <div class="ff-confirmation-box">
        <span class="ff-confirmation-label"><?php esc_html_e('Confirmation Number', 'formflow-lite'); ?></span>
        <span class="ff-confirmation-number"><?php echo esc_html($confirmation_number); ?></span>
        <p class="ff-confirmation-hint"><?php esc_html_e('Please save this number for your records.', 'formflow-lite'); ?></p>
    </div>
    <?php endif; ?>

    <div class="ff-success-details">
        <h3><?php esc_html_e('Appointment Details', 'formflow-lite'); ?></h3>
        <div class="ff-success-grid">
            <div class="ff-success-item">
                <span class="ff-success-label"><?php esc_html_e('Device', 'formflow-lite'); ?></span>
                <span class="ff-success-value"><?php echo esc_html($device_name); ?></span>
            </div>
            <div class="ff-success-item">
                <span class="ff-success-label"><?php esc_html_e('Date', 'formflow-lite'); ?></span>
                <span class="ff-success-value" id="success-date"><?php echo esc_html(\FFFL\Utilities::getAppointmentDateDisplay($form_data['schedule_date'] ?? '')); ?></span>
            </div>
            <div class="ff-success-item">
                <span class="ff-success-label"><?php esc_html_e('Time', 'formflow-lite'); ?></span>
                <span class="ff-success-value" id="success-time"><?php echo esc_html(\FFFL\Utilities::getTimeSlotDisplay($form_data['schedule_time'] ?? '')); ?></span>
            </div>
            <div class="ff-success-item">
                <span class="ff-success-label"><?php esc_html_e('Address', 'formflow-lite'); ?></span>
                <span class="ff-success-value">
                    <?php echo esc_html($form_data['street'] ?? ''); ?><br>
                    <?php echo esc_html(($form_data['city'] ?? '') . ', ' . ($form_data['state'] ?? '') . ' ' . ($form_data['zip'] ?? $form_data['zip_confirm'] ?? '')); ?>
                </span>
            </div>
        </div>
    </div>

    <div class="ff-success-email">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20">
            <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z" />
            <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z" />
        </svg>
        <p><?php esc_html_e('A confirmation email has been sent to:', 'formflow-lite'); ?></p>
        <strong id="success-email"><?php echo esc_html($form_data['email'] ?? ''); ?></strong>
    </div>

    <div class="ff-success-next">
        <h3><?php esc_html_e('What Happens Next?', 'formflow-lite'); ?></h3>
        <ol class="ff-next-steps">
            <li>
                <strong><?php esc_html_e('Confirmation Call', 'formflow-lite'); ?></strong>
                <p><?php esc_html_e('You may receive a call to confirm your appointment 1-2 days before the scheduled date.', 'formflow-lite'); ?></p>
            </li>
            <li>
                <strong><?php esc_html_e('Installation Day', 'formflow-lite'); ?></strong>
                <p><?php esc_html_e('Our certified technician will arrive during your scheduled time window. Please ensure an adult (18+) is present.', 'formflow-lite'); ?></p>
            </li>
            <?php if ($device_type === 'thermostat') : ?>
            <li>
                <strong><?php esc_html_e('Setup & Training', 'formflow-lite'); ?></strong>
                <p><?php esc_html_e('The technician will install your thermostat and show you how to use it, including the web and mobile app features.', 'formflow-lite'); ?></p>
            </li>
            <?php else : ?>
            <li>
                <strong><?php esc_html_e('Quick Installation', 'formflow-lite'); ?></strong>
                <p><?php esc_html_e('The outdoor switch installation is quick and non-invasive. The technician will verify everything is working properly.', 'formflow-lite'); ?></p>
            </li>
            <?php endif; ?>
            <li>
                <strong><?php esc_html_e('Start Saving', 'formflow-lite'); ?></strong>
                <p><?php esc_html_e('Once installed, you\'ll automatically start participating in the Energy Wise Rewards program and earning rewards!', 'formflow-lite'); ?></p>
            </li>
        </ol>
    </div>

    <div class="ff-success-contact">
        <p><?php esc_html_e('Questions about your enrollment or need to reschedule?', 'formflow-lite'); ?></p>
        <p>
            <?php
            printf(
                esc_html__('Contact us at %s or call %s', 'formflow-lite'),
                '<a href="mailto:support@energywiserewards.com">support@energywiserewards.com</a>',
                '<a href="tel:1-800-XXX-XXXX">1-800-XXX-XXXX</a>'
            );
            ?>
        </p>
    </div>

    <div class="ff-success-actions">
        <button type="button" class="ff-btn ff-btn-secondary" onclick="window.print();">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="16" height="16">
                <path fill-rule="evenodd" d="M5 4v3H4a2 2 0 00-2 2v3a2 2 0 002 2h1v2a2 2 0 002 2h6a2 2 0 002-2v-2h1a2 2 0 002-2V9a2 2 0 00-2-2h-1V4a2 2 0 00-2-2H7a2 2 0 00-2 2zm8 0H7v3h6V4zm0 8H7v4h6v-4z" clip-rule="evenodd" />
            </svg>
            <?php esc_html_e('Print Confirmation', 'formflow-lite'); ?>
        </button>
    </div>
</div>
