<?php
/**
 * REST API endpoints for WordPress Auto Blogger
 *
 * @package WordPress_Auto_Blogger
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class WPAB_REST_API
 */
class WPAB_REST_API {
    
    /**
     * Initialize REST API
     */
    public static function init() {
        add_action('rest_api_init', array(__CLASS__, 'register_routes'));
    }
    
    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Register wpab-cloud/v1 namespace for cloud integration
        register_rest_route('wpab-cloud/v1', '/publish', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'publish_content'),
            'permission_callback' => array(__CLASS__, 'check_api_key'),
            'args'                => array(
                'title' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'content' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'excerpt' => array(
                    'required' => false,
                    'type'     => 'string',
                ),
                'status' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'draft',
                    'enum'     => array('draft', 'publish', 'pending', 'private'),
                ),
                'categories' => array(
                    'required' => false,
                    'type'     => 'array',
                    'items'    => array(
                        'type' => 'integer',
                    ),
                ),
                'tags' => array(
                    'required' => false,
                    'type'     => 'array',
                    'items'    => array(
                        'type' => 'string',
                    ),
                ),
                'meta' => array(
                    'required' => false,
                    'type'     => 'object',
                ),
                'featured_image_url' => array(
                    'required' => false,
                    'type'     => 'string',
                    'format'   => 'uri',
                ),
            ),
        ));
        
        // Also register wpautoblogger/v1 namespace for backward compatibility
        register_rest_route('wpautoblogger/v1', '/publish', array(
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => array(__CLASS__, 'publish_content'),
            'permission_callback' => array(__CLASS__, 'check_api_key'),
            'args'                => array(
                'title' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'content' => array(
                    'required' => true,
                    'type'     => 'string',
                ),
                'excerpt' => array(
                    'required' => false,
                    'type'     => 'string',
                ),
                'status' => array(
                    'required' => false,
                    'type'     => 'string',
                    'default'  => 'draft',
                    'enum'     => array('draft', 'publish', 'pending', 'private'),
                ),
                'categories' => array(
                    'required' => false,
                    'type'     => 'array',
                    'items'    => array(
                        'type' => 'integer',
                    ),
                ),
                'tags' => array(
                    'required' => false,
                    'type'     => 'array',
                    'items'    => array(
                        'type' => 'string',
                    ),
                ),
                'meta' => array(
                    'required' => false,
                    'type'     => 'object',
                ),
                'featured_image_url' => array(
                    'required' => false,
                    'type'     => 'string',
                    'format'   => 'uri',
                ),
            ),
        ));
        
        // Health check endpoint
        register_rest_route('wpab-cloud/v1', '/health', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array(__CLASS__, 'health_check'),
            'permission_callback' => array(__CLASS__, 'check_api_key'),
        ));
    }
    
    /**
     * Check API key for authentication
     *
     * @param WP_REST_Request $request Request object.
     * @return bool|WP_Error
     */
    public static function check_api_key($request) {
        $api_key = $request->get_header('X-API-Key');
        
        if (empty($api_key)) {
            return new WP_Error('missing_api_key', 'API key is required', array('status' => 401));
        }
        
        // Get the stored API key from options
        $stored_api_key = get_option('wpab_api_key');
        
        // If no API key is set, generate and store one
        if (empty($stored_api_key)) {
            $stored_api_key = wp_generate_password(32, false);
            update_option('wpab_api_key', $stored_api_key);
        }
        
        // Validate API key
        if ($api_key !== $stored_api_key) {
            return new WP_Error('invalid_api_key', 'Invalid API key', array('status' => 401));
        }
        
        return true;
    }
    
    /**
     * Publish content via REST API
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response|WP_Error
     */
    public static function publish_content($request) {
        try {
            // Get parameters
            $title = sanitize_text_field($request->get_param('title'));
            $content = wp_kses_post($request->get_param('content'));
            $excerpt = sanitize_text_field($request->get_param('excerpt'));
            $status = $request->get_param('status');
            $categories = $request->get_param('categories');
            $tags = $request->get_param('tags');
            $meta = $request->get_param('meta');
            $featured_image_url = $request->get_param('featured_image_url');
            
            // Create post array
            $post_data = array(
                'post_title'   => $title,
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status'  => $status,
                'post_type'    => 'post',
                'post_author'  => 1, // Default to admin user
            );
            
            // Insert the post
            $post_id = wp_insert_post($post_data, true);
            
            if (is_wp_error($post_id)) {
                return new WP_Error('post_creation_failed', $post_id->get_error_message(), array('status' => 500));
            }
            
            // Set categories
            if (!empty($categories)) {
                wp_set_post_categories($post_id, $categories);
            }
            
            // Set tags
            if (!empty($tags)) {
                wp_set_post_tags($post_id, $tags);
            }
            
            // Set meta data
            if (!empty($meta) && is_array($meta)) {
                foreach ($meta as $meta_key => $meta_value) {
                    update_post_meta($post_id, $meta_key, $meta_value);
                }
            }
            
            // Handle featured image
            if (!empty($featured_image_url)) {
                $image_id = self::upload_image_from_url($featured_image_url, $post_id);
                if ($image_id && !is_wp_error($image_id)) {
                    set_post_thumbnail($post_id, $image_id);
                }
            }
            
            // Get the post object
            $post = get_post($post_id);
            
            // Return success response
            return new WP_REST_Response(array(
                'success' => true,
                'post_id' => $post_id,
                'post_url' => get_permalink($post_id),
                'post_status' => $post->post_status,
                'message' => 'Post created successfully',
            ), 201);
            
        } catch (Exception $e) {
            return new WP_Error('unexpected_error', $e->getMessage(), array('status' => 500));
        }
    }
    
    /**
     * Health check endpoint
     *
     * @param WP_REST_Request $request Request object.
     * @return WP_REST_Response
     */
    public static function health_check($request) {
        return new WP_REST_Response(array(
            'status' => 'ok',
            'plugin_version' => WPAUTOBLOGGER_VERSION,
            'wordpress_version' => get_bloginfo('version'),
            'php_version' => PHP_VERSION,
            'api_endpoints' => array(
                'publish' => rest_url('wpab-cloud/v1/publish'),
                'health' => rest_url('wpab-cloud/v1/health'),
            ),
            'timestamp' => current_time('c'),
        ), 200);
    }
    
    /**
     * Upload image from URL
     *
     * @param string $image_url Image URL.
     * @param int    $post_id   Post ID.
     * @return int|WP_Error Attachment ID or error.
     */
    private static function upload_image_from_url($image_url, $post_id) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        // Download image
        $tmp = download_url($image_url);
        
        if (is_wp_error($tmp)) {
            return $tmp;
        }
        
        // Get file info
        $file_array = array(
            'name'     => basename($image_url),
            'tmp_name' => $tmp,
        );
        
        // Upload image
        $attachment_id = media_handle_sideload($file_array, $post_id);
        
        // Delete temp file
        @unlink($tmp);
        
        return $attachment_id;
    }
}