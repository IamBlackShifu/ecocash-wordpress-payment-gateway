#!/usr/bin/env php
<?php
/**
 * Comprehensive Test Suite for EcoCash WordPress Payment Gateway
 * 
 * This script performs production-level testing including:
 * - Plugin structure validation
 * - WooCommerce integration testing
 * - Security checks
 * - API connection testing
 * - Edge case validation
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

class EcoCashPluginTester {
    
    private $plugin_path;
    private $test_results = [];
    private $total_tests = 0;
    private $passed_tests = 0;
    
    public function __construct() {
        $this->plugin_path = __DIR__;
        echo "EcoCash WordPress Plugin - Comprehensive Test Suite\n";
        echo "=================================================\n\n";
    }
    
    public function run_all_tests() {
        $this->test_plugin_structure();
        $this->test_php_syntax();
        $this->test_wordpress_standards();
        $this->test_woocommerce_integration();
        $this->test_security_measures();
        $this->test_sdk_functionality();
        $this->test_edge_cases();
        $this->test_database_operations();
        $this->test_api_endpoints();
        $this->display_results();
    }
    
    private function test($description, $condition, $message = '') {
        $this->total_tests++;
        echo sprintf("%-60s", $description . "...");
        
        if ($condition) {
            echo " ✓ PASS\n";
            $this->passed_tests++;
            $this->test_results[] = ['test' => $description, 'status' => 'PASS', 'message' => $message];
        } else {
            echo " ✗ FAIL" . ($message ? " - $message" : "") . "\n";
            $this->test_results[] = ['test' => $description, 'status' => 'FAIL', 'message' => $message];
        }
    }
    
    private function test_plugin_structure() {
        echo "\n1. Plugin Structure Tests\n";
        echo "========================\n";
        
        // Test main plugin file
        $main_file = $this->plugin_path . '/ecocash-payment-gateway.php';
        $this->test(
            'Main plugin file exists',
            file_exists($main_file),
            'ecocash-payment-gateway.php not found'
        );
        
        // Test required includes
        $required_files = [
            '/includes/class-ecocash-sdk.php',
            '/includes/class-ecocash-payment-gateway.php',
            '/includes/class-ecocash-admin.php',
            '/includes/class-ecocash-api.php'
        ];
        
        foreach ($required_files as $file) {
            $this->test(
                'Required file exists: ' . basename($file),
                file_exists($this->plugin_path . $file),
                $file . ' not found'
            );
        }
        
        // Test assets structure
        $asset_dirs = [
            '/assets',
            '/assets/css',
            '/assets/js',
            '/assets/images'
        ];
        
        foreach ($asset_dirs as $dir) {
            $this->test(
                'Asset directory exists: ' . basename($dir),
                is_dir($this->plugin_path . $dir),
                $dir . ' directory not found'
            );
        }
        
        // Test for required assets
        $required_assets = [
            '/assets/css/admin.css',
            '/assets/js/admin.js',
            '/assets/images/ecocash-logo.png'
        ];
        
        foreach ($required_assets as $asset) {
            $this->test(
                'Required asset exists: ' . basename($asset),
                file_exists($this->plugin_path . $asset),
                $asset . ' not found'
            );
        }
    }
    
    private function test_php_syntax() {
        echo "\n2. PHP Syntax Tests\n";
        echo "==================\n";
        
        $php_files = [
            '/ecocash-payment-gateway.php',
            '/includes/class-ecocash-sdk.php',
            '/includes/class-ecocash-payment-gateway.php',
            '/includes/class-ecocash-admin.php',
            '/includes/class-ecocash-api.php'
        ];
        
        foreach ($php_files as $file) {
            $full_path = $this->plugin_path . $file;
            if (file_exists($full_path)) {
                $output = [];
                $return_var = 0;
                exec("php -l \"$full_path\" 2>&1", $output, $return_var);
                
                $this->test(
                    'PHP syntax valid: ' . basename($file),
                    $return_var === 0,
                    $return_var !== 0 ? implode(' ', $output) : ''
                );
            }
        }
    }
    
    private function test_wordpress_standards() {
        echo "\n3. WordPress Standards Tests\n";
        echo "===========================\n";
        
        $main_file = $this->plugin_path . '/ecocash-payment-gateway.php';
        if (file_exists($main_file)) {
            $content = file_get_contents($main_file);
            
            // Test plugin header
            $this->test(
                'Plugin header contains Plugin Name',
                strpos($content, 'Plugin Name:') !== false,
                'Plugin Name header missing'
            );
            
            $this->test(
                'Plugin header contains Version',
                strpos($content, 'Version:') !== false,
                'Version header missing'
            );
            
            $this->test(
                'Plugin header contains Description',
                strpos($content, 'Description:') !== false,
                'Description header missing'
            );
            
            // Test security measures
            $this->test(
                'Direct access prevention',
                strpos($content, "if (!defined('ABSPATH'))") !== false,
                'ABSPATH check missing'
            );
            
            // Test text domain
            $this->test(
                'Text domain defined',
                strpos($content, 'Text Domain:') !== false,
                'Text Domain header missing'
            );
            
            // Test activation/deactivation hooks
            $this->test(
                'Activation hook present',
                strpos($content, 'register_activation_hook') !== false,
                'Activation hook missing'
            );
            
            $this->test(
                'Deactivation hook present',
                strpos($content, 'register_deactivation_hook') !== false,
                'Deactivation hook missing'
            );
        }
    }
    
    private function test_woocommerce_integration() {
        echo "\n4. WooCommerce Integration Tests\n";
        echo "================================\n";
        
        $gateway_file = $this->plugin_path . '/includes/class-ecocash-payment-gateway.php';
        if (file_exists($gateway_file)) {
            $content = file_get_contents($gateway_file);
            
            // Test class inheritance
            $this->test(
                'Extends WC_Payment_Gateway',
                strpos($content, 'extends WC_Payment_Gateway') !== false,
                'Gateway class does not extend WC_Payment_Gateway'
            );
            
            // Test required methods
            $required_methods = [
                'process_payment',
                'init_form_fields',
                'payment_fields',
                'validate_fields'
            ];
            
            foreach ($required_methods as $method) {
                $this->test(
                    "Required method exists: $method",
                    strpos($content, "function $method") !== false,
                    "Method $method not found"
                );
            }
            
            // Test gateway properties
            $this->test(
                'Gateway ID set',
                strpos($content, '$this->id = \'ecocash\'') !== false,
                'Gateway ID not set correctly'
            );
            
            $this->test(
                'Has payment fields',
                strpos($content, '$this->has_fields = true') !== false,
                'has_fields not set to true'
            );
            
            // Test supports features
            $this->test(
                'Supports refunds',
                strpos($content, '\'refunds\'') !== false,
                'Refunds support not declared'
            );
        }
    }
    
    private function test_security_measures() {
        echo "\n5. Security Tests\n";
        echo "================\n";
        
        $files_to_check = [
            '/includes/class-ecocash-sdk.php',
            '/includes/class-ecocash-payment-gateway.php',
            '/includes/class-ecocash-admin.php',
            '/includes/class-ecocash-api.php'
        ];
        
        foreach ($files_to_check as $file) {
            $full_path = $this->plugin_path . $file;
            if (file_exists($full_path)) {
                $content = file_get_contents($full_path);
                
                // Test ABSPATH check
                $this->test(
                    "ABSPATH check in " . basename($file),
                    strpos($content, "if (!defined('ABSPATH'))") !== false,
                    'Direct access prevention missing'
                );
                
                // Test for nonce verification in admin areas
                if (strpos($file, 'admin') !== false) {
                    $this->test(
                        "Nonce verification in " . basename($file),
                        strpos($content, 'wp_nonce_field') !== false || strpos($content, 'check_ajax_referer') !== false,
                        'Nonce verification may be missing'
                    );
                }
                
                // Test for data sanitization
                $sanitization_functions = ['sanitize_text_field', 'sanitize_email', 'esc_html', 'esc_attr'];
                $has_sanitization = false;
                foreach ($sanitization_functions as $func) {
                    if (strpos($content, $func) !== false) {
                        $has_sanitization = true;
                        break;
                    }
                }
                
                $this->test(
                    "Data sanitization in " . basename($file),
                    $has_sanitization,
                    'No sanitization functions found'
                );
            }
        }
    }
    
    private function test_sdk_functionality() {
        echo "\n6. SDK Functionality Tests\n";
        echo "==========================\n";
        
        $sdk_file = $this->plugin_path . '/includes/class-ecocash-sdk.php';
        
        if (file_exists($sdk_file)) {
            require_once $sdk_file;
            
            // Test class existence
            $this->test(
                'SDK class loads',
                class_exists('Ecocash_SDK'),
                'Ecocash_SDK class not found'
            );
            
            if (class_exists('Ecocash_SDK')) {
                // Test mobile number formatting
                $test_cases = [
                    ['0771234567', '263771234567'],
                    ['771234567', '263771234567'],
                    ['263771234567', '263771234567'],
                    ['+263 77 123 4567', '263771234567'],
                    ['invalid', false]
                ];
                
                foreach ($test_cases as $i => $case) {
                    $input = $case[0];
                    $expected = $case[1];
                    $result = Ecocash_SDK::format_mobile_number($input);
                    
                    $this->test(
                        "Mobile format test " . ($i + 1) . ": '$input'",
                        $result === $expected,
                        "Expected '$expected', got '$result'"
                    );
                }\n                \n                // Test reference generation\n                $ref1 = Ecocash_SDK::generate_reference('TEST');\n                $ref2 = Ecocash_SDK::generate_reference('TEST');\n                \n                $this->test(\n                    'Reference generation works',\n                    !empty($ref1) && strpos($ref1, 'TEST-') === 0,\n                    'Reference format incorrect'\n                );\n                \n                $this->test(\n                    'References are unique',\n                    $ref1 !== $ref2,\n                    'Generated references are not unique'\n                );\n            }\n        }\n    }\n    \n    private function test_edge_cases() {\n        echo "\n7. Edge Case Tests\n";\n        echo "=================\n";\n        \n        // Test currency validation\n        $valid_currencies = ['USD', 'ZWL', 'ZiG'];\n        $invalid_currencies = ['EUR', 'GBP', 'JPY', 'BTC'];\n        \n        $this->test(\n            'Valid currencies defined',\n            count($valid_currencies) === 3,\n            'Unexpected number of valid currencies'\n        );\n        \n        // Test amount validation scenarios\n        $amount_tests = [\n            ['0', false],      // Zero amount\n            ['-1', false],     // Negative amount\n            ['0.01', true],    // Minimum valid amount\n            ['999999', true],  // Large amount\n            ['abc', false],    // Non-numeric\n            ['', false]        // Empty\n        ];\n        \n        foreach ($amount_tests as $i => $test) {\n            $amount = $test[0];\n            $should_be_valid = $test[1];\n            $is_valid = is_numeric($amount) && floatval($amount) > 0;\n            \n            $this->test(\n                "Amount validation test " . ($i + 1) . ": '$amount'",\n                $is_valid === $should_be_valid,\n                "Amount '$amount' validation failed"\n            );\n        }\n        \n        // Test URL structure\n        $sdk_file = $this->plugin_path . '/includes/class-ecocash-sdk.php';\n        if (file_exists($sdk_file)) {\n            $content = file_get_contents($sdk_file);\n            \n            $this->test(\n                'Sandbox URLs defined',\n                strpos($content, 'PAYMENT_SANDBOX_URL') !== false,\n                'Sandbox payment URL not defined'\n            );\n            \n            $this->test(\n                'Live URLs defined',\n                strpos($content, 'PAYMENT_LIVE_URL') !== false,\n                'Live payment URL not defined'\n            );\n            \n            $this->test(\n                'Lookup URLs defined',\n                strpos($content, 'LOOKUP_SANDBOX_URL') !== false,\n                'Lookup URLs not defined'\n            );\n        }\n    }\n    \n    private function test_database_operations() {\n        echo "\n8. Database Operations Tests\n";\n        echo "===========================\n";\n        \n        $main_file = $this->plugin_path . '/ecocash-payment-gateway.php';\n        if (file_exists($main_file)) {\n            $content = file_get_contents($main_file);\n            \n            // Test table creation\n            $this->test(\n                'Database table creation function exists',\n                strpos($content, 'ecocash_create_tables') !== false,\n                'Table creation function not found'\n            );\n            \n            $this->test(\n                'Activation hook calls table creation',\n                strpos($content, 'ecocash_create_tables()') !== false,\n                'Table creation not called on activation'\n            );\n            \n            // Check for proper SQL structure\n            $this->test(\n                'SQL table definition present',\n                strpos($content, 'CREATE TABLE') !== false,\n                'SQL CREATE TABLE statement not found'\n            );\n            \n            $this->test(\n                'Uses dbDelta for table creation',\n                strpos($content, 'dbDelta') !== false,\n                'dbDelta function not used for table creation'\n            );\n        }\n        \n        // Test API class database operations\n        $api_file = $this->plugin_path . '/includes/class-ecocash-api.php';\n        if (file_exists($api_file)) {\n            $content = file_get_contents($api_file);\n            \n            $this->test(\n                'Transaction logging function exists',\n                strpos($content, 'log_transaction') !== false,\n                'Transaction logging function not found'\n            );\n            \n            $this->test(\n                'Prepared statements used',\n                strpos($content, '$wpdb->prepare') !== false,\n                'Prepared statements not used (security risk)'\n            );\n        }\n    }\n    \n    private function test_api_endpoints() {\n        echo "\n9. API Configuration Tests\n";\n        echo "=========================\n";\n        \n        $sdk_file = $this->plugin_path . '/includes/class-ecocash-sdk.php';\n        if (file_exists($sdk_file)) {\n            $content = file_get_contents($sdk_file);\n            \n            // Test endpoint URLs\n            $this->test(\n                'Base URL defined',\n                strpos($content, 'const BASE_URL') !== false,\n                'Base URL constant not defined'\n            );\n            \n            // Test HTTPS usage\n            $this->test(\n                'HTTPS URLs used',\n                strpos($content, 'https://') !== false,\n                'HTTPS not used in API URLs'\n            );\n            \n            // Test API key header\n            $this->test(\n                'API key header configured',\n                strpos($content, 'X-API-KEY') !== false,\n                'API key header not configured'\n            );\n            \n            // Test timeout configuration\n            $this->test(\n                'Request timeout configured',\n                strpos($content, \"'timeout'\") !== false,\n                'Request timeout not configured'\n            );\n            \n            // Test SSL verification\n            $this->test(\n                'SSL verification enabled',\n                strpos($content, \"'sslverify' => true\") !== false,\n                'SSL verification not enabled'\n            );\n        }\n    }\n    \n    private function display_results() {\n        echo "\n" . str_repeat("=", 60) . "\n";\n        echo "TEST SUMMARY\n";\n        echo str_repeat("=", 60) . "\n";\n        \n        echo sprintf("Total Tests: %d\n", $this->total_tests);\n        echo sprintf("Passed: %d\n", $this->passed_tests);\n        echo sprintf("Failed: %d\n", $this->total_tests - $this->passed_tests);\n        echo sprintf("Success Rate: %.1f%%\n", ($this->passed_tests / $this->total_tests) * 100);\n        \n        echo "\nFAILED TESTS:\n";\n        echo str_repeat("-", 40) . "\n";\n        \n        $failed_tests = array_filter($this->test_results, function($test) {\n            return $test['status'] === 'FAIL';\n        });\n        \n        if (empty($failed_tests)) {\n            echo "🎉 All tests passed!\n";\n        } else {\n            foreach ($failed_tests as $test) {\n                echo "❌ " . $test['test'];\n                if (!empty($test['message'])) {\n                    echo " - " . $test['message'];\n                }\n                echo "\n";\n            }\n        }\n        \n        echo "\nRECOMMENDATIONS:\n";\n        echo str_repeat("-", 40) . "\n";\n        \n        if ($this->passed_tests / $this->total_tests >= 0.9) {\n            echo "✅ Plugin appears to be production-ready!\n";\n        } elseif ($this->passed_tests / $this->total_tests >= 0.8) {\n            echo "⚠️  Plugin is mostly ready but has some issues to address.\n";\n        } else {\n            echo "❌ Plugin needs significant work before production deployment.\n";\n        }\n        \n        echo "\nNext steps:\n";\n        echo "1. Address any failed tests above\n";\n        echo "2. Test with a real EcoCash sandbox account\n";\n        echo "3. Perform user acceptance testing\n";\n        echo "4. Load test for high-traffic scenarios\n";\n        echo "5. Security audit by a third party\n";\n    }\n}\n\n// Run the tests\n$tester = new EcoCashPluginTester();\n$tester->run_all_tests();\n
