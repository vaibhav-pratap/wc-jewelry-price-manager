<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Settings class manages plugin settings integration with WooCommerce.
 */
class WC_JPM_Settings {
    private $db;
    private $audit;

    /**
     * Constructor to initialize dependencies and hooks.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB();     // Database operations
        $this->audit = new WC_JPM_Audit(); // Audit logging

        // Add settings tab to WooCommerce
        add_filter('woocommerce_settings_tabs_array', [$this, 'add_settings_tab'], 50);
        add_action('woocommerce_settings_tabs_wc_jpm_settings', [$this, 'settings_tab_content']);
        add_action('woocommerce_update_options_wc_jpm_settings', [$this, 'save_settings']);

        // Add sections for vendor settings and pricing rules
        add_action('woocommerce_admin_field_vendor_settings', [$this, 'render_vendor_settings']);
        add_action('woocommerce_admin_field_pricing_rules', [$this, 'render_pricing_rules']);
    }

    /**
     * Adds a custom settings tab to WooCommerce settings.
     *
     * @param array $tabs Existing settings tabs.
     * @return array Modified tabs array.
     */
    public function add_settings_tab($tabs) {
        $tabs['wc_jpm_settings'] = __('Jewelry Price Manager', 'wc-jpm');
        return $tabs;
    }

    /**
     * Renders the content for the Jewelry Price Manager settings tab.
     */
    public function settings_tab_content() {
        woocommerce_admin_fields($this->get_settings());
    }

    /**
     * Defines the settings fields for the tab.
     *
     * @return array Settings fields array.
     */
    private function get_settings() {
        return [
            'section_title' => [
                'name' => __('Jewelry Price Manager Settings', 'wc-jpm'),
                'type' => 'title',
                'desc' => __('Configure settings for dynamic jewelry pricing.', 'wc-jpm'),
                'id' => 'wc_jpm_settings_section_title',
            ],
            'labor_cost' => [
                'name' => __('Labor Cost', 'wc-jpm'),
                'type' => 'number',
                'desc' => __('Fixed labor cost added to each jewelry product (in store currency).', 'wc-jpm'),
                'id' => 'wc_jpm_labor_cost',
                'default' => 0,
                'custom_attributes' => ['step' => '0.01', 'min' => '0'],
            ],
            'vendor_settings' => [
                'name' => __('Vendor Settings', 'wc-jpm'),
                'type' => 'vendor_settings',
                'desc' => __('Manage vendors for fetching material rates.', 'wc-jpm'),
                'id' => 'wc_jpm_vendor_settings',
            ],
            'pricing_rules' => [
                'name' => __('Pricing Rules', 'wc-jpm'),
                'type' => 'pricing_rules',
                'desc' => __('Define pricing rules based on conditions like weight or order total.', 'wc-jpm'),
                'id' => 'wc_jpm_pricing_rules',
            ],
            'section_end' => [
                'type' => 'sectionend',
                'id' => 'wc_jpm_settings_section_end',
            ],
        ];
    }

    /**
     * Renders the vendor settings section.
     */
    public function render_vendor_settings() {
        include WC_JPM_PATH . 'templates/admin/vendor-settings.php';
    }

    /**
     * Renders the pricing rules section.
     */
    public function render_pricing_rules() {
        include WC_JPM_PATH . 'templates/admin/pricing-rules.php';
    }

    /**
     * Saves settings when updated in WooCommerce.
     */
    public function save_settings() {
        try {
            woocommerce_update_options($this->get_settings());

            // Handle vendor settings form submission
            if (isset($_POST['wc_jpm_add_vendor'])) {
                $vendor_data = [
                    'name' => sanitize_text_field($_POST['vendor_name']),
                    'api_endpoint' => esc_url_raw($_POST['vendor_api_endpoint']),
                    'api_key' => sanitize_text_field($_POST['vendor_api_key']),
                    'is_active' => 1, // Default to active for new vendors
                ];
                $this->db->insert_vendor($vendor_data);
                $this->audit->log('Vendor Added', json_encode($vendor_data));
            }

            // Handle pricing rules form submission
            if (isset($_POST['wc_jpm_add_rule'])) {
                $rule = [
                    'condition' => sanitize_text_field($_POST['rule_condition']),
                    'threshold' => floatval($_POST['rule_threshold']),
                    'discount' => floatval($_POST['rule_discount']),
                ];
                $rules = get_option('wc_jpm_pricing_rules', []);
                $rules[] = $rule;
                update_option('wc_jpm_pricing_rules', $rules);
                $this->audit->log('Pricing Rule Added', json_encode($rule));
            }

            // Log general settings update
            $this->audit->log('Settings Updated', json_encode($_POST));
        } catch (Exception $e) {
            error_log("WC_JPM_Settings: Failed to save settings - " . $e->getMessage());
        }
    }
}