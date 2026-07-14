<?php
/**
 * Email Handler
 *
 * Handles sending confirmation and notification emails.
 *
 * @package FormFlow
 */

namespace FFFL\Forms;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email Handler class
 */
class EmailHandler
{
    /**
     * Instance settings
     *
     * @var array
     */
    private array $instance;

    /**
     * Constructor
     *
     * @param array $instance Form instance settings.
     */
    public function __construct(array $instance)
    {
        $this->instance = $instance;
    }

    /**
     * Send customer confirmation email
     *
     * @param array  $form_data         Form data.
     * @param string $confirmation_number Confirmation number.
     * @return bool True if sent successfully.
     */
    public function sendCustomerConfirmation(array $form_data, string $confirmation_number): bool
    {
        $to = $form_data['email'] ?? '';
        if (empty($to) || !is_email($to)) {
            return false;
        }

        $subject = $this->getCustomerSubject($form_data);
        $message = $this->getCustomerEmailBody($form_data, $confirmation_number);
        $headers = $this->getEmailHeaders();

        return wp_mail($to, $subject, $message, $headers);
    }

    /**
     * Send admin notification email
     *
     * @param array  $form_data         Form data.
     * @param string $confirmation_number Confirmation number.
     * @return bool True if sent successfully.
     */
    public function sendAdminNotification(array $form_data, string $confirmation_number): bool
    {
        $to = $this->instance['support_email_to'] ?? '';
        if (empty($to)) {
            return false;
        }

        // Support multiple recipients
        $recipients = array_map('trim', explode(',', $to));
        $recipients = array_filter($recipients, 'is_email');

        if (empty($recipients)) {
            return false;
        }

        $subject = $this->getAdminSubject($form_data, $confirmation_number);
        $message = $this->getAdminEmailBody($form_data, $confirmation_number);
        $headers = $this->getEmailHeaders();

        return wp_mail($recipients, $subject, $message, $headers);
    }

    /**
     * Get email headers
     *
     * @return array
     */
    private function getEmailHeaders(): array
    {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        $from = $this->instance['support_email_from'] ?? '';
        if (!empty($from) && is_email($from)) {
            $from_name = $this->getUtilityName();
            $headers[] = "From: {$from_name} <{$from}>";
        }

        return $headers;
    }

    /**
     * Get utility display name
     *
     * @return string
     */
    private function getUtilityName(): string
    {
        $utility = $this->instance['utility'] ?? '';
        $names = [
            'delmarva_md' => 'Delmarva Power',
            'pepco_md' => 'Pepco',
            'pepco_dc' => 'Pepco',
        ];

        return $names[$utility] ?? 'Energy Wise Rewards';
    }

    /**
     * Get customer email subject
     *
     * @param array $form_data Form data.
     * @return string
     */
    private function getCustomerSubject(array $form_data): string
    {
        return sprintf(
            /* translators: %s: Utility name */
            __('%s Energy Wise Rewards - Enrollment Confirmation', 'formflow-lite'),
            $this->getUtilityName()
        );
    }

    /**
     * Get admin email subject
     *
     * @param array  $form_data         Form data.
     * @param string $confirmation_number Confirmation number.
     * @return string
     */
    private function getAdminSubject(array $form_data, string $confirmation_number): string
    {
        return sprintf(
            /* translators: 1: Confirmation number, 2: Customer name */
            __('New Enrollment: %1$s - %2$s', 'formflow-lite'),
            $confirmation_number,
            ($form_data['first_name'] ?? '') . ' ' . ($form_data['last_name'] ?? '')
        );
    }

    /**
     * Get customer email body
     *
     * @param array  $form_data         Form data.
     * @param string $confirmation_number Confirmation number.
     * @return string
     */
    private function getCustomerEmailBody(array $form_data, string $confirmation_number): string
    {
        $utility_name = $this->getUtilityName();
        $device_name = ($form_data['device_type'] ?? '') === 'thermostat'
            ? __('Web-Programmable Thermostat', 'formflow-lite')
            : __('Outdoor Switch', 'formflow-lite');

        $date = $form_data['schedule_date'] ?? '';
        if ($date) {
            $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
            if ($date_obj) {
                $date = $date_obj->format('l, F j, Y');
            }
        }

        $time = $this->formatTimeSlot($form_data['schedule_time'] ?? '');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #0066cc; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0;">
                <h1 style="margin: 0; font-size: 24px;"><?php echo esc_html($utility_name); ?></h1>
                <p style="margin: 8px 0 0 0; opacity: 0.9;">Energy Wise Rewards Program</p>
            </div>

            <div style="background: #f8f9fa; padding: 30px; border: 1px solid #ddd; border-top: none;">
                <h2 style="color: #28a745; margin-top: 0;">
                    <?php esc_html_e('Enrollment Confirmed!', 'formflow-lite'); ?>
                </h2>

                <p><?php printf(
                    /* translators: %s: Customer first name */
                    esc_html__('Dear %s,', 'formflow-lite'),
                    esc_html($form_data['first_name'] ?? '')
                ); ?></p>

                <p><?php esc_html_e('Thank you for enrolling in the Energy Wise Rewards program. Your installation appointment has been scheduled.', 'formflow-lite'); ?></p>

                <div style="background: #d4edda; border: 1px solid #28a745; border-radius: 4px; padding: 16px; margin: 20px 0; text-align: center;">
                    <p style="margin: 0; font-size: 12px; color: #666; text-transform: uppercase;"><?php esc_html_e('Confirmation Number', 'formflow-lite'); ?></p>
                    <p style="margin: 8px 0 0 0; font-size: 24px; font-weight: bold; color: #28a745; letter-spacing: 2px;"><?php echo esc_html($confirmation_number); ?></p>
                </div>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;"><?php esc_html_e('Appointment Details', 'formflow-lite'); ?></h3>

                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; color: #666;"><?php esc_html_e('Device:', 'formflow-lite'); ?></td>
                        <td style="padding: 8px 0; font-weight: bold;"><?php echo esc_html($device_name); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;"><?php esc_html_e('Date:', 'formflow-lite'); ?></td>
                        <td style="padding: 8px 0; font-weight: bold;"><?php echo esc_html($date); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;"><?php esc_html_e('Time:', 'formflow-lite'); ?></td>
                        <td style="padding: 8px 0; font-weight: bold;"><?php echo esc_html($time); ?></td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; color: #666;"><?php esc_html_e('Address:', 'formflow-lite'); ?></td>
                        <td style="padding: 8px 0; font-weight: bold;">
                            <?php echo esc_html($form_data['street'] ?? ''); ?><br>
                            <?php echo esc_html(($form_data['city'] ?? '') . ', ' . ($form_data['state'] ?? '') . ' ' . ($form_data['zip_confirm'] ?? $form_data['zip'] ?? '')); ?>
                        </td>
                    </tr>
                </table>

                <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 30px;"><?php esc_html_e('What to Expect', 'formflow-lite'); ?></h3>

                <ul style="padding-left: 20px;">
                    <li><?php esc_html_e('A certified technician will arrive during your scheduled time window', 'formflow-lite'); ?></li>
                    <li><?php esc_html_e('Please ensure an adult (18+) is present for the installation', 'formflow-lite'); ?></li>
                    <li><?php esc_html_e('Installation is FREE and typically takes 30-45 minutes', 'formflow-lite'); ?></li>
                    <li><?php esc_html_e('You may receive a confirmation call 1-2 days before your appointment', 'formflow-lite'); ?></li>
                </ul>

                <?php if (!empty($form_data['special_instructions'])) : ?>
                <p><strong><?php esc_html_e('Your Special Instructions:', 'formflow-lite'); ?></strong><br>
                <?php echo esc_html($form_data['special_instructions']); ?></p>
                <?php endif; ?>

                <p style="margin-top: 30px;"><?php esc_html_e('If you need to reschedule or have any questions, please contact us.', 'formflow-lite'); ?></p>

                <p><?php esc_html_e('Thank you for helping save energy!', 'formflow-lite'); ?></p>

                <p style="margin-bottom: 0;"><strong><?php echo esc_html($utility_name); ?> Energy Wise Rewards Team</strong></p>
            </div>

            <div style="background: #333; color: #999; padding: 20px; text-align: center; font-size: 12px; border-radius: 0 0 8px 8px;">
                <p style="margin: 0;"><?php esc_html_e('This is an automated message. Please do not reply directly to this email.', 'formflow-lite'); ?></p>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Get admin email body
     *
     * @param array  $form_data         Form data.
     * @param string $confirmation_number Confirmation number.
     * @return string
     */
    private function getAdminEmailBody(array $form_data, string $confirmation_number): string
    {
        $utility_name = $this->getUtilityName();
        $device_name = ($form_data['device_type'] ?? '') === 'thermostat'
            ? __('Web-Programmable Thermostat', 'formflow-lite')
            : __('Outdoor Switch', 'formflow-lite');

        $date = $form_data['schedule_date'] ?? '';
        if ($date) {
            $date_obj = \DateTime::createFromFormat('Y-m-d', $date);
            if ($date_obj) {
                $date = $date_obj->format('l, F j, Y');
            }
        }

        $time = $this->formatTimeSlot($form_data['schedule_time'] ?? '');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
        </head>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
            <h2 style="color: #0066cc; margin-top: 0;"><?php esc_html_e('New Energy Wise Rewards Enrollment', 'formflow-lite'); ?></h2>

            <p><strong><?php esc_html_e('Confirmation Number:', 'formflow-lite'); ?></strong> <?php echo esc_html($confirmation_number); ?></p>
            <p><strong><?php esc_html_e('Instance:', 'formflow-lite'); ?></strong> <?php echo esc_html($this->instance['name'] ?? ''); ?></p>

            <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px;"><?php esc_html_e('Customer Information', 'formflow-lite'); ?></h3>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #666; width: 150px;"><?php esc_html_e('Name:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html(($form_data['first_name'] ?? '') . ' ' . ($form_data['last_name'] ?? '')); ?></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Email:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><a href="mailto:<?php echo esc_attr($form_data['email'] ?? ''); ?>"><?php echo esc_html($form_data['email'] ?? ''); ?></a></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Phone:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($form_data['phone'] ?? ''); ?> (<?php echo esc_html($form_data['phone_type'] ?? ''); ?>)</td>
                </tr>
                <?php if (!empty($form_data['alt_phone'])) : ?>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Alt Phone:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($form_data['alt_phone']); ?> (<?php echo esc_html($form_data['alt_phone_type'] ?? ''); ?>)</td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Account Number:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($form_data['utility_no'] ?? ''); ?></td>
                </tr>
            </table>

            <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 20px;"><?php esc_html_e('Service Address', 'formflow-lite'); ?></h3>

            <p>
                <?php echo esc_html($form_data['street'] ?? ''); ?><br>
                <?php echo esc_html(($form_data['city'] ?? '') . ', ' . ($form_data['state'] ?? '') . ' ' . ($form_data['zip_confirm'] ?? $form_data['zip'] ?? '')); ?>
            </p>

            <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 20px;"><?php esc_html_e('Appointment Details', 'formflow-lite'); ?></h3>

            <table style="width: 100%; border-collapse: collapse;">
                <tr>
                    <td style="padding: 6px 0; color: #666; width: 150px;"><?php esc_html_e('Device:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($device_name); ?></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Date:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($date); ?></td>
                </tr>
                <tr>
                    <td style="padding: 6px 0; color: #666;"><?php esc_html_e('Time:', 'formflow-lite'); ?></td>
                    <td style="padding: 6px 0;"><?php echo esc_html($time); ?></td>
                </tr>
            </table>

            <?php if (!empty($form_data['special_instructions'])) : ?>
            <h3 style="border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-top: 20px;"><?php esc_html_e('Special Instructions', 'formflow-lite'); ?></h3>
            <p><?php echo esc_html($form_data['special_instructions']); ?></p>
            <?php endif; ?>

            <?php if (!empty($form_data['promo_code'])) : ?>
            <p><strong><?php esc_html_e('Promo Code:', 'formflow-lite'); ?></strong> <?php echo esc_html($form_data['promo_code']); ?></p>
            <?php endif; ?>

            <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">

            <p style="font-size: 12px; color: #666;">
                <?php esc_html_e('Submitted:', 'formflow-lite'); ?> <?php echo esc_html(current_time('mysql')); ?><br>
                <?php esc_html_e('IP Address:', 'formflow-lite'); ?> <?php echo esc_html($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /**
     * Format time slot code to readable string
     *
     * @param string $code Time slot code (AM, MD, PM, EV).
     * @return string Formatted time.
     */
    private function formatTimeSlot(string $code): string
    {
        $slots = [
            'AM' => __('Morning (8:00 AM - 12:00 PM)', 'formflow-lite'),
            'MD' => __('Midday (10:00 AM - 2:00 PM)', 'formflow-lite'),
            'PM' => __('Afternoon (12:00 PM - 5:00 PM)', 'formflow-lite'),
            'EV' => __('Evening (3:00 PM - 7:00 PM)', 'formflow-lite'),
        ];

        return $slots[$code] ?? $code;
    }
}
