<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Material class manages material-related data and operations.
 */
class WC_JPM_Material {
    private $db;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB(); // Dependency for database operations
    }

    /**
     * Retrieves all materials from the database.
     *
     * @return array Array of material data.
     */
    public function get_materials() {
        try {
            $materials = $this->db->get_materials();
            foreach ($materials as &$material) {
                $material['purity_options'] = maybe_unserialize($material['purity_options']) ?: [];
            }
            return $materials;
        } catch (Exception $e) {
            error_log("WC_JPM_Material: Failed to retrieve materials - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Gets a specific material by ID.
     *
     * @param int $material_id The material ID.
     * @return array|null Material data or null if not found.
     */
    public function get_material($material_id) {
        try {
            global $wpdb;
            $material = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$this->db->materials_table} WHERE id = %d",
                    $material_id
                ),
                ARRAY_A
            );
            if ($material) {
                $material['purity_options'] = maybe_unserialize($material['purity_options']) ?: [];
            }
            return $material;
        } catch (Exception $e) {
            error_log("WC_JPM_Material: Failed to retrieve material $material_id - " . $e->getMessage());
            return null;
        }
    }

    /**
     * Adds a new material to the database.
     *
     * @param string $name Material name.
     * @param string $unit Measurement unit (e.g., 'grams').
     * @param array $purity_options Array of purity options (e.g., ['18' => '18K']).
     * @return int|bool Inserted ID on success, false on failure.
     */
    public function add_material($name, $unit = 'grams', $purity_options = []) {
        try {
            $data = [
                'name' => sanitize_text_field($name),
                'unit' => sanitize_text_field($unit),
                'purity_options' => serialize($purity_options),
            ];
            $result = $this->db->insert_material($data);
            if ($result === false) {
                error_log("WC_JPM_Material: Failed to add material '$name'");
                return false;
            }

            // Log the action
            $audit = new WC_JPM_Audit();
            $audit->log('Material Added', json_encode($data));

            return $wpdb->insert_id;
        } catch (Exception $e) {
            error_log("WC_JPM_Material: Exception adding material '$name' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves purity options for a material.
     *
     * @param int $material_id The material ID.
     * @return array Purity options array.
     */
    public function get_purity_options($material_id) {
        $material = $this->get_material($material_id);
        return $material ? $material['purity_options'] : [];
    }

    /**
     * Calculates the effective rate for a material based on purity.
     *
     * @param int $material_id The material ID.
     * @param string $purity The selected purity (e.g., '18' for 18K).
     * @param float $base_rate The base rate for 100% purity.
     * @return float Adjusted rate based on purity.
     */
    public function calculate_purity_rate($material_id, $purity, $base_rate) {
        try {
            $material = $this->get_material($material_id);
            if (!$material || !$purity || !$base_rate) {
                return $base_rate; // Return base rate if invalid input
            }

            $purity_options = $material['purity_options'];
            if (!isset($purity_options[$purity])) {
                return $base_rate; // No adjustment if purity not found
            }

            // Calculate purity percentage (e.g., 18K gold = 18/24 = 75%)
            $name = strtolower($material['name']);
            if ($name === 'gold') {
                $purity_value = floatval($purity) / 24; // Gold uses karats (max 24)
            } elseif ($name === 'silver' && $purity === '925') {
                $purity_value = 0.925; // Sterling silver
            } elseif ($name === 'platinum' && $purity === '950') {
                $purity_value = 0.95; // Platinum 950
            } else {
                $purity_value = 1; // Default to 100% if unknown
            }

            return $base_rate * $purity_value;
        } catch (Exception $e) {
            error_log("WC_JPM_Material: Exception calculating purity rate for material $material_id - " . $e->getMessage());
            return $base_rate;
        }
    }
}