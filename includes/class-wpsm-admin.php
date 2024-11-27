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
        // TODO: Create the meta boxes separately new class
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        
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
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        // Add reply meta box
        add_meta_box(
            'wpsm_ticket_reply',
            __('Add Reply', 'woo-product-support'),
            array(__CLASS__, 'render_reply_meta_box'),
            'support_ticket',
            'normal',
            'high'
        );
        
        // Add ticket details meta box
        add_meta_box(
            'wpsm_ticket_details',
            __('Ticket Details', 'woo-product-support'),
            array(__CLASS__, 'render_details_meta_box'),
            'support_ticket',
            'side',
            'high'
        );
    }
    
    /**
     * Render reply meta box
     */
    public static function render_reply_meta_box($post) {
        wp_nonce_field('wpsm_ticket_reply', 'wpsm_ticket_reply_nonce');
        ?>
        <div class="wpsm-reply-box">
            <!-- First show existing replies -->
            <div class="wpsm-replies">
                <h3><?php _e('Conversation History', 'woo-product-support'); ?></h3>
                <?php
                $replies = get_comments(array(
                    'post_id' => $post->ID,
                    'order' => 'DESC',
                    'type' => 'ticket_reply'
                ));
                
                if ($replies) {
                    foreach ($replies as $reply) {
                        $user_info = get_userdata($reply->user_id);
                        $is_customer = $reply->user_id == $post->post_author;
                        ?>
                        <div class="wpsm-reply <?php echo $is_customer ? 'customer' : 'staff'; ?>">
                            <div class="wpsm-reply-meta">
                                <?php 
                                echo get_avatar($reply->user_id, 32);
                                echo '<strong>' . esc_html($user_info->display_name) . '</strong>';
                                if ($is_customer) {
                                    echo ' <span class="customer-badge">' . __('Customer', 'woo-product-support') . '</span>';
                                }
                                echo ' <span class="reply-date">' . human_time_diff(strtotime($reply->comment_date)) . ' ago</span>';
                                ?>
                            </div>
                            <div class="wpsm-reply-content">
                                <?php echo wpautop($reply->comment_content); ?>
                            </div>
                        </div>
                        <?php
                    }
                } else {
                    echo '<p>' . __('No replies yet.', 'woo-product-support') . '</p>';
                }
                ?>
            </div>

            <!-- Show the reply form -->
            <div class="wpsm-reply-form" style="margin-top: 20px;">
                <h3><?php _e('Add Reply', 'woo-product-support'); ?></h3>
                <div class="wpsm-reply-content">
                    <textarea name="ticket_reply" id="ticket_reply" rows="5" style="width: 100%;" required></textarea>
                </div>
                
                <div class="wpsm-reply-actions" style="margin-top: 10px;">
                    <button type="button" id="submit_ticket_reply" class="button button-primary">
                        <?php esc_html_e('Submit Reply', 'woo-product-support'); ?>
                    </button>
                    <label style="margin-left: 10px;">
                        <input type="checkbox" name="mark_resolved" id="mark_resolved" value="1">
                        <?php esc_html_e('Mark as Resolved', 'woo-product-support'); ?>
                    </label>
                    <span class="spinner" style="float: none; margin: 0 10px;"></span>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render details meta box
     */
    public static function render_details_meta_box($post) {
        wp_nonce_field('wpsm_admin_nonce', 'wpsm_ticket_details_nonce');
        
        $customer_id = get_post_meta($post->ID, '_ticket_customer_id', true);
        $product_id = get_post_meta($post->ID, '_ticket_product_id', true);
        $priority = get_post_meta($post->ID, '_ticket_priority', true);
        $customer = get_user_by('id', $customer_id);
        $product = wc_get_product($product_id);
        ?>
        <div class="wpsm-ticket-details">
            <p>
                <strong><?php _e('Customer:', 'woo-product-support'); ?></strong><br>
                <?php 
                if ($customer) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(admin_url('user-edit.php?user_id=' . $customer_id)),
                        esc_html($customer->display_name)
                    );
                }
                ?>
            </p>
            <p>
                <strong><?php _e('Product:', 'woo-product-support'); ?></strong><br>
                <?php 
                if ($product) {
                    printf(
                        '<a href="%s">%s</a>',
                        esc_url(get_edit_post_link($product_id)),
                        esc_html($product->get_name())
                    );
                }
                ?>
            </p>
            <p>
                <strong><?php _e('Priority:', 'woo-product-support'); ?></strong><br>
                <select name="ticket_priority" id="ticket_priority" style="width: 100%;">
                    <option value="low" <?php selected($priority, 'low'); ?>><?php _e('Low', 'woo-product-support'); ?></option>
                    <option value="medium" <?php selected($priority, 'medium'); ?>><?php _e('Medium', 'woo-product-support'); ?></option>
                    <option value="high" <?php selected($priority, 'high'); ?>><?php _e('High', 'woo-product-support'); ?></option>
                    <option value="urgent" <?php selected($priority, 'urgent'); ?>><?php _e('Urgent', 'woo-product-support'); ?></option>
                </select>
            </p>
            <p>
                <strong><?php _e('Status:', 'woo-product-support'); ?></strong><br>
                <select name="post_status" id="ticket_status" style="width: 100%;">
                    <option value="ticket_open" <?php selected($post->post_status, 'ticket_open'); ?>><?php _e('Open', 'woo-product-support'); ?></option>
                    <option value="ticket_in_progress" <?php selected($post->post_status, 'ticket_in_progress'); ?>><?php _e('In Progress', 'woo-product-support'); ?></option>
                    <option value="ticket_resolved" <?php selected($post->post_status, 'ticket_resolved'); ?>><?php _e('Resolved', 'woo-product-support'); ?></option>
                </select>
            </p>
            <div class="wpsm-details-actions">
                <button type="button" id="update_ticket_details" class="button button-primary">
                    <?php esc_html_e('Update Details', 'woo-product-support'); ?>
                </button>
                <span class="spinner" style="float: none; margin: 0 10px;"></span>
            </div>
        </div>
        <?php
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