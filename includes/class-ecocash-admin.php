<?php
/**
 * Ecocash Admin Interface
 * 
 * Handles admin pages and settings
 */

if (!defined('ABSPATH')) {
    exit;
}

class Ecocash_Admin {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'init_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Ecocash Settings', ECOCASH_PLUGIN_TEXT_DOMAIN),
            __('Ecocash', ECOCASH_PLUGIN_TEXT_DOMAIN),
            'manage_options',
            'ecocash-settings',
            array($this, 'settings_page'),
            'dashicons-money-alt',
            56
        );
        
        add_submenu_page(
            'ecocash-settings',
            __('Settings', ECOCASH_PLUGIN_TEXT_DOMAIN),
            __('Settings', ECOCASH_PLUGIN_TEXT_DOMAIN),
            'manage_options',
            'ecocash-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ecocash-settings',
            __('Transactions', ECOCASH_PLUGIN_TEXT_DOMAIN),
            __('Transactions', ECOCASH_PLUGIN_TEXT_DOMAIN),
            'manage_options',
            'ecocash-transactions',
            array($this, 'transactions_page')
        );
        
        add_submenu_page(
            'ecocash-settings',
            __('Setup Guide', ECOCASH_PLUGIN_TEXT_DOMAIN),
            __('Setup Guide', ECOCASH_PLUGIN_TEXT_DOMAIN),
            'manage_options',
            'ecocash-setup',
            array($this, 'setup_page')
        );
    }
    
    /**
     * Initialize settings
     */
    public function init_settings() {
        register_setting('ecocash_settings', 'ecocash_enabled');
        register_setting('ecocash_settings', 'ecocash_sandbox_mode');
        register_setting('ecocash_settings', 'ecocash_api_key_sandbox');
        register_setting('ecocash_settings', 'ecocash_api_key_live');
        register_setting('ecocash_settings', 'ecocash_title');
        register_setting('ecocash_settings', 'ecocash_description');
        register_setting('ecocash_settings', 'ecocash_currency');
        register_setting('ecocash_settings', 'ecocash_debug');
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        $enabled = get_option('ecocash_enabled', 'no');
        $sandbox_mode = get_option('ecocash_sandbox_mode', 'yes');
        $api_key_sandbox = get_option('ecocash_api_key_sandbox', '');
        $api_key_live = get_option('ecocash_api_key_live', '');
        $title = get_option('ecocash_title', 'Ecocash Payment');
        $description = get_option('ecocash_description', 'Pay securely using your Ecocash mobile wallet.');
        $currency = get_option('ecocash_currency', 'USD');
        $debug = get_option('ecocash_debug', 'no');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ecocash-admin-header">
                <div class="ecocash-status-card">
                    <h3><?php _e('Gateway Status', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h3>
                    <p class="status <?php echo $enabled === 'yes' ? 'enabled' : 'disabled'; ?>">
                        <?php echo $enabled === 'yes' ? __('Enabled', ECOCASH_PLUGIN_TEXT_DOMAIN) : __('Disabled', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                    </p>
                    <p class="mode <?php echo $sandbox_mode === 'yes' ? 'sandbox' : 'live'; ?>">
                        <?php echo $sandbox_mode === 'yes' ? __('Sandbox Mode', ECOCASH_PLUGIN_TEXT_DOMAIN) : __('Live Mode', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                    </p>
                </div>
                
                <div class="ecocash-test-connection">
                    <h3><?php _e('Test Connection', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h3>
                    <button type="button" id="test-connection" class="button button-secondary">
                        <?php _e('Test API Connection', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                    </button>
                    <div id="connection-result"></div>
                </div>
            </div>
            
            <form method="post" action="">
                <?php wp_nonce_field('ecocash_settings', 'ecocash_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Enable Gateway', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ecocash_enabled" value="yes" <?php checked($enabled, 'yes'); ?> />
                                <?php _e('Enable Ecocash Payment Gateway', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                            </label>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Environment', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ecocash_sandbox_mode" value="yes" <?php checked($sandbox_mode, 'yes'); ?> />
                                <?php _e('Enable Sandbox Mode (for testing)', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('Use sandbox mode for testing. Disable for live transactions.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Sandbox API Key', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="password" name="ecocash_api_key_sandbox" value="<?php echo esc_attr($api_key_sandbox); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Ecocash sandbox API key for testing.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Live API Key', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="password" name="ecocash_api_key_live" value="<?php echo esc_attr($api_key_live); ?>" class="regular-text" />
                            <p class="description"><?php _e('Your Ecocash live API key for production.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Gateway Title', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <input type="text" name="ecocash_title" value="<?php echo esc_attr($title); ?>" class="regular-text" />
                            <p class="description"><?php _e('This controls the title which the user sees during checkout.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Gateway Description', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <textarea name="ecocash_description" rows="3" class="large-text"><?php echo esc_textarea($description); ?></textarea>
                            <p class="description"><?php _e('Payment method description that the customer will see on your checkout.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Default Currency', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <select name="ecocash_currency">
                                <option value="USD" <?php selected($currency, 'USD'); ?>>USD - United States Dollar</option>
                                <option value="ZWL" <?php selected($currency, 'ZWL'); ?>>ZWL - Zimbabwe Dollar</option>
                                <option value="ZiG" <?php selected($currency, 'ZiG'); ?>>ZiG - Zimbabwe Gold</option>
                            </select>
                            <p class="description"><?php _e('Default currency for transactions.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row"><?php _e('Debug Logging', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="ecocash_debug" value="yes" <?php checked($debug, 'yes'); ?> />
                                <?php _e('Enable debug logging', ECOCASH_PLUGIN_TEXT_DOMAIN); ?>
                            </label>
                            <p class="description"><?php _e('Log Ecocash events for debugging purposes.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <style>
        .ecocash-admin-header {
            display: flex;
            gap: 20px;
            margin: 20px 0;
        }
        
        .ecocash-status-card, .ecocash-test-connection {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            border-radius: 4px;
            flex: 1;
        }
        
        .status.enabled {
            color: #46b450;
            font-weight: bold;
        }
        
        .status.disabled {
            color: #dc3232;
            font-weight: bold;
        }
        
        .mode.sandbox {
            color: #ffb900;
            font-weight: bold;
        }
        
        .mode.live {
            color: #46b450;
            font-weight: bold;
        }
        
        #connection-result.success {
            color: #46b450;
            margin-top: 10px;
        }
        
        #connection-result.error {
            color: #dc3232;
            margin-top: 10px;
        }
        </style>
        <?php
    }
    
    /**
     * Transactions page
     */
    public function transactions_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ecocash_transactions';
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Get total count
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $total_pages = ceil($total_items / $per_page);
        
        // Get transactions
        $transactions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $per_page,
            $offset
        ));
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="tablenav top">
                <div class="alignleft actions bulkactions">
                    <p><?php printf(__('Total Transactions: %d', ECOCASH_PLUGIN_TEXT_DOMAIN), $total_items); ?></p>
                </div>
                
                <?php if ($total_pages > 1): ?>
                <div class="tablenav-pages">
                    <span class="displaying-num"><?php printf(__('%d items', ECOCASH_PLUGIN_TEXT_DOMAIN), $total_items); ?></span>
                    <span class="pagination-links">
                        <?php
                        echo paginate_links(array(
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $current_page
                        ));
                        ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Order', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Reference', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Mobile', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Amount', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Status', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Type', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                        <th><?php _e('Date', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="8"><?php _e('No transactions found.', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo esc_html($transaction->id); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $transaction->order_id . '&action=edit'); ?>">
                                #<?php echo esc_html($transaction->order_id); ?>
                            </a>
                        </td>
                        <td><?php echo esc_html($transaction->transaction_reference); ?></td>
                        <td><?php echo esc_html($transaction->mobile_number); ?></td>
                        <td><?php echo esc_html($transaction->amount . ' ' . $transaction->currency); ?></td>
                        <td>
                            <span class="status-<?php echo esc_attr($transaction->status); ?>">
                                <?php echo esc_html(ucfirst($transaction->status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html(ucfirst($transaction->transaction_type)); ?></td>
                        <td><?php echo esc_html($transaction->created_at); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <style>
        .status-initiated { color: #ffb900; }
        .status-completed { color: #46b450; }
        .status-failed { color: #dc3232; }
        .status-pending { color: #72aee6; }
        </style>
        <?php
    }
    
    /**
     * Setup guide page
     */
    public function setup_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="ecocash-setup-guide">
                <div class="setup-step">
                    <h2>1. <?php _e('Get Your API Credentials', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('To use this plugin, you need to get API credentials from Ecocash:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Visit', ECOCASH_PLUGIN_TEXT_DOMAIN); ?> <a href="https://developers.ecocash.co.zw" target="_blank">developers.ecocash.co.zw</a></li>
                        <li><?php _e('Sign up for a developer account', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Create a new application to get your API key', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Use sandbox mode for testing, live mode for production', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ol>
                </div>
                
                <div class="setup-step">
                    <h2>2. <?php _e('Configure the Plugin', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('Once you have your API credentials:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Go to the', ECOCASH_PLUGIN_TEXT_DOMAIN); ?> <a href="<?php echo admin_url('admin.php?page=ecocash-settings'); ?>"><?php _e('Settings page', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></a></li>
                        <li><?php _e('Enter your API keys (sandbox and/or live)', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Enable sandbox mode for testing', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Customize the gateway title and description', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Test the connection using the test button', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ol>
                </div>
                
                <div class="setup-step">
                    <h2>3. <?php _e('Enable in WooCommerce', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('Configure the payment gateway in WooCommerce:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Go to WooCommerce → Settings → Payments', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Find "Ecocash Payment Gateway" and click "Manage"', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Enable the gateway and configure additional settings', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Save changes', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ol>
                </div>
                
                <div class="setup-step">
                    <h2>4. <?php _e('Test Your Setup', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('Before going live, test your setup:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Make sure sandbox mode is enabled', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Create a test product in your store', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Go through the checkout process', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Use a test mobile number: 263771234567', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Check the', ECOCASH_PLUGIN_TEXT_DOMAIN); ?> <a href="<?php echo admin_url('admin.php?page=ecocash-transactions'); ?>"><?php _e('transactions page', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></a> <?php _e('for results', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ol>
                </div>
                
                <div class="setup-step">
                    <h2>5. <?php _e('Go Live', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('When you\'re ready for production:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ol>
                        <li><?php _e('Add your live API key', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Disable sandbox mode', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Test with a small real transaction', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Monitor the transactions page for any issues', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ol>
                </div>
                
                <div class="setup-step">
                    <h2><?php _e('Supported Features', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <ul>
                        <li>✅ <?php _e('Instant payments (C2B)', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Transaction lookup', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Refund processing', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Sandbox and live environments', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Multiple currencies (USD, ZWL, ZiG)', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Mobile number validation', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Transaction logging', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li>✅ <?php _e('Debug logging', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ul>
                </div>
                
                <div class="setup-step support">
                    <h2><?php _e('Need Help?', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></h2>
                    <p><?php _e('If you encounter any issues:', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></p>
                    <ul>
                        <li><?php _e('Check the', ECOCASH_PLUGIN_TEXT_DOMAIN); ?> <a href="<?php echo admin_url('admin.php?page=ecocash-transactions'); ?>"><?php _e('transactions page', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></a> <?php _e('for error details', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Enable debug logging in settings', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                        <li><?php _e('Visit the', ECOCASH_PLUGIN_TEXT_DOMAIN); ?> <a href="https://developers.ecocash.co.zw" target="_blank"><?php _e('Ecocash developer portal', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></a></li>
                        <li><?php _e('Contact plugin support on GitHub', ECOCASH_PLUGIN_TEXT_DOMAIN); ?></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <style>
        .ecocash-setup-guide {
            max-width: 800px;
        }
        
        .setup-step {
            background: #fff;
            border: 1px solid #ccd0d4;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        
        .setup-step h2 {
            color: #23282d;
            margin-top: 0;
        }
        
        .setup-step ol, .setup-step ul {
            margin-left: 20px;
        }
        
        .setup-step.support {
            background: #f0f6fc;
            border-color: #72aee6;
        }
        </style>
        <?php
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!wp_verify_nonce($_POST['ecocash_settings_nonce'], 'ecocash_settings')) {
            return;
        }
        
        $settings = array(
            'ecocash_enabled' => isset($_POST['ecocash_enabled']) ? 'yes' : 'no',
            'ecocash_sandbox_mode' => isset($_POST['ecocash_sandbox_mode']) ? 'yes' : 'no',
            'ecocash_api_key_sandbox' => sanitize_text_field($_POST['ecocash_api_key_sandbox']),
            'ecocash_api_key_live' => sanitize_text_field($_POST['ecocash_api_key_live']),
            'ecocash_title' => sanitize_text_field($_POST['ecocash_title']),
            'ecocash_description' => sanitize_textarea_field($_POST['ecocash_description']),
            'ecocash_currency' => sanitize_text_field($_POST['ecocash_currency']),
            'ecocash_debug' => isset($_POST['ecocash_debug']) ? 'yes' : 'no'
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</p></div>';
    }
    
    /**
     * Enqueue admin scripts
     */
    public function admin_scripts($hook) {
        if (strpos($hook, 'ecocash') !== false) {
            wp_enqueue_script('jquery');
            
            // Add inline script for test connection
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $("#test-connection").on("click", function() {
                        var button = $(this);
                        var result = $("#connection-result");
                        
                        var sandbox_mode = $("input[name=ecocash_sandbox_mode]").is(":checked");
                        var api_key = sandbox_mode ? 
                            $("input[name=ecocash_api_key_sandbox]").val() : 
                            $("input[name=ecocash_api_key_live]").val();
                        
                        if (!api_key) {
                            result.html("Please enter an API key first.").removeClass("success").addClass("error");
                            return;
                        }
                        
                        button.prop("disabled", true).text("Testing...");
                        result.removeClass("success error").text("");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "ecocash_test_connection",
                                api_key: api_key,
                                sandbox_mode: sandbox_mode,
                                nonce: "' . wp_create_nonce('ecocash_ajax_nonce') . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    result.html(response.data.message).removeClass("error").addClass("success");
                                } else {
                                    result.html(response.data.message).removeClass("success").addClass("error");
                                }
                            },
                            error: function() {
                                result.html("Connection test failed.").removeClass("success").addClass("error");
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Test API Connection");
                            }
                        });
                    });
                });
            ');
        }
    }
    
    /**
     * Add order meta box
     */
    public function add_order_meta_box() {
        add_meta_box(
            'ecocash-order-details',
            __('Ecocash Payment Details', ECOCASH_PLUGIN_TEXT_DOMAIN),
            array($this, 'order_meta_box_content'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Order meta box content
     */
    public function order_meta_box_content($post) {
        $order = wc_get_order($post->ID);
        
        if ($order->get_payment_method() !== 'ecocash') {
            echo '<p>' . __('This order did not use Ecocash payment.', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</p>';
            return;
        }
        
        $ecocash_reference = $order->get_meta('_ecocash_reference');
        $ecocash_mobile = $order->get_meta('_ecocash_mobile');
        
        $ecocash_api = new Ecocash_API();
        $transactions = $ecocash_api->get_order_transactions($order->get_id());
        
        echo '<div class="ecocash-order-details">';
        
        if ($ecocash_reference) {
            echo '<p><strong>' . __('Reference:', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</strong> ' . esc_html($ecocash_reference) . '</p>';
        }
        
        if ($ecocash_mobile) {
            echo '<p><strong>' . __('Mobile:', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</strong> ' . esc_html($ecocash_mobile) . '</p>';
        }
        
        if (!empty($transactions)) {
            echo '<h4>' . __('Transaction History:', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</h4>';
            echo '<table class="widefat">';
            echo '<thead><tr><th>' . __('Type', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</th><th>' . __('Status', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</th><th>' . __('Date', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($transactions as $transaction) {
                echo '<tr>';
                echo '<td>' . esc_html(ucfirst($transaction->transaction_type)) . '</td>';
                echo '<td>' . esc_html(ucfirst($transaction->status)) . '</td>';
                echo '<td>' . esc_html($transaction->created_at) . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        // Add status check button
        if ($ecocash_reference) {
            echo '<p><button type="button" class="button" id="check-ecocash-status">' . __('Check Status', ECOCASH_PLUGIN_TEXT_DOMAIN) . '</button></p>';
            echo '<div id="ecocash-status-result"></div>';
            
            // Add inline script
            wp_add_inline_script('jquery', '
                jQuery(document).ready(function($) {
                    $("#check-ecocash-status").on("click", function() {
                        var button = $(this);
                        var result = $("#ecocash-status-result");
                        
                        button.prop("disabled", true).text("Checking...");
                        
                        $.ajax({
                            url: ajaxurl,
                            type: "POST",
                            data: {
                                action: "ecocash_check_payment_status",
                                order_id: "' . $order->get_id() . '",
                                reference: "' . esc_js($ecocash_reference) . '",
                                nonce: "' . wp_create_nonce('ecocash_status_check') . '"
                            },
                            success: function(response) {
                                if (response.success) {
                                    result.html("<div class=\"notice notice-success inline\"><p>" + response.data.message + "</p></div>");
                                    if (response.data.reload) {
                                        setTimeout(function() {
                                            location.reload();
                                        }, 2000);
                                    }
                                } else {
                                    result.html("<div class=\"notice notice-error inline\"><p>" + response.data.message + "</p></div>");
                                }
                            },
                            error: function() {
                                result.html("<div class=\"notice notice-error inline\"><p>Error checking status.</p></div>");
                            },
                            complete: function() {
                                button.prop("disabled", false).text("Check Status");
                            }
                        });
                    });
                });
            ');
        }
        
        echo '</div>';
    }
}