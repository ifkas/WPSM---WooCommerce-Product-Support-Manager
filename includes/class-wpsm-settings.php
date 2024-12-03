<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Settings {
    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'add_settings_menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));

        // Add settings link to plugin page
        $plugin_base = plugin_basename(WPSM_PLUGIN_FILE);
        add_filter('plugin_action_links_' . $plugin_base, array(__CLASS__, 'add_plugin_action_links'));
    }

    public static function add_plugin_action_links($links) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            admin_url('edit.php?post_type=support_ticket&page=wpsm-settings'),
            __('Settings', 'woo-product-support')
        );
        array_unshift($links, $settings_link);
        return $links;
    }

    public static function add_settings_menu() {
        add_submenu_page(
            'edit.php?post_type=support_ticket', // Parent slug
            __('WooCommerce Support Ticket Settings', 'woo-product-support'),
            __('Settings', 'woo-product-support'),
            'manage_options',
            'wpsm-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function register_settings() {
        register_setting('wpsm_settings', 'wpsm_settings');

        // General Settings Section
        add_settings_section(
            'wpsm_general_settings',
            __('General Settings', 'woo-product-support'),
            array(__CLASS__, 'general_settings_callback'),
            'wpsm-settings'
        );

        // Email Settings Section
        add_settings_section(
            'wpsm_email_settings',
            __('Email Notifications', 'woo-product-support'),
            array(__CLASS__, 'email_settings_callback'),
            'wpsm-settings'
        );

        // Add Settings Fields
        add_settings_field(
            'wpsm_tickets_per_page',
            __('Tickets Per Page', 'woo-product-support'),
            array(__CLASS__, 'number_field_callback'),
            'wpsm-settings',
            'wpsm_general_settings',
            array(
                'label_for' => 'wpsm_tickets_per_page',
                'default' => 10,
                'min' => 5,
                'max' => 50
            )
        );

        add_settings_field(
            'wpsm_enable_email_notifications',
            __('Enable Email Notifications', 'woo-product-support'),
            array(__CLASS__, 'checkbox_field_callback'),
            'wpsm-settings',
            'wpsm_email_settings',
            array(
                'label_for' => 'wpsm_enable_email_notifications',
                'description' => __('Send email notifications for new tickets and replies', 'woo-product-support')
            )
        );

        add_settings_field(
            'wpsm_admin_notification_email',
            __('Admin Notification Email', 'woo-product-support'),
            array(__CLASS__, 'email_field_callback'),
            'wpsm-settings',
            'wpsm_email_settings',
            array(
                'label_for' => 'wpsm_admin_notification_email',
                'description' => __('Email address for admin notifications (leave blank to use default admin email)', 'woo-product-support')
            )
        );
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('wpsm_settings');
                do_settings_sections('wpsm-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function general_settings_callback() {
        echo '<p>' . __('Configure general settings for the support ticket system.', 'woo-product-support') . '</p>';
    }

    public static function email_settings_callback() {
        echo '<p>' . __('Configure email notification settings.', 'woo-product-support') . '</p>';
    }

    public static function number_field_callback($args) {
        $options = get_option('wpsm_settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : $args['default'];
        ?>
        <input type="number" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="wpsm_settings[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               min="<?php echo esc_attr($args['min']); ?>"
               max="<?php echo esc_attr($args['max']); ?>"
               class="regular-text">
        <?php
    }

    public static function checkbox_field_callback($args) {
        $options = get_option('wpsm_settings');
        $checked = isset($options[$args['label_for']]) ? $options[$args['label_for']] : 1;
        ?>
        <input type="checkbox" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="wpsm_settings[<?php echo esc_attr($args['label_for']); ?>]"
               value="1"
               <?php checked(1, $checked, true); ?>>
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }

    public static function email_field_callback($args) {
        $options = get_option('wpsm_settings');
        $value = isset($options[$args['label_for']]) ? $options[$args['label_for']] : get_option('admin_email');
        ?>
        <input type="email" 
               id="<?php echo esc_attr($args['label_for']); ?>"
               name="wpsm_settings[<?php echo esc_attr($args['label_for']); ?>]"
               value="<?php echo esc_attr($value); ?>"
               class="regular-text">
        <p class="description"><?php echo esc_html($args['description']); ?></p>
        <?php
    }
}