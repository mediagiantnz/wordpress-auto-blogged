<?php
/**
 * Plugin Name: WP Auto Blogger Cloud Connector
 * Description: REST API connector for WordPress Auto Blogger cloud service
 * Version: 1.0.0
 * Author: WP Auto Blogger Team
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: Proprietary
 * Text Domain: wp-auto-blogger-cloud
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WPAB_CLOUD_VERSION', '1.0.0');
define('WPAB_CLOUD_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPAB_CLOUD_PLUGIN_URL', plugin_dir_url(__FILE__));

// Initialize REST API
add_action('init', 'wpab_cloud_init');
function wpab_cloud_init() {
    // Register REST API routes
    add_action('rest_api_init', 'wpab_cloud_register_routes');
}

// Register REST routes
function wpab_cloud_register_routes() {
    // Publish endpoint
    register_rest_route('wpab-cloud/v1', '/publish', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpab_cloud_publish_content',
        'permission_callback' => 'wpab_cloud_check_api_key',
    ));
    
    // Health check endpoint
    register_rest_route('wpab-cloud/v1', '/health', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpab_cloud_health_check',
        'permission_callback' => 'wpab_cloud_check_api_key',
    ));
    
    // Verify endpoint
    register_rest_route('wpab-cloud/v1', '/verify', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'wpab_cloud_verify_site',
        'permission_callback' => 'wpab_cloud_check_api_key',
    ));
    
    // Categories endpoint
    register_rest_route('wpab-cloud/v1', '/categories', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpab_cloud_get_categories',
        'permission_callback' => 'wpab_cloud_check_api_key',
    ));
    
    // Users endpoint
    register_rest_route('wpab-cloud/v1', '/users', array(
        'methods'             => WP_REST_Server::READABLE,
        'callback'            => 'wpab_cloud_get_users',
        'permission_callback' => 'wpab_cloud_check_api_key',
    ));
}

// Add CORS headers
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function($value) {
        $origin = get_http_origin();
        $allowed_origins = array(
            'https://portal.wpautoblogger.ai',
            'http://localhost:3000', // For development
            'http://localhost:5173'  // For development
        );
        
        if (in_array($origin, $allowed_origins)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            header('Access-Control-Allow-Origin: https://portal.wpautoblogger.ai');
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Site-Key, X-Client-Id');
        header('Access-Control-Allow-Credentials: true');
        header('Access-Control-Max-Age: 86400');
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            status_header(200);
            exit();
        }
        
        return $value;
    });
}, 15);

// Check API key
function wpab_cloud_check_api_key($request) {
    $site_key = $request->get_header('X-Site-Key');
    $api_key = $request->get_header('X-API-Key'); // Also support X-API-Key for backward compatibility
    
    // Use X-Site-Key primarily, fall back to X-API-Key
    $provided_key = !empty($site_key) ? $site_key : $api_key;
    
    if (empty($provided_key)) {
        return new WP_Error('missing_authentication', 'Site key is required', array('status' => 401));
    }
    
    // Get stored site key
    $stored_site_key = get_option('wpab_site_key');
    
    // Generate key if it doesn't exist
    if (empty($stored_site_key)) {
        $stored_site_key = wp_generate_password(32, false);
        update_option('wpab_site_key', $stored_site_key);
    }
    
    // Validate key
    if ($provided_key === $stored_site_key) {
        return true;
    }
    
    return new WP_Error('invalid_authentication', 'Invalid site key', array('status' => 401));
}

// Publish content
function wpab_cloud_publish_content($request) {
    try {
        $params = $request->get_json_params();
        
        // Create post
        $post_data = array(
            'post_title'   => sanitize_text_field($params['title']),
            'post_content' => wp_kses_post($params['content']),
            'post_excerpt' => sanitize_text_field($params['excerpt'] ?? ''),
            'post_status'  => $params['status'] ?? 'draft',
            'post_type'    => 'post',
            'post_author'  => $params['author'] ?? 1,
        );
        
        $post_id = wp_insert_post($post_data, true);
        
        if (is_wp_error($post_id)) {
            return new WP_Error('post_creation_failed', $post_id->get_error_message(), array('status' => 500));
        }
        
        // Set categories
        if (!empty($params['categories'])) {
            wp_set_post_categories($post_id, $params['categories']);
        }
        
        // Set tags
        if (!empty($params['tags'])) {
            wp_set_post_tags($post_id, $params['tags']);
        }
        
        // Set meta
        if (!empty($params['meta'])) {
            foreach ($params['meta'] as $key => $value) {
                update_post_meta($post_id, $key, $value);
            }
        }
        
        // Handle featured image
        if (!empty($params['featured_image_url'])) {
            $image_id = wpab_cloud_upload_image($params['featured_image_url'], $post_id);
            if ($image_id && !is_wp_error($image_id)) {
                set_post_thumbnail($post_id, $image_id);
            }
        }
        
        return new WP_REST_Response(array(
            'success' => true,
            'post_id' => $post_id,
            'post_url' => get_permalink($post_id),
            'post_status' => get_post_status($post_id),
        ), 201);
        
    } catch (Exception $e) {
        return new WP_Error('unexpected_error', $e->getMessage(), array('status' => 500));
    }
}

// Health check
function wpab_cloud_health_check($request) {
    return new WP_REST_Response(array(
        'status' => 'ok',
        'plugin_version' => WPAB_CLOUD_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'api_endpoints' => array(
            'publish' => rest_url('wpab-cloud/v1/publish'),
            'health' => rest_url('wpab-cloud/v1/health'),
            'verify' => rest_url('wpab-cloud/v1/verify'),
            'categories' => rest_url('wpab-cloud/v1/categories'),
            'users' => rest_url('wpab-cloud/v1/users'),
        ),
        'timestamp' => current_time('c'),
    ), 200);
}

// Verify site
function wpab_cloud_verify_site($request) {
    return new WP_REST_Response(array(
        'success' => true,
        'site_url' => get_site_url(),
        'site_name' => get_bloginfo('name'),
        'api_active' => true,
        'plugin_version' => WPAB_CLOUD_VERSION,
    ), 200);
}

// Get categories
function wpab_cloud_get_categories($request) {
    $categories = get_categories(array(
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    
    $result = array();
    foreach ($categories as $category) {
        $result[] = array(
            'id' => $category->term_id,
            'name' => $category->name,
            'slug' => $category->slug,
            'count' => $category->count,
        );
    }
    
    return new WP_REST_Response($result, 200);
}

// Get users
function wpab_cloud_get_users($request) {
    $users = get_users(array(
        'role__in' => array('administrator', 'editor', 'author'),
        'orderby' => 'display_name',
        'order' => 'ASC',
    ));
    
    $result = array();
    foreach ($users as $user) {
        $result[] = array(
            'id' => $user->ID,
            'name' => $user->display_name,
            'email' => $user->user_email,
            'role' => implode(', ', $user->roles),
        );
    }
    
    return new WP_REST_Response($result, 200);
}

// Upload image helper
function wpab_cloud_upload_image($image_url, $post_id) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    
    $tmp = download_url($image_url);
    
    if (is_wp_error($tmp)) {
        return $tmp;
    }
    
    $file_array = array(
        'name'     => basename($image_url),
        'tmp_name' => $tmp,
    );
    
    $attachment_id = media_handle_sideload($file_array, $post_id);
    
    @unlink($tmp);
    
    return $attachment_id;
}

// Add admin menu
add_action('admin_menu', 'wpab_cloud_admin_menu');
function wpab_cloud_admin_menu() {
    add_options_page(
        'WP Auto Blogger Cloud',
        'Auto Blogger Cloud',
        'manage_options',
        'wpab-cloud-settings',
        'wpab_cloud_settings_page'
    );
}

// Settings page
function wpab_cloud_settings_page() {
    // Handle key generation
    if (isset($_POST['generate_key']) && wp_verify_nonce($_POST['wpab_cloud_nonce'], 'wpab_cloud_settings')) {
        update_option('wpab_site_key', wp_generate_password(32, false));
        echo '<div class="notice notice-success"><p>New site key generated successfully!</p></div>';
    }
    
    $site_key = get_option('wpab_site_key');
    
    // Generate key if it doesn't exist
    if (empty($site_key)) {
        $site_key = wp_generate_password(32, false);
        update_option('wpab_site_key', $site_key);
    }
    ?>
    <div class="wrap">
        <h1>WP Auto Blogger Cloud Settings</h1>
        
        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
            <h2>Site Key</h2>
            
            <table class="form-table">
                <tr>
                    <th scope="row">Site Key</th>
                    <td>
                        <input type="text" class="regular-text" value="<?php echo esc_attr($site_key); ?>" readonly style="font-family: monospace; width: 400px;">
                        <button type="button" class="button" onclick="copyToClipboard('<?php echo esc_js($site_key); ?>', this)">Copy</button>
                        <p class="description">Copy this key and paste it in the WP Auto Blogger Portal when connecting your site.</p>
                    </td>
                </tr>
            </table>
            
            <form method="post" action="">
                <?php wp_nonce_field('wpab_cloud_settings', 'wpab_cloud_nonce'); ?>
                <p>
                    <input type="submit" name="generate_key" class="button button-secondary" 
                           value="Generate New Key" 
                           onclick="return confirm('Are you sure? The old key will stop working immediately.');">
                </p>
            </form>
        </div>
        
        <div style="background: #fff; padding: 20px; margin-top: 20px; border: 1px solid #ccd0d4;">
            <h2>API Endpoints</h2>
            
            <table class="widefat">
                <thead>
                    <tr>
                        <th>Endpoint</th>
                        <th>Method</th>
                        <th>URL</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Publish Content</td>
                        <td>POST</td>
                        <td><code><?php echo esc_url(rest_url('wpab-cloud/v1/publish')); ?></code></td>
                    </tr>
                    <tr>
                        <td>Health Check</td>
                        <td>GET</td>
                        <td><code><?php echo esc_url(rest_url('wpab-cloud/v1/health')); ?></code></td>
                    </tr>
                    <tr>
                        <td>Verify Site</td>
                        <td>POST</td>
                        <td><code><?php echo esc_url(rest_url('wpab-cloud/v1/verify')); ?></code></td>
                    </tr>
                    <tr>
                        <td>Get Categories</td>
                        <td>GET</td>
                        <td><code><?php echo esc_url(rest_url('wpab-cloud/v1/categories')); ?></code></td>
                    </tr>
                    <tr>
                        <td>Get Users</td>
                        <td>GET</td>
                        <td><code><?php echo esc_url(rest_url('wpab-cloud/v1/users')); ?></code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    
    <script>
    function copyToClipboard(text, button) {
        navigator.clipboard.writeText(text).then(function() {
            var originalText = button.textContent;
            button.textContent = 'Copied!';
            setTimeout(function() {
                button.textContent = originalText;
            }, 2000);
        });
    }
    </script>
    <?php
}