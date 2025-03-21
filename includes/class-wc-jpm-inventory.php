<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Inventory class manages inventory tracking for jewelry materials.
 */
class WC_JPM_Inventory {
    private $db;

    /**
     * Constructor to initialize dependencies and hooks.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB(); // Dependency for database operations

        // Hook to deduct inventory when an order is completed
        add_action('woocommerce_order_status_completed', [$this, 'deduct_inventory']);
    }

    /**
     * Deducts inventory based on a completed order.
     *
     * @param int $order_id The ID of the completed order.
     */
    public function deduct_inventory($order_id) {
        try {
            $order = wc_get_order($order_id);
            if (!$order) {
                error_log("WC_JPM_Inventory: Invalid order ID $order_id");
                return;
            }

            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $quantity = $item->get_quantity();

                if (get_post_meta($product_id, '_is_jewelry', true) !== 'yes') {
                    continue; // Skip non-jewelry products
                }

                $materials = $this->get_product_materials($product_id);
                foreach ($materials as $material_id => $weight) {
                    $total_weight = $weight * $quantity; // Weight per unit * quantity ordered
                    $this->update_inventory($material_id, -$total_weight); // Deduct total weight
                }
            }
        } catch (Exception $e) {
            error_log("WC_JPM_Inventory: Failed to deduct inventory for order $order_id - " . $e->getMessage());
        }
    }

    /**
     * Updates inventory for a material by adding or subtracting a quantity.
     *
     * @param int $material_id The material ID.
     * @param float $quantity_change The change in quantity (positive to add, negative to deduct).
     * @return bool True on success, false on failure.
     */
    public function update_inventory($material_id, $quantity_change) {
        try {
            $current = $this->db->get_inventory($material_id);
            $current_quantity = $current ? floatval($current['quantity']) : 0;
            $new_quantity = max(0, $current_quantity + $quantity_change); // Prevent negative inventory

            $result = $this->db->update_inventory($material_id, $new_quantity);
            if ($result === false) {
                error_log("WC_JPM_Inventory: Failed to update inventory for material $material_id");
                return false;
            }

            // Log the change (optional integration with audit)
            $audit = new WC_JPM_Audit();
            $audit->log(
                'Inventory Updated',
                json_encode([
                    'material_id' => $material_id,
                    'change' => $quantity_change,
                    'new_quantity' => $new_quantity,
                ])
            );

            return true;
        } catch (Exception $e) {
            error_log("WC_JPM_Inventory: Exception updating inventory for material $material_id - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Checks if sufficient inventory exists for a product order.
     *
     * @param int $product_id The product ID.
     * @param int $quantity The quantity to check (default: 1).
     * @return bool True if enough inventory, false otherwise.
     */
    public function has_sufficient_inventory($product_id, $quantity = 1) {
        try {
            if (get_post_meta($product_id, '_is_jewelry', true) !== 'yes') {
                return true; // Non-jewelry products don’t require inventory check
            }

            $materials = $this->get_product_materials($product_id);
            foreach ($materials as $material_id => $weight) {
                $total_weight = $weight * $quantity;
                $inventory = $this->db->get_inventory($material_id);
                $available = $inventory ? floatval($inventory['quantity']) : 0;

                if ($available < $total_weight) {
                    return false; // Insufficient inventory for this material
                }
            }
            return true;
        } catch (Exception $e) {
            error_log("WC_JPM_Inventory: Exception checking inventory for product $product_id - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the materials and their weights for a product.
     *
     * @param int $product_id The product ID.
     * @return array Array of material IDs mapped to their weights.
     */
    private function get_product_materials($product_id) {
        $materials = [];
        $all_materials = $this->db->get_materials();

        foreach ($all_materials as $material) {
            $weight = floatval(get_post_meta($product_id, "_material_{$material['id']}_weight", true));
            if ($weight > 0) {
                $materials[$material['id']] = $weight;
            }
        }
        return $materials;
    }

    /**
     * Gets the current inventory level for a material.
     *
     * @param int $material_id The material ID.
     * @return float Current inventory quantity or 0 if none.
     */
    public function get_inventory($material_id) {
        $inventory = $this->db->get_inventory($material_id);
        return $inventory ? floatval($inventory['quantity']) : 0;
    }
}