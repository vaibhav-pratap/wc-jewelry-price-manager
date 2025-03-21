<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Price class manages price calculations and display for jewelry products.
 */
class WC_JPM_Price {
    private $db;
    private $material;
    private $api;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB();         // Database operations
        $this->material = new WC_JPM_Material(); // Material and purity handling
        $this->api = new WC_JPM_API();       // Rate fetching
    }

    /**
     * Calculates the total price for a jewelry product.
     *
     * @param int $product_id The product ID.
     * @return float Calculated price or 0 if not calculable.
     */
    public function calculate_price($product_id) {
        try {
            if (get_post_meta($product_id, '_is_jewelry', true) !== 'yes') {
                $product = wc_get_product($product_id);
                return $product ? floatval($product->get_price()) : 0; // Non-jewelry uses WooCommerce price
            }

            $rates = $this->api->get_rates();
            $materials = $this->material->get_materials();
            $total_price = 0;

            foreach ($materials as $material) {
                $weight = floatval(get_post_meta($product_id, "_material_{$material['id']}_weight", true));
                $purity = get_post_meta($product_id, "_material_{$material['id']}_purity", true);

                if ($weight > 0 && isset($rates[$material['name']])) {
                    $base_rate = $rates[$material['name']];
                    $adjusted_rate = $this->material->calculate_purity_rate($material['id'], $purity, $base_rate);
                    $material_cost = $adjusted_rate * $weight;
                    $total_price += $material_cost;
                }
            }

            // Apply pricing rules (e.g., markup or discount)
            $total_price = $this->apply_pricing_rules($total_price, $product_id);

            // Add fixed labor cost or other fees (configurable via settings)
            $labor_cost = floatval(get_option('wc_jpm_labor_cost', 0));
            $total_price += $labor_cost;

            return round($total_price, wc_get_price_decimals());
        } catch (Exception $e) {
            error_log("WC_JPM_Price: Failed to calculate price for product $product_id - " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Applies pricing rules to the calculated price.
     *
     * @param float $price Base price before rules.
     * @param int $product_id The product ID.
     * @return float Adjusted price after rules.
     */
    private function apply_pricing_rules($price, $product_id) {
        $rules = get_option('wc_jpm_pricing_rules', []);
        if (empty($rules)) {
            return $price; // No rules, return base price
        }

        $adjusted_price = $price;
        foreach ($rules as $rule) {
            if ($rule['condition'] === 'weight') {
                $total_weight = $this->get_total_weight($product_id);
                if ($total_weight >= floatval($rule['threshold'])) {
                    $adjusted_price *= (1 - (floatval($rule['discount']) / 100));
                }
            } elseif ($rule['condition'] === 'order_total') {
                $cart_total = WC()->cart ? WC()->cart->get_subtotal() : 0;
                if ($cart_total >= floatval($rule['threshold'])) {
                    $adjusted_price *= (1 - (floatval($rule['discount']) / 100));
                }
            }
        }
        return $adjusted_price;
    }

    /**
     * Calculates the total weight of materials in a product.
     *
     * @param int $product_id The product ID.
     * @return float Total weight in grams.
     */
    private function get_total_weight($product_id) {
        $materials = $this->material->get_materials();
        $total_weight = 0;

        foreach ($materials as $material) {
            $weight = floatval(get_post_meta($product_id, "_material_{$material['id']}_weight", true));
            $total_weight += $weight;
        }
        return $total_weight;
    }

    /**
     * Filters the WooCommerce price HTML to use calculated price for jewelry.
     *
     * @param string $price_html Original price HTML.
     * @param WC_Product $product Product object.
     * @return string Modified price HTML.
     */
    public function custom_price_html($price_html, $product) {
        if (get_post_meta($product->get_id(), '_is_jewelry', true) !== 'yes') {
            return $price_html; // Non-jewelry uses default WooCommerce price
        }

        $calculated_price = $this->calculate_price($product->get_id());
        if ($calculated_price > 0) {
            return wc_price($calculated_price);
        }
        return $price_html; // Fallback to original if calculation fails
    }

    /**
     * Displays the price breakdown on the single product page.
     */
    public function display_price_breakdown() {
        global $product;
        if (get_post_meta($product->get_id(), '_is_jewelry', true) !== 'yes') {
            return; // Only for jewelry products
        }

        $rates = $this->api->get_rates();
        $materials = $this->material->get_materials();
        $breakdown = [];
        $total_material_cost = 0;

        foreach ($materials as $material) {
            $weight = floatval(get_post_meta($product->get_id(), "_material_{$material['id']}_weight", true));
            $purity = get_post_meta($product->get_id(), "_material_{$material['id']}_purity", true);

            if ($weight > 0 && isset($rates[$material['name']])) {
                $base_rate = $rates[$material['name']];
                $adjusted_rate = $this->material->calculate_purity_rate($material['id'], $purity, $base_rate);
                $cost = $adjusted_rate * $weight;
                $total_material_cost += $cost;

                $breakdown[] = [
                    'material' => ucfirst($material['name']),
                    'weight' => $weight,
                    'unit' => $material['unit'],
                    'purity' => $purity ? $material['purity_options'][$purity] : 'N/A',
                    'cost' => $cost,
                ];
            }
        }

        $labor_cost = floatval(get_option('wc_jpm_labor_cost', 0));
        $final_price = $this->calculate_price($product->get_id());

        include WC_JPM_PATH . 'templates/frontend/price-breakdown.php';
    }
}