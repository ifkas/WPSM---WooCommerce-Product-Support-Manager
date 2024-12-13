<?php
/**
 * Plugin Name: WooCommerce Product Support Manager
 * Plugin URI: -- || --
 * Description: A comprehensive product support ticket system for WooCommerce customers
 * Version: 1.1.0
 * Author: Ivo Culic
 * Author URI: https://ivoculic.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woo-product-support
 */

 if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('WPSM_VERSION', '1.0.0');
define('WPSM_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPSM_PLUGIN_BASENAME', plugin_basename(__FILE__));
if (!defined('WPSM_PLUGIN_FILE')) {
    define('WPSM_PLUGIN_FILE', __FILE__);
}

// Autoload classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'WPSM_') !== false) {
        $class_name = strtolower(str_replace('_', '-', $class_name));
        $class_file = WPSM_PLUGIN_DIR . 'includes/class-' . $class_name . '.php';
        if (file_exists($class_file)) {
            require_once $class_file;
        }
    }
});

// Check if WooCommerce is active
function wpsm_is_woocommerce_active() {
    $active_plugins = (array) get_option('active_plugins', array());
    if (is_multisite()) {
        $active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
    }
    return in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins);
}

// Admin notice if WooCommerce is not active
function wpsm_admin_notice_wc_not_active() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('WooCommerce Product Support Manager requires WooCommerce to be installed and activated.', 'woo-product-support'); ?></p>
    </div>
    <?php
}

// Plugin activation hook
register_activation_hook(__FILE__, 'wpsm_activate');

function wpsm_activate() {
    if (!wpsm_is_woocommerce_active()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('WooCommerce Product Support Manager requires WooCommerce to be installed and activated.', 'woo-product-support'));
    }
    
    // Load Post Types class to register CPT
    require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-post-types.php';
    WPSM_Post_Types::register_post_types();
    
    flush_rewrite_rules();
}

// Plugin deactivation hook
register_deactivation_hook(__FILE__, 'wpsm_deactivate');

function wpsm_deactivate() {
    flush_rewrite_rules();
}

// Initialize plugin
add_action('plugins_loaded', 'wpsm_init');

function wpsm_init() {
    if (wpsm_is_woocommerce_active()) {

        // Load required files
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-post-types.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-ticket-meta.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-my-account.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-admin.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-assets.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-emails.php';
        require_once WPSM_PLUGIN_DIR . 'includes/class-wpsm-settings.php';
        
        // Initialize classes
        WPSM_Post_Types::init();
        WPSM_Ticket_Meta::init();
        WPSM_My_Account::init();
        WPSM_Admin::init();
        WPSM_Assets::init();
        WPSM_Emails::init();
        WPSM_Settings::init();

        // Send email notifications - hooks
        add_action('wp_insert_post', function($post_id, $post) {
            if ($post->post_type === 'support_ticket' && $post->post_status === 'ticket_open') {
                WPSM_Emails::notify_admin_new_ticket($post_id);
            }
        }, 10, 2);

        add_action('wp_insert_comment', function($comment_id, $comment) {
            if ($comment->comment_type === 'ticket_reply') {
                WPSM_Emails::notify_customer_new_reply($comment_id);
                WPSM_Emails::notify_admin_new_reply($comment_id);
            }
        }, 10, 2);
        
        // Load text domain
        load_plugin_textdomain('woo-product-support', false, dirname(WPSM_PLUGIN_BASENAME) . '/languages');

    } else {
        add_action('admin_notices', 'wpsm_admin_notice_wc_not_active');
    }
}