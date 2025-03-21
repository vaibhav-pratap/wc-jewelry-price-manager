<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Product class manages jewelry product settings in WooCommerce.
 */
class WC_JPM_Product {
    private $material;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->material = new WC_JPM_Material(); // Dependency for material data
    }

    /**
     * Adds a 'Jewelry Settings' tab to the WooCommerce product data tabs (classic editor).
     *
     * @param array $tabs Existing product data tabs.
     * @return array Modified tabs array.
     */
    public function add_jewelry_tab($tabs) {
        $tabs['jewelry'] = [
            'label' => __('Jewelry Settings', 'wc-jpm'),
            'target' => 'jewelry_product_data',
            'class' => ['show_if_simple'], // Show only for simple products
            'priority' => 25,
        ];
        return $tabs;
    }

    /**
     * Renders the content for the 'Jewelry Settings' tab in the classic editor.
     */
    public function jewelry_tab_content() {
        global $post;
        $materials = $this->material->get_materials();
        ?>
        <div id="jewelry_product_data" class="panel woocommerce_options_panel">
            <?php
            woocommerce_wp_checkbox([
                'id' => '_is_jewelry',
                'label' => __('Is Jewelry Product', 'wc-jpm'),
                'description' => __('Enable this to treat the product as jewelry and calculate its price dynamically.', 'wc-jpm'),
            ]);

            foreach ($materials as $material) {
                woocommerce_wp_text_input([
                    'id' => "_material_{$material['id']}_weight",
                    'label' => sprintf(__('%s Weight (%s)', 'wc-jpm'), ucfirst($material['name']), $material['unit']),
                    'type' => 'number',
                    'custom_attributes' => ['step' => '0.01', 'min' => '0'],
                    'desc_tip' => true,
                    'description' => __('Enter the weight of this material used in the product.', 'wc-jpm'),
                ]);

                woocommerce_wp_select([
                    'id' => "_material_{$material['id']}_purity",
                    'label' => sprintf(__('%s Purity', 'wc-jpm'), ucfirst($material['name'])),
                    'options' => array_merge(['' => __('Select Purity', 'wc-jpm')], $this->material->get_purity_options($material['id'])),
                    'desc_tip' => true,
                    'description' => __('Select the purity level of this material.', 'wc-jpm'),
                ]);
            }
            ?>
            <div id="wc-jpm-price-preview">
                <p><?php _e('Price will be calculated dynamically based on material rates and settings.', 'wc-jpm'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Saves jewelry product data from both classic and Gutenberg editors.
     *
     * @param int $post_id The product ID.
     */
    public function save_jewelry_data($post_id) {
        try {
            // Check if this is a product and user has permission
            if (!current_user_can('edit_post', $post_id) || get_post_type($post_id) !== 'product') {
                return;
            }

            // Save 'Is Jewelry' checkbox
            $is_jewelry = isset($_POST['_is_jewelry']) ? 'yes' : 'no';
            update_post_meta($post_id, '_is_jewelry', $is_jewelry);

            // Save material weights and purities
            $materials = $this->material->get_materials();
            foreach ($materials as $material) {
                $weight_key = "_material_{$material['id']}_weight";
                $purity_key = "_material_{$material['id']}_purity";

                $weight = isset($_POST[$weight_key]) ? floatval($_POST[$weight_key]) : 0;
                $purity = isset($_POST[$purity_key]) ? sanitize_text_field($_POST[$purity_key]) : '';

                update_post_meta($post_id, $weight_key, $weight);
                update_post_meta($post_id, $purity_key, $purity);
            }

            // Log the update
            $audit = new WC_JPM_Audit();
            $audit->log('Jewelry Product Updated', json_encode(['product_id' => $post_id, 'is_jewelry' => $is_jewelry]));
        } catch (Exception $e) {
            error_log("WC_JPM_Product: Failed to save jewelry data for product $post_id - " . $e->getMessage());
        }
    }

    /**
     * Checks if a product is a jewelry product.
     *
     * @param int $product_id The product ID.
     * @return bool True if jewelry product, false otherwise.
     */
    public function is_jewelry_product($product_id) {
        return get_post_meta($product_id, '_is_jewelry', true) === 'yes';
    }

    /**
     * Retrieves the materials used in a product with their weights and purities.
     *
     * @param int $product_id The product ID.
     * @return array Array of materials with weights and purities.
     */
    public function get_product_materials($product_id) {
        $materials_data = [];
        $materials = $this->material->get_materials();

        foreach ($materials as $material) {
            $weight = floatval(get_post_meta($product_id, "_material_{$material['id']}_weight", true));
            $purity = get_post_meta($product_id, "_material_{$material['id']}_purity", true);

            if ($weight > 0) {
                $materials_data[$material['id']] = [
                    'name' => $material['name'],
                    'weight' => $weight,
                    'purity' => $purity,
                    'unit' => $material['unit'],
                ];
            }
        }
        return $materials_data;
    }
}