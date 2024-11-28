<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Post_Types {
    
    public static function init() {
        add_action('init', array(__CLASS__, 'register_post_types'), 5);
        add_action('init', array(__CLASS__, 'register_post_status'), 10);
        // to prevent changing the author when publishing / customer role
        add_filter('wp_insert_post_data', array(__CLASS__, 'preserve_ticket_author'), 10, 2);
    }

    public static function preserve_ticket_author($data, $postarr) {
        if ($data['post_type'] === 'support_ticket' && !empty($postarr['ID'])) {
            // Must Keep the original author instead of changing to current user
            $original_post = get_post($postarr['ID']);
            if ($original_post) {
                $data['post_author'] = $original_post->post_author;
            }
        }
        return $data;
    }
    
    public static function register_post_types() {
        if (!is_blog_installed() || post_type_exists('support_ticket')) {
            return;
        }

        register_post_type('support_ticket', 
            array(
                'labels' => array(
                    'name'                  => __('Product Support Tickets', 'woo-product-support'),
                    'singular_name'         => __('Product Support Ticket', 'woo-product-support'),
                    'menu_name'             => _x('Support Tickets', 'Admin menu name', 'woo-product-support'),
                    'add_new'              => __('Add Ticket', 'woo-product-support'),
                    'add_new_item'         => __('Add New Ticket', 'woo-product-support'),
                    'edit'                 => __('Edit', 'woo-product-support'),
                    'edit_item'            => __('Edit Ticket', 'woo-product-support'),
                    'new_item'             => __('New Ticket', 'woo-product-support'),
                    'view_item'            => __('View Ticket', 'woo-product-support'),
                    'search_items'         => __('Search Tickets', 'woo-product-support'),
                    'not_found'            => __('No tickets found', 'woo-product-support'),
                    'not_found_in_trash'   => __('No tickets found in trash', 'woo-product-support'),
                ),
                'description'          => __('This is where support tickets are stored.', 'woo-product-support'),
                'public'               => false,
                'show_ui'              => true,
                'capability_type'      => 'post',
                'map_meta_cap'         => true,
                'publicly_queryable'   => false,
                'exclude_from_search'  => true,
                'post_status'          => array('ticket_open', 'ticket_in_progress', 'ticket_resolved', 'publish'),
                'hierarchical'         => false,
                'rewrite'              => false,
                'supports'             => array('title', 'editor', 'comments'),
                'has_archive'          => false,
                'show_in_nav_menus'    => false,
                'show_in_menu'         => true,
                'menu_position'        => 56,    // Position after WooCommerce
                'menu_icon'            => 'dashicons-sos',
                'show_in_rest'         => true,
                'rest_base'            => false
            )
        );
    }
    
    public static function register_post_status() {
        register_post_status('ticket_open', array(
            'label'                     => _x('Open', 'Ticket status', 'woo-product-support'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Open <span class="count">(%s)</span>', 'Open <span class="count">(%s)</span>', 'woo-product-support'),
        ));
        
        register_post_status('ticket_in_progress', array(
            'label'                     => _x('In Progress', 'Ticket status', 'woo-product-support'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('In Progress <span class="count">(%s)</span>', 'In Progress <span class="count">(%s)</span>', 'woo-product-support'),
        ));
        
        register_post_status('ticket_resolved', array(
            'label'                     => _x('Resolved', 'Ticket status', 'woo-product-support'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Resolved <span class="count">(%s)</span>', 'Resolved <span class="count">(%s)</span>', 'woo-product-support'),
        ));
    }
}