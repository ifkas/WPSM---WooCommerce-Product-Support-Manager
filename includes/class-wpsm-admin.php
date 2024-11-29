<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Admin {
    
    /**
     * Init admin func
     */
    public static function init() {
        // Existing filters and actions
        add_filter('manage_support_ticket_posts_columns', array(__CLASS__, 'set_custom_columns'));
        add_action('manage_support_ticket_posts_custom_column', array(__CLASS__, 'render_custom_columns'), 10, 2);
        add_filter('manage_edit-support_ticket_sortable_columns', array(__CLASS__, 'set_sortable_columns'));
                
        // AJAX handlers
        add_action('wp_ajax_wpsm_submit_reply', array(__CLASS__, 'handle_ticket_reply_ajax'));
        add_action('wp_ajax_wpsm_update_ticket_details', array(__CLASS__, 'handle_ticket_details_ajax'));
    }
    
    /**
     * Custom columns for tickets list
     */
    public static function set_custom_columns($columns) {
        $new_columns = array();
        foreach ($columns as $key => $value) {
            if ($key === 'title') {
                $new_columns[$key] = __('Subject', 'woo-product-support');
                $new_columns['customer'] = __('Customer', 'woo-product-support');
                $new_columns['product'] = __('Product', 'woo-product-support');
                $new_columns['priority'] = __('Priority', 'woo-product-support');
                $new_columns['status'] = __('Status', 'woo-product-support');
                $new_columns['replies'] = __('Replies', 'woo-product-support');
            } else if ($key !== 'date') {
                $new_columns[$key] = $value;
            }
        }
        $new_columns['date'] = __('Created', 'woo-product-support');
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public static function render_custom_columns($column, $post_id) {
        switch ($column) {
            case 'customer':
                $customer_id = get_post_meta($post_id, '_ticket_customer_id', true);
                $customer = get_user_by('id', $customer_id);
                if ($customer) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(admin_url('user-edit.php?user_id=' . $customer_id)),
                        esc_html($customer->display_name)
                    );
                }
                break;
                
            case 'product':
                $product_id = get_post_meta($post_id, '_ticket_product_id', true);
                $product = wc_get_product($product_id);
                if ($product) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_post_link($product_id)),
                        esc_html($product->get_name())
                    );
                }
                break;
                
            case 'priority':
                $priority = get_post_meta($post_id, '_ticket_priority', true);
                echo '<span class="ticket-priority priority-' . esc_attr($priority) . '">' 
                    . esc_html(ucfirst($priority)) 
                    . '</span>';
                break;
                
            case 'status':
                $status = get_post_status_object(get_post_status($post_id));
                echo '<span class="ticket-status status-' . esc_attr($status->name) . '">' 
                    . esc_html($status->label) 
                    . '</span>';
                break;
                
            case 'replies':
                $replies = get_comments(array(
                    'post_id' => $post_id,
                    'count' => true
                ));
                echo esc_html($replies);
                break;
        }
    }
    
    /**
     * Set sortable columns
     */
    public static function set_sortable_columns($columns) {
        $columns['priority'] = 'priority';
        $columns['status'] = 'status';
        return $columns;
    }
    
    /**
     * Handle ticket reply submission via AJAX
     */
    public static function handle_ticket_reply_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpsm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-product-support')));
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-product-support')));
        }

        // Get and validate data
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $reply = isset($_POST['reply']) ? wp_kses_post($_POST['reply']) : '';
        $mark_resolved = isset($_POST['mark_resolved']) && $_POST['mark_resolved'] == 1;

        if (!$post_id || empty($reply)) {
            wp_send_json_error(array('message' => __('Please provide a reply message', 'woo-product-support')));
        }

        // Create the comment/reply
        $comment_data = array(
            'comment_post_ID' => $post_id,
            'comment_content' => $reply,
            'user_id' => get_current_user_id(),
            'comment_type' => 'ticket_reply',
            'comment_approved' => 1,
            'comment_author' => wp_get_current_user()->display_name,
            'comment_author_email' => wp_get_current_user()->user_email
        );

        $comment_id = wp_insert_comment($comment_data);

        if ($comment_id) {
            if ($mark_resolved) {
                wp_update_post(array(
                    'ID' => $post_id,
                    'post_status' => 'ticket_resolved'
                ));
            } else {
                $current_status = get_post_status($post_id);
                if ($current_status === 'ticket_open') {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_status' => 'ticket_in_progress'
                    ));
                }
            }

            wp_send_json_success(array('message' => __('Reply added successfully', 'woo-product-support')));
        }

        wp_send_json_error(array('message' => __('Error adding reply', 'woo-product-support')));
    }

    public static function handle_ticket_details_ajax() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpsm_admin_nonce')) {
            wp_send_json_error(array('message' => __('Security check failed', 'woo-product-support')));
        }

        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_send_json_error(array('message' => __('Permission denied', 'woo-product-support')));
        }

        // Get and validate data
        $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
        $priority = isset($_POST['priority']) ? sanitize_text_field($_POST['priority']) : '';
        $status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if (!$post_id) {
            wp_send_json_error(array('message' => __('Invalid ticket ID', 'woo-product-support')));
        }

        // Update priority
        if ($priority) {
            update_post_meta($post_id, '_ticket_priority', $priority);
        }

        // Update status
        if ($status) {
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => $status
            ));
        }

        wp_send_json_success(array(
            'message' => __('Ticket details updated successfully', 'woo-product-support'),
            'priority' => $priority,
            'status' => $status
        ));
    }
}