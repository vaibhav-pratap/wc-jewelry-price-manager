/**
 * WooCommerce Jewelry Price Manager - Frontend JavaScript
 * Handles price alerts and dynamic price updates on the frontend.
 */
jQuery(document).ready(function($) {
    // Price Alert Form Submission (already in price-alert.php, but can be centralized here if needed)
    // Currently handled inline in price-alert.php for simplicity

    // Dynamic Price Update (optional feature for real-time price refresh)
    function updatePriceDisplay() {
        $('.wc-jpm-price-breakdown').each(function() {
            var $container = $(this);
            var productId = $container.closest('.product').find('input[name="product_id"]').val() || $('body').data('product-id');

            if (productId) {
                $.ajax({
                    url: wc_jpm_vars.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wc_jpm_get_price',
                        product_id: productId
                    },
                    success: function(response) {
                        if (response.success) {
                            $container.find('.wc-jpm-breakdown-table tfoot th:last').text(response.data.price);
                        }
                    },
                    error: function() {
                        console.error('Failed to update price');
                    }
                });
            }
        });
    }

    // Trigger price update on page load and every 5 minutes (optional)
    updatePriceDisplay();
    setInterval(updatePriceDisplay, 300000); // 5 minutes

    // Add to Cart validation (optional, requires server-side check too)
    $('.single_add_to_cart_button').on('click', function(e) {
        var $button = $(this);
        var productId = $button.closest('form.cart').find('input[name="add-to-cart"]').val();

        if (!productId) return;

        $.ajax({
            url: wc_jpm_vars.ajax_url,
            type: 'POST',
            data: {
                action: 'wc_jpm_check_inventory',
                product_id: productId,
                quantity: $button.closest('form.cart').find('input[name="quantity"]').val() || 1
            },
            success: function(response) {
                if (!response.success) {
                    e.preventDefault();
                    alert(response.data.message || 'Insufficient inventory. Please adjust your quantity.');
                }
            },
            error: function() {
                console.error('Inventory check failed');
            }
        });
    });
});