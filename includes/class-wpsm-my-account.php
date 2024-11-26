<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_My_Account {
    
    public static function init() {
        // Add endpoints
        add_action('init', array(__CLASS__, 'add_endpoints'));
        
        // Add menu items
        add_filter('woocommerce_account_menu_items', array(__CLASS__, 'add_menu_items'));
        
        // Add content for the new endpoint
        add_action('woocommerce_account_support-tickets_endpoint', array(__CLASS__, 'endpoint_content'));
        
        // Handle form submission
        add_action('template_redirect', array(__CLASS__, 'handle_ticket_submission'));
    }
    
    /**
     * Add new support tickets endpoint
     */
    public static function add_endpoints() {
        add_rewrite_endpoint('support-tickets', EP_ROOT | EP_PAGES);
    }
    
    /**
     * Add menu items
     */
    public static function add_menu_items($items) {
        // Remove the logout menu item
        $logout = $items['customer-logout'];
        unset($items['customer-logout']);
        
        // Reorder the logout menu item at the bottom
        $items['support-tickets'] = __('Support Tickets', 'woo-product-support');
        $items['customer-logout'] = $logout;
        
        return $items;
    }
    
    /**
     * Endpoint content
     */
    public static function endpoint_content() {
        // Load the appropriate template
        wc_get_template(
            'myaccount/support-tickets.php',
            array(),
            'woocommerce-product-support-manager/',
            WPSM_PLUGIN_DIR . 'templates/'
        );
    }
    
    /**
     * Hndle the form submission
     */
    public static function handle_ticket_submission() {
        if (!isset($_POST['wpsm_submit_ticket']) || 
            !wp_verify_nonce($_POST['wpsm_ticket_nonce'], 'wpsm_submit_ticket')) {
            return;
        }
        
        // Get current customer
        $customer_id = get_current_user_id();
        
        if (!$customer_id) {
            wc_add_notice(__('You must be logged in to submit a ticket.', 'woo-product-support'), 'error');
            return;
        }
        
        // Validate required fields
        if (empty($_POST['ticket_subject']) || empty($_POST['ticket_message']) || empty($_POST['ticket_product'])) {
            wc_add_notice(__('Please fill in all required fields.', 'woo-product-support'), 'error');
            return;
        }
        
        // Create the ticket
        $ticket_data = array(
            'post_title'    => sanitize_text_field($_POST['ticket_subject']),
            'post_content'  => wp_kses_post($_POST['ticket_message']),
            'post_status'   => 'ticket_open',
            'post_type'     => 'support_ticket',
            'post_author'   => $customer_id
        );
        
        $ticket_id = wp_insert_post($ticket_data);
        
        if (!is_wp_error($ticket_id)) {
            // Add ticket meta
            update_post_meta($ticket_id, '_ticket_customer_id', $customer_id);
            update_post_meta($ticket_id, '_ticket_product_id', absint($_POST['ticket_product']));
            update_post_meta($ticket_id, '_ticket_priority', 
                isset($_POST['ticket_priority']) ? sanitize_text_field($_POST['ticket_priority']) : 'medium'
            );
            
            // Redirect to the ticket list with the default woo success message 
            // (later we'll add option to customize this)
            wc_add_notice(__('Your support ticket has been submitted successfully.', 'woo-product-support'), 'success');
            wp_redirect(wc_get_account_endpoint_url('support-tickets'));
            exit;
        } else {
            wc_add_notice(__('There was an error submitting your ticket. Please try again.', 'woo-product-support'), 'error');
        }
    }
    
    /**
     * Get customer's already purchased products
     */
    public static function get_customer_purchased_products($customer_id = null) {
        if (!$customer_id) {
            $customer_id = get_current_user_id();
        }
        
        $products = array();
        
        $args = array(
            'customer_id' => $customer_id,
            'status' => array('wc-completed', 'wc-processing'),
            'limit' => -1
        );
        
        $orders = wc_get_orders($args);
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                if (!isset($products[$product_id])) {
                    $products[$product_id] = array(
                        'id' => $product_id,
                        'name' => $item->get_name()
                    );
                }
            }
        }
        
        return array_values($products);
    }
}