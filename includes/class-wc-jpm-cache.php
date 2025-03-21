<?php
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * WC_JPM_Cache class provides caching functionality using WordPress transients.
 */
class WC_JPM_Cache {
    /**
     * Prefix for all cache keys to avoid conflicts.
     *
     * @var string
     */
    private $prefix = 'wc_jpm_';

    /**
     * Constructor (empty as no initialization is needed).
     */
    public function __construct() {
        // No dependencies or hooks needed; purely utility-based
    }

    /**
     * Sets a value in the cache with an expiration time.
     *
     * @param string $key Cache key.
     * @param mixed $value Value to cache.
     * @param int $expiration Expiration time in seconds (default: 1 hour).
     * @return bool True on success, false on failure.
     */
    public function set($key, $value, $expiration = 3600) {
        try {
            $cache_key = $this->prefix . sanitize_key($key);
            $result = set_transient($cache_key, $value, $expiration);
            if ($result === false) {
                error_log("WC_JPM_Cache: Failed to set cache for key '$key'");
            }
            return $result;
        } catch (Exception $e) {
            error_log("WC_JPM_Cache: Exception while setting cache for '$key' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Retrieves a value from the cache.
     *
     * @param string $key Cache key.
     * @return mixed Cached value or false if not found/expired.
     */
    public function get($key) {
        try {
            $cache_key = $this->prefix . sanitize_key($key);
            $value = get_transient($cache_key);
            return $value; // Returns false if transient doesn't exist or expired
        } catch (Exception $e) {
            error_log("WC_JPM_Cache: Exception while getting cache for '$key' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Deletes a specific cache entry.
     *
     * @param string $key Cache key.
     * @return bool True on success, false on failure.
     */
    public function delete($key) {
        try {
            $cache_key = $this->prefix . sanitize_key($key);
            $result = delete_transient($cache_key);
            if ($result === false) {
                error_log("WC_JPM_Cache: Failed to delete cache for key '$key'");
            }
            return $result;
        } catch (Exception $e) {
            error_log("WC_JPM_Cache: Exception while deleting cache for '$key' - " . $e->getMessage());
            return false;
        }
    }

    /**
     * Clears all cache entries with the plugin's prefix (optional utility method).
     *
     * @return bool True if any cache was cleared, false otherwise.
     */
    public function clear_all() {
        global $wpdb;
        try {
            $prefix = $this->prefix . '%';
            $sql = $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name LIKE %s",
                '_transient_' . $prefix,
                '_transient_timeout_' . $prefix
            );
            $result = $wpdb->query($sql);
            if ($result === false) {
                error_log("WC_JPM_Cache: Failed to clear all cache - Database error.");
                return false;
            }
            return $result > 0; // True if rows were deleted
        } catch (Exception $e) {
            error_log("WC_JPM_Cache: Exception while clearing all cache - " . $e->getMessage());
            return false;
        }
    }
}