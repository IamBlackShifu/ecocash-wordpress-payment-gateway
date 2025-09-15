<?php
/**
 * Ecocash WooCommerce Payment Gateway
 * 
 * Integrates Ecocash payments with WooCommerce checkout
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ecocash_Payment_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'ecocash';
        $this->icon = ECOCASH_PLUGIN_URL . 'assets/images/ecocash-logo.png';
        $this->has_fields = true;
        $this->method_title = __('Ecocash Payment Gateway', ECOCASH_PLUGIN_TEXT_DOMAIN);
        $this->method_description = __('Accept payments via Ecocash mobile wallet (Zimbabwe)', ECOCASH_PLUGIN_TEXT_DOMAIN);
        
        // Load settings
        $this->init_form_fields();
        $this->init_settings();
        
        // Define user set variables
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->sandbox_mode = $this->get_option('sandbox_mode') === 'yes';
        $this->api_key_sandbox = $this->get_option('api_key_sandbox');
        $this->api_key_live = $this->get_option('api_key_live');
        $this->debug = $this->get_option('debug') === 'yes';
        $this->auto_capture = $this->get_option('auto_capture') === 'yes';
        
        // Supports
        $this->supports = array(
            'products',
            'refunds'
        );
        
        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_action('wp_enqueue_scripts', array($this, 'frontend_scripts'));
        
        // AJAX actions
        add_action('wp_ajax_ecocash_check_payment_status', array($this, 'ajax_check_payment_status'));
        add_action('wp_ajax_nopriv_ecocash_check_payment_status', array($this, 'ajax_check_payment_status'));
    }
    
    /**
     * Initialize gateway settings form fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable Ecocash Payment Gateway', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => __('Ecocash Payment', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'desc_tip' => true
            ),
            'description' => array(
                'title' => __('Description', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'textarea',
                'description' => __('Payment method description that the customer will see on your checkout.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => __('Pay securely using your Ecocash mobile wallet. You will receive a payment prompt on your phone.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'desc_tip' => true
            ),
            'sandbox_mode' => array(
                'title' => __('Sandbox Mode', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable Sandbox Mode', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => 'yes',
                'description' => __('Use sandbox mode for testing. Disable for live transactions.', ECOCASH_PLUGIN_TEXT_DOMAIN)
            ),
            'api_key_sandbox' => array(
                'title' => __('Sandbox API Key', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'password',
                'description' => __('Your Ecocash sandbox API key for testing.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'desc_tip' => true
            ),
            'api_key_live' => array(
                'title' => __('Live API Key', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'password',
                'description' => __('Your Ecocash live API key for production.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'desc_tip' => true
            ),
            'auto_capture' => array(
                'title' => __('Auto Capture', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Automatically complete orders when payment is successful', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => 'yes',
                'description' => __('When enabled, orders will be automatically marked as completed when payment is confirmed.', ECOCASH_PLUGIN_TEXT_DOMAIN)
            ),
            'debug' => array(
                'title' => __('Debug Log', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'type' => 'checkbox',
                'label' => __('Enable logging', ECOCASH_PLUGIN_TEXT_DOMAIN),
                'default' => 'no',
                'description' => __('Log Ecocash events for debugging purposes.', ECOCASH_PLUGIN_TEXT_DOMAIN)
            )
        );
    }
    
    /**
     * Check if gateway is available
     */
    public function is_available() {
        if ($this->enabled !== 'yes') {
            return false;
        }
        
        // Check if API key is configured
        $api_key = $this->sandbox_mode ? $this->api_key_sandbox : $this->api_key_live;
        if (empty($api_key)) {
            return false;
        }
        
        // Check if currency is supported
        $supported_currencies = array('USD', 'ZWL', 'ZiG');
        if (!in_array(get_woocommerce_currency(), $supported_currencies)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Payment form fields
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wptexturize($this->description));
        }
        
        // Add mobile number field
        echo '<div class="ecocash-payment-fields">';
        echo '<p class="form-row form-row-wide">';
        echo '<label for="ecocash_mobile_number">' . __('Ecocash Mobile Number', ECOCASH_PLUGIN_TEXT_DOMAIN) . ' <span class="required">*</span></label>';
        echo '<input id="ecocash_mobile_number" name="ecocash_mobile_number" type="tel" placeholder="263771234567" autocomplete="tel" />';
        echo '<small>' . __('Enter your Ecocash mobile number (e.g., 263771234567)', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</small>';
        echo '</p>';
        echo '</div>';
        
        // Add some inline styles
        echo '<style>
            .ecocash-payment-fields input[type="tel"] {
                width: 100%;
                padding: 8px 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
            }
            .ecocash-payment-fields small {
                color: #666;
                font-style: italic;
            }
        </style>';
    }
    
    /**
     * Validate fields
     */
    public function validate_fields() {
        $mobile_number = isset($_POST['ecocash_mobile_number']) ? sanitize_text_field($_POST['ecocash_mobile_number']) : '';
        
        if (empty($mobile_number)) {
            wc_add_notice(__('Ecocash mobile number is required.', ECOCASH_PLUGIN_TEXT_DOMAIN), 'error');
            return false;
        }
        
        // Validate mobile number format
        $formatted_mobile = Ecocash_SDK::format_mobile_number($mobile_number);
        if (!$formatted_mobile) {
            wc_add_notice(__('Please enter a valid Zimbabwean mobile number (e.g., 263771234567).', ECOCASH_PLUGIN_TEXT_DOMAIN), 'error');
            return false;
        }
        
        return true;
    }
    
    /**
     * Process payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $mobile_number = isset($_POST['ecocash_mobile_number']) ? sanitize_text_field($_POST['ecocash_mobile_number']) : '';
        
        if (empty($mobile_number)) {
            wc_add_notice(__('Ecocash mobile number is required.', ECOCASH_PLUGIN_TEXT_DOMAIN), 'error');
            return array('result' => 'fail');
        }
        
        // Initialize API
        $api_key = $this->sandbox_mode ? $this->api_key_sandbox : $this->api_key_live;
        
        // Debug logging for development
        if ($this->debug || get_option('ecocash_debug') === 'yes') {
            error_log('EcoCash Debug - Payment Process:');
            error_log('- Sandbox Mode: ' . ($this->sandbox_mode ? 'YES' : 'NO'));
            error_log('- API Key (first 10 chars): ' . substr($api_key, 0, 10) . '...');
            error_log('- API Key Empty: ' . (empty($api_key) ? 'YES' : 'NO'));
            error_log('- Order ID: ' . $order->get_id());
            error_log('- Order Total: ' . $order->get_total());
            error_log('- Currency: ' . $order->get_currency());
            error_log('- Mobile Number: ' . substr($mobile_number, 0, 6) . 'XXXXX');
        }
        
        if (empty($api_key)) {
            error_log('EcoCash Error: No API key configured for ' . ($this->sandbox_mode ? 'sandbox' : 'live') . ' mode');
            wc_add_notice(__('Payment configuration error. Please contact store administrator.', ECOCASH_PLUGIN_TEXT_DOMAIN), 'error');
            return array('result' => 'fail');
        }
        
        $ecocash_api = new Ecocash_API($api_key, $this->sandbox_mode);
        
        // Process payment
        $result = $ecocash_api->process_order_payment($order, $mobile_number);
        
        if ($result['success']) {
            // Mark as on-hold (awaiting payment)
            $order->update_status('on-hold', __('Awaiting Ecocash payment confirmation.', ECOCASH_PLUGIN_TEXT_DOMAIN));
            
            // Reduce stock
            wc_reduce_stock_levels($order_id);
            
            // Empty cart
            WC()->cart->empty_cart();
            
            // Return success and redirect to thank you page
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        } else {
            wc_add_notice(__('Payment failed: ', ECOCASH_PLUGIN_TEXT_DOMAIN) . $result['message'], 'error');
            return array('result' => 'fail');
        }
    }
    
    /**
     * Process refund
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return new WP_Error('invalid_order', __('Invalid order.', ECOCASH_PLUGIN_TEXT_DOMAIN));
        }
        
        // Initialize API
        $api_key = $this->sandbox_mode ? $this->api_key_sandbox : $this->api_key_live;
        $ecocash_api = new Ecocash_API($api_key, $this->sandbox_mode);
        
        // Process refund
        $result = $ecocash_api->process_order_refund($order, $amount, $reason);
        
        if ($result['success']) {
            return true;
        } else {
            return new WP_Error('refund_failed', $result['message']);
        }
    }
    
    /**
     * Thank you page
     */
    public function thankyou_page($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }
        
        $ecocash_reference = $order->get_meta('_ecocash_reference');
        $ecocash_mobile = $order->get_meta('_ecocash_mobile');
        
        if ($ecocash_reference) {
            echo '<div class="ecocash-payment-info">';
            echo '<h3>' . __('Ecocash Payment Information', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</h3>';
            echo '<p><strong>' . __('Payment Reference:', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</strong> ' . esc_html($ecocash_reference) . '</p>';
            echo '<p><strong>' . __('Mobile Number:', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</strong> ' . esc_html($ecocash_mobile) . '</p>';
            echo '<p>' . __('You should receive a payment prompt on your phone shortly. Please complete the payment to confirm your order.', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</p>';
            
            // Add payment status checker
            echo '<div id="ecocash-status-checker">';
            echo '<button type="button" id="check-payment-status" class="button">' . __('Check Payment Status', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</button>';
            echo '<div id="payment-status-result"></div>';
            echo '</div>';
            
            echo '</div>';
            
            // Add JavaScript for status checking
            $this->thankyou_page_script($order_id, $ecocash_reference);
        }
    }
    
    /**
     * Add JavaScript for payment status checking
     */
    private function thankyou_page_script($order_id, $reference) {
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            $('#check-payment-status').on('click', function() {
                var button = $(this);
                var resultDiv = $('#payment-status-result');
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Checking...', ECOCASH_PLUGIN_TEXT_DOMAIN)); ?>');
                resultDiv.empty();
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'ecocash_check_payment_status',
                        order_id: '<?php echo esc_js($order_id); ?>',
                        reference: '<?php echo esc_js($reference); ?>',
                        nonce: '<?php echo wp_create_nonce('ecocash_status_check'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="woocommerce-message">' + response.data.message + '</div>');
                            if (response.data.reload) {
                                setTimeout(function() {
                                    location.reload();
                                }, 2000);
                            }
                        } else {
                            resultDiv.html('<div class="woocommerce-error">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="woocommerce-error"><?php echo esc_js(__('Error checking payment status. Please try again.', ECOCASH_PLUGIN_TEXT_DOMAIN)); ?></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php echo esc_js(__('Check Payment Status', ECOCASH_PLUGIN_TEXT_DOMAIN)); ?>');
                    }
                });
            });
            
            // Auto-check status every 30 seconds
            setInterval(function() {
                $('#check-payment-status').trigger('click');
            }, 30000);
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for payment status check
     */
    public function ajax_check_payment_status() {
        check_ajax_referer('ecocash_status_check', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $reference = sanitize_text_field($_POST['reference']);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Invalid order.', ECOCASH_PLUGIN_TEXT_DOMAIN)));
        }
        
        // Initialize API
        $api_key = $this->sandbox_mode ? $this->api_key_sandbox : $this->api_key_live;
        $ecocash_api = new Ecocash_API($api_key, $this->sandbox_mode);
        
        // Check status
        $result = $ecocash_api->check_transaction_status($order_id);
        
        if ($result['success']) {
            $status = isset($result['data']['status']) ? $result['data']['status'] : 'unknown';
            
            if ($status === 'SUCCESSFUL' || $status === 'completed') {
                // Payment successful
                $order->payment_complete();
                if ($this->auto_capture) {
                    $order->update_status('completed');
                }
                
                wp_send_json_success(array(
                    'message' => __('Payment confirmed! Your order has been completed.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                    'reload' => true
                ));
            } elseif ($status === 'FAILED' || $status === 'failed') {
                // Payment failed
                $order->update_status('failed', __('Ecocash payment failed.', ECOCASH_PLUGIN_TEXT_DOMAIN));
                
                wp_send_json_success(array(
                    'message' => __('Payment failed. Please try again or contact support.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                    'reload' => true
                ));
            } else {
                // Still pending
                wp_send_json_success(array(
                    'message' => __('Payment is still pending. Please complete the payment on your phone.', ECOCASH_PLUGIN_TEXT_DOMAIN),
                    'reload' => false
                ));
            }
        } else {
            wp_send_json_error(array('message' => __('Could not check payment status.', ECOCASH_PLUGIN_TEXT_DOMAIN)));
        }
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function frontend_scripts() {
        if (is_checkout() || is_order_received_page()) {
            wp_enqueue_script('jquery');
        }
    }
}