<?php
/**
 * Ecocash Payment Gateway Uninstall Script
 * 
 * Runs when the plugin is uninstalled
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
$options_to_delete = array(
    'ecocash_enabled',
    'ecocash_sandbox_mode',
    'ecocash_api_key_sandbox',
    'ecocash_api_key_live',
    'ecocash_title',
    'ecocash_description',
    'ecocash_currency',
    'ecocash_debug'
);

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// Optionally, drop the transactions table
// Uncomment the following lines if you want to remove transaction data on uninstall
/*
global $wpdb;
$table_name = $wpdb->prefix . 'ecocash_transactions';
$wpdb->query("DROP TABLE IF EXISTS $table_name");
*/

// Clean up any log files
$log_dir = WP_PLUGIN_DIR . '/ecocash-payment-gateway/logs/';
if (is_dir($log_dir)) {
    $files = glob($log_dir . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

// Clear any cached data
wp_cache_flush();