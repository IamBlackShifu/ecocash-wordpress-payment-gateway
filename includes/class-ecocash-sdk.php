<?php
/**
 * Ecocash SDK for PHP
 * 
 * PHP implementation of the Ecocash Payment Gateway SDK
 * Based on the Dart SDK functionality
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ecocash_SDK {
    
    /**
     * API Key for authentication
     */
    private $api_key;
    
    /**
     * Whether to use sandbox mode
     */
    private $sandbox_mode;
    
    /**
     * Base URLs for different environments
     */
    const BASE_URL = 'https://developers.ecocash.co.zw/api/ecocash_pay';
    
    
    // Payment endpoints
    const PAYMENT_LIVE_URL = self::BASE_URL . '/api/v2/payment/instant/c2b/live';
    const PAYMENT_SANDBOX_URL = 'https://developers.ecocash.co.zw/api/ecocash_pay/api/v2/payment/instant/c2b/sandbox';
    // self::BASE_URL . '/api/v2/payment/instant/c2b/sandbox';
    
    // Lookup endpoints
    const LOOKUP_LIVE_URL = self::BASE_URL . '/api/v1/transaction/c2b/status/live';
    const LOOKUP_SANDBOX_URL = self::BASE_URL . '/api/v1/transaction/c2b/status/sandbox';
    
    // Refund endpoints
    const REFUND_LIVE_URL = self::BASE_URL . '/api/v2/refund/instant/c2b/live';
    const REFUND_SANDBOX_URL = self::BASE_URL . '/api/v2/refund/instant/c2b/sandbox';
    
    /**
     * Initialize the Ecocash SDK
     * 
     * @param string $api_key The API key for authentication
     * @param bool $sandbox_mode Whether to use sandbox mode (default: false)
     */
    public function __construct($api_key, $sandbox_mode = false) {
        $this->api_key = $api_key;
        $this->sandbox_mode = $sandbox_mode;
    }
    
    /**
     * Make a payment request
     * 
     * @param array $payment_data Payment request data
     * @return array Result array with success/error information
     */
    public function make_payment($payment_data) {
        $url = $this->sandbox_mode ? self::PAYMENT_SANDBOX_URL : self::PAYMENT_LIVE_URL;
        
        // Validate required fields
        $required_fields = ['mobileNumber', 'amount', 'reason', 'currency', 'reference'];
        foreach ($required_fields as $field) {
            if (empty($payment_data[$field])) {
                return $this->error_result("Missing required field: {$field}", 400);
            }
        }
        
        // Validate mobile number format (Zimbabwe format)
        if (!$this->validate_mobile_number($payment_data['mobileNumber'])) {
            return $this->error_result('Invalid mobile number format. Expected format: 263xxxxxxxxx', 400);
        }
        
        // Validate amount
        if (!is_numeric($payment_data['amount']) || $payment_data['amount'] <= 0) {
            return $this->error_result('Invalid amount. Must be a positive number.', 400);
        }
        
        // Validate currency
        $supported_currencies = ['USD', 'ZWL', 'ZiG'];
        if (!in_array($payment_data['currency'], $supported_currencies)) {
            return $this->error_result('Unsupported currency. Supported currencies: ' . implode(', ', $supported_currencies), 400);
        }
        
        // Prepare request data
        $request_data = array(
            'customerMsisdn' => $payment_data['mobileNumber'],
            'amount' => floatval($payment_data['amount']),
            'reason' => $payment_data['reason'],
            'currency' => $payment_data['currency'],
            'sourceReference' => $payment_data['reference']
        );
        
        return $this->make_request('POST', $url, $request_data);
    }
    
    /**
     * Lookup transaction status
     * 
     * @param array $lookup_data Lookup request data
     * @return array Result array with success/error information
     */
    public function lookup_transaction($lookup_data) {
        $url = $this->sandbox_mode ? self::LOOKUP_SANDBOX_URL : self::LOOKUP_LIVE_URL;
        
        // Validate required fields
        $required_fields = ['mobileNumber', 'reference'];
        foreach ($required_fields as $field) {
            if (empty($lookup_data[$field])) {
                return $this->error_result("Missing required field: {$field}", 400);
            }
        }
        
        // Validate mobile number format
        if (!$this->validate_mobile_number($lookup_data['mobileNumber'])) {
            return $this->error_result('Invalid mobile number format. Expected format: 263xxxxxxxxx', 400);
        }
        
        $request_data = array(
            'customerMsisdn' => $lookup_data['mobileNumber'],
            'sourceReference' => $lookup_data['reference']
        );
        
        return $this->make_request('POST', $url, $request_data);
    }
    
    /**
     * Process a refund
     * 
     * @param array $refund_data Refund request data
     * @return array Result array with success/error information
     */
    public function process_refund($refund_data) {
        $url = $this->sandbox_mode ? self::REFUND_SANDBOX_URL : self::REFUND_LIVE_URL;
        
        // Validate required fields
        $required_fields = [
            'originalEcocashTransactionReference', 
            'refundCorrelator', 
            'sourceMobileNumber', 
            'amount', 
            'clientName', 
            'currency', 
            'reasonForRefund'
        ];
        
        foreach ($required_fields as $field) {
            if (empty($refund_data[$field])) {
                return $this->error_result("Missing required field: {$field}", 400);
            }
        }
        
        // Validate mobile number format
        if (!$this->validate_mobile_number($refund_data['sourceMobileNumber'])) {
            return $this->error_result('Invalid mobile number format. Expected format: 263xxxxxxxxx', 400);
        }
        
        // Validate amount
        if (!is_numeric($refund_data['amount']) || $refund_data['amount'] <= 0) {
            return $this->error_result('Invalid amount. Must be a positive number.', 400);
        }
        
        $request_data = array(
            'originalEcocashTransactionReference' => $refund_data['originalEcocashTransactionReference'],
            'refundCorrelator' => $refund_data['refundCorrelator'],
            'sourceMobileNumber' => $refund_data['sourceMobileNumber'],
            'amount' => floatval($refund_data['amount']),
            'clientName' => $refund_data['clientName'],
            'currency' => $refund_data['currency'],
            'reasonForRefund' => $refund_data['reasonForRefund']
        );
        
        return $this->make_request('POST', $url, $request_data);
    }
    
    /**
     * Make HTTP request to Ecocash API
     * 
     * @param string $method HTTP method
     * @param string $url API endpoint URL
     * @param array $data Request data
     * @return array Result array
     */
    private function make_request($method, $url, $data = array()) {
        $headers = array(
            'Content-Type: application/json',
            'X-API-KEY: ' . $this->api_key
        );
        
        // Debug logging for development
        if ($this->sandbox_mode || get_option('ecocash_debug') === 'yes') {
            error_log('EcoCash API Request Debug:');
            error_log('- Method: ' . $method);
            error_log('- URL: ' . $url);
            error_log('- API Key (first 10 chars): ' . substr($this->api_key, 0, 10) . '...');
            error_log('- API Key Length: ' . strlen($this->api_key));
            error_log('- API Key Empty: ' . (empty($this->api_key) ? 'YES' : 'NO'));
            error_log('- Sandbox Mode: ' . ($this->sandbox_mode ? 'YES' : 'NO'));
            error_log('- Request Data: ' . json_encode($data));
        }
        
        $args = array(
            'method' => $method,
            'headers' => $headers,
            'body' => json_encode($data),
            'timeout' => 30,
            'sslverify' => true
        );
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $error_message = 'Network error: ' . $response->get_error_message();
            error_log('EcoCash API Network Error: ' . $error_message);
            return $this->error_result($error_message, 500);
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        // Enhanced debug logging
        if ($this->sandbox_mode || get_option('ecocash_debug') === 'yes') {
            error_log('EcoCash API Response Debug:');
            error_log('- Status Code: ' . $status_code);
            error_log('- Response Body: ' . $body);
            error_log('- Response Headers: ' . json_encode(wp_remote_retrieve_headers($response)));
        }
        
        // Log the request for debugging (only in sandbox mode or if debug is enabled)
        if ($this->sandbox_mode || get_option('ecocash_debug') === 'yes') {
            $this->log_request($method, $url, $data, $status_code, $body);
        }
        
        if ($status_code === 200) {
            $decoded_body = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('EcoCash API JSON Decode Error: ' . json_last_error_msg());
                return $this->error_result('Invalid JSON response from API', 500, $body);
            }
            return $this->success_result($decoded_body);
        } else {
            $error_message = $this->get_error_message($status_code);
            
            // Try to decode error response for more details
            $decoded_error = json_decode($body, true);
            if ($decoded_error && isset($decoded_error['message'])) {
                $error_message .= ' - ' . $decoded_error['message'];
            }
            
            error_log('EcoCash API Error Response: Status ' . $status_code . ' - ' . $error_message);
            return $this->error_result($error_message, $status_code, $body);
        }
    }
    
    /**
     * Validate Zimbabwean mobile number format
     * 
     * @param string $mobile_number Mobile number to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_mobile_number($mobile_number) {
        // Remove any spaces or special characters
        $mobile_number = preg_replace('/[^0-9]/', '', $mobile_number);
        
        // Check if it matches Zimbabwe format: 263xxxxxxxxx (12 digits)
        return preg_match('/^263[0-9]{9}$/', $mobile_number);
    }
    
    /**
     * Create success result
     * 
     * @param mixed $data Response data
     * @return array Success result
     */
    private function success_result($data = null) {
        return array(
            'success' => true,
            'data' => $data,
            'error' => null
        );
    }
    
    /**
     * Create error result
     * 
     * @param string $message Error message
     * @param int $status_code HTTP status code
     * @param string $details Additional error details
     * @return array Error result
     */
    private function error_result($message, $status_code = 500, $details = null) {
        return array(
            'success' => false,
            'data' => null,
            'error' => array(
                'message' => $message,
                'status_code' => $status_code,
                'details' => $details
            )
        );
    }
    
    /**
     * Get error message based on HTTP status code
     * 
     * @param int $status_code HTTP status code
     * @return string Error message
     */
    private function get_error_message($status_code) {
        switch ($status_code) {
            case 400:
                return 'Bad Request: The request was unacceptable, often due to missing a required parameter.';
            case 401:
                return 'Unauthorized: No valid API key provided.';
            case 402:
                return 'Request Failed: The parameters were valid but the request failed.';
            case 403:
                return 'Forbidden: The API key doesn\'t have permissions to perform the request.';
            case 404:
                return 'Not Found: The requested resource doesn\'t exist.';
            case 409:
                return 'Conflict: The request conflicts with another request.';
            case 429:
                return 'Too Many Requests: Too many requests hit the API too quickly.';
            case 500:
            default:
                return 'Server Error: Something went wrong on Ecocash\'s end.';
        }
    }
    
    /**
     * Log API request for debugging
     * 
     * @param string $method HTTP method
     * @param string $url Request URL
     * @param array $data Request data
     * @param int $status_code Response status code
     * @param string $response Response body
     */
    private function log_request($method, $url, $data, $status_code, $response) {
        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'method' => $method,
            'url' => $url,
            'request_data' => $data,
            'status_code' => $status_code,
            'response' => $response
        );
        
        // Log to WordPress error log
        error_log('Ecocash API Request: ' . json_encode($log_entry));
        
        // Also save to custom log file if possible
        $log_file = ECOCASH_PLUGIN_PATH . 'logs/ecocash.log';
        if (is_writable(dirname($log_file))) {
            file_put_contents($log_file, date('Y-m-d H:i:s') . ' - ' . json_encode($log_entry) . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
    
    /**
     * Generate unique transaction reference in UUID format
     * 
     * @param string $prefix Optional prefix for the reference
     * @return string Unique UUID-formatted reference
     */
    public static function generate_reference($prefix = 'TXN') {
        // Generate a UUID v4 format
        return sprintf('%s-%04x%04x-%04x-%04x-%04x%04x%04x',
            strtolower($prefix),
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Generate proper UUID v4
     * 
     * @return string UUID v4 formatted string
     */
    public static function generate_uuid() {
        return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }
    
    /**
     * Format mobile number to Zimbabwe standard
     * 
     * @param string $mobile_number Mobile number input
     * @return string|false Formatted mobile number or false if invalid
     */
    public static function format_mobile_number($mobile_number) {
        // Remove any non-numeric characters
        $mobile_number = preg_replace('/[^0-9]/', '', $mobile_number);
        
        // Handle different input formats
        if (strlen($mobile_number) === 10 && substr($mobile_number, 0, 1) === '0') {
            // Format: 0771234567 -> 263771234567
            $mobile_number = '263' . substr($mobile_number, 1);
        } elseif (strlen($mobile_number) === 9) {
            // Format: 771234567 -> 263771234567
            $mobile_number = '263' . $mobile_number;
        } elseif (strlen($mobile_number) === 12 && substr($mobile_number, 0, 3) === '263') {
            // Already in correct format: 263771234567
            // No change needed
        } else {
            return false; // Invalid format
        }
        
        // Final validation
        if (preg_match('/^263[0-9]{9}$/', $mobile_number)) {
            return $mobile_number;
        }
        
        return false;
    }
}