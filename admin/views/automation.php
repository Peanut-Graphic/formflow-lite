<?php
/**
 * Combined Automation View
 *
 * Displays Webhooks and Reports in a tabbed interface.
 *
 * @package FormFlow
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include breadcrumbs partial
require_once FFFL_PLUGIN_DIR . 'admin/views/partials/breadcrumbs.php';

// Base URL for tabs
$base_url = admin_url('admin.php?page=fffl-automation');
?>

<div class="wrap ff-admin-wrap">
    <?php fffl_breadcrumbs(['Dashboard' => 'fffl-dashboard'], __('Automation', 'formflow-lite')); ?>

    <h1><?php esc_html_e('Automation', 'formflow-lite'); ?></h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'webhooks', $base_url)); ?>"
           class="nav-tab <?php echo ($tab === 'webhooks') ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-rest-api"></span>
            <?php esc_html_e('Webhooks', 'formflow-lite'); ?>
        </a>
    </nav>

    <div class="ff-tab-content">
        <?php if ($tab === 'webhooks') : ?>
            <!-- Webhooks Tab -->
            <?php include FFFL_PLUGIN_DIR . 'admin/views/tabs/automation-webhooks.php'; ?>

        <?php endif; ?>
    </div>
</div>
