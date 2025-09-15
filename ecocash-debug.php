<?php
/**
 * EcoCash Debug Helper
 * 
 * Add this temporarily to your WordPress site to debug EcoCash configuration
 * Place this file in your WordPress root directory and access via yoursite.com/ecocash-debug.php
 */

// Load WordPress environment
require_once __DIR__ . '/wp-config.php';
require_once __DIR__ . '/wp-load.php';

// Only allow administrators to access this debug page
if (!current_user_can('manage_options')) {
    die('Access denied. Administrator permissions required.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>EcoCash Debug Information</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug-section { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
        .pass { color: green; font-weight: bold; }
        .fail { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .api-key { font-family: monospace; background: #f0f0f0; padding: 5px; }
    </style>
</head>
<body>
    <h1>EcoCash WordPress Plugin Debug Information</h1>
    
    <div class="debug-section">
        <h2>Plugin Status</h2>
        <?php
        $plugin_file = WP_PLUGIN_DIR . '/ecocash-wordpress-payment-gateway/ecocash-payment-gateway.php';
        $plugin_active = is_plugin_active('ecocash-wordpress-payment-gateway/ecocash-payment-gateway.php');
        
        echo '<p>Plugin File Exists: ' . (file_exists($plugin_file) ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
        echo '<p>Plugin Active: ' . ($plugin_active ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
        ?>
    </div>
    
    <div class="debug-section">
        <h2>WooCommerce Status</h2>
        <?php
        $wc_active = class_exists('WooCommerce');
        $wc_version = $wc_active ? WC()->version : 'N/A';
        
        echo '<p>WooCommerce Active: ' . ($wc_active ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
        echo '<p>WooCommerce Version: ' . $wc_version . '</p>';
        ?>
    </div>
    
    <div class="debug-section">
        <h2>EcoCash Configuration</h2>
        <?php
        $enabled = get_option('ecocash_enabled', 'no');
        $sandbox_mode = get_option('ecocash_sandbox_mode', 'yes');
        $api_key_sandbox = get_option('ecocash_api_key_sandbox', '');
        $api_key_live = get_option('ecocash_api_key_live', '');
        $debug_mode = get_option('ecocash_debug', 'no');
        $title = get_option('ecocash_title', '');
        $description = get_option('ecocash_description', '');
        
        echo '<p>Gateway Enabled: ' . ($enabled === 'yes' ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
        echo '<p>Sandbox Mode: ' . ($sandbox_mode === 'yes' ? '<span class="warning">YES</span>' : '<span class="pass">LIVE MODE</span>') . '</p>';
        echo '<p>Debug Logging: ' . ($debug_mode === 'yes' ? '<span class="pass">ENABLED</span>' : '<span class="warning">DISABLED</span>') . '</p>';
        
        echo '<h3>API Keys</h3>';
        echo '<p>Sandbox API Key: ';
        if (!empty($api_key_sandbox)) {
            echo '<span class="pass">CONFIGURED</span><br>';
            echo '<span class="api-key">Key: ' . substr($api_key_sandbox, 0, 10) . '...</span><br>';
            echo '<span class="api-key">Length: ' . strlen($api_key_sandbox) . ' characters</span>';
        } else {
            echo '<span class="fail">NOT CONFIGURED</span>';
        }
        echo '</p>';
        
        echo '<p>Live API Key: ';
        if (!empty($api_key_live)) {
            echo '<span class="pass">CONFIGURED</span><br>';
            echo '<span class="api-key">Key: ' . substr($api_key_live, 0, 10) . '...</span><br>';
            echo '<span class="api-key">Length: ' . strlen($api_key_live) . ' characters</span>';
        } else {
            echo '<span class="fail">NOT CONFIGURED</span>';
        }
        echo '</p>';
        
        echo '<h3>Current Active Key</h3>';
        $current_api_key = $sandbox_mode === 'yes' ? $api_key_sandbox : $api_key_live;
        echo '<p>Currently Using: ' . ($sandbox_mode === 'yes' ? 'Sandbox' : 'Live') . ' API Key</p>';
        echo '<p>Active Key Status: ';
        if (!empty($current_api_key)) {
            echo '<span class="pass">AVAILABLE</span><br>';
            echo '<span class="api-key">Key: ' . substr($current_api_key, 0, 10) . '...</span><br>';
            echo '<span class="api-key">Full Key (for testing): ' . $current_api_key . '</span>';
        } else {
            echo '<span class="fail">NOT AVAILABLE</span>';
        }
        echo '</p>';
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Payment Gateway Availability</h2>
        <?php
        if (class_exists('Ecocash_Payment_Gateway')) {
            $gateway = new Ecocash_Payment_Gateway();
            $available = $gateway->is_available();
            $supported_currencies = array('USD', 'ZWL', 'ZiG');
            $current_currency = get_woocommerce_currency();
            
            echo '<p>Gateway Class Loaded: <span class="pass">YES</span></p>';
            echo '<p>Gateway Available: ' . ($available ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
            echo '<p>Current Currency: ' . $current_currency . '</p>';
            echo '<p>Currency Supported: ' . (in_array($current_currency, $supported_currencies) ? '<span class="pass">YES</span>' : '<span class="fail">NO</span>') . '</p>';
            echo '<p>Supported Currencies: ' . implode(', ', $supported_currencies) . '</p>';
        } else {
            echo '<p>Gateway Class Loaded: <span class="fail">NO</span></p>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Test API Connection</h2>
        <?php
        if (!empty($current_api_key) && class_exists('Ecocash_SDK')) {
            echo '<p>Testing API connection with current configuration...</p>';
            
            try {
                $sdk = new Ecocash_SDK($current_api_key, $sandbox_mode === 'yes');
                
                // Test lookup (this won't charge anything)
                $test_data = array(
                    'mobileNumber' => '263771234567',
                    'reference' => 'TEST-' . time()
                );
                
                $result = $sdk->lookup_transaction($test_data);
                
                echo '<p>API Connection Test: ';
                if ($result['success']) {
                    echo '<span class="pass">SUCCESS</span></p>';
                    echo '<pre>' . json_encode($result, JSON_PRETTY_PRINT) . '</pre>';
                } else {
                    echo '<span class="warning">EXPECTED FAILURE (Test Reference)</span></p>';
                    echo '<p>Status Code: ' . (isset($result['error']['status_code']) ? $result['error']['status_code'] : 'Unknown') . '</p>';
                    echo '<p>Message: ' . (isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error') . '</p>';
                    
                    if (isset($result['error']['status_code']) && $result['error']['status_code'] === 401) {
                        echo '<p><span class="fail">API KEY AUTHENTICATION FAILED</span></p>';
                    } else {
                        echo '<p><span class="pass">API Key appears to be working (lookup failed as expected for test reference)</span></p>';
                    }
                }
            } catch (Exception $e) {
                echo '<span class="fail">ERROR</span></p>';
                echo '<p>Exception: ' . $e->getMessage() . '</p>';
            }
        } else {
            echo '<p><span class="fail">Cannot test - No API key configured or SDK not loaded</span></p>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Recent Error Log (if debug enabled)</h2>
        <?php
        if ($debug_mode === 'yes') {
            $log_file = WP_CONTENT_DIR . '/debug.log';
            if (file_exists($log_file)) {
                $log_content = file_get_contents($log_file);
                $ecocash_logs = array();
                
                $lines = explode("\n", $log_content);
                foreach ($lines as $line) {
                    if (stripos($line, 'ecocash') !== false) {
                        $ecocash_logs[] = $line;
                    }
                }
                
                if (!empty($ecocash_logs)) {
                    echo '<p>Recent EcoCash log entries (last 10):</p>';
                    echo '<pre>';
                    $recent_logs = array_slice($ecocash_logs, -10);
                    foreach ($recent_logs as $log) {
                        echo htmlspecialchars($log) . "\n";
                    }
                    echo '</pre>';
                } else {
                    echo '<p>No EcoCash entries found in debug log.</p>';
                }
            } else {
                echo '<p>Debug log file not found. Make sure WP_DEBUG_LOG is enabled in wp-config.php</p>';
            }
        } else {
            echo '<p>Debug logging is disabled. Enable it in EcoCash settings to see detailed logs.</p>';
        }
        ?>
    </div>
    
    <div class="debug-section">
        <h2>Recommended Actions</h2>
        <ul>
            <?php if ($enabled !== 'yes'): ?>
            <li><strong>Enable the EcoCash gateway</strong> in WooCommerce → Settings → Payments</li>
            <?php endif; ?>
            
            <?php if (empty($current_api_key)): ?>
            <li><strong>Configure your API key</strong> in WordPress Admin → EcoCash → Settings</li>
            <?php endif; ?>
            
            <?php if ($debug_mode !== 'yes'): ?>
            <li><strong>Enable debug logging</strong> in EcoCash settings to get detailed error information</li>
            <?php endif; ?>
            
            <?php if (!in_array($current_currency, array('USD', 'ZWL', 'ZiG'))): ?>
            <li><strong>Change store currency</strong> to USD, ZWL, or ZiG in WooCommerce → Settings → General</li>
            <?php endif; ?>
            
            <li>Check the WordPress error log for detailed error messages</li>
            <li>Test with a small amount first in sandbox mode</li>
        </ul>
    </div>
    
    <div class="debug-section">
        <h2>Quick Actions</h2>
        <p><a href="<?php echo admin_url('admin.php?page=ecocash-settings'); ?>">Go to EcoCash Settings</a></p>
        <p><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout'); ?>">Go to WooCommerce Payment Settings</a></p>
        <p><strong>Remember to delete this debug file after troubleshooting!</strong></p>
    </div>
</body>
</html>
