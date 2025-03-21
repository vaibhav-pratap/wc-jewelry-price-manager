<?php
/*
Plugin Name: WooCommerce Jewelry Price Manager
Description: Advanced jewelry price management with dynamic vendors, analytics, and customization.
Version: 1.0.0
Author: Vaibhav Singh
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 8.0
Text Domain: wc-jpm
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define constants
define('WC_JPM_VERSION', '1.0.0');
define('WC_JPM_PATH', plugin_dir_path(__FILE__));
define('WC_JPM_URL', plugin_dir_url(__FILE__));

// Include all class files
require_once WC_JPM_PATH . 'includes/class-wc-jpm-db.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-settings.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-material.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-price.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-product.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-admin.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-api.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-cache.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-inventory.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-analytics.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-alerts.php';
require_once WC_JPM_PATH . 'includes/class-wc-jpm-audit.php';

/**
 * Main plugin class for WooCommerce Jewelry Price Manager.
 */
class WC_Jewelry_Price_Manager {
    public $db;
    public $settings;
    public $material;
    public $price;
    public $product;
    public $admin;
    public $api;
    public $cache;
    public $inventory;
    public $analytics;
    public $alerts;
    public $audit;

    /**
     * Constructor to initialize components and register hooks.
     */
    public function __construct() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', [$this, 'no_woocommerce_notice']);
            return;
        }

        // Initialize components
        $this->db = new WC_JPM_DB();
        $this->settings = new WC_JPM_Settings();
        $this->material = new WC_JPM_Material();
        $this->price = new WC_JPM_Price();
        $this->product = new WC_JPM_Product();
        $this->admin = new WC_JPM_Admin();
        $this->api = new WC_JPM_API();
        $this->cache = new WC_JPM_Cache();
        $this->inventory = new WC_JPM_Inventory();
        $this->analytics = new WC_JPM_Analytics();
        $this->alerts = new WC_JPM_Alerts();
        $this->audit = new WC_JPM_Audit();

        // Register hooks
        add_action('init', [$this, 'load_textdomain']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);
        
        // Classic WooCommerce hooks
        add_filter('woocommerce_product_data_tabs', [$this->product, 'add_jewelry_tab']);
        add_action('woocommerce_product_data_panels', [$this->product, 'jewelry_tab_content']);
        add_action('woocommerce_process_product_meta', [$this->product, 'save_jewelry_data']);

        // Gutenberg hooks
        add_action('admin_init', [$this, 'register_gutenberg_fields']);
        
        // Frontend hooks
        add_filter('woocommerce_get_price_html', [$this->price, 'custom_price_html'], 10, 2);
        add_action('woocommerce_single_product_summary', [$this->price, 'display_price_breakdown'], 20);
        add_action('woocommerce_single_product_summary', [$this->alerts, 'display_alert_form'], 30);
        add_action('wc_jpm_update_rates', [$this->api, 'update_rates']);
        add_action('woocommerce_order_status_completed', [$this->inventory, 'deduct_inventory']);
    }

    /**
     * Loads the plugin's text domain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain('wc-jpm', false, basename(dirname(__FILE__)) . '/languages');
    }

    /**
     * Enqueues frontend scripts and styles.
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_script('wc-jpm-frontend', WC_JPM_URL . 'assets/js/frontend.js', ['jquery'], WC_JPM_VERSION, true);
        wp_enqueue_script('chart-js', WC_JPM_URL . 'assets/vendor/chart.min.js', [], '4.4.0', true);
        wp_localize_script('wc-jpm-frontend', 'wc_jpm_vars', ['ajax_url' => admin_url('admin-ajax.php')]);
    }

    /**
     * Enqueues admin scripts and styles, including Gutenberg support.
     */
    public function enqueue_admin_scripts($hook) {
        if (in_array($hook, ['post.php', 'post-new.php']) && get_post_type() === 'product') {
            wp_enqueue_script('wc-jpm-admin', WC_JPM_URL . 'assets/js/admin.js', ['wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-edit-post'], WC_JPM_VERSION, true);
            wp_enqueue_style('wc-jpm-admin', WC_JPM_URL . 'assets/css/admin.css', [], WC_JPM_VERSION);
            wp_localize_script('wc-jpm-admin', 'wc_jpm_admin_vars', ['materials' => $this->material->get_materials()]);
        }
    }

    /**
     * Registers Gutenberg fields for the product editor.
     */
    public function register_gutenberg_fields() {
        if (function_exists('register_block_type')) {
            wp_register_script(
                'wc-jpm-gutenberg',
                WC_JPM_URL . 'assets/js/admin.js',
                ['wp-blocks', 'wp-element', 'wp-components', 'wp-data', 'wp-edit-post'],
                WC_JPM_VERSION,
                true
            );
            wp_localize_script('wc-jpm-gutenberg', 'wc_jpm_admin_vars', ['materials' => $this->material->get_materials()]);
            
            register_block_type('wc-jpm/jewelry-settings', [
                'editor_script' => 'wc-jpm-gutenberg',
            ]);
        }
    }

    /**
     * Displays an admin notice if WooCommerce is not active.
     */
    public function no_woocommerce_notice() {
        echo '<div class="error"><p>' . __('WooCommerce Jewelry Price Manager requires WooCommerce to be installed and active.', 'wc-jpm') . '</p></div>';
    }
}

/**
 * Activation hook to set up database tables and cron jobs.
 */
function wc_jpm_activate() {
    $db = new WC_JPM_DB();
    $db->create_tables();
    if (!wp_next_scheduled('wc_jpm_update_rates')) {
        wp_schedule_event(time(), 'daily', 'wc_jpm_update_rates');
    }
}
register_activation_hook(__FILE__, 'wc_jpm_activate');

/**
 * Deactivation hook to clear scheduled events.
 */
function wc_jpm_deactivate() {
    wp_clear_scheduled_hook('wc_jpm_update_rates');
}
register_deactivation_hook(__FILE__, 'wc_jpm_deactivate');

/**
 * Initialize the plugin.
 */
function wc_jpm_init() {
    new WC_Jewelry_Price_Manager();
}
add_action('plugins_loaded', 'wc_jpm_init');