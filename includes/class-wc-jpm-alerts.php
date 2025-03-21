<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Alerts class manages customer price alerts for jewelry products.
 */
class WC_JPM_Alerts {
    private $db;
    private $price;

    /**
     * Constructor to initialize dependencies and register hooks.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB();    // Database access for storing alerts
        $this->price = new WC_JPM_Price(); // Price calculation for threshold checks

        // Register AJAX handler for alert subscriptions
        add_action('wp_ajax_wc_jpm_subscribe_alert', [$this, 'subscribe_alert']);
        add_action('wp_ajax_nopriv_wc_jpm_subscribe_alert', [$this, 'subscribe_alert']); // Allow non-logged-in users
        
        // Hook to display the alert form on single product pages
        add_action('woocommerce_single_product_summary', [$this, 'display_alert_form'], 30);

        // Cron job to check price changes and send alerts
        add_action('wc_jpm_check_price_alerts', [$this, 'check_price_alerts']);
        if (!wp_next_scheduled('wc_jpm_check_price_alerts')) {
            wp_schedule_event(time(), 'hourly', 'wc_jpm_check_price_alerts');
        }
    }

    /**
     * Displays the price alert subscription form on the frontend.
     */
    public function display_alert_form() {
        global $product;
        if (get_post_meta($product->get_id(), '_is_jewelry', true) !== 'yes') {
            return; // Only show for jewelry products
        }
        include WC_JPM_PATH . 'templates/frontend/price-alert.php';
    }

    /**
     * Handles AJAX subscription requests for price alerts.
     */
    public function subscribe_alert() {
        try {
            $product_id = intval($_POST['product_id']);
            $email = sanitize_email($_POST['email']);

            if (!$product_id || !$email) {
                wp_send_json_error(__('Invalid product ID or email.', 'wc-jpm'));
            }

            $current_price = $this->price->calculate_price($product_id);
            if ($current_price === 0) {
                wp_send_json_error(__('Unable to calculate current price.', 'wc-jpm'));
            }

            // Set threshold to 90% of current price (10% drop)
            $threshold_price = $current_price * 0.9;

            $this->db->add_alert($product_id, $email, $threshold_price);
            wp_send_json_success(__('Subscribed to price alerts successfully!', 'wc-jpm'));
        } catch (Exception $e) {
            error_log("WC_JPM_Alerts: Subscription failed - " . $e->getMessage());
            wp_send_json_error(__('An error occurred while subscribing.', 'wc-jpm'));
        }
    }

    /**
     * Checks all active alerts and sends notifications if prices drop below thresholds.
     */
    public function check_price_alerts() {
        global $wpdb;
        $alerts = $wpdb->get_results("SELECT * FROM {$this->db->alerts_table}", ARRAY_A);

        foreach ($alerts as $alert) {
            $current_price = $this->price->calculate_price($alert['product_id']);
            if ($current_price && $current_price < $alert['threshold_price']) {
                $this->send_alert_email($alert['email'], $alert['product_id'], $current_price);
                $wpdb->delete($this->db->alerts_table, ['id' => $alert['id']]); // Remove after sending
            }
        }
    }

    /**
     * Sends an email notification for a price drop.
     *
     * @param string $email Recipient email.
     * @param int $product_id Product ID.
     * @param float $current_price New price.
     */
    private function send_alert_email($email, $product_id, $current_price) {
        $product = wc_get_product($product_id);
        $subject = sprintf(__('Price Drop Alert for %s', 'wc-jpm'), $product->get_name());
        $message = sprintf(
            __('The price of %s has dropped to %s! Visit %s to purchase now.', 'wc-jpm'),
            $product->get_name(),
            wc_price($current_price),
            get_permalink($product_id)
        );

        wp_mail($email, $subject, $message, ['Content-Type: text/html; charset=UTF-8']);
    }
}