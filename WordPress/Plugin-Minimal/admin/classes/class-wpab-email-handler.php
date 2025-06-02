<?php
// File: wp-auto-blogger/admin/classes/class-wpab-email-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Email_Handler {
    
    private $mailgun_domain;
    private $mailgun_api_key;
    private $from_name;
    private $from_email;
    private $signature;
    
    public function __construct() {
        $options = get_option( 'wpab_options', array() );
        
        $this->mailgun_domain = isset( $options['mailgun_domain'] ) ? $options['mailgun_domain'] : '';
        $this->mailgun_api_key = isset( $options['mailgun_api_key'] ) ? $options['mailgun_api_key'] : '';
        $this->from_name = isset( $options['email_from_name'] ) ? $options['email_from_name'] : get_bloginfo('name');
        $this->from_email = isset( $options['email_from_address'] ) ? $options['email_from_address'] : get_option('admin_email');
        $this->signature = isset( $options['email_signature'] ) ? $options['email_signature'] : '';
        
        // Hook into post creation to send approval emails
        add_action( 'wpab_post_generated', array( $this, 'send_approval_email' ), 10, 2 );
        
        // Hook for content calendar alerts
        add_action( 'wpab_check_content_calendar', array( $this, 'check_and_send_alerts' ) );
        
        // Schedule content calendar checks
        if ( ! wp_next_scheduled( 'wpab_check_content_calendar' ) ) {
            wp_schedule_event( time(), 'daily', 'wpab_check_content_calendar' );
        }
    }
    
    /**
     * Send approval email for newly generated content
     */
    public function send_approval_email( $post_id, $topic ) {
        $options = get_option( 'wpab_options', array() );
        
        // Check if approval emails are enabled
        if ( ! isset( $options['send_approval_emails'] ) || $options['send_approval_emails'] != '1' ) {
            return;
        }
        
        // Get recipient email
        $recipient_email = isset( $options['approval_email_address'] ) ? $options['approval_email_address'] : get_option('admin_email');
        
        if ( empty( $recipient_email ) ) {
            error_log( 'WPAB: No recipient email configured for approval emails.' );
            return;
        }
        
        // Generate approval keys
        $approve_key = wp_generate_password( 32, false );
        $reject_key = wp_generate_password( 32, false );
        
        // Store keys as post meta
        update_post_meta( $post_id, '_wpab_approve_key', $approve_key );
        update_post_meta( $post_id, '_wpab_reject_key', $reject_key );
        
        // Get post details
        $post = get_post( $post_id );
        $post_title = $post->post_title;
        $post_url = get_permalink( $post_id );
        $edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );
        
        // Build approval URLs
        $base_url = home_url('/wpab-approval/');
        $approve_url = add_query_arg( array(
            'wpab_action' => 'approve',
            'key' => $approve_key,
            'post_id' => $post_id
        ), $base_url );
        
        $reject_url = add_query_arg( array(
            'wpab_action' => 'reject',
            'key' => $reject_key,
            'post_id' => $post_id
        ), $base_url );
        
        // Create email content
        $subject = 'New Blog Post Ready for Review: ' . $post_title;
        
        $html_content = $this->get_email_template( 'approval', array(
            'post_title' => $post_title,
            'post_url' => $post_url,
            'edit_url' => $edit_url,
            'approve_url' => $approve_url,
            'reject_url' => $reject_url,
            'post_excerpt' => wp_trim_words( $post->post_content, 50 ),
            'signature' => $this->signature
        ));
        
        // Send email
        $this->send_mailgun_email( $recipient_email, $subject, $html_content );
    }
    
    /**
     * Check content calendar and send alerts
     */
    public function check_and_send_alerts() {
        $options = get_option( 'wpab_options', array() );
        
        // Check if alerts are enabled
        if ( ! isset( $options['send_calendar_alerts'] ) || $options['send_calendar_alerts'] != '1' ) {
            return;
        }
        
        // Get recipient email
        $recipient_email = isset( $options['alert_email_address'] ) ? $options['alert_email_address'] : get_option('admin_email');
        
        if ( empty( $recipient_email ) ) {
            return;
        }
        
        // Count approved topics
        $approved_topics = get_option( 'wpab_generated_topics', array() );
        $approved_count = 0;
        
        foreach ( $approved_topics as $topic ) {
            if ( isset( $topic['status'] ) && $topic['status'] === 'approved' ) {
                $approved_count++;
            }
        }
        
        // Get last alert sent data
        $last_alert_7 = get_option( 'wpab_last_alert_7', 0 );
        $last_alert_3 = get_option( 'wpab_last_alert_3', 0 );
        $last_alert_0 = get_option( 'wpab_last_alert_0', 0 );
        
        $current_time = time();
        $one_week_ago = $current_time - WEEK_IN_SECONDS;
        
        // Send appropriate alert
        if ( $approved_count <= 7 && $approved_count > 3 && $last_alert_7 < $one_week_ago ) {
            $this->send_calendar_alert( $recipient_email, $approved_count, 'warning' );
            update_option( 'wpab_last_alert_7', $current_time );
        } elseif ( $approved_count <= 3 && $approved_count > 0 && $last_alert_3 < $one_week_ago ) {
            $this->send_calendar_alert( $recipient_email, $approved_count, 'critical' );
            update_option( 'wpab_last_alert_3', $current_time );
        } elseif ( $approved_count === 0 && $last_alert_0 < $one_week_ago ) {
            $this->send_calendar_alert( $recipient_email, $approved_count, 'empty' );
            update_option( 'wpab_last_alert_0', $current_time );
        }
    }
    
    /**
     * Send content calendar alert
     */
    private function send_calendar_alert( $recipient_email, $count, $level ) {
        $subject_prefix = '';
        $message_type = '';
        
        switch ( $level ) {
            case 'warning':
                $subject_prefix = '⚠️ Low Content Alert';
                $message_type = 'Your content calendar is running low.';
                break;
            case 'critical':
                $subject_prefix = '🚨 Critical Content Alert';
                $message_type = 'Your content calendar is critically low.';
                break;
            case 'empty':
                $subject_prefix = '❗ Empty Content Calendar';
                $message_type = 'Your content calendar is empty!';
                break;
        }
        
        $subject = $subject_prefix . ': ' . $count . ' topics remaining';
        
        $html_content = $this->get_email_template( 'alert', array(
            'message_type' => $message_type,
            'topic_count' => $count,
            'calendar_url' => admin_url( 'admin.php?page=wpab-content-calendar' ),
            'signature' => $this->signature
        ));
        
        $this->send_mailgun_email( $recipient_email, $subject, $html_content );
    }
    
    /**
     * Send email via Mailgun
     */
    private function send_mailgun_email( $to, $subject, $html_content, $text_content = '' ) {
        if ( empty( $this->mailgun_api_key ) || empty( $this->mailgun_domain ) ) {
            error_log( 'WPAB: Mailgun configuration missing. Cannot send email.' );
            return false;
        }
        
        // If no text content provided, strip HTML
        if ( empty( $text_content ) ) {
            $text_content = wp_strip_all_tags( $html_content );
        }
        
        $url = 'https://api.mailgun.net/v3/' . $this->mailgun_domain . '/messages';
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Basic ' . base64_encode( 'api:' . $this->mailgun_api_key ),
            ),
            'body' => array(
                'from' => $this->from_name . ' <' . $this->from_email . '>',
                'to' => $to,
                'subject' => $subject,
                'text' => $text_content,
                'html' => $html_content,
            ),
            'timeout' => 30,
        );
        
        $response = wp_remote_post( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            error_log( 'WPAB: Error sending Mailgun email: ' . $response->get_error_message() );
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code( $response );
        if ( $response_code !== 200 ) {
            $body = wp_remote_retrieve_body( $response );
            error_log( 'WPAB: Mailgun API error (' . $response_code . '): ' . $body );
            return false;
        }
        
        return true;
    }
    
    /**
     * Get email template
     */
    private function get_email_template( $template_type, $variables = array() ) {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background-color: #f4f4f4; padding: 20px; text-align: center; }
        .content { padding: 20px; background-color: #fff; }
        .button { display: inline-block; padding: 12px 24px; margin: 10px 5px; text-decoration: none; border-radius: 5px; font-weight: bold; }
        .approve-button { background-color: #28a745; color: white; }
        .reject-button { background-color: #dc3545; color: white; }
        .edit-button { background-color: #007bff; color: white; }
        .footer { background-color: #f4f4f4; padding: 20px; text-align: center; font-size: 12px; }
        .excerpt { background-color: #f8f9fa; padding: 15px; border-left: 4px solid #007bff; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">';
        
        if ( $template_type === 'approval' ) {
            $html .= '
        <div class="header">
            <h2>New Blog Post for Review</h2>
        </div>
        <div class="content">
            <h3>' . esc_html( $variables['post_title'] ) . '</h3>
            <div class="excerpt">
                <p><strong>Preview:</strong></p>
                <p>' . esc_html( $variables['post_excerpt'] ) . '</p>
            </div>
            <p>A new blog post has been generated and is ready for your review.</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . esc_url( $variables['approve_url'] ) . '" class="button approve-button">✓ Approve & Publish</a>
                <a href="' . esc_url( $variables['reject_url'] ) . '" class="button reject-button">✗ Reject</a>
                <a href="' . esc_url( $variables['edit_url'] ) . '" class="button edit-button">✎ Edit Post</a>
            </div>
            <p><strong>Direct link to post:</strong> <a href="' . esc_url( $variables['post_url'] ) . '">' . esc_url( $variables['post_url'] ) . '</a></p>
        </div>';
        } elseif ( $template_type === 'alert' ) {
            $html .= '
        <div class="header">
            <h2>Content Calendar Alert</h2>
        </div>
        <div class="content">
            <p><strong>' . esc_html( $variables['message_type'] ) . '</strong></p>
            <p>You currently have <strong>' . intval( $variables['topic_count'] ) . '</strong> approved topics remaining in your content calendar.</p>
            <p>To ensure continuous content publication, please generate more topics soon.</p>
            <div style="text-align: center; margin: 30px 0;">
                <a href="' . esc_url( $variables['calendar_url'] ) . '" class="button edit-button">View Content Calendar</a>
            </div>
        </div>';
        }
        
        $html .= '
        <div class="footer">
            ' . ( ! empty( $variables['signature'] ) ? '<p>' . nl2br( esc_html( $variables['signature'] ) ) . '</p>' : '' ) . '
            <p>Powered by WP Auto Blogger</p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Clean up on deactivation
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'wpab_check_content_calendar' );
    }
}