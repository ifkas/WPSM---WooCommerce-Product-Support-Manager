<?php
if (!defined('ABSPATH')) {
    exit;
}

class WPSM_Emails {
    public static function init() {
        // No direct initialization needed, ignore this - methods will be called from hooks
    }

    public static function should_send_emails() {
        $options = get_option('wpsm_settings', WPSM_Settings::get_default_settings());
        return !empty($options['wpsm_enable_email_notifications']);
    }

    public static function get_admin_email() {
        $options = get_option('wpsm_settings', WPSM_Settings::get_default_settings());
        return !empty($options['wpsm_admin_notification_email']) ? 
               $options['wpsm_admin_notification_email'] : 
               get_option('admin_email');
    }

    /**
     * Send notification to admin when new ticket is created
     */
    public static function notify_admin_new_ticket($ticket_id) {
        if (!self::should_send_emails()) {
            return;
        }

        $ticket = get_post($ticket_id);
        $customer = get_user_by('id', $ticket->post_author);
        $product_id = get_post_meta($ticket_id, '_ticket_product_id', true);
        $product = wc_get_product($product_id);
        
        $admin_email = self::get_admin_email();
        $subject = sprintf(__('New Support Ticket: %s', 'woo-product-support'), $ticket->post_title);
        
        $message = sprintf(
            /* translators: 1: customer name, 2: ticket title, 3: product name */
            __('A new support ticket has been created by %1$s

Ticket: %2$s
Product: %3$s

Click here to view and reply: %4$s', 'woo-product-support'),
            $customer->display_name,
            $ticket->post_title,
            $product ? $product->get_name() : 'N/A',
            admin_url('post.php?post=' . $ticket_id . '&action=edit')
        );

        wp_mail($admin_email, $subject, $message);
    }

    /**
     * Send notification to customer when admin replies
     */
    public static function notify_customer_new_reply($comment_id) {
        if (!self::should_send_emails()) {
            return;
        }

        $comment = get_comment($comment_id);
        $ticket = get_post($comment->comment_post_ID);
        $customer = get_user_by('id', $ticket->post_author);
        
        // Only send if reply is from admin
        if ($comment->user_id == $ticket->post_author) {
            return;
        }

        $subject = sprintf(__('New Reply to Your Support Ticket: %s', 'woo-product-support'), $ticket->post_title);
        
        $message = sprintf(
            /* translators: 1: ticket title, 2: reply content */
            __('A new reply has been added to your support ticket: %1$s

Reply:
%2$s

Click here to view and respond: %3$s', 'woo-product-support'),
            $ticket->post_title,
            $comment->comment_content,
            wc_get_account_endpoint_url('support-tickets') . '?ticket_id=' . $ticket->ID
        );

        wp_mail($customer->user_email, $subject, $message);
    }

    /**
     * Send notification to admin when customer replies
     */
    public static function notify_admin_new_reply($comment_id) {
        if (!self::should_send_emails()) {
            return;
        }

        $comment = get_comment($comment_id);
        $ticket = get_post($comment->comment_post_ID);
        
        // Only send if reply is from customer
        if ($comment->user_id != $ticket->post_author) {
            return;
        }

        $admin_email = get_option('admin_email');
        $subject = sprintf(__('New Customer Reply - Ticket: %s', 'woo-product-support'), $ticket->post_title);
        
        $message = sprintf(
            /* translators: 1: ticket title, 2: customer name, 3: reply content */
            __('A customer has replied to support ticket: %1$s
Customer: %2$s

Reply:
%3$s

Click here to view and respond: %4$s', 'woo-product-support'),
            $ticket->post_title,
            $comment->comment_author,
            $comment->comment_content,
            admin_url('post.php?post=' . $ticket->ID . '&action=edit')
        );

        wp_mail($admin_email, $subject, $message);
    }
}