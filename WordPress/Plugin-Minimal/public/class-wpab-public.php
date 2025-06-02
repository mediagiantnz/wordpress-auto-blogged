<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Public {

    public function add_rewrite_rules() {
        add_rewrite_rule( '^wpab-approval/?', 'index.php?wpab_approval=1', 'top' );
        add_rewrite_tag( '%wpab_approval%', '1' );
    }

    public function approval_handler() {
        if ( get_query_var( 'wpab_approval' ) ) {
            $action = isset( $_GET['wpab_action'] ) ? sanitize_text_field( $_GET['wpab_action'] ) : '';
            $key = isset( $_GET['key'] ) ? sanitize_text_field( $_GET['key'] ) : '';

            if ( $this->validate_approval_key( $key ) ) {
                if ( $action === 'approve' ) {
                    $this->publish_approved_content( $key );
                    echo 'Content approved and published.';
                } elseif ( $action === 'reject' ) {
                    $this->handle_rejection( $key );
                    echo 'Content rejected.';
                } else {
                    echo 'Invalid action.';
                }
            } else {
                echo 'Invalid or expired approval key.';
            }
            exit;
        }
    }

    private function validate_approval_key( $key ) {
        if ( empty( $key ) ) {
            return false;
        }
        
        // Search for post with this approval or rejection key
        $args = array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => '_wpab_approve_key',
                    'value' => $key,
                    'compare' => '='
                ),
                array(
                    'key' => '_wpab_reject_key',
                    'value' => $key,
                    'compare' => '='
                )
            ),
            'posts_per_page' => 1
        );
        
        $posts = get_posts( $args );
        
        return ! empty( $posts );
    }

    private function publish_approved_content( $key ) {
        // Find post with this approval key
        $args = array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'meta_key' => '_wpab_approve_key',
            'meta_value' => $key,
            'posts_per_page' => 1
        );
        
        $posts = get_posts( $args );
        
        if ( ! empty( $posts ) ) {
            $post_id = $posts[0]->ID;
            
            // Publish the post
            wp_update_post( array(
                'ID' => $post_id,
                'post_status' => 'publish'
            ) );
            
            // Remove approval keys
            delete_post_meta( $post_id, '_wpab_approve_key' );
            delete_post_meta( $post_id, '_wpab_reject_key' );
            
            // Mark the topic as published
            $topics = get_option( 'wpab_generated_topics', array() );
            foreach ( $topics as &$topic ) {
                if ( isset( $topic['post_id'] ) && $topic['post_id'] == $post_id ) {
                    $topic['status'] = 'published';
                    break;
                }
            }
            update_option( 'wpab_generated_topics', $topics );
            
            return true;
        }
        
        return false;
    }

    private function handle_rejection( $key ) {
        // Find post with this rejection key
        $args = array(
            'post_type' => 'post',
            'post_status' => 'draft',
            'meta_key' => '_wpab_reject_key',
            'meta_value' => $key,
            'posts_per_page' => 1
        );
        
        $posts = get_posts( $args );
        
        if ( ! empty( $posts ) ) {
            $post_id = $posts[0]->ID;
            
            // Delete the post
            wp_delete_post( $post_id, true );
            
            // Mark the topic as rejected
            $topics = get_option( 'wpab_generated_topics', array() );
            foreach ( $topics as &$topic ) {
                if ( isset( $topic['post_id'] ) && $topic['post_id'] == $post_id ) {
                    $topic['status'] = 'rejected';
                    unset( $topic['post_id'] );
                    break;
                }
            }
            update_option( 'wpab_generated_topics', $topics );
            
            return true;
        }
        
        return false;
    }

    // Add additional methods as needed.
}
