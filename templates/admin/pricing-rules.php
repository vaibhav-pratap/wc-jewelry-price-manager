<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="wc-jpm-admin-section">
    <h3><?php _e('Pricing Rules', 'wc-jpm'); ?></h3>
    <p><?php _e('Define rules to apply discounts based on conditions such as total material weight or order total.', 'wc-jpm'); ?></p>

    <?php
    $rules = get_option('wc_jpm_pricing_rules', []);
    ?>

    <?php if (!empty($rules)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Condition', 'wc-jpm'); ?></th>
                    <th><?php _e('Threshold', 'wc-jpm'); ?></th>
                    <th><?php _e('Discount (%)', 'wc-jpm'); ?></th>
                    <th><?php _e('Actions', 'wc-jpm'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rules as $index => $rule) : ?>
                    <tr>
                        <td><?php echo esc_html(ucfirst(str_replace('_', ' ', $rule['condition']))); ?></td>
                        <td><?php echo esc_html($rule['threshold']); ?></td>
                        <td><?php echo esc_html($rule['discount']); ?></td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field('wc_jpm_delete_rule_' . $index, 'wc_jpm_delete_rule_nonce'); ?>
                                <input type="hidden" name="rule_index" value="<?php echo esc_attr($index); ?>">
                                <input type="submit" name="wc_jpm_delete_rule" class="button button-secondary" value="<?php _e('Delete', 'wc-jpm'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete this rule?', 'wc-jpm'); ?>');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No pricing rules defined yet.', 'wc-jpm'); ?></p>
    <?php endif; ?>

    <h4><?php _e('Add New Pricing Rule', 'wc-jpm'); ?></h4>
    <form method="post" action="">
        <?php wp_nonce_field('wc_jpm_add_rule', 'wc_jpm_add_rule_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="rule_condition"><?php _e('Condition', 'wc-jpm'); ?></label></th>
                <td>
                    <select name="rule_condition" id="rule_condition" required>
                        <option value="weight"><?php _e('Total Material Weight (grams)', 'wc-jpm'); ?></option>
                        <option value="order_total"><?php _e('Order Total', 'wc-jpm'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="rule_threshold"><?php _e('Threshold', 'wc-jpm'); ?></label></th>
                <td>
                    <input type="number" name="rule_threshold" id="rule_threshold" step="0.01" min="0" required>
                    <p class="description"><?php _e('The minimum value to trigger the discount (e.g., 10 grams or $100).', 'wc-jpm'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="rule_discount"><?php _e('Discount (%)', 'wc-jpm'); ?></label></th>
                <td>
                    <input type="number" name="rule_discount" id="rule_discount" step="0.01" min="0" max="100" required>
                    <p class="description"><?php _e('Percentage discount to apply (e.g., 10 for 10%).', 'wc-jpm'); ?></p>
                </td>
            </tr>
        </table>
        <input type="submit" name="wc_jpm_add_rule" class="button button-primary" value="<?php _e('Add Rule', 'wc-jpm'); ?>">
    </form>

    <?php
    // Handle rule deletion
    if (isset($_POST['wc_jpm_delete_rule']) && check_admin_referer('wc_jpm_delete_rule_' . $_POST['rule_index'], 'wc_jpm_delete_rule_nonce')) {
        $index = intval($_POST['rule_index']);
        if (isset($rules[$index])) {
            unset($rules[$index]);
            $rules = array_values($rules); // Reindex array
            update_option('wc_jpm_pricing_rules', $rules);
            $audit = new WC_JPM_Audit();
            $audit->log('Pricing Rule Deleted', json_encode(['index' => $index]));
            echo '<div class="notice notice-success is-dismissible"><p>' . __('Pricing rule deleted successfully.', 'wc-jpm') . '</p></div>';
        }
    }
    ?>
</div>