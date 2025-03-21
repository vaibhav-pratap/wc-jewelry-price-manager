<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Admin class handles admin-specific functionality for WooCommerce Jewelry Price Manager.
 */
class WC_JPM_Admin {
    private $db;

    /**
     * Constructor to initialize dependencies and register hooks.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB(); // Dependency for accessing database methods
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Adds submenu pages under WooCommerce for analytics and audit logs.
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',                          // Parent slug
            __('Jewelry Analytics', 'wc-jpm'),      // Page title
            __('Analytics', 'wc-jpm'),              // Menu title
            'manage_options',                       // Capability
            'wc-jpm-analytics',                     // Menu slug
            [$this, 'analytics_page']               // Callback
        );
        add_submenu_page(
            'woocommerce',                          // Parent slug
            __('Jewelry Audit Log', 'wc-jpm'),      // Page title
            __('Audit Log', 'wc-jpm'),              // Menu title
            'manage_options',                       // Capability
            'wc-jpm-audit',                         // Menu slug
            [$this, 'audit_page']                   // Callback
        );
    }

    /**
     * Renders the analytics page.
     */
    public function analytics_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-jpm'));
        }
        include WC_JPM_PATH . 'templates/admin/analytics.php';
    }

    /**
     * Renders the audit log page.
     */
    public function audit_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.', 'wc-jpm'));
        }
        include WC_JPM_PATH . 'templates/admin/audit-log.php';
    }
}