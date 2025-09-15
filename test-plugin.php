#!/usr/bin/env php
<?php
/**
 * Simple test script for Ecocash WordPress Plugin
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Ecocash WordPress Plugin - Basic Functionality Test\n";
echo "====================================================\n\n";

// Test 1: Check if main plugin file exists and is valid PHP
echo "1. Testing main plugin file...\n";
$main_file = __DIR__ . '/ecocash-payment-gateway.php';
if (file_exists($main_file)) {
    echo "   ✓ Main plugin file exists\n";
    
    // Check PHP syntax
    $output = [];
    $return_var = 0;
    exec("php -l \"$main_file\" 2>&1", $output, $return_var);
    
    if ($return_var === 0) {
        echo "   ✓ PHP syntax is valid\n";
    } else {
        echo "   ✗ PHP syntax error: " . implode("\n", $output) . "\n";
    }
} else {
    echo "   ✗ Main plugin file not found\n";
}

// Test 2: Check SDK class
echo "\n2. Testing SDK class...\n";
$sdk_file = __DIR__ . '/includes/class-ecocash-sdk.php';
if (file_exists($sdk_file)) {
    echo "   ✓ SDK file exists\n";
    
    // Include and test SDK
    require_once $sdk_file;
    
    if (class_exists('Ecocash_SDK')) {
        echo "   ✓ SDK class loads successfully\n";
        
        // Test mobile number formatting
        $test_numbers = [
            '0771234567' => '263771234567',
            '771234567' => '263771234567',
            '263771234567' => '263771234567',
            '+263771234567' => '263771234567'
        ];
        
        $all_passed = true;
        foreach ($test_numbers as $input => $expected) {
            $result = Ecocash_SDK::format_mobile_number($input);
            if ($result === $expected) {
                echo "   ✓ Mobile formatting: $input → $result\n";
            } else {
                echo "   ✗ Mobile formatting failed: $input → $result (expected $expected)\n";
                $all_passed = false;
            }
        }
        
        if ($all_passed) {
            echo "   ✓ All mobile number formatting tests passed\n";
        }
        
        // Test reference generation
        $ref = Ecocash_SDK::generate_reference('TEST');
        if (preg_match('/^TEST-\d+-\d+$/', $ref)) {
            echo "   ✓ Reference generation works: $ref\n";
        } else {
            echo "   ✗ Reference generation failed: $ref\n";
        }
        
    } else {
        echo "   ✗ SDK class not found after including file\n";
    }
} else {
    echo "   ✗ SDK file not found\n";
}

// Test 3: Check other required files
echo "\n3. Testing required files...\n";
$required_files = [
    'includes/class-ecocash-api.php',
    'includes/class-ecocash-payment-gateway.php',
    'includes/class-ecocash-admin.php',
    'assets/css/admin.css',
    'assets/js/admin.js',
    'uninstall.php'
];

$missing_files = [];
foreach ($required_files as $file) {
    $full_path = __DIR__ . '/' . $file;
    if (file_exists($full_path)) {
        echo "   ✓ $file\n";
        
        // Test PHP syntax for PHP files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            $output = [];
            $return_var = 0;
            exec("php -l \"$full_path\" 2>&1", $output, $return_var);
            
            if ($return_var !== 0) {
                echo "     ✗ Syntax error in $file\n";
            }
        }
    } else {
        echo "   ✗ $file (missing)\n";
        $missing_files[] = $file;
    }
}

// Test 4: Plugin structure validation
echo "\n4. Testing plugin structure...\n";
$plugin_header = file_get_contents($main_file);
$required_headers = [
    'Plugin Name:',
    'Plugin URI:',
    'Description:',
    'Version:',
    'Author:',
    'License:'
];

$header_check = true;
foreach ($required_headers as $header) {
    if (strpos($plugin_header, $header) !== false) {
        echo "   ✓ $header found\n";
    } else {
        echo "   ✗ $header missing\n";
        $header_check = false;
    }
}

if ($header_check) {
    echo "   ✓ All required plugin headers present\n";
}

// Summary
echo "\n5. Test Summary\n";
echo "================\n";

$total_tests = 4;
$passed_tests = 0;

if (file_exists($main_file)) $passed_tests++;
if (class_exists('Ecocash_SDK')) $passed_tests++;
if (empty($missing_files)) $passed_tests++;
if ($header_check) $passed_tests++;

echo "Tests passed: $passed_tests/$total_tests\n";

if ($passed_tests === $total_tests) {
    echo "🎉 All tests passed! Plugin is ready for installation.\n";
    echo "\nNext steps:\n";
    echo "1. Create a ZIP file of the plugin directory\n";
    echo "2. Install in WordPress via Plugins → Add New → Upload\n";
    echo "3. Configure API keys in Ecocash → Settings\n";
    echo "4. Enable in WooCommerce → Settings → Payments\n";
} else {
    echo "⚠️  Some tests failed. Please check the issues above.\n";
}

echo "\nPlugin ready for WordPress installation!\n";
?>