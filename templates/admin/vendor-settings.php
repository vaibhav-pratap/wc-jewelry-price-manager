<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
?>

<div class="wc-jpm-admin-section">
    <h3><?php _e('Vendor Settings', 'wc-jpm'); ?></h3>
    <p><?php _e('Manage vendors for fetching material rates. Only one vendor can be active at a time.', 'wc-jpm'); ?></p>

    <?php
    $db = new WC_JPM_DB();
    $vendors = $db->get_vendors();
    ?>

    <?php if (!empty($vendors)) : ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Name', 'wc-jpm'); ?></th>
                    <th><?php _e('API Endpoint', 'wc-jpm'); ?></th>
                    <th><?php _e('API Key', 'wc-jpm'); ?></th>
                    <th><?php _e('Status', 'wc-jpm'); ?></th>
                    <th><?php _e('Actions', 'wc-jpm'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor) : ?>
                    <tr>
                        <td><?php echo esc_html($vendor['name']); ?></td>
                        <td><?php echo esc_html($vendor['api_endpoint']); ?></td>
                        <td><?php echo esc_html(substr($vendor['api_key'], 0, 4) . str_repeat('*', strlen($vendor['api_key']) - 4)); ?></td>
                        <td><?php echo $vendor['is_active'] ? __('Active', 'wc-jpm') : __('Inactive', 'wc-jpm'); ?></td>
                        <td>
                            <form method="post" action="" style="display:inline;">
                                <?php wp_nonce_field('wc_jpm_toggle_vendor_' . $vendor['id'], 'wc_jpm_toggle_vendor_nonce'); ?>
                                <input type="hidden" name="vendor_id" value="<?php echo esc_attr($vendor['id']); ?>">
                                <input type="submit" name="wc_jpm_toggle_vendor" class="button button-secondary" value="<?php echo $vendor['is_active'] ? __('Deactivate', 'wc-jpm') : __('Activate', 'wc-jpm'); ?>">
                            </form>
                            <form method="post" action="" style="display:inline; margin-left: 5px;">
                                <?php wp_nonce_field('wc_jpm_delete_vendor_' . $vendor['id'], 'wc_jpm_delete_vendor_nonce'); ?>
                                <input type="hidden" name="vendor_id" value="<?php echo esc_attr($vendor['id']); ?>">
                                <input type="submit" name="wc_jpm_delete_vendor" class="button button-secondary" value="<?php _e('Delete', 'wc-jpm'); ?>" onclick="return confirm('<?php _e('Are you sure you want to delete this vendor?', 'wc-jpm'); ?>');">
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else : ?>
        <p><?php _e('No vendors defined yet.', 'wc-jpm'); ?></p>
    <?php endif; ?>

    <h4><?php _e('Add New Vendor', 'wc-jpm'); ?></h4>
    <form method="post" action="">
        <?php wp_nonce_field('wc_jpm_add_vendor', 'wc_jpm_add_vendor_nonce'); ?>
        <table class="form-table">
            <tr>
                <th><label for="vendor_name"><?php _e('Vendor Name', 'wc-jpm'); ?></label></th>
                <td>
                    <input type="text" name="vendor_name" id="vendor_name" required>
                    <p class="description"><?php _e('A unique name for this vendor.', 'wc-jpm'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="vendor_api_endpoint"><?php _e('API Endpoint', 'wc-jpm'); ?></label></th>
                <td>
                    <input type="url" name="vendor_api_endpoint" id="vendor_api_endpoint" required>
                    <p class="description"><?php _e('The URL to fetch material rates from this vendor.', 'wc-jpm'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="vendor_api_key"><?php _e('API Key', 'wc-jpm'); ?></label></th>
                <td>
                    <input type="text" name="vendor_api_key" id="vendor_api_key">
                    <p class="description"><?php _e('Optional API key for authentication (if required by the vendor).', 'wc-jpm'); ?></p>
                </td>
            </tr>
        </table>
        <input type="submit" name="wc_jpm_add_vendor" class="button button-primary" value="<?php _e('Add Vendor', 'wc-jpm'); ?>">
    </form>

    <?php
    // Handle vendor actions
    global $wpdb;

    // Add Vendor (handled in WC_JPM_Settings::save_settings())
    // Toggle Vendor Active Status
    if (isset($_POST['wc_jpm_toggle_vendor']) && check_admin_referer('wc_jpm_toggle_vendor_' . $_POST['vendor_id'], 'wc_jpm_toggle_vendor_nonce')) {
        $vendor_id = intval($_POST['vendor_id']);
        $vendor = $db->get_vendors(); // Fetch all vendors to find the one
        foreach ($vendor as $v) {
            if ($v['id'] == $vendor_id) {
                $new_status = $v['is_active'] ? 0 : 1;
                if ($new_status == 1) {
                    // Deactivate all other vendors
                    $wpdb->update($db->vendors_table, ['is_active' => 0], ['is_active' => 1]);
                }
                $db->update_vendor($vendor_id, ['is_active' => $new_status]);
                $audit = new WC_JPM_Audit();
                $audit->log('Vendor Status Toggled', json_encode(['vendor_id' => $vendor_id, 'is_active' => $new_status]));
                echo '<div class="notice notice-success is-dismissible"><p>' . __('Vendor status updated successfully.', 'wc-jpm') . '</p></div>';
                break;
            }
        }
    }

    // Delete Vendor
    if (isset($_POST['wc_jpm_delete_vendor']) && check_admin_referer('wc_jpm_delete_vendor_' . $_POST['vendor_id'], 'wc_jpm_delete_vendor_nonce')) {
        $vendor_id = intval($_POST['vendor_id']);
        $wpdb->delete($db->vendors_table, ['id' => $vendor_id]);
        $audit = new WC_JPM_Audit();
        $audit->log('Vendor Deleted', json_encode(['vendor_id' => $vendor_id]));
        echo '<div class="notice notice-success is-dismissible"><p>' . __('Vendor deleted successfully.', 'wc-jpm') . '</p></div>';
    }
    ?>
</div>