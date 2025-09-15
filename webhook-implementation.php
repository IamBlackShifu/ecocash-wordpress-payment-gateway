<?php
/**
 * EcoCash Webhook Handler
 * 
 * Handles real-time payment notifications from EcoCash
 * Add this as a new file: includes/class-ecocash-webhook.php
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ecocash_Webhook {
    
    private $webhook_secret;
    
    public function __construct() {
        // Initialize webhook handling
        add_action('rest_api_init', array($this, 'register_webhook_endpoint'));
        add_action('wp_ajax_nopriv_ecocash_webhook', array($this, 'handle_webhook'));
        add_action('wp_ajax_ecocash_webhook', array($this, 'handle_webhook'));
        
        // Get webhook secret from settings
        $this->webhook_secret = get_option('ecocash_webhook_secret', '');
    }
    
    /**
     * Register REST API endpoint for webhooks
     */
    public function register_webhook_endpoint() {
        register_rest_route('ecocash/v1', '/webhook', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_webhook'),
            'permission_callback' => array($this, 'verify_webhook_signature'),
        ));
    }
    
    /**
     * Verify webhook signature for security
     */
    public function verify_webhook_signature($request) {
        // If no secret is configured, allow (for testing)
        if (empty($this->webhook_secret)) {
            return true;
        }
        
        $signature = $request->get_header('X-EcoCash-Signature');
        if (!$signature) {
            return new WP_Error('no_signature', 'Missing webhook signature', array('status' => 401));
        }
        
        $body = $request->get_body();
        $expected_signature = hash_hmac('sha256', $body, $this->webhook_secret);
        
        if (!hash_equals($expected_signature, $signature)) {
            return new WP_Error('invalid_signature', 'Invalid webhook signature', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Process incoming webhook
     */
    public function process_webhook($request) {
        try {
            $body = $request->get_body();
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return new WP_Error('invalid_json', 'Invalid JSON payload', array('status' => 400));
            }
            
            // Log the webhook for debugging
            $this->log_webhook('Webhook received', $data);
            
            // Validate required fields
            if (!isset($data['transactionReference']) || !isset($data['status'])) {
                return new WP_Error('missing_fields', 'Missing required fields', array('status' => 400));
            }
            
            // Process the webhook
            $result = $this->handle_payment_notification($data);
            
            if ($result['success']) {
                return rest_ensure_response(array(
                    'status' => 'success',
                    'message' => 'Webhook processed successfully'
                ));
            } else {
                return new WP_Error('processing_failed', $result['message'], array('status' => 500));
            }
            
        } catch (Exception $e) {
            $this->log_webhook('Webhook processing error', array(
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ));
            
            return new WP_Error('exception', 'Internal error', array('status' => 500));
        }
    }
    
    /**
     * Handle payment notification from webhook
     */
    private function handle_payment_notification($data) {
        global $wpdb;
        
        $transaction_reference = sanitize_text_field($data['transactionReference']);
        $status = sanitize_text_field($data['status']);
        $ecocash_reference = isset($data['ecocashReference']) ? sanitize_text_field($data['ecocashReference']) : null;
        
        // Find the order associated with this transaction
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE transaction_reference = %s",
            $transaction_reference
        ));
        
        if (!$transaction) {
            $this->log_webhook('Transaction not found', array(
                'reference' => $transaction_reference
            ));
            return array('success' => false, 'message' => 'Transaction not found');
        }
        
        // Get the order
        $order = wc_get_order($transaction->order_id);
        if (!$order) {
            $this->log_webhook('Order not found', array(
                'order_id' => $transaction->order_id,
                'reference' => $transaction_reference
            ));
            return array('success' => false, 'message' => 'Order not found');
        }
        
        // Update transaction in database
        $wpdb->update(
            $table_name,
            array(
                'status' => $status,
                'ecocash_reference' => $ecocash_reference,
                'updated_at' => current_time('mysql')
            ),
            array('transaction_reference' => $transaction_reference),
            array('%s', '%s', '%s'),
            array('%s')
        );
        
        // Handle different payment statuses
        $this->update_order_status($order, $status, $data);
        
        $this->log_webhook('Payment status updated', array(
            'order_id' => $order->get_id(),
            'status' => $status,
            'reference' => $transaction_reference
        ));
        
        return array('success' => true, 'message' => 'Payment status updated');
    }
    
    /**
     * Update order status based on payment status
     */
    private function update_order_status($order, $status, $webhook_data) {
        $current_status = $order->get_status();
        
        switch (strtolower($status)) {
            case 'successful':
            case 'completed':
            case 'paid':
                if (in_array($current_status, array('pending', 'on-hold'))) {
                    $order->payment_complete();
                    $order->add_order_note(sprintf(
                        __('EcoCash payment completed via webhook. Reference: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                        isset($webhook_data['ecocashReference']) ? $webhook_data['ecocashReference'] : 'N/A'
                    ));
                    
                    // Auto-complete if enabled
                    if (get_option('ecocash_auto_complete') === 'yes') {
                        $order->update_status('completed');
                    }
                    
                    // Trigger WooCommerce payment complete actions
                    do_action('woocommerce_payment_complete', $order->get_id());
                }
                break;
                
            case 'failed':
            case 'cancelled':
            case 'declined':
                if (!in_array($current_status, array('failed', 'cancelled'))) {
                    $reason = isset($webhook_data['failureReason']) ? $webhook_data['failureReason'] : 'Payment failed';
                    $order->update_status('failed', sprintf(
                        __('EcoCash payment failed: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                        $reason
                    ));
                    
                    // Restore stock if payment failed
                    wc_increase_stock_levels($order);
                }
                break;
                
            case 'pending':
            case 'initiated':
            case 'processing':
                $order->add_order_note(sprintf(
                    __('EcoCash payment status update: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                    $status
                ));
                break;
                
            case 'expired':
            case 'timeout':
                $order->update_status('cancelled', __('EcoCash payment expired or timed out.', ECOCASH_PLUGIN_TEXT_DOMAIN));
                wc_increase_stock_levels($order);
                break;
                
            default:
                $this->log_webhook('Unknown payment status', array(
                    'status' => $status,
                    'order_id' => $order->get_id(),
                    'webhook_data' => $webhook_data
                ));
                
                $order->add_order_note(sprintf(
                    __('EcoCash payment status update (unknown): %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                    $status
                ));
        }
        
        // Save any metadata from the webhook
        if (isset($webhook_data['ecocashReference'])) {
            $order->update_meta_data('_ecocash_reference_confirmed', $webhook_data['ecocashReference']);
        }
        
        if (isset($webhook_data['timestamp'])) {
            $order->update_meta_data('_ecocash_webhook_timestamp', $webhook_data['timestamp']);
        }
        
        $order->save();
    }
    
    /**
     * Log webhook events
     */
    private function log_webhook($message, $data = array()) {
        if (get_option('ecocash_debug') === 'yes') {
            $log_entry = array(
                'timestamp' => current_time('mysql'),
                'message' => $message,
                'data' => $data,
                'ip' => $this->get_client_ip(),
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : ''
            );
            
            error_log('EcoCash Webhook: ' . wp_json_encode($log_entry));
            
            // Also save to webhook-specific log
            $log_dir = ECOCASH_PLUGIN_PATH . 'logs';
            if (!is_dir($log_dir)) {
                wp_mkdir_p($log_dir);
            }
            
            $log_file = $log_dir . '/webhook-' . date('Y-m') . '.log';
            if (is_writable($log_dir)) {
                file_put_contents(
                    $log_file,
                    date('Y-m-d H:i:s') . ' - ' . $message . ' | ' . wp_json_encode($data) . PHP_EOL,
                    FILE_APPEND | LOCK_EX
                );
            }
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Handle legacy webhook endpoint (for backward compatibility)
     */
    public function handle_webhook() {
        // Verify nonce if it exists
        if (isset($_POST['nonce']) && !wp_verify_nonce($_POST['nonce'], 'ecocash_webhook')) {
            wp_die('Security check failed');
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            wp_die('Invalid JSON');
        }
        
        $result = $this->handle_payment_notification($data);
        
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }
    
    /**
     * Get webhook URL for configuration
     */
    public static function get_webhook_url() {
        return rest_url('ecocash/v1/webhook');
    }
    
    /**
     * Test webhook functionality
     */
    public function test_webhook($transaction_reference, $status = 'successful') {
        $test_data = array(
            'transactionReference' => $transaction_reference,
            'status' => $status,
            'ecocashReference' => 'TEST-' . time(),
            'timestamp' => current_time('mysql'),
            'amount' => 10.00,
            'currency' => 'USD'
        );
        
        return $this->handle_payment_notification($test_data);
    }
}
