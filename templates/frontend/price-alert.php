<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
global $product;
?>

<div class="wc-jpm-price-alert">
    <h4><?php _e('Price Alert', 'wc-jpm'); ?></h4>
    <p><?php _e('Get notified when the price drops!', 'wc-jpm'); ?></p>
    
    <form id="wc-jpm-price-alert-form" method="post" action="">
        <input type="hidden" name="product_id" value="<?php echo esc_attr($product->get_id()); ?>">
        <label for="wc-jpm-alert-email"><?php _e('Your Email:', 'wc-jpm'); ?></label>
        <input type="email" id="wc-jpm-alert-email" name="email" required>
        <button type="submit" class="button"><?php _e('Subscribe', 'wc-jpm'); ?></button>
    </form>
    
    <div id="wc-jpm-alert-response" style="margin-top: 10px;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    $('#wc-jpm-price-alert-form').on('submit', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $response = $('#wc-jpm-alert-response');

        $.ajax({
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            type: 'POST',
            data: {
                action: 'wc_jpm_subscribe_alert',
                product_id: $form.find('input[name="product_id"]').val(),
                email: $form.find('input[name="email"]').val()
            },
            success: function(response) {
                if (response.success) {
                    $response.html('<span style="color: green;">' + response.data + '</span>');
                    $form[0].reset();
                } else {
                    $response.html('<span style="color: red;">' + response.data + '</span>');
                }
            },
            error: function() {
                $response.html('<span style="color: red;"><?php _e('An error occurred. Please try again.', 'wc-jpm'); ?></span>');
            }
        });
    });
});
</script>