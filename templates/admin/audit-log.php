<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="wrap wc-jpm-admin-section">
    <h2><?php _e('Audit Log', 'wc-jpm'); ?></h2>
    <p><?php _e('View a log of all significant actions performed within the Jewelry Price Manager plugin.', 'wc-jpm'); ?></p>

    <?php
    $audit = new WC_JPM_Audit();
    $logs = $audit->get_logs(100); // Fetch the latest 100 logs
    ?>

    <?php if (!empty($logs)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'wc-jpm'); ?></th>
                    <th><?php _e('User', 'wc-jpm'); ?></th>
                    <th><?php _e('Action', 'wc-jpm'); ?></th>
                    <th><?php _e('Details', 'wc-jpm'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log) : ?>
                    <tr>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log['created_at']))); ?></td>
                        <td>
                            <?php
                            if ($log['user_id'] > 0) {
                                $user = get_userdata($log['user_id']);
                                echo $user ? esc_html($user->display_name) : __('Unknown User', 'wc-jpm');
                            } else {
                                _e('System', 'wc-jpm'); // For actions by cron or no user
                            }
                            ?>
                        </td>
                        <td><?php echo esc_html($log['action']); ?></td>
                        <td>
                            <?php
                            if ($log['details']) {
                                $details = json_decode($log['details'], true);
                                if (is_array($details)) {
                                    echo '<pre>' . esc_html(print_r($details, true)) . '</pre>';
                                } else {
                                    echo esc_html($log['details']);
                                }
                            } else {
                                _e('No details', 'wc-jpm');
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No audit logs found yet.', 'wc-jpm'); ?></p>
    <?php endif; ?>

    <form method="post" action="" style="margin-top: 20px;">
        <?php wp_nonce_field('wc_jpm_clear_logs', 'wc_jpm_clear_logs_nonce'); ?>
        <input type="submit" name="wc_jpm_clear_logs" class="button button-secondary" value="<?php _e('Clear Logs', 'wc-jpm'); ?>" onclick="return confirm('<?php _e('Are you sure you want to clear all logs?', 'wc-jpm'); ?>');">
    </form>

    <?php
    // Handle log clearing
    if (isset($_POST['wc_jpm_clear_logs']) && check_admin_referer('wc_jpm_clear_logs', 'wc_jpm_clear_logs_nonce')) {
        if ($audit->clear_logs()) {
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Audit logs cleared successfully.', 'wc-jpm') . '</p></div>';
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>' . __('Failed to clear audit logs.', 'wc-jpm') . '</p></div>';
        }
    }
    ?>
</div>