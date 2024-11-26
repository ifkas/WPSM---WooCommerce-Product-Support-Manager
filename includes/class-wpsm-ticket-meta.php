<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Ticket_Meta {
    
    public static function init() {
        add_action('add_meta_boxes', array(__CLASS__, 'add_meta_boxes'));
        add_action('save_post_support_ticket', array(__CLASS__, 'save_meta_boxes'), 10, 2);
    }
    
    /**
     * Add meta boxes to support ticket edit page
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'wpsm_ticket_details',
            __('Ticket Details', 'woo-product-support'),
            array(__CLASS__, 'render_ticket_details_meta_box'),
            'support_ticket',
            'normal',
            'high'
        );

        add_meta_box(
            'wpsm_customer_details',
            __('Customer Details', 'woo-product-support'),
            array(__CLASS__, 'render_customer_details_meta_box'),
            'support_ticket',
            'side',
            'high'
        );
    }
    
    /**
     * Render ticket details meta box
     */
    public static function render_ticket_details_meta_box($post) {
        // Add nonce for security
        wp_nonce_field('wpsm_save_ticket_meta', 'wpsm_ticket_meta_nonce');
        
        // Get saved values
        $product_id = get_post_meta($post->ID, '_ticket_product_id', true);
        $priority = get_post_meta($post->ID, '_ticket_priority', true);
        
        // Default priority if not set
        if (empty($priority)) {
            $priority = 'medium';
        }
        
        // Get customer's purchased products
        $customer_id = get_post_meta($post->ID, '_ticket_customer_id', true);
        $purchased_products = self::get_customer_purchased_products($customer_id);
        ?>
        <div class="wpsm-meta-box-content">
            <!-- Product Selection -->
            <div class="wpsm-field-row">
                <label for="ticket_product_id"><?php _e('Related Product', 'woo-product-support'); ?></label>
                <select name="ticket_product_id" id="ticket_product_id" class="widefat">
                    <option value=""><?php _e('Select a product', 'woo-product-support'); ?></option>
                    <?php foreach ($purchased_products as $product) : ?>
                        <option value="<?php echo esc_attr($product['id']); ?>" 
                            <?php selected($product_id, $product['id']); ?>>
                            <?php echo esc_html($product['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Priority Selection -->
            <div class="wpsm-field-row">
                <label for="ticket_priority"><?php _e('Priority', 'woo-product-support'); ?></label>
                <select name="ticket_priority" id="ticket_priority" class="widefat">
                    <option value="low" <?php selected($priority, 'low'); ?>>
                        <?php _e('Low', 'woo-product-support'); ?>
                    </option>
                    <option value="medium" <?php selected($priority, 'medium'); ?>>
                        <?php _e('Medium', 'woo-product-support'); ?>
                    </option>
                    <option value="high" <?php selected($priority, 'high'); ?>>
                        <?php _e('High', 'woo-product-support'); ?>
                    </option>
                    <option value="urgent" <?php selected($priority, 'urgent'); ?>>
                        <?php _e('Urgent', 'woo-product-support'); ?>
                    </option>
                </select>
            </div>
        </div>
        <style>
            .wpsm-field-row {
                margin-bottom: 15px;
            }
            .wpsm-field-row label {
                display: block;
                margin-bottom: 5px;
                font-weight: bold;
            }
        </style>
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
    public static function save_meta_boxes($post_id, $post) {
        // Check if our nonce is set and verify it
        if (!isset($_POST['wpsm_ticket_meta_nonce']) || 
            !wp_verify_nonce($_POST['wpsm_ticket_meta_nonce'], 'wpsm_save_ticket_meta')) {
            return;
        }
        
        // If this is an autosave, our form has not been submitted
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Save product ID
        if (isset($_POST['ticket_product_id'])) {
            update_post_meta($post_id, '_ticket_product_id', 
                sanitize_text_field($_POST['ticket_product_id']));
        }
        
        // Save priority
        if (isset($_POST['ticket_priority'])) {
            update_post_meta($post_id, '_ticket_priority', 
                sanitize_text_field($_POST['ticket_priority']));
        }
    }
    
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