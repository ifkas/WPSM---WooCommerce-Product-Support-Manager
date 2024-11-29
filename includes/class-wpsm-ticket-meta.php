<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Ticket_Meta {
    
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        // add_action('save_post_support_ticket', array(__CLASS__, 'save_meta_boxes'), 10, 2);
    }
    
    /**
     * Add meta boxes to support ticket edit page
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
            array(__CLASS__, 'render_ticket_details_meta_box'),
            'support_ticket',
            'side',
            'high'
        );

        // Add customer details meta box
        add_meta_box(
            'wpsm_customer_details',
            __('Customer Details', 'woo-product-support'),
            array(__CLASS__, 'render_customer_details_meta_box'),
            'support_ticket',
            'side',
            'high'
        );
    }

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
     * Render ticket details meta box
     */
    public static function render_ticket_details_meta_box($post) {
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
     * Render customer details meta box
     */
    public static function render_customer_details_meta_box($post) {
        $customer_id = get_post_meta($post->ID, '_ticket_customer_id', true);
        
        if (!$customer_id) {
            echo '<p>' . __('No customer associated with this ticket.', 'woo-product-support') . '</p>';
            return;
        }
        
        $customer = new WC_Customer($customer_id);
        ?>
        <div class="wpsm-customer-details">
            <p><strong><?php _e('Name:', 'woo-product-support'); ?></strong> 
                <?php echo esc_html($customer->get_first_name() . ' ' . $customer->get_last_name()); ?>
            </p>
            <p><strong><?php _e('Email:', 'woo-product-support'); ?></strong> 
                <?php echo esc_html($customer->get_email()); ?>
            </p>
            <p><strong><?php _e('Total Orders:', 'woo-product-support'); ?></strong> 
                <?php echo esc_html($customer->get_order_count()); ?>
            </p>
            <p><strong><?php _e('Customer Since:', 'woo-product-support'); ?></strong> 
                <?php echo esc_html(date_i18n(get_option('date_format'), strtotime($customer->get_date_created()))); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . $customer_id)); ?>" 
                   class="button button-small">
                    <?php _e('View Customer Profile', 'woo-product-support'); ?>
                </a>
            </p>
        </div>
        <?php
    }
    
    /**
     * Save meta box data
     */
    // public static function save_meta_boxes($post_id, $post) {
    //     // Check if our nonce is set and verify it
    //     if (!isset($_POST['wpsm_ticket_meta_nonce']) || 
    //         !wp_verify_nonce($_POST['wpsm_ticket_meta_nonce'], 'wpsm_save_ticket_meta')) {
    //         return;
    //     }
        
    //     // If this is an autosave, our form has not been submitted
    //     if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    //         return;
    //     }
        
    //     // Check the user's permissions
    //     if (!current_user_can('edit_post', $post_id)) {
    //         return;
    //     }
        
    //     // Save product ID
    //     if (isset($_POST['ticket_product_id'])) {
    //         update_post_meta($post_id, '_ticket_product_id', 
    //             sanitize_text_field($_POST['ticket_product_id']));
    //     }
        
    //     // Save priority
    //     if (isset($_POST['ticket_priority'])) {
    //         update_post_meta($post_id, '_ticket_priority', 
    //             sanitize_text_field($_POST['ticket_priority']));
    //     }
    // }
    
    /**
     * Get customer's purchased products
     */
    private static function get_customer_purchased_products($customer_id) {
        $products = array();
        
        if (!$customer_id) {
            return $products;
        }
        
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