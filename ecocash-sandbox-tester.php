<?php
/**
 * EcoCash Sandbox Connection Tester
 * 
 * Simple script to test EcoCash API connectivity and validate integration
 */

// Prevent direct access when used in WordPress
if (!defined('ABSPATH') && !defined('ECOCASH_TEST_MODE')) {
    define('ECOCASH_TEST_MODE', true);
}

class EcoCash_Sandbox_Tester {
    
    private $api_key;
    private $results = [];
    
    public function __construct($api_key = null) {
        $this->api_key = $api_key;
    }
    
    public function run_tests() {
        echo "<h2>EcoCash Sandbox Connection Test</h2>\n";
        echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px;'>\n";
        
        if (!$this->api_key) {
            echo "<p style='color: red;'>❌ No API key provided. Please set your sandbox API key.</p>\n";
            return;
        }
        
        // Include SDK if in WordPress environment
        if (defined('ABSPATH')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-ecocash-sdk.php';
        } elseif (file_exists(__DIR__ . '/includes/class-ecocash-sdk.php')) {
            require_once __DIR__ . '/includes/class-ecocash-sdk.php';
        } else {
            echo "<p style='color: red;'>❌ SDK not found. Please ensure class-ecocash-sdk.php exists.</p>\n";
            return;
        }
        
        if (!class_exists('Ecocash_SDK')) {
            echo "<p style='color: red;'>❌ Ecocash_SDK class not loaded.</p>\n";
            return;
        }
        
        $sdk = new Ecocash_SDK($this->api_key, true); // true = sandbox mode
        
        // Test 1: Basic connection test
        $this->test_connection($sdk);
        
        // Test 2: Mobile number validation
        $this->test_mobile_validation();
        
        // Test 3: Payment data validation
        $this->test_payment_validation($sdk);
        
        // Test 4: Lookup functionality
        $this->test_lookup_functionality($sdk);
        
        echo "</div>\n";
        $this->display_summary();
    }
    
    private function test_connection($sdk) {
        echo "<h3>🔗 Testing API Connection</h3>\n";
        
        // Test with a dummy lookup to check API connectivity
        $test_data = [
            'mobileNumber' => '263771234567',
            'reference' => 'CONN-TEST-' . time()
        ];
        
        echo "Testing connection to EcoCash sandbox...\n";
        $result = $sdk->lookup_transaction($test_data);
        
        if ($result['success']) {
            echo "<span style='color: green;'>✅ API connection successful!</span>\n";
            $this->results['connection'] = 'PASS';
        } elseif (isset($result['error']['status_code']) && $result['error']['status_code'] !== 401) {
            echo "<span style='color: green;'>✅ API connection successful (expected lookup failure)</span>\n";
            $this->results['connection'] = 'PASS';
        } else {
            echo "<span style='color: red;'>❌ API connection failed: " . $result['error']['message'] . "</span>\n";
            $this->results['connection'] = 'FAIL';
        }
        echo "\n";
    }
    
    private function test_mobile_validation() {
        echo "<h3>📱 Testing Mobile Number Validation</h3>\n";
        
        $test_cases = [
            ['0771234567', '263771234567', 'Local format (0771234567)'],
            ['771234567', '263771234567', 'Short format (771234567)'],
            ['263771234567', '263771234567', 'International format (263771234567)'],
            ['+263 77 123 4567', '263771234567', 'Formatted international (+263 77 123 4567)'],
            ['1234567890', false, 'Invalid number (1234567890)'],
            ['', false, 'Empty string'],
            ['invalid', false, 'Non-numeric string']
        ];
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $case) {
            $input = $case[0];
            $expected = $case[1];
            $description = $case[2];
            
            $result = Ecocash_SDK::format_mobile_number($input);
            
            if ($result === $expected) {
                echo "<span style='color: green;'>✅</span> $description\n";
                $passed++;
            } else {
                echo "<span style='color: red;'>❌</span> $description - Expected: " . 
                     ($expected ?: 'false') . ", Got: " . ($result ?: 'false') . "\n";
            }
        }
        
        $this->results['mobile_validation'] = ($passed === $total) ? 'PASS' : 'PARTIAL';
        echo "\nMobile validation: $passed/$total tests passed\n\n";
    }
    
    private function test_payment_validation($sdk) {
        echo "<h3>💳 Testing Payment Data Validation</h3>\n";
        
        $test_cases = [
            [
                'data' => [
                    'mobileNumber' => '263771234567',
                    'amount' => 10.50,
                    'reason' => 'Test payment',
                    'currency' => 'USD',
                    'reference' => 'TEST-' . time()
                ],
                'should_pass' => true,
                'description' => 'Valid payment data'
            ],
            [
                'data' => [
                    'mobileNumber' => 'invalid',
                    'amount' => 10.50,
                    'reason' => 'Test payment',
                    'currency' => 'USD',
                    'reference' => 'TEST-' . time()
                ],
                'should_pass' => false,
                'description' => 'Invalid mobile number'
            ],
            [
                'data' => [
                    'mobileNumber' => '263771234567',
                    'amount' => -10.50,
                    'reason' => 'Test payment',
                    'currency' => 'USD',
                    'reference' => 'TEST-' . time()
                ],
                'should_pass' => false,
                'description' => 'Negative amount'
            ],
            [
                'data' => [
                    'mobileNumber' => '263771234567',
                    'amount' => 10.50,
                    'reason' => 'Test payment',
                    'currency' => 'EUR',
                    'reference' => 'TEST-' . time()
                ],
                'should_pass' => false,
                'description' => 'Unsupported currency'
            ]
        ];
        
        $passed = 0;
        $total = count($test_cases);
        
        foreach ($test_cases as $case) {
            $result = $sdk->make_payment($case['data']);
            $actually_passed = $result['success'];
            
            if ($actually_passed === $case['should_pass']) {
                echo "<span style='color: green;'>✅</span> " . $case['description'] . "\n";
                $passed++;
            } else {
                echo "<span style='color: red;'>❌</span> " . $case['description'] . 
                     " - Expected " . ($case['should_pass'] ? 'success' : 'failure') . 
                     ", got " . ($actually_passed ? 'success' : 'failure') . "\n";
            }
        }
        
        $this->results['payment_validation'] = ($passed === $total) ? 'PASS' : 'PARTIAL';
        echo "\nPayment validation: $passed/$total tests passed\n\n";
    }
    
    private function test_lookup_functionality($sdk) {
        echo "<h3>🔍 Testing Transaction Lookup</h3>\n";
        
        // Test lookup with various scenarios
        $test_cases = [
            [
                'data' => [
                    'mobileNumber' => '263771234567',
                    'reference' => 'NONEXISTENT-' . time()
                ],
                'description' => 'Lookup non-existent transaction'
            ],
            [
                'data' => [
                    'mobileNumber' => 'invalid',
                    'reference' => 'TEST-' . time()
                ],
                'description' => 'Lookup with invalid mobile number'
            ],
            [
                'data' => [
                    'mobileNumber' => '263771234567',
                    'reference' => ''
                ],
                'description' => 'Lookup with empty reference'
            ]
        ];
        
        $total_lookups = 0;
        $successful_api_calls = 0;
        
        foreach ($test_cases as $case) {
            $total_lookups++;
            echo "Testing: " . $case['description'] . "... ";
            
            $result = $sdk->lookup_transaction($case['data']);
            
            // We expect most lookups to "fail" with proper error messages
            // Success here means the API call was made properly
            if (isset($result['error']) && isset($result['error']['status_code'])) {
                if ($result['error']['status_code'] !== 401) { // Not an auth error
                    echo "<span style='color: green;'>✅ API call successful</span>\n";
                    $successful_api_calls++;
                } else {
                    echo "<span style='color: red;'>❌ Authentication error</span>\n";
                }
            } elseif ($result['success']) {
                echo "<span style='color: green;'>✅ Lookup successful</span>\n";
                $successful_api_calls++;
            } else {
                echo "<span style='color: red;'>❌ API call failed</span>\n";
            }
        }
        
        $this->results['lookup'] = ($successful_api_calls > 0) ? 'PASS' : 'FAIL';
        echo "\nLookup functionality: $successful_api_calls/$total_lookups API calls successful\n\n";
    }
    
    private function display_summary() {
        echo "<h3>📊 Test Summary</h3>\n";
        echo "<table border='1' style='border-collapse: collapse; width: 100%;'>\n";
        echo "<tr><th>Test Category</th><th>Result</th></tr>\n";
        
        $overall_status = 'PASS';
        foreach ($this->results as $test => $result) {
            $color = ($result === 'PASS') ? 'green' : (($result === 'PARTIAL') ? 'orange' : 'red');
            echo "<tr><td>" . ucwords(str_replace('_', ' ', $test)) . "</td>";
            echo "<td style='color: $color; font-weight: bold;'>$result</td></tr>\n";
            
            if ($result === 'FAIL') {
                $overall_status = 'NEEDS_WORK';
            } elseif ($result === 'PARTIAL' && $overall_status === 'PASS') {
                $overall_status = 'GOOD';
            }
        }
        echo "</table>\n";
        
        echo "<h4>Overall Assessment: ";
        switch ($overall_status) {
            case 'PASS':
                echo "<span style='color: green; font-weight: bold;'>✅ EXCELLENT - Ready for production testing</span>";
                break;
            case 'GOOD':
                echo "<span style='color: orange; font-weight: bold;'>⚠️ GOOD - Minor issues to address</span>";
                break;
            case 'NEEDS_WORK':
                echo "<span style='color: red; font-weight: bold;'>❌ NEEDS WORK - Critical issues found</span>";
                break;
        }
        echo "</h4>\n";
        
        echo "<h4>Next Steps:</h4>\n";
        echo "<ul>\n";
        echo "<li>Set up a real EcoCash sandbox account and test with your credentials</li>\n";
        echo "<li>Implement webhook support for real-time payment notifications</li>\n";
        echo "<li>Add comprehensive error logging for production debugging</li>\n";
        echo "<li>Test the complete checkout flow in a staging environment</li>\n";
        echo "<li>Perform load testing with multiple concurrent transactions</li>\n";
        echo "</ul>\n";
    }
}

// Example usage (uncomment to use):

$api_key = '-bAkwtHjSZmWT29zkIwhN9va2Clq0uEL';
$tester = new EcoCash_Sandbox_Tester($api_key);
$tester->run_tests();


// If running standalone
if (defined('ECOCASH_TEST_MODE') && isset($_GET['test'])) {
    $api_key = isset($_GET['api_key']) ? $_GET['api_key'] : null;
    echo "<!DOCTYPE html><html><head><title>EcoCash Sandbox Test</title></head><body>";
    $tester = new EcoCash_Sandbox_Tester($api_key);
    $tester->run_tests();
    echo "</body></html>";
}
