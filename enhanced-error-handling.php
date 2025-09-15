<?php
/**
 * Enhanced Error Handling and Logging for EcoCash Plugin
 * 
 * Add this content to your existing class-ecocash-api.php file to improve
 * production error handling and debugging capabilities
 */

// Add these methods to your Ecocash_API class:

/**
 * Enhanced error logging with structured data
 */
private function log_error($message, $context = [], $level = 'error') {
    // Only log if debug mode is enabled or in critical situations
    if (get_option('ecocash_debug') !== 'yes' && $level !== 'critical') {
        return;
    }
    
    $log_entry = [
        'timestamp' => current_time('mysql'),
        'level' => $level,
        'message' => $message,
        'context' => $context,
        'user_id' => get_current_user_id(),
        'ip_address' => $this->get_client_ip(),
        'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
        'sandbox_mode' => $this->sandbox_mode,
        'trace' => wp_debug_backtrace_summary()
    ];
    
    // Log to WordPress error log
    error_log('EcoCash ' . strtoupper($level) . ': ' . wp_json_encode($log_entry));
    
    // Also save to custom log file for easier debugging
    $this->save_to_custom_log($log_entry);
    
    // For critical errors, also notify administrators
    if ($level === 'critical') {
        $this->notify_admin_of_critical_error($log_entry);
    }
}

/**
 * Save log entry to custom log file
 */
private function save_to_custom_log($log_entry) {
    $log_dir = ECOCASH_PLUGIN_PATH . 'logs';
    
    // Create logs directory if it doesn't exist
    if (!is_dir($log_dir)) {
        wp_mkdir_p($log_dir);
        // Add .htaccess to protect log files
        file_put_contents($log_dir . '/.htaccess', "Order deny,allow\nDeny from all");
    }
    
    $log_file = $log_dir . '/ecocash-' . date('Y-m') . '.log';
    
    if (is_writable($log_dir)) {
        $log_line = date('Y-m-d H:i:s') . ' [' . strtoupper($log_entry['level']) . '] ' . 
                   $log_entry['message'] . ' | Context: ' . wp_json_encode($log_entry['context']) . PHP_EOL;
        
        file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Get client IP address safely
 */
private function get_client_ip() {
    $ip_keys = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    
    foreach ($ip_keys as $key) {
        if (!empty($_SERVER[$key])) {
            $ip = $_SERVER[$key];
            // Handle comma-separated IPs (for proxies)
            if (strpos($ip, ',') !== false) {
                $ip = trim(explode(',', $ip)[0]);
            }
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
}

/**
 * Notify administrators of critical errors
 */
private function notify_admin_of_critical_error($log_entry) {
    $admin_email = get_option('admin_email');
    if (!$admin_email) return;
    
    $subject = '[EcoCash Plugin] Critical Error Alert';
    $message = "A critical error occurred in the EcoCash payment plugin:\n\n";
    $message .= "Time: " . $log_entry['timestamp'] . "\n";
    $message .= "Message: " . $log_entry['message'] . "\n";
    $message .= "Context: " . wp_json_encode($log_entry['context'], JSON_PRETTY_PRINT) . "\n";
    $message .= "Site: " . get_site_url() . "\n\n";
    $message .= "Please check the plugin logs for more details.";
    
    wp_mail($admin_email, $subject, $message);
}

/**
 * Enhanced API request with retry logic and better error handling
 */
private function make_api_request_with_retry($method, $url, $data = [], $max_retries = 3) {
    $attempts = 0;
    $last_error = null;
    
    while ($attempts < $max_retries) {
        $attempts++;
        
        $this->log_error("API request attempt $attempts", [
            'method' => $method,
            'url' => $url,
            'attempt' => $attempts
        ], 'info');
        
        // Use the existing SDK method but with enhanced error handling
        if (!$this->sdk) {
            $this->log_error('SDK not initialized', [], 'critical');
            return ['success' => false, 'message' => 'Configuration error'];
        }
        
        // Make the request using existing SDK method
        if ($method === 'payment') {
            $result = $this->sdk->make_payment($data);
        } elseif ($method === 'lookup') {
            $result = $this->sdk->lookup_transaction($data);
        } elseif ($method === 'refund') {
            $result = $this->sdk->process_refund($data);
        } else {
            return ['success' => false, 'message' => 'Invalid request method'];
        }
        
        // If successful, return immediately
        if ($result['success']) {
            $this->log_error("API request successful", [
                'method' => $method,
                'attempts' => $attempts
            ], 'info');
            return $result;
        }
        
        $last_error = $result;
        
        // Check if this is a retryable error
        if (isset($result['error']['status_code'])) {
            $status_code = $result['error']['status_code'];
            
            // Don't retry on authentication or client errors
            if ($status_code >= 400 && $status_code < 500 && $status_code !== 429) {
                $this->log_error("Non-retryable error", [
                    'status_code' => $status_code,
                    'message' => $result['error']['message']
                ], 'error');
                break;
            }
        }
        
        // Wait before retrying (exponential backoff)
        if ($attempts < $max_retries) {
            $wait_time = pow(2, $attempts); // 2, 4, 8 seconds
            $this->log_error("Retrying after $wait_time seconds", [
                'attempt' => $attempts,
                'max_retries' => $max_retries
            ], 'info');
            sleep($wait_time);
        }
    }
    
    // All retries failed
    $this->log_error("API request failed after $max_retries attempts", [
        'method' => $method,
        'last_error' => $last_error
    ], 'error');
    
    return $last_error ?: ['success' => false, 'message' => 'Request failed after retries'];
}

/**
 * Enhanced order payment processing with better error handling
 */
public function process_order_payment_enhanced($order, $mobile_number) {
    try {
        if (!$this->sdk) {
            throw new Exception('API not configured properly');
        }
        
        // Validate order
        if (!$order || !is_a($order, 'WC_Order')) {
            throw new Exception('Invalid order object');
        }
        
        // Format and validate mobile number
        $formatted_mobile = Ecocash_SDK::format_mobile_number($mobile_number);
        if (!$formatted_mobile) {
            throw new Exception('Invalid mobile number format');
        }
        
        // Validate amount
        $amount = $order->get_total();
        if (!is_numeric($amount) || $amount <= 0) {
            throw new Exception('Invalid order amount');
        }
        
        // Check for duplicate transactions
        if ($this->check_duplicate_transaction($order->get_id())) {
            throw new Exception('Duplicate transaction detected');
        }
        
        // Generate unique reference with additional entropy
        $reference = 'WC-' . $order->get_id() . '-' . time() . '-' . wp_rand(100, 999);
        
        $payment_data = [
            'mobileNumber' => $formatted_mobile,
            'amount' => $amount,
            'reason' => 'Payment for Order #' . $order->get_order_number(),
            'currency' => $order->get_currency(),
            'reference' => $reference
        ];
        
        // Log the payment attempt
        $this->log_error('Payment attempt started', [
            'order_id' => $order->get_id(),
            'amount' => $amount,
            'currency' => $order->get_currency(),
            'mobile' => substr($formatted_mobile, 0, 6) . 'XXXXX' // Masked for security
        ], 'info');
        
        // Make payment request with retry logic
        $result = $this->make_api_request_with_retry('payment', null, $payment_data);
        
        // Log the transaction regardless of outcome
        $this->log_transaction($order->get_id(), $reference, $formatted_mobile, $amount, $order->get_currency(), $result, 'payment');
        
        if ($result['success']) {
            // Success handling
            $order->add_order_note(sprintf(
                __('EcoCash payment initiated successfully. Reference: %s, Mobile: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                $reference,
                $formatted_mobile
            ));
            
            $order->update_meta_data('_ecocash_reference', $reference);
            $order->update_meta_data('_ecocash_mobile', $formatted_mobile);
            $order->update_meta_data('_ecocash_initiated_at', current_time('mysql'));
            $order->save();
            
            // Schedule a status check for later
            $this->schedule_payment_status_check($order->get_id(), $reference);
            
            return [
                'success' => true,
                'reference' => $reference,
                'message' => 'Payment request sent successfully'
            ];
            
        } else {
            // Failure handling
            $error_message = isset($result['error']['message']) ? $result['error']['message'] : 'Unknown error';
            
            $order->add_order_note(sprintf(
                __('EcoCash payment failed. Error: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                $error_message
            ));
            
            return [
                'success' => false,
                'message' => $error_message
            ];
        }
        
    } catch (Exception $e) {
        $this->log_error('Payment processing exception', [
            'order_id' => $order ? $order->get_id() : 'unknown',
            'exception' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 'critical');
        
        return [
            'success' => false,
            'message' => 'Payment processing error: ' . $e->getMessage()
        ];
    }
}

/**
 * Check for duplicate transactions
 */
private function check_duplicate_transaction($order_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ecocash_transactions';
    
    // Check for recent transactions for this order
    $recent_transaction = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name 
         WHERE order_id = %d 
         AND transaction_type = 'payment' 
         AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        $order_id
    ));
    
    return $recent_transaction > 0;
}

/**
 * Schedule payment status check
 */
private function schedule_payment_status_check($order_id, $reference) {
    // Schedule status checks at intervals: 30s, 2min, 5min, 10min
    $intervals = [30, 120, 300, 600];
    
    foreach ($intervals as $delay) {
        wp_schedule_single_event(
            time() + $delay,
            'ecocash_check_payment_status',
            [$order_id, $reference]
        );
    }
}

/**
 * Enhanced transaction status checking
 */
public function check_transaction_status_enhanced($order_id, $reference = null, $mobile_number = null) {
    try {
        if (!$this->sdk) {
            throw new Exception('API not configured');
        }
        
        $order = wc_get_order($order_id);
        if (!$order) {
            throw new Exception('Order not found');
        }
        
        // Get stored values if not provided
        $reference = $reference ?: $order->get_meta('_ecocash_reference');
        $mobile_number = $mobile_number ?: $order->get_meta('_ecocash_mobile');
        
        if (!$reference || !$mobile_number) {
            throw new Exception('Missing transaction reference or mobile number');
        }
        
        $lookup_data = [
            'mobileNumber' => $mobile_number,
            'reference' => $reference
        ];
        
        $result = $this->make_api_request_with_retry('lookup', null, $lookup_data);
        
        if ($result['success']) {
            $status = isset($result['data']['status']) ? $result['data']['status'] : 'unknown';
            
            // Update transaction log
            $this->update_transaction_status($reference, $result['data']);
            
            // Handle different status responses
            $this->handle_payment_status_update($order, $status, $result['data']);
            
            return [
                'success' => true,
                'data' => $result['data']
            ];
        } else {
            $this->log_error('Status check failed', [
                'order_id' => $order_id,
                'reference' => $reference,
                'error' => $result['error']
            ], 'error');
            
            return [
                'success' => false,
                'message' => $result['error']['message']
            ];
        }
        
    } catch (Exception $e) {
        $this->log_error('Status check exception', [
            'order_id' => $order_id,
            'exception' => $e->getMessage()
        ], 'error');
        
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

/**
 * Handle payment status updates
 */
private function handle_payment_status_update($order, $status, $data) {
    $current_status = $order->get_status();
    
    switch (strtolower($status)) {
        case 'successful':
        case 'completed':
            if ($current_status === 'on-hold' || $current_status === 'pending') {
                $order->payment_complete();
                $order->add_order_note(__('EcoCash payment confirmed and completed.', ECOCASH_PLUGIN_TEXT_DOMAIN));
                
                // Auto-complete if enabled
                if (get_option('ecocash_auto_capture') === 'yes') {
                    $order->update_status('completed');
                }
            }
            break;
            
        case 'failed':
        case 'cancelled':
            if ($current_status !== 'failed' && $current_status !== 'cancelled') {
                $order->update_status('failed', __('EcoCash payment failed or was cancelled.', ECOCASH_PLUGIN_TEXT_DOMAIN));
            }
            break;
            
        case 'pending':
        case 'initiated':
            // Keep as on-hold, add note about status
            $order->add_order_note(__('EcoCash payment is still pending.', ECOCASH_PLUGIN_TEXT_DOMAIN));
            break;
            
        default:
            $this->log_error('Unknown payment status received', [
                'order_id' => $order->get_id(),
                'status' => $status,
                'data' => $data
            ], 'warning');
    }
}
