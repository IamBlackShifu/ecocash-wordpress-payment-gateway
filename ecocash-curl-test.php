<?php
/**
 * EcoCash CURL Test Script
 * 
 * Direct test of the EcoCash API with the exact format you provided
 * This will help identify if the issue is with WordPress integration or API credentials
 */

// Your API key (replace with your actual sandbox key)
$api_key = '1wddI46HBW3pK7pH32wgr3st9wIM7E4w';

// Generate a UUID v4 for sourceReference
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

// Test data
$test_data = array(
    'customerMsisdn' => '263774222475',
    'amount' => 10.50,
    'reason' => 'Payment',
    'currency' => 'USD',
    'sourceReference' => generateUUID()
);

echo "<h1>EcoCash Direct API Test</h1>\n";
echo "<h2>Test Configuration</h2>\n";
echo "<p><strong>API Key:</strong> " . substr($api_key, 0, 10) . "...</p>\n";
echo "<p><strong>Endpoint:</strong> https://developers.ecocash.co.zw/api/ecocash_pay/api/v2/payment/instant/c2b/sandbox</p>\n";
echo "<p><strong>Request Data:</strong></p>\n";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>\n";

// Initialize CURL
$ch = curl_init();

// Set CURL options
curl_setopt_array($ch, array(
    CURLOPT_URL => 'https://developers.ecocash.co.zw/api/ecocash_pay/api/v2/payment/instant/c2b/sandbox',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($test_data),
    CURLOPT_HTTPHEADER => array(
        'X-API-KEY: ' . $api_key,
        'Content-Type: application/json'
    ),
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_VERBOSE => true
));

echo "<h2>Making API Request...</h2>\n";

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);

echo "<h2>Response Details</h2>\n";
echo "<p><strong>HTTP Status Code:</strong> " . $http_code . "</p>\n";

if ($curl_error) {
    echo "<p><strong>CURL Error:</strong> <span style='color: red;'>" . $curl_error . "</span></p>\n";
} else {
    echo "<p><strong>CURL Error:</strong> <span style='color: green;'>None</span></p>\n";
}

echo "<p><strong>Response Body:</strong></p>\n";
echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ccc;'>";
echo htmlspecialchars($response);
echo "</pre>\n";

// Try to decode JSON response
$decoded_response = json_decode($response, true);
if ($decoded_response !== null) {
    echo "<h2>Parsed Response</h2>\n";
    echo "<pre>" . json_encode($decoded_response, JSON_PRETTY_PRINT) . "</pre>\n";
    
    if (isset($decoded_response['status'])) {
        echo "<p><strong>Transaction Status:</strong> " . $decoded_response['status'] . "</p>\n";
    }
    
    if (isset($decoded_response['message'])) {
        echo "<p><strong>Message:</strong> " . $decoded_response['message'] . "</p>\n";
    }
} else {
    echo "<h2>JSON Parse Error</h2>\n";
    echo "<p>Could not parse response as JSON. JSON Error: " . json_last_error_msg() . "</p>\n";
}

curl_close($ch);

echo "<h2>Analysis</h2>\n";

switch ($http_code) {
    case 200:
        echo "<p style='color: green; font-weight: bold;'>✅ SUCCESS: API request completed successfully!</p>\n";
        break;
    case 401:
        echo "<p style='color: red; font-weight: bold;'>❌ AUTHENTICATION ERROR: Invalid API key</p>\n";
        echo "<p>Check that your API key is correct and has the right permissions.</p>\n";
        break;
    case 400:
        echo "<p style='color: red; font-weight: bold;'>❌ BAD REQUEST: Invalid request data</p>\n";
        echo "<p>Check the request format and required fields.</p>\n";
        break;
    case 403:
        echo "<p style='color: red; font-weight: bold;'>❌ FORBIDDEN: API key doesn't have permission</p>\n";
        break;
    case 404:
        echo "<p style='color: red; font-weight: bold;'>❌ NOT FOUND: API endpoint not found</p>\n";
        break;
    case 500:
        echo "<p style='color: red; font-weight: bold;'>❌ SERVER ERROR: EcoCash server error</p>\n";
        break;
    default:
        echo "<p style='color: orange; font-weight: bold;'>⚠️ UNEXPECTED STATUS: HTTP " . $http_code . "</p>\n";
}

echo "<h2>Next Steps</h2>\n";
echo "<ul>\n";
echo "<li>If you get a 401 error, verify your API key with EcoCash support</li>\n";
echo "<li>If you get a 200 response, the API is working - check WordPress integration</li>\n";
echo "<li>If you get network errors, check your server's outbound connectivity</li>\n";
echo "<li>Compare this direct test result with WordPress debug logs</li>\n";
echo "</ul>\n";

echo "<p><em>Generated UUID for this test: " . $test_data['sourceReference'] . "</em></p>\n";
?>
