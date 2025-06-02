<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Approval_Handler {
    
    /**
     * Initialize the approval handler
     */
    public function __construct() {
        // Add query vars
        add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
        
        // Handle approval requests
        add_action( 'template_redirect', array( $this, 'handle_approval_request' ) );
    }
    
    /**
     * Add custom query vars
     */
    public function add_query_vars( $vars ) {
        $vars[] = 'wpab_approval';
        return $vars;
    }
    
    /**
     * Handle approval request
     */
    public function handle_approval_request() {
        // Check if this is an approval request
        $approval_action = get_query_var( 'wpab_approval' );
        if ( empty( $approval_action ) ) {
            return;
        }
        
        // Check if user is logged in
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            // Store the current URL for redirect after login
            $redirect_url = ( is_ssl() ? 'https://' : 'http://' ) . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            wp_redirect( wp_login_url( $redirect_url ) );
            exit;
        }
        
        // Get parameters
        $key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        
        // Validate parameters
        if ( empty( $key ) || empty( $post_id ) ) {
            wp_die( 'Invalid request parameters.', 'Error', array( 'response' => 400 ) );
        }
        
        // Handle based on action
        switch ( $approval_action ) {
            case 'approve':
                $this->handle_approve( $key, $post_id );
                break;
            case 'reject':
                $this->handle_reject( $key, $post_id );
                break;
            default:
                wp_die( 'Invalid action.', 'Error', array( 'response' => 400 ) );
        }
    }
    
    /**
     * Handle post approval
     */
    private function handle_approve( $key, $post_id ) {
        // Validate the key
        $stored_key = get_post_meta( $post_id, '_wpab_approve_key', true );
        if ( $key !== $stored_key ) {
            wp_die( 'Invalid or expired approval key.', 'Error', array( 'response' => 403 ) );
        }
        
        // Check post exists and is draft
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'draft' ) {
            wp_die( 'Post not found or already processed.', 'Error', array( 'response' => 404 ) );
        }
        
        // Publish the post
        $result = wp_update_post( array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ) );
        
        if ( is_wp_error( $result ) ) {
            wp_die( 'Failed to publish post: ' . $result->get_error_message(), 'Error', array( 'response' => 500 ) );
        }
        
        // Remove approval keys
        delete_post_meta( $post_id, '_wpab_approve_key' );
        delete_post_meta( $post_id, '_wpab_reject_key' );
        
        // Redirect to the published post
        wp_redirect( get_permalink( $post_id ) . '?wpab_approved=1' );
        exit;
    }
    
    /**
     * Handle post rejection
     */
    private function handle_reject( $key, $post_id ) {
        // Validate the key
        $stored_key = get_post_meta( $post_id, '_wpab_reject_key', true );
        if ( $key !== $stored_key ) {
            wp_die( 'Invalid or expired rejection key.', 'Error', array( 'response' => 403 ) );
        }
        
        // Check post exists and is draft
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'draft' ) {
            wp_die( 'Post not found or already processed.', 'Error', array( 'response' => 404 ) );
        }
        
        // Delete the post
        $result = wp_delete_post( $post_id, true );
        
        if ( ! $result ) {
            wp_die( 'Failed to delete post.', 'Error', array( 'response' => 500 ) );
        }
        
        // Redirect to homepage with message
        wp_redirect( home_url( '?wpab_rejected=1' ) );
        exit;
    }
}