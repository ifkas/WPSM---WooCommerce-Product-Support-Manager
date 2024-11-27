<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Assets {
    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
    }

    public static function enqueue_frontend_assets() {
        // Only load on my account ticket pages
        // if (is_account_page() && (is_wc_endpoint_url('support-tickets') || isset($_GET['ticket_id']))) {
        if (is_account_page()) {
            wp_enqueue_style(
                'wpsm-frontend-styles',
                WPSM_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                WPSM_VERSION
            );

            wp_enqueue_script(
                'wpsm-frontend-scripts',
                WPSM_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                WPSM_VERSION,
                true
            );

            // Add any localized script data
            wp_localize_script('wpsm-frontend-scripts', 'wpsmFrontend', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wpsm_frontend_nonce')
            ));
        }
    }

    public static function enqueue_admin_assets($hook) {
        // Only load on plugin's admin pages
        if ('post.php' !== $hook && 'post-new.php' !== $hook) {
            return;
        }

        global $post_type;
        if ('support_ticket' !== $post_type) {
            return;
        }

        wp_enqueue_style(
            'wpsm-admin-styles',
            WPSM_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WPSM_VERSION
        );

        wp_enqueue_script(
            'wpsm-admin-scripts',
            WPSM_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            WPSM_VERSION,
            true
        );

        // Add any localized script data
        wp_localize_script('wpsm-admin-scripts', 'wpsmAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpsm_admin_nonce')
        ));
    }
}