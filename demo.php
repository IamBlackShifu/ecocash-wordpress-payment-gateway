<?php
/**
 * Demo script showing how the Ecocash WordPress Plugin works
 * 
 * This demonstrates the key functionality without requiring a full WordPress installation
 */

// Mock WordPress functions for demo purposes
function __($text, $domain = '') { return $text; }
function get_option($name, $default = false) { 
    static $options = [
        'ecocash_sandbox_mode' => 'yes',
        'ecocash_api_key_sandbox' => 'demo-sandbox-key-12345',
        'ecocash_enabled' => 'yes',
        'ecocash_title' => 'Ecocash Payment',
        'ecocash_description' => 'Pay securely using your Ecocash mobile wallet.'
    ];
    return isset($options[$name]) ? $options[$name] : $default;
}

// Include the SDK
require_once 'includes/class-ecocash-sdk.php';

echo "=== Ecocash WordPress Plugin Demo ===\n\n";

// 1. Initialize the SDK
echo "1. Initializing Ecocash SDK...\n";
$api_key = get_option('ecocash_api_key_sandbox');
$sandbox_mode = get_option('ecocash_sandbox_mode') === 'yes';

$ecocash_sdk = new Ecocash_SDK($api_key, $sandbox_mode);
echo "   ✓ SDK initialized with sandbox mode: " . ($sandbox_mode ? 'YES' : 'NO') . "\n";
echo "   ✓ Using API key: " . substr($api_key, 0, 10) . "...\n\n";

// 2. Test mobile number validation and formatting
echo "2. Testing mobile number validation...\n";
$test_numbers = [
    '0771234567',
    '771234567', 
    '263771234567',
    '+263771234567',
    '1234567890' // Invalid
];

foreach ($test_numbers as $number) {
    $formatted = Ecocash_SDK::format_mobile_number($number);
    $status = $formatted ? '✓ VALID' : '✗ INVALID';
    echo "   {$number} → " . ($formatted ?: 'N/A') . " [{$status}]\n";
}
echo "\n";

// 3. Simulate a payment request
echo "3. Simulating payment request...\n";
$payment_data = [
    'mobileNumber' => '263771234567',
    'amount' => 25.50,
    'reason' => 'Payment for WordPress Demo Order #123',
    'currency' => 'USD',
    'reference' => Ecocash_SDK::generate_reference('DEMO')
];

echo "   Payment Details:\n";
echo "   - Mobile: {$payment_data['mobileNumber']}\n";
echo "   - Amount: {$payment_data['amount']} {$payment_data['currency']}\n";
echo "   - Reference: {$payment_data['reference']}\n";
echo "   - Reason: {$payment_data['reason']}\n";

// Note: We won't make actual API calls in demo mode
echo "   ✓ Payment request prepared (would be sent to Ecocash API)\n\n";

// 4. Show transaction lookup example
echo "4. Transaction lookup example...\n";
$lookup_data = [
    'mobileNumber' => '263771234567',
    'reference' => $payment_data['reference']
];

echo "   Lookup Details:\n";
echo "   - Mobile: {$lookup_data['mobileNumber']}\n";
echo "   - Reference: {$lookup_data['reference']}\n";
echo "   ✓ Lookup request prepared (would check transaction status)\n\n";

// 5. Show refund example
echo "5. Refund processing example...\n";
$refund_data = [
    'originalEcocashTransactionReference' => 'demo-ecocash-ref-123456',
    'refundCorrelator' => Ecocash_SDK::generate_reference('REF'),
    'sourceMobileNumber' => '263771234567',
    'amount' => 10.00,
    'clientName' => 'WordPress Demo Store',
    'currency' => 'USD',
    'reasonForRefund' => 'Customer requested refund'
];

echo "   Refund Details:\n";
echo "   - Original Reference: {$refund_data['originalEcocashTransactionReference']}\n";
echo "   - Refund Reference: {$refund_data['refundCorrelator']}\n";
echo "   - Amount: {$refund_data['amount']} {$refund_data['currency']}\n";
echo "   - Reason: {$refund_data['reasonForRefund']}\n";
echo "   ✓ Refund request prepared (would process refund via API)\n\n";

// 6. Show WordPress integration features
echo "6. WordPress Plugin Features:\n";
echo "   ✓ WooCommerce payment gateway integration\n";
echo "   ✓ Admin settings page with API configuration\n";
echo "   ✓ Transaction logging and status tracking\n";
echo "   ✓ Real-time payment status checking via AJAX\n";
echo "   ✓ Mobile number validation on checkout\n";
echo "   ✓ Sandbox/Live environment switching\n";
echo "   ✓ Multi-currency support (USD, ZWL, ZiG)\n";
echo "   ✓ Refund processing from WordPress admin\n";
echo "   ✓ Debug logging for troubleshooting\n";
echo "   ✓ Responsive admin interface\n\n";

// 7. Installation instructions
echo "7. Installation Instructions:\n";
echo "   1. Upload plugin files to /wp-content/plugins/ecocash-payment-gateway/\n";
echo "   2. Activate plugin in WordPress admin\n";
echo "   3. Go to Ecocash → Settings to configure API keys\n";
echo "   4. Enable the gateway in WooCommerce → Settings → Payments\n";
echo "   5. Test with sandbox mode before going live\n\n";

echo "=== Demo Complete ===\n";
echo "Plugin is ready for installation in WordPress with WooCommerce!\n";
?>