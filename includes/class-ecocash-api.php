<?php
/**
 * Ecocash API Wrapper Class
 * 
 * Handles API interactions and provides convenience methods
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ecocash_API {
    
    private $sdk;
    private $api_key;
    private $sandbox_mode;
    
    public function __construct($api_key = null, $sandbox_mode = null) {
        $this->api_key = $api_key ?: $this->get_api_key();
        $this->sandbox_mode = $sandbox_mode !== null ? $sandbox_mode : (get_option('ecocash_sandbox_mode') === 'yes');
        
        if ($this->api_key) {
            $this->sdk = new Ecocash_SDK($this->api_key, $this->sandbox_mode);
        }
    }
    
    /**
     * Get API key based on current mode
     */
    private function get_api_key() {
        if ($this->sandbox_mode) {
            return get_option('ecocash_api_key_sandbox');
        } else {
            return get_option('ecocash_api_key_live');
        }
    }
    
    /**
     * Test API connection
     */
    public function test_connection() {
        if (!$this->sdk) {
            return array('success' => false, 'message' => 'No API key configured');
        }
        
        // Try a simple lookup with a dummy reference to test connectivity
        $test_data = array(
            'mobileNumber' => '263771234567',
            'reference' => 'TEST-' . time()
        );
        
        $result = $this->sdk->lookup_transaction($test_data);
        
        // Even if the lookup fails (which is expected for a test reference),
        // we consider it successful if we get a proper API response
        if ($result['success'] || ($result['error'] && $result['error']['status_code'] !== 401)) {
            return array('success' => true, 'message' => 'API connection successful');
        } else {
            return array('success' => false, 'message' => $result['error']['message']);
        }
    }
    
    /**
     * Process payment for WooCommerce order
     */
    public function process_order_payment($order, $mobile_number) {
        if (!$this->sdk) {
            return array('success' => false, 'message' => 'API not configured');
        }
        
        // Format mobile number
        $formatted_mobile = Ecocash_SDK::format_mobile_number($mobile_number);
        if (!$formatted_mobile) {
            return array('success' => false, 'message' => 'Invalid mobile number format');
        }
        
        // Generate unique reference
        $reference = 'WC-' . $order->get_id() . '-' . time();
        
        // Get order details
        $amount = $order->get_total();
        $currency = $order->get_currency();
        $reason = 'Payment for Order #' . $order->get_order_number();
        
        // Validate currency
        $supported_currencies = array('USD', 'ZWL', 'ZiG');
        if (!in_array($currency, $supported_currencies)) {
            return array('success' => false, 'message' => 'Unsupported currency: ' . $currency);
        }
        
        $payment_data = array(
            'mobileNumber' => $formatted_mobile,
            'amount' => $amount,
            'reason' => $reason,
            'currency' => $currency,
            'reference' => $reference
        );
        
        // Make payment request
        $result = $this->sdk->make_payment($payment_data);
        
        // Log transaction
        $this->log_transaction($order->get_id(), $reference, $formatted_mobile, $amount, $currency, $result, 'payment');
        
        if ($result['success']) {
            // Add order note
            $order->add_order_note(sprintf(
                __('Ecocash payment initiated. Reference: %s, Mobile: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                $reference,
                $formatted_mobile
            ));
            
            // Store transaction reference in order meta
            $order->update_meta_data('_ecocash_reference', $reference);
            $order->update_meta_data('_ecocash_mobile', $formatted_mobile);
            $order->save();
            
            return array(
                'success' => true,
                'reference' => $reference,
                'message' => 'Payment request sent successfully'
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['error']['message']
            );
        }
    }
    
    /**
     * Check transaction status
     */
    public function check_transaction_status($order_id, $reference = null, $mobile_number = null) {
        if (!$this->sdk) {
            return array('success' => false, 'message' => 'API not configured');
        }
        
        // Get stored values if not provided
        if (!$reference || !$mobile_number) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return array('success' => false, 'message' => 'Order not found');
            }
            
            $reference = $reference ?: $order->get_meta('_ecocash_reference');
            $mobile_number = $mobile_number ?: $order->get_meta('_ecocash_mobile');
        }
        
        if (!$reference || !$mobile_number) {
            return array('success' => false, 'message' => 'Missing transaction reference or mobile number');
        }
        
        $lookup_data = array(
            'mobileNumber' => $mobile_number,
            'reference' => $reference
        );
        
        $result = $this->sdk->lookup_transaction($lookup_data);
        
        if ($result['success']) {
            // Update transaction log
            $this->update_transaction_status($reference, $result['data']);
            
            return array(
                'success' => true,
                'data' => $result['data']
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['error']['message']
            );
        }
    }
    
    /**
     * Process refund
     */
    public function process_order_refund($order, $amount, $reason = '') {
        if (!$this->sdk) {
            return array('success' => false, 'message' => 'API not configured');
        }
        
        $ecocash_reference = $order->get_meta('_ecocash_reference');
        $mobile_number = $order->get_meta('_ecocash_mobile');
        
        if (!$ecocash_reference || !$mobile_number) {
            return array('success' => false, 'message' => 'No Ecocash transaction found for this order');
        }
        
        // Get the original Ecocash transaction reference from the database
        $original_ecocash_ref = $this->get_ecocash_transaction_reference($ecocash_reference);
        if (!$original_ecocash_ref) {
            return array('success' => false, 'message' => 'Original Ecocash transaction reference not found');
        }
        
        $refund_correlator = 'REF-' . $order->get_id() . '-' . time();
        $client_name = get_bloginfo('name');
        
        $refund_data = array(
            'originalEcocashTransactionReference' => $original_ecocash_ref,
            'refundCorrelator' => $refund_correlator,
            'sourceMobileNumber' => $mobile_number,
            'amount' => $amount,
            'clientName' => $client_name,
            'currency' => $order->get_currency(),
            'reasonForRefund' => $reason ?: 'Order refund'
        );
        
        $result = $this->sdk->process_refund($refund_data);
        
        // Log refund transaction
        $this->log_transaction($order->get_id(), $refund_correlator, $mobile_number, $amount, $order->get_currency(), $result, 'refund');
        
        if ($result['success']) {
            $order->add_order_note(sprintf(
                __('Ecocash refund processed. Amount: %s %s, Reference: %s', ECOCASH_PLUGIN_TEXT_DOMAIN),
                $amount,
                $order->get_currency(),
                $refund_correlator
            ));
            
            return array(
                'success' => true,
                'reference' => $refund_correlator,
                'data' => $result['data']
            );
        } else {
            return array(
                'success' => false,
                'message' => $result['error']['message']
            );
        }
    }
    
    /**
     * Log transaction to database
     */
    private function log_transaction($order_id, $reference, $mobile_number, $amount, $currency, $result, $type = 'payment') {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        
        $status = $result['success'] ? 'initiated' : 'failed';
        $ecocash_reference = null;
        
        if ($result['success'] && isset($result['data']['ecocashReference'])) {
            $ecocash_reference = $result['data']['ecocashReference'];
        }
        
        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'transaction_reference' => $reference,
                'ecocash_reference' => $ecocash_reference,
                'mobile_number' => $mobile_number,
                'amount' => $amount,
                'currency' => $currency,
                'status' => $status,
                'reason' => $result['success'] ? 'Transaction initiated' : $result['error']['message'],
                'transaction_type' => $type,
                'sandbox_mode' => $this->sandbox_mode ? 1 : 0
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s', '%s', '%d')
        );
    }
    
    /**
     * Update transaction status in database
     */
    private function update_transaction_status($reference, $data) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        
        $update_data = array(
            'status' => isset($data['status']) ? $data['status'] : 'completed'
        );
        
        if (isset($data['ecocashReference'])) {
            $update_data['ecocash_reference'] = $data['ecocashReference'];
        }
        
        $wpdb->update(
            $table_name,
            $update_data,
            array('transaction_reference' => $reference),
            array('%s', '%s'),
            array('%s')
        );
    }
    
    /**
     * Get Ecocash transaction reference from database
     */
    private function get_ecocash_transaction_reference($reference) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        
        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT ecocash_reference FROM $table_name WHERE transaction_reference = %s AND transaction_type = 'payment'",
            $reference
        ));
        
        return $result;
    }
    
    /**
     * Get transaction history for an order
     */
    public function get_order_transactions($order_id) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d ORDER BY created_at DESC",
            $order_id
        ));
        
        return $results;
    }
}