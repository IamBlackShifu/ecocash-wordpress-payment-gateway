<?php
/**
 * Plugin Name: Ecocash Payment Gateway for WordPress
 * Plugin URI: https://github.com/IamBlackShifu/ecocashWordPress
 * Description: A comprehensive WordPress plugin for integrating Ecocash payment services (Zimbabwe). Supports WooCommerce integration with instant payments, transaction lookup, and refund processing.
 * Version: 1.0.0
 * Author: Ecocash WordPress Plugin Team
 * Author URI: https://github.com/IamBlackShifu/ecocashWordPress
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: ecocash-payment-gateway
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ECOCASH_PLUGIN_FILE', __FILE__);
define('ECOCASH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ECOCASH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ECOCASH_PLUGIN_VERSION', '1.0.0');
define('ECOCASH_PLUGIN_TEXT_DOMAIN', 'ecocash-payment-gateway');

// Check if WooCommerce is active
function ecocash_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'ecocash_woocommerce_missing_notice');
        return false;
    }
    return true;
}

// Show admin notice if WooCommerce is not active
function ecocash_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>' . __('Ecocash Payment Gateway', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</strong>: ' . 
         __('WooCommerce is required for this plugin to work. Please install and activate WooCommerce.', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</p></div>';
}

// Initialize the plugin
function ecocash_init() {
    // Load text domain for translations
    load_plugin_textdomain(ECOCASH_PLUGIN_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Check if WooCommerce is active
    if (!ecocash_check_woocommerce()) {
        return;
    }
    
    // Include required files
    require_once ECOCASH_PLUGIN_PATH . 'includes/class-ecocash-sdk.php';
    require_once ECOCASH_PLUGIN_PATH . 'includes/class-ecocash-payment-gateway.php';
    require_once ECOCASH_PLUGIN_PATH . 'includes/class-ecocash-admin.php';
    require_once ECOCASH_PLUGIN_PATH . 'includes/class-ecocash-api.php';
    
    // Initialize admin interface
    if (is_admin()) {
        new Ecocash_Admin();
    }
    
    // Add the gateway to WooCommerce
    add_filter('woocommerce_payment_gateways', 'ecocash_add_gateway_class');
}

// Add Ecocash gateway to WooCommerce
function ecocash_add_gateway_class($gateways) {
    $gateways[] = 'Ecocash_Payment_Gateway';
    return $gateways;
}

// Plugin activation hook
function ecocash_activate() {
    // Create database tables if needed
    ecocash_create_tables();
    
    // Set default options
    $default_options = array(
        'ecocash_enabled' => 'no',
        'ecocash_sandbox_mode' => 'yes',
        'ecocash_api_key_sandbox' => '',
        'ecocash_api_key_live' => '',
        'ecocash_title' => 'Ecocash Payment',
        'ecocash_description' => 'Pay securely using your Ecocash mobile wallet.',
        'ecocash_currency' => 'USD',
        'ecocash_debug' => 'no'
    );
    
    foreach ($default_options as $key => $value) {
        if (get_option($key) === false) {
            add_option($key, $value);
        }
    }
}

// Plugin deactivation hook
function ecocash_deactivate() {
    // Clean up if needed
}

// Create database tables for transaction logging
function ecocash_create_tables() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ecocash_transactions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        order_id bigint(20) NOT NULL,
        transaction_reference varchar(255) NOT NULL,
        ecocash_reference varchar(255) DEFAULT NULL,
        mobile_number varchar(20) NOT NULL,
        amount decimal(10,2) NOT NULL,
        currency varchar(3) NOT NULL,
        status varchar(50) NOT NULL,
        reason text,
        transaction_type varchar(20) DEFAULT 'payment',
        sandbox_mode tinyint(1) DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY order_id (order_id),
        KEY transaction_reference (transaction_reference),
        KEY ecocash_reference (ecocash_reference),
        KEY status (status)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'ecocash_activate');
register_deactivation_hook(__FILE__, 'ecocash_deactivate');

// Initialize the plugin
add_action('plugins_loaded', 'ecocash_init');

// Add custom action links
function ecocash_action_links($links) {
    $settings_link = '<a href="admin.php?page=ecocash-settings">' . __('Settings', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ecocash_action_links');

// Add custom admin styles
function ecocash_admin_styles($hook) {
    if (strpos($hook, 'ecocash') !== false) {
        wp_enqueue_style('ecocash-admin', ECOCASH_PLUGIN_URL . 'assets/css/admin.css', array(), ECOCASH_PLUGIN_VERSION);
        wp_enqueue_script('ecocash-admin', ECOCASH_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ECOCASH_PLUGIN_VERSION, true);
        
        // Localize script for AJAX
        wp_localize_script('ecocash-admin', 'ecocash_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ecocash_ajax_nonce'),
            'text' => array(
                'testing_connection' => __('Testing connection...', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'connection_successful' => __('Connection successful!', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'connection_failed' => __('Connection failed!', ECOCASH_PLUGIN_TEXT_DOMAIN),
            )
        ));
    }
}
add_action('admin_enqueue_scripts', 'ecocash_admin_styles');

// Handle AJAX test connection
function ecocash_test_connection() {
    check_ajax_referer('ecocash_ajax_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    $api_key = sanitize_text_field($_POST['api_key']);
    $sandbox_mode = $_POST['sandbox_mode'] === 'true';
    
    $ecocash_api = new Ecocash_API($api_key, $sandbox_mode);
    
    // Test with a simple lookup request (this won't charge anything)
    $test_result = $ecocash_api->test_connection();
    
    if ($test_result['success']) {
        wp_send_json_success(array('message' => __('API connection successful!', ECOCASH_PLUGIN_TEXT_DOMAIN)));
    } else {
        wp_send_json_error(array('message' => $test_result['message']));
    }
}
add_action('wp_ajax_ecocash_test_connection', 'ecocash_test_connection');