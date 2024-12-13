<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * My Account class
 */

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

        // Handle reply submission
        add_action('template_redirect', array(__CLASS__, 'handle_ticket_reply'));
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
        $options = get_option('wpsm_settings', WPSM_Settings::get_default_settings());
        $tickets_per_page = isset($options['wpsm_tickets_per_page']) ? absint($options['wpsm_tickets_per_page']) : 10;

        error_log('Tickets per page setting: ' . $tickets_per_page); // Debug line


        // Check if viewing a single ticket
        $ticket_id = isset($_GET['ticket_id']) ? absint($_GET['ticket_id']) : 0;
    
        // Load the appropriate template
        if ($ticket_id) {
            wc_get_template(
                'myaccount/single-ticket.php',
                array('ticket_id' => $ticket_id),
                'woocommerce-product-support-manager/',
                WPSM_PLUGIN_DIR . 'templates/'
            );
        } else {
            // Update the query to use pagination
            $current_page = empty($_GET['support_ticket_page']) ? 1 : absint($_GET['support_ticket_page']);
            
            $args = array(
                'post_type' => 'support_ticket',
                'post_status' => array('ticket_open', 'ticket_in_progress', 'ticket_resolved'),
                'author' => get_current_user_id(),
                'posts_per_page' => $tickets_per_page,
                'paged' => $current_page
            );

            $tickets_query = new WP_Query($args);

            global $wp_query;
            $temp_query = $wp_query;
            $wp_query = $tickets_query;

            wc_get_template(
                'myaccount/support-tickets.php',
                array(
                    'tickets' => $tickets_query->posts,
                    'max_num_pages' => $tickets_query->max_num_pages,
                    'current_page' => $current_page
                ),
                'woocommerce-product-support-manager/',
                WPSM_PLUGIN_DIR . 'templates/'
            );

            $wp_query = $temp_query;
            wp_reset_postdata();
        }
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
     * Handle the ticket reply submission
     */
    public static function handle_ticket_reply() {
        if (!isset($_POST['wpsm_add_reply']) || 
            !wp_verify_nonce($_POST['wpsm_reply_nonce'], 'wpsm_add_reply')) {
            return;
        }
        
        $ticket_id = isset($_POST['ticket_id']) ? absint($_POST['ticket_id']) : 0;
        $reply_content = isset($_POST['ticket_reply']) ? wp_kses_post($_POST['ticket_reply']) : '';
        
        if (!$ticket_id || !$reply_content) {
            wc_add_notice(__('Invalid request.', 'woo-product-support'), 'error');
            return;
        }
        
        // Check if user owns the ticket
        $ticket = get_post($ticket_id);
        if (!$ticket || $ticket->post_author != get_current_user_id()) {
            wc_add_notice(__('You don\'t have permission to reply to this ticket.', 'woo-product-support'), 'error');
            return;
        }
        
        // Add the reply
        $reply_data = array(
            'comment_post_ID' => $ticket_id,
            'comment_content' => $reply_content,
            'user_id' => get_current_user_id(),
            'comment_type' => 'ticket_reply'
        );
        
        $reply_id = wp_insert_comment($reply_data);
        
        if ($reply_id) {
            // Update ticket status to in-progress if it was open
            if (get_post_status($ticket_id) === 'ticket_open') {
                wp_update_post(array(
                    'ID' => $ticket_id,
                    'post_status' => 'ticket_in_progress'
                ));
            }
            
            wc_add_notice(__('Your reply has been submitted successfully.', 'woo-product-support'), 'success');
        } else {
            wc_add_notice(__('There was an error submitting your reply. Please try again.', 'woo-product-support'), 'error');
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