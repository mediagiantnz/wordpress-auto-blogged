<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Public {

    /**
     * Handle post approval via admin-post.php
     */
    public function handle_approve_post() {
        // Check if user is logged in
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            // Store the original URL to redirect back after login
            $redirect_url = add_query_arg( $_GET, admin_url( 'admin-post.php' ) );
            wp_redirect( wp_login_url( $redirect_url ) );
            exit;
        }
        
        $key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        
        if ( $this->validate_approval_key( $key, $post_id, 'approve' ) ) {
            if ( $this->publish_post( $post_id ) ) {
                wp_redirect( admin_url( 'edit.php?post_status=publish&wpab_message=approved' ) );
                exit;
            }
        }
        
        wp_die( 'Invalid or expired approval link. Please check your email for the latest approval request.', 'Invalid Link', array( 'response' => 403 ) );
    }
    
    /**
     * Handle post rejection via admin-post.php
     */
    public function handle_reject_post() {
        // Check if user is logged in
        if ( ! is_user_logged_in() || ! current_user_can( 'edit_posts' ) ) {
            // Store the original URL to redirect back after login
            $redirect_url = add_query_arg( $_GET, admin_url( 'admin-post.php' ) );
            wp_redirect( wp_login_url( $redirect_url ) );
            exit;
        }
        
        $key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';
        $post_id = isset( $_GET['post_id'] ) ? intval( $_GET['post_id'] ) : 0;
        
        if ( $this->validate_approval_key( $key, $post_id, 'reject' ) ) {
            if ( $this->delete_post( $post_id ) ) {
                wp_redirect( admin_url( 'edit.php?wpab_message=rejected' ) );
                exit;
            }
        }
        
        wp_die( 'Invalid or expired rejection link. Please check your email for the latest approval request.', 'Invalid Link', array( 'response' => 403 ) );
    }

    /**
     * Validate approval key
     */
    private function validate_approval_key( $key, $post_id, $action = 'approve' ) {
        if ( empty( $key ) || empty( $post_id ) ) {
            return false;
        }
        
        // Check if post exists and is a draft
        $post = get_post( $post_id );
        if ( ! $post || $post->post_status !== 'draft' ) {
            return false;
        }
        
        // Check the appropriate key based on action
        if ( $action === 'approve' ) {
            $stored_key = get_post_meta( $post_id, '_wpab_approve_key', true );
        } else {
            $stored_key = get_post_meta( $post_id, '_wpab_reject_key', true );
        }
        
        return ( $key === $stored_key );
    }

    /**
     * Publish a post
     */
    private function publish_post( $post_id ) {
        // Publish the post
        $result = wp_update_post( array(
            'ID' => $post_id,
            'post_status' => 'publish'
        ) );
        
        if ( ! is_wp_error( $result ) ) {
            // Remove approval keys
            delete_post_meta( $post_id, '_wpab_approve_key' );
            delete_post_meta( $post_id, '_wpab_reject_key' );
            
            // Log the approval
            error_log( 'WPAB: Post ' . $post_id . ' approved and published by user ' . get_current_user_id() );
            
            return true;
        }
        
        return false;
    }

    /**
     * Delete a post
     */
    private function delete_post( $post_id ) {
        // Delete the post
        $result = wp_delete_post( $post_id, true );
        
        if ( $result ) {
            // Log the rejection
            error_log( 'WPAB: Post ' . $post_id . ' rejected and deleted by user ' . get_current_user_id() );
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Add admin notices for approval actions
     */
    public function add_admin_notices() {
        if ( isset( $_GET['wpab_message'] ) ) {
            $message = sanitize_text_field( $_GET['wpab_message'] );
            $class = 'notice notice-success is-dismissible';
            $text = '';
            
            switch ( $message ) {
                case 'approved':
                    $text = 'Post approved and published successfully!';
                    break;
                case 'rejected':
                    $text = 'Post rejected and deleted successfully.';
                    break;
            }
            
            if ( $text ) {
                printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $text ) );
            }
        }
    }
    
    /**
     * Add frontend notices for approval actions
     */
    public function add_frontend_notices() {
        // Check for approval success
        if ( isset( $_GET['wpab_approved'] ) && $_GET['wpab_approved'] == '1' ) {
            echo '<div style="background: #4CAF50; color: white; padding: 15px; text-align: center; font-size: 16px;">';
            echo 'Post approved and published successfully! You are now viewing the published post.';
            echo '</div>';
        }
        
        // Check for rejection success
        if ( isset( $_GET['wpab_rejected'] ) && $_GET['wpab_rejected'] == '1' ) {
            echo '<div style="background: #f44336; color: white; padding: 15px; text-align: center; font-size: 16px;">';
            echo 'Post rejected and deleted successfully.';
            echo '</div>';
        }
    }
}

// Add frontend notices
add_action( 'wp_body_open', array( new WPAB_Public(), 'add_frontend_notices' ) );