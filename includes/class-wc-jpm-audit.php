<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Audit class manages audit logging for plugin actions.
 */
class WC_JPM_Audit {
    private $db;

    /**
     * Constructor to initialize dependencies.
     */
    public function __construct() {
        $this->db = new WC_JPM_DB(); // Dependency for database operations
    }

    /**
     * Logs an action to the audit table.
     *
     * @param string $action The action being logged (e.g., 'Settings Updated').
     * @param string $details Additional details about the action (e.g., JSON-encoded data).
     * @return bool True on success, false on failure.
     */
    public function log($action, $details = '') {
        try {
            $user_id = get_current_user_id(); // Get the current user's ID
            if (!$user_id) {
                $user_id = 0; // Log as guest/system if no user is logged in (e.g., cron)
            }

            $result = $this->db->log_audit($action, $details);
            if ($result === false) {
                error_log("WC_JPM_Audit: Failed to log action '$action' - Database error.");
                return false;
            }
            return true;
        } catch (Exception $e) {
            error_log("WC_JPM_Audit: Exception while logging '$action' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves the most recent audit logs.
     *
     * @param int $limit Number of logs to retrieve (default: 100).
     * @return array Array of audit log entries.
     */
    public function get_logs($limit = 100) {
        try {
            $logs = $this->db->get_audit_logs($limit);
            return $logs ?: [];
        } catch (Exception $e) {
            error_log("WC_JPM_Audit: Failed to retrieve logs - " . $e->getMessage());
            return [];
        }
    }

    /**
     * Clears all audit logs (optional utility method).
     *
     * @return bool True on success, false on failure.
     */
    public function clear_logs() {
        global $wpdb;
        try {
            $result = $wpdb->query("TRUNCATE TABLE {$this->db->audit_table}");
            if ($result === false) {
                error_log("WC_JPM_Audit: Failed to clear logs - Database error.");
                return false;
            }
            $this->log('Audit Logs Cleared', 'All previous logs were removed.');
            return true;
        } catch (Exception $e) {
            error_log("WC_JPM_Audit: Exception while clearing logs - " . $e->getMessage());
            return false;
        }
    }
}