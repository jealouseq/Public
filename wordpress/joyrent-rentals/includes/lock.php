<?php
if (!defined('ABSPATH')) exit;

// Database-backed mutex shared by concurrent PHP workers; only its owner can release it.
final class JR_Lock {
    public static function acquire(string $name, int $ttl = 600): string|false {
        global $wpdb;
        $old = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name));
        if ($old && (int) $old < time() - $ttl) $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $name, $old));
        $owner = time().':'.wp_generate_uuid4();
        $inserted = $wpdb->query($wpdb->prepare("INSERT IGNORE INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')", $name, $owner));
        wp_cache_delete($name, 'options'); wp_cache_delete('notoptions', 'options');
        return $inserted === 1 ? $owner : false;
    }
    public static function release(string $name, string $owner): void {
        global $wpdb;
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->options} WHERE option_name=%s AND option_value=%s", $name, $owner));
        wp_cache_delete($name, 'options');
    }
}
