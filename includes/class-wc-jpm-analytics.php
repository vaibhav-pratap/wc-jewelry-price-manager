<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Analytics class manages historical rate analytics for jewelry materials.
 */
class WC_JPM_Analytics {
    private $db;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB(); // Dependency for accessing rate history
    }

    /**
     * Retrieves rate trends for a specific material over a given number of days.
     *
     * @param int $material_id The ID of the material.
     * @param int $days Number of days to look back (default: 30).
     * @return array Array of rate data with timestamps.
     */
    public function get_rate_trends($material_id, $days = 30) {
        global $wpdb;
        try {
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT rate, recorded_at 
                     FROM {$this->db->rate_history_table} 
                     WHERE material_id = %d 
                     AND recorded_at > DATE_SUB(NOW(), INTERVAL %d DAY) 
                     ORDER BY recorded_at ASC",
                    $material_id,
                    $days
                ),
                ARRAY_A
            );
            return $results ?: [];
        } catch (Exception $e) {
            error_log("WC_JPM_Analytics: Failed to fetch rate trends - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets the average rate for a material over a specified period.
     *
     * @param int $material_id The ID of the material.
     * @param int $days Number of days to average (default: 30).
     * @return float Average rate or 0 if no data.
     */
    public function get_average_rate($material_id, $days = 30) {
        global $wpdb;
        try {
            $average = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT AVG(rate) 
                     FROM {$this->db->rate_history_table} 
                     WHERE material_id = %d 
                     AND recorded_at > DATE_SUB(NOW(), INTERVAL %d DAY)",
                    $material_id,
                    $days
                )
            );
            return floatval($average) ?: 0;
        } catch (Exception $e) {
            error_log("WC_JPM_Analytics: Failed to fetch average rate - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Retrieves the price impact on products based on rate changes over a period.
     *
     * @param int $days Number of days to analyze (default: 30).
     * @return array Array of product IDs with their price change percentages.
     */
    public function get_price_impact($days = 30) {
        $price = new WC_JPM_Price();
        $materials = $this->db->get_materials();
        $impacts = [];

        // Get all jewelry products
        $args = [
            'post_type' => 'product',
            'meta_query' => [
                [
                    'key' => '_is_jewelry',
                    'value' => 'yes',
                ],
            ],
            'posts_per_page' => -1,
        ];
        $products = get_posts($args);

        foreach ($products as $product) {
            $product_id = $product->ID;
            $current_price = $price->calculate_price($product_id);

            // Simulate price with oldest rate in period
            $oldest_rates = [];
            foreach ($materials as $material) {
                $trends = $this->get_rate_trends($material['id'], $days);
                $oldest_rates[$material['name']] = !empty($trends) ? $trends[0]['rate'] : $current_price;
            }

            // Temporarily override rates for simulation
            $original_rates = get_option('wc_jpm_rates', []);
            update_option('wc_jpm_rates', $oldest_rates);
            $old_price = $price->calculate_price($product_id);
            update_option('wc_jpm_rates', $original_rates); // Restore original rates

            $change = $current_price > 0 ? (($current_price - $old_price) / $old_price) * 100 : 0;
            $impacts[$product_id] = [
                'name' => get_the_title($product_id),
                'current_price' => $current_price,
                'old_price' => $old_price,
                'change_percentage' => round($change, 2),
            ];
        }

        return $impacts;
    }
}