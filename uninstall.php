<?php
/**
 * Uninstall CLOA Product Sync Plugin
 * 
 * This file is called when the plugin is deleted via WordPress admin.
 * It cleans up all plugin data from the database.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Security check - only run if called properly
if (!current_user_can('activate_plugins')) {
    return;
}

// Remove all plugin options
delete_option('cloa_sync_version');
delete_option('cloa_api_key');
delete_option('cloa_api_url');
delete_option('cloa_sync_enabled');
delete_option('cloa_sync_frequency');
delete_option('cloa_sync_categories');
delete_option('cloa_last_sync');
delete_option('cloa_sync_status');
delete_option('cloa_sync_progress');

// Remove all scheduled events
wp_clear_scheduled_hook('cloa_sync_products');
wp_clear_scheduled_hook('cloa_process_sync_batch');

// Remove product metadata added by plugin
global $wpdb;

// Clean up product meta data
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cloa_last_sync'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cloa_sync_status'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key = '_cloa_synced'");

// Remove any transients created by the plugin
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_cloa_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_cloa_%'");

// Remove any user meta related to the plugin
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'cloa_%'");

// Clear any cached data
wp_cache_flush();

// Log the uninstall (optional, for debugging)
if (defined('WP_DEBUG') && WP_DEBUG) {
    error_log('CLOA Product Sync plugin uninstalled and cleaned up successfully');
}