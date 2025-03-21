<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_DB class manages database operations for the plugin.
 */
class WC_JPM_DB {
    private $vendors_table;
    private $materials_table;
    private $inventory_table;
    private $rate_history_table;
    private $alerts_table;
    private $audit_table;

    /**
     * Constructor to initialize table names.
     */
    public function __construct() {
        global $wpdb;
        $this->vendors_table = $wpdb->prefix . 'wc_jpm_vendors';
        $this->materials_table = $wpdb->prefix . 'wc_jpm_materials';
        $this->inventory_table = $wpdb->prefix . 'wc_jpm_inventory';
        $this->rate_history_table = $wpdb->prefix . 'wc_jpm_rate_history';
        $this->alerts_table = $wpdb->prefix . 'wc_jpm_alerts';
        $this->audit_table = $wpdb->prefix . 'wc_jpm_audit';
    }

    /**
     * Creates all necessary database tables on plugin activation.
     */
    public function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $tables = [
            "CREATE TABLE $this->vendors_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                api_endpoint varchar(255) NOT NULL,
                api_key varchar(255) DEFAULT '',
                is_active tinyint(1) DEFAULT 0,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE $this->materials_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                unit varchar(50) DEFAULT 'grams',
                purity_options text,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE $this->inventory_table (
                id mediumint(9) NOT NULL AUTO_INCREMENT,
                material_id mediumint(9) NOT NULL,
                quantity float NOT NULL,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (material_id) REFERENCES $this->materials_table(id) ON DELETE CASCADE
            ) $charset_collate;",
            "CREATE TABLE $this->rate_history_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                material_id mediumint(9) NOT NULL,
                rate float NOT NULL,
                recorded_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                FOREIGN KEY (material_id) REFERENCES $this->materials_table(id) ON DELETE CASCADE
            ) $charset_collate;",
            "CREATE TABLE $this->alerts_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                product_id bigint(20) NOT NULL,
                email varchar(255) NOT NULL,
                threshold_price float NOT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;",
            "CREATE TABLE $this->audit_table (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                action varchar(255) NOT NULL,
                details text,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;"
        ];

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($tables as $sql) {
            dbDelta($sql);
        }

        // Seed default data if tables are empty
        $this->seed_default_data();
    }

    /**
     * Seeds default data for vendors and materials if tables are empty.
     */
    private function seed_default_data() {
        global $wpdb;

        if ($wpdb->get_var("SELECT COUNT(*) FROM $this->vendors_table") == 0) {
            $wpdb->insert($this->vendors_table, [
                'name' => 'Free Metal API (Mock)',
                'api_endpoint' => 'https://api.example.com/metal-rates',
                'api_key' => '',
                'is_active' => 1,
            ]);
        }

        if ($wpdb->get_var("SELECT COUNT(*) FROM $this->materials_table") == 0) {
            $wpdb->insert($this->materials_table, [
                'name' => 'gold',
                'unit' => 'grams',
                'purity_options' => serialize(['18' => '18K', '22' => '22K', '24' => '24K']),
            ]);
            $wpdb->insert($this->materials_table, [
                'name' => 'silver',
                'unit' => 'grams',
                'purity_options' => serialize(['925' => '925 Sterling']),
            ]);
            $wpdb->insert($this->materials_table, [
                'name' => 'platinum',
                'unit' => 'grams',
                'purity_options' => serialize(['950' => '950 Platinum']),
            ]);
        }
    }

    // Vendor Methods
    /**
     * Inserts a new vendor into the database.
     */
    public function insert_vendor($data) {
        global $wpdb;
        return $wpdb->insert($this->vendors_table, $data);
    }

    /**
     * Retrieves all vendors.
     */
    public function get_vendors() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM $this->vendors_table", ARRAY_A);
    }

    /**
     * Gets the active vendor.
     */
    public function get_active_vendor() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM $this->vendors_table WHERE is_active = 1", ARRAY_A);
    }

    /**
     * Updates a vendor by ID.
     */
    public function update_vendor($id, $data) {
        global $wpdb;
        return $wpdb->update($this->vendors_table, $data, ['id' => $id]);
    }

    // Material Methods
    /**
     * Retrieves all materials.
     */
    public function get_materials() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM $this->materials_table", ARRAY_A);
    }

    /**
     * Inserts a new material.
     */
    public function insert_material($data) {
        global $wpdb;
        return $wpdb->insert($this->materials_table, $data);
    }

    // Inventory Methods
    /**
     * Updates or inserts inventory for a material.
     */
    public function update_inventory($material_id, $quantity) {
        global $wpdb;
        $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->inventory_table WHERE material_id = %d", $material_id));
        if ($existing) {
            return $wpdb->update(
                $this->inventory_table,
                ['quantity' => $quantity, 'updated_at' => current_time('mysql')],
                ['material_id' => $material_id]
            );
        } else {
            return $wpdb->insert($this->inventory_table, [
                'material_id' => $material_id,
                'quantity' => $quantity,
                'updated_at' => current_time('mysql'),
            ]);
        }
    }

    /**
     * Gets inventory for a material.
     */
    public function get_inventory($material_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $this->inventory_table WHERE material_id = %d", $material_id), ARRAY_A);
    }

    // Rate History Methods
    /**
     * Logs a material rate.
     */
    public function log_rate($material_id, $rate) {
        global $wpdb;
        return $wpdb->insert($this->rate_history_table, [
            'material_id' => $material_id,
            'rate' => $rate,
            'recorded_at' => current_time('mysql'),
        ]);
    }

    // Alert Methods
    /**
     * Adds a price alert subscription.
     */
    public function add_alert($product_id, $email, $threshold) {
        global $wpdb;
        return $wpdb->insert($this->alerts_table, [
            'product_id' => $product_id,
            'email' => $email,
            'threshold_price' => $threshold,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Gets all alerts for a product.
     */
    public function get_alerts($product_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare("SELECT * FROM $this->alerts_table WHERE product_id = %d", $product_id), ARRAY_A);
    }

    // Audit Methods
    /**
     * Logs an audit action.
     */
    public function log_audit($action, $details) {
        global $wpdb;
        return $wpdb->insert($this->audit_table, [
            'user_id' => get_current_user_id(),
            'action' => $action,
            'details' => $details,
            'created_at' => current_time('mysql'),
        ]);
    }

    /**
     * Retrieves recent audit logs.
     */
    public function get_audit_logs($limit = 100) {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM $this->audit_table ORDER BY created_at DESC LIMIT " . intval($limit), ARRAY_A);
    }
}