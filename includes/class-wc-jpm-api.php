<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_API class handles fetching and updating material rates from vendors.
 */
class WC_JPM_API {
    private $db;
    private $cache;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB();    // Database access for vendors and rate logging
        $this->cache = new WC_JPM_Cache(); // Caching for rates and exchange rates
    }

    /**
     * Updates material rates by fetching from all active vendors and aggregating them.
     */
    public function update_rates() {
        try {
            $vendors = $this->db->get_vendors();
            $rates = [];

            foreach ($vendors as $vendor) {
                if (!$vendor['is_active']) {
                    continue; // Skip inactive vendors
                }

                $response = wp_remote_get(
                    $vendor['api_endpoint'],
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $vendor['api_key'],
                        ],
                        'timeout' => 15,
                    ]
                );

                if (is_wp_error($response)) {
                    error_log("WC_JPM_API: Failed to fetch rates from {$vendor['name']} - " . $response->get_error_message());
                    continue;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!$body || !is_array($body)) {
                    error_log("WC_JPM_API: Invalid response from {$vendor['name']}");
                    continue;
                }

                foreach ($body as $material => $rate) {
                    $rates[$material][] = floatval($rate);
                    $material_id = $this->get_material_id($material);
                    if ($material_id) {
                        $this->db->log_rate($material_id, $rate); // Log for analytics
                    }
                }
            }

            // Aggregate rates (using average for multi-vendor support)
            $aggregated = [];
            foreach ($rates as $material => $values) {
                if (!empty($values)) {
                    $aggregated[$material] = array_sum($values) / count($values);
                }
            }

            if (empty($aggregated)) {
                error_log("WC_JPM_API: No valid rates fetched from any vendor.");
                return;
            }

            // Convert rates to store currency
            $this->convert_rates($aggregated);

            // Store and cache the aggregated rates
            $this->cache->set('rates', $aggregated, DAY_IN_SECONDS);
            update_option('wc_jpm_rates', $aggregated);
        } catch (Exception $e) {
            error_log("WC_JPM_API: Rate update failed - " . $e->getMessage());
        }
    }

    /**
     * Converts rates to the store's currency if different from vendor currency.
     *
     * @param array &$rates Reference to the rates array to modify.
     */
    private function convert_rates(&$rates) {
        $store_currency = get_woocommerce_currency();
        $vendor_currency = 'USD'; // Assuming vendor rates are in USD; make configurable if needed

        if ($store_currency !== $vendor_currency) {
            $exchange_rate = $this->get_exchange_rate($vendor_currency, $store_currency);
            if ($exchange_rate) {
                foreach ($rates as &$rate) {
                    $rate *= $exchange_rate;
                }
            } else {
                error_log("WC_JPM_API: Failed to convert rates to $store_currency - using raw rates.");
            }
        }
    }

    /**
     * Fetches the exchange rate between two currencies.
     *
     * @param string $from Source currency code (e.g., 'USD').
     * @param string $to Target currency code (e.g., 'EUR').
     * @return float Exchange rate or 1 on failure.
     */
    private function get_exchange_rate($from, $to) {
        $cached = $this->cache->get("exchange_{$from}_{$to}");
        if ($cached !== false) {
            return $cached;
        }

        try {
            $response = wp_remote_get("https://api.exchangerate-api.com/v4/latest/{$from}", ['timeout' => 10]);
            if (is_wp_error($response)) {
                error_log("WC_JPM_API: Exchange rate fetch failed - " . $response->get_error_message());
                return 1;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            $rate = isset($data['rates'][$to]) ? floatval($data['rates'][$to]) : 1;

            $this->cache->set("exchange_{$from}_{$to}", $rate, HOUR_IN_SECONDS);
            return $rate;
        } catch (Exception $e) {
            error_log("WC_JPM_API: Exchange rate fetch exception - " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Retrieves the current rates, either from cache or stored option.
     *
     * @return array Current material rates.
     */
    public function get_rates() {
        $cached_rates = $this->cache->get('rates');
        return $cached_rates !== false ? $cached_rates : get_option('wc_jpm_rates', []);
    }

    /**
     * Gets the material ID by name.
     *
     * @param string $name Material name.
     * @return int|null Material ID or null if not found.
     */
    private function get_material_id($name) {
        global $wpdb;
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$this->db->materials_table} WHERE name = %s",
                sanitize_text_field($name)
            )
        );
    }
}