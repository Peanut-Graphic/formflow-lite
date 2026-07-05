<?php
/**
 * Combined Data View - Lite Version
 *
 * Displays Submissions and Activity Logs in a tabbed interface.
 *
 * @package FormFlow Lite
 */

if (!defined('ABSPATH')) {
    exit;
}

// Include breadcrumbs partial
require_once FFFL_PLUGIN_DIR . 'admin/views/partials/breadcrumbs.php';

// Base URL for tabs (preserves filters)
$base_url = admin_url('admin.php?page=fffl-data');

// Default to submissions tab
if (empty($tab) || !in_array($tab, ['submissions', 'activity'])) {
    $tab = 'submissions';
}
?>

<div class="wrap ff-admin-wrap">
    <?php fffl_breadcrumbs(['Dashboard' => 'fffl-dashboard'], __('Data', 'formflow-lite')); ?>

    <h1><?php esc_html_e('Data', 'formflow-lite'); ?></h1>

    <!-- Tab Navigation -->
    <nav class="nav-tab-wrapper">
        <a href="<?php echo esc_url(add_query_arg('tab', 'submissions', $base_url)); ?>"
           class="nav-tab <?php echo ($tab === 'submissions') ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Submissions', 'formflow-lite'); ?>
        </a>
        <a href="<?php echo esc_url(add_query_arg('tab', 'activity', $base_url)); ?>"
           class="nav-tab <?php echo ($tab === 'activity') ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-media-text"></span>
            <?php esc_html_e('Activity Logs', 'formflow-lite'); ?>
        </a>
    </nav>

    <div class="ff-tab-content">
        <?php if ($tab === 'submissions') : ?>
            <!-- Submissions Tab -->
            <?php include FFFL_PLUGIN_DIR . 'admin/views/tabs/data-submissions.php'; ?>

        <?php elseif ($tab === 'activity') : ?>
            <!-- Activity Logs Tab -->
            <?php include FFFL_PLUGIN_DIR . 'admin/views/tabs/data-activity.php'; ?>

        <?php endif; ?>
    </div>
</div>

<!-- Submission Details Modal -->
<div id="ff-submission-modal" class="ff-modal" style="display:none;">
    <div class="ff-modal-content ff-modal-large">
        <div class="ff-modal-header">
            <h2><?php esc_html_e('Submission Details', 'formflow-lite'); ?></h2>
            <button type="button" class="ff-modal-close">&times;</button>
        </div>
        <div class="ff-modal-body" id="ff-submission-content">
            <div class="ff-loading">
                <span class="spinner is-active"></span>
                <?php esc_html_e('Loading...', 'formflow-lite'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Log Details Modal -->
<div id="ff-details-modal" class="ff-modal" style="display:none;">
    <div class="ff-modal-content">
        <span class="ff-modal-close">&times;</span>
        <h3><?php esc_html_e('Log Details', 'formflow-lite'); ?></h3>
        <pre id="ff-details-content"></pre>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // HTML escape function to prevent XSS. Escapes the five significant HTML
    // characters INCLUDING both quote styles so the output is safe in attribute
    // contexts as well as text/element contexts. Arrays/objects are stringified
    // first so nested/structured values can never smuggle raw markup through.
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        if (typeof str === 'object') {
            try { str = JSON.stringify(str); } catch (e) { str = String(str); }
        }
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Render an email cell. Emits a mailto: link ONLY when the value looks like
    // a real email address (with the address percent-encoded for the href);
    // otherwise renders the value as escaped text with no href, so an
    // attacker-controlled "email" can never inject markup or a javascript: URL.
    function renderEmailCell(email) {
        var raw = (email === null || email === undefined) ? '' : String(email);
        if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(raw)) {
            return '<a href="mailto:' + encodeURIComponent(raw) + '">' + escapeHtml(raw) + '</a>';
        }
        return escapeHtml(raw);
    }

    // View submission details modal
    $('.ff-view-submission').on('click', function() {
        var submissionId = $(this).data('id');
        $('#ff-submission-content').html('<div class="ff-loading"><span class="spinner is-active"></span> <?php echo esc_js(__('Loading...', 'formflow-lite')); ?></div>');
        $('#ff-submission-modal').show();

        $.ajax({
            url: fffl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'fffl_get_submission_details',
                nonce: fffl_admin.nonce,
                submission_id: submissionId
            },
            success: function(response) {
                if (response.success) {
                    renderSubmissionDetails(response.data);
                } else {
                    $('#ff-submission-content').html('<div class="ff-error">' + (response.data.message || '<?php echo esc_js(__('Error loading submission.', 'formflow-lite')); ?>') + '</div>');
                }
            },
            error: function() {
                $('#ff-submission-content').html('<div class="ff-error"><?php echo esc_js(__('Error loading submission.', 'formflow-lite')); ?></div>');
            }
        });
    });

    function renderSubmissionDetails(data) {
        var s = data.submission;
        var fd = data.form_data;

        var html = '<div class="ff-submission-details">';

        // Header with status
        html += '<div class="ff-detail-header">';
        html += '<span class="ff-detail-id">#' + escapeHtml(s.id) + '</span>';
        html += '<span class="ff-status ff-status-' + escapeHtml(s.status) + '">' + escapeHtml(String(s.status || '').replace('_', ' ')) + '</span>';
        if (s.is_test) {
            html += '<span class="ff-status ff-status-test"><?php echo esc_js(__('Test', 'formflow-lite')); ?></span>';
        }
        html += '</div>';

        // Basic Info Section
        html += '<div class="ff-detail-section">';
        html += '<h4><?php echo esc_js(__('Basic Information', 'formflow-lite')); ?></h4>';
        html += '<table class="ff-detail-table">';
        html += '<tr><th><?php echo esc_js(__('Form Instance', 'formflow-lite')); ?></th><td>' + escapeHtml(s.instance_name || '—') + '</td></tr>';
        html += '<tr><th><?php echo esc_js(__('Session ID', 'formflow-lite')); ?></th><td><code>' + escapeHtml(s.session_id) + '</code></td></tr>';
        html += '<tr><th><?php echo esc_js(__('Account Number', 'formflow-lite')); ?></th><td>' + escapeHtml(s.account_number || '—') + '</td></tr>';
        html += '<tr><th><?php echo esc_js(__('Current Step', 'formflow-lite')); ?></th><td>' + escapeHtml(s.step) + '/5</td></tr>';
        html += '<tr><th><?php echo esc_js(__('Created', 'formflow-lite')); ?></th><td>' + escapeHtml(s.created_at) + '</td></tr>';
        if (s.completed_at) {
            html += '<tr><th><?php echo esc_js(__('Completed', 'formflow-lite')); ?></th><td>' + escapeHtml(s.completed_at) + '</td></tr>';
        }
        html += '</table>';
        html += '</div>';

        // Customer Info Section
        if (fd.first_name || fd.last_name || fd.email) {
            html += '<div class="ff-detail-section">';
            html += '<h4><?php echo esc_js(__('Customer Information', 'formflow-lite')); ?></h4>';
            html += '<table class="ff-detail-table">';
            if (fd.first_name || fd.last_name) {
                html += '<tr><th><?php echo esc_js(__('Name', 'formflow-lite')); ?></th><td>' + escapeHtml((fd.first_name || '') + ' ' + (fd.last_name || '')) + '</td></tr>';
            }
            if (fd.email) {
                html += '<tr><th><?php echo esc_js(__('Email', 'formflow-lite')); ?></th><td>' + renderEmailCell(fd.email) + '</td></tr>';
            }
            if (fd.phone) {
                html += '<tr><th><?php echo esc_js(__('Phone', 'formflow-lite')); ?></th><td>' + escapeHtml(fd.phone) + '</td></tr>';
            }
            if (fd.street || fd.city || fd.state) {
                var address = [fd.street, fd.city, fd.state, fd.zip].filter(Boolean).join(', ');
                html += '<tr><th><?php echo esc_js(__('Address', 'formflow-lite')); ?></th><td>' + escapeHtml(address) + '</td></tr>';
            }
            html += '</table>';
            html += '</div>';
        }

        // Device & Program Section
        if (fd.device_type || fd.promo_code) {
            html += '<div class="ff-detail-section">';
            html += '<h4><?php echo esc_js(__('Program Details', 'formflow-lite')); ?></h4>';
            html += '<table class="ff-detail-table">';
            if (fd.device_type) {
                var deviceLabel = fd.device_type === 'thermostat' ? '<?php echo esc_js(__('Smart Thermostat', 'formflow-lite')); ?>' : '<?php echo esc_js(__('Outdoor Switch (DCU)', 'formflow-lite')); ?>';
                html += '<tr><th><?php echo esc_js(__('Device Type', 'formflow-lite')); ?></th><td>' + deviceLabel + '</td></tr>';
            }
            if (fd.promo_code) {
                html += '<tr><th><?php echo esc_js(__('Promo Code', 'formflow-lite')); ?></th><td><code>' + escapeHtml(fd.promo_code) + '</code></td></tr>';
            }
            if (fd.confirmation_number) {
                html += '<tr><th><?php echo esc_js(__('Confirmation #', 'formflow-lite')); ?></th><td><strong>' + escapeHtml(fd.confirmation_number) + '</strong></td></tr>';
            }
            html += '</table>';
            html += '</div>';
        }

        // Schedule Section
        if (fd.schedule_date || fd.schedule_time) {
            html += '<div class="ff-detail-section">';
            html += '<h4><?php echo esc_js(__('Installation Appointment', 'formflow-lite')); ?></h4>';
            html += '<table class="ff-detail-table">';
            if (fd.schedule_date) {
                html += '<tr><th><?php echo esc_js(__('Date', 'formflow-lite')); ?></th><td>' + escapeHtml(fd.schedule_date) + '</td></tr>';
            }
            if (fd.schedule_time || fd.schedule_time_display) {
                html += '<tr><th><?php echo esc_js(__('Time', 'formflow-lite')); ?></th><td>' + escapeHtml(fd.schedule_time_display || fd.schedule_time) + '</td></tr>';
            }
            html += '</table>';
            html += '</div>';
        }

        // Technical Section
        html += '<div class="ff-detail-section">';
        html += '<h4><?php echo esc_js(__('Technical Details', 'formflow-lite')); ?></h4>';
        html += '<table class="ff-detail-table">';
        html += '<tr><th><?php echo esc_js(__('IP Address', 'formflow-lite')); ?></th><td>' + escapeHtml(s.ip_address || '—') + '</td></tr>';
        if (s.user_agent) {
            html += '<tr><th><?php echo esc_js(__('User Agent', 'formflow-lite')); ?></th><td class="ff-user-agent">' + escapeHtml(s.user_agent) + '</td></tr>';
        }
        html += '</table>';
        html += '</div>';

        // Raw Form Data (collapsible)
        html += '<div class="ff-detail-section ff-collapsible">';
        html += '<h4 class="ff-collapsible-header"><?php echo esc_js(__('Raw Form Data', 'formflow-lite')); ?> <span class="dashicons dashicons-arrow-down-alt2"></span></h4>';
        html += '<div class="ff-collapsible-content" style="display:none;">';
        html += '<pre class="ff-raw-data">' + escapeHtml(JSON.stringify(fd, null, 2)) + '</pre>';
        html += '</div>';
        html += '</div>';

        html += '</div>';

        $('#ff-submission-content').html(html);

        // Handle collapsible sections
        $('.ff-collapsible-header').off('click').on('click', function() {
            $(this).find('.dashicons').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
            $(this).next('.ff-collapsible-content').slideToggle(200);
        });
    }

    // View log details modal
    $('.ff-view-details').on('click', function(e) {
        e.preventDefault();
        var details = $(this).data('details');
        $('#ff-details-content').text(JSON.stringify(details, null, 2));
        $('#ff-details-modal').show();
    });

    // Close modals
    $('.ff-modal-close').on('click', function() {
        $(this).closest('.ff-modal').hide();
    });

    $(window).on('click', function(e) {
        if ($(e.target).hasClass('ff-modal')) {
            $(e.target).hide();
        }
    });

    // Export CSV
    $('#ff-export-csv').on('click', function() {
        var $btn = $(this);
        $btn.prop('disabled', true).find('.dashicons').addClass('ff-spin');

        var params = new URLSearchParams(window.location.search);

        $.ajax({
            url: fffl_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'fffl_export_submissions_csv',
                nonce: fffl_admin.nonce,
                instance_id: params.get('instance_id') || '',
                status: params.get('status') || '',
                search: params.get('search') || '',
                date_from: params.get('date_from') || '',
                date_to: params.get('date_to') || ''
            },
            success: function(response) {
                if (response.success) {
                    var blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' });
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = response.data.filename;
                    link.click();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Export failed.', 'formflow-lite')); ?>');
                }
                $btn.prop('disabled', false).find('.dashicons').removeClass('ff-spin');
            },
            error: function() {
                alert('<?php echo esc_js(__('Export failed.', 'formflow-lite')); ?>');
                $btn.prop('disabled', false).find('.dashicons').removeClass('ff-spin');
            }
        });
    });

    // Bulk Actions - Select All
    $('#ff-select-all').on('change', function() {
        $('.ff-row-cb').prop('checked', $(this).prop('checked'));
        updateBulkCount();
    });

    // Bulk Actions - Individual checkbox
    $(document).on('change', '.ff-row-cb', function() {
        updateBulkCount();
        var allChecked = $('.ff-row-cb:not(:checked)').length === 0;
        $('#ff-select-all').prop('checked', allChecked);
    });

    function updateBulkCount() {
        var count = $('.ff-row-cb:checked').length;
        if (count > 0) {
            $('#ff-bulk-count').show().find('.count').text(count);
        } else {
            $('#ff-bulk-count').hide();
        }
    }

    // Apply Bulk Action
    $('#ff-apply-bulk').on('click', function() {
        var action = $('#ff-bulk-action').val();
        var ids = $('.ff-row-cb:checked').map(function() {
            return $(this).val();
        }).get();

        if (!action) {
            alert('<?php echo esc_js(__('Please select a bulk action.', 'formflow-lite')); ?>');
            return;
        }

        if (ids.length === 0) {
            alert('<?php echo esc_js(__('Please select at least one item.', 'formflow-lite')); ?>');
            return;
        }

        if (action === 'delete') {
            if (!confirm('<?php echo esc_js(__('Are you sure you want to delete the selected items? This cannot be undone.', 'formflow-lite')); ?>')) {
                return;
            }
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('<?php echo esc_js(__('Processing...', 'formflow-lite')); ?>');

        var currentTab = '<?php echo esc_js($tab); ?>';
        var isSubmissions = currentTab === 'submissions';
        var ajaxAction = isSubmissions ? 'fffl_bulk_submissions_action' : 'fffl_bulk_logs_action';
        var idKey = isSubmissions ? 'submission_ids' : 'log_ids';

        var data = {
            action: ajaxAction,
            nonce: fffl_admin.nonce,
            bulk_action: action
        };
        data[idKey] = ids;

        $.ajax({
            url: fffl_admin.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message || '<?php echo esc_js(__('Action failed.', 'formflow-lite')); ?>');
                    $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'formflow-lite')); ?>');
                }
            },
            error: function() {
                alert('<?php echo esc_js(__('Action failed.', 'formflow-lite')); ?>');
                $btn.prop('disabled', false).text('<?php echo esc_js(__('Apply', 'formflow-lite')); ?>');
            }
        });
    });
});
</script>
