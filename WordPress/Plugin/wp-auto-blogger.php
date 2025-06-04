<?php
/**
 * Plugin Name: WP Auto Blogger
 * Description: Automatically generates blog content using AI with multi-provider support (OpenAI, Claude, Gemini).
 * Version: 2.0.12
 * Author: WP Auto Blogger Team
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: Proprietary
 * Text Domain: wp-auto-blogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Suppress errors from other plugins during our operations
if (is_admin() && (
    // During plugin activation
    (isset($_GET['action']) && $_GET['action'] === 'activate' && isset($_GET['plugin']) && strpos($_GET['plugin'], 'wp-auto-blogger') !== false) ||
    // On our admin pages
    (isset($_GET['page']) && strpos($_GET['page'], 'wpab') === 0)
)) {
    // Start buffering if not already started
    if (!ob_get_level()) {
        ob_start();
    }
    
    // Suppress notices and warnings from other plugins
    $original_error_reporting = error_reporting();
    error_reporting($original_error_reporting & ~E_NOTICE & ~E_WARNING);
}

// Define constants
define( 'WPAB_VERSION', '2.0.10' );
define( 'WPAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Initialize default options on admin_init instead of activation
add_action('admin_init', 'wpab_ensure_defaults');
function wpab_ensure_defaults() {
    // Only run once
    if (get_option('wpab_defaults_set')) {
        return;
    }
    
    // Set default options if they don't exist
    $default_options = array(
        'ai_provider' => 'openai',
        'openai_model' => 'gpt-3.5-turbo',
        'claude_model' => 'claude-3-5-sonnet-20241022',
        'gemini_model' => 'gemini-pro',
        'schedule_frequency' => 'daily',
        'default_author' => get_current_user_id(),
        'default_post_status' => 'publish',
    );
    
    $existing_options = get_option('wpab_options', array());
    $options = wp_parse_args($existing_options, $default_options);
    update_option('wpab_options', $options);
    
    // Initialize empty topics array if it doesn't exist
    if (false === get_option('wpab_topics')) {
        update_option('wpab_topics', array());
    }
    
    // Initialize service pages array if it doesn't exist
    if (false === get_option('wpab_service_pages')) {
        update_option('wpab_service_pages', array());
    }
    
    // Mark that we've set defaults
    update_option('wpab_defaults_set', true);
}

// Deactivation cleanup without hooks to avoid conflicts
add_action('admin_init', 'wpab_check_deactivation');
function wpab_check_deactivation() {
    // Check if our plugin is active
    if (!is_plugin_active(plugin_basename(__FILE__))) {
        // Clear any scheduled events
        wp_clear_scheduled_hook('wpab_generate_content_event');
        wp_clear_scheduled_hook('wpab_publish_scheduled_post');
    }
}

// Include necessary admin classes
require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-admin.php';

// Initialize the admin class if in wp-admin
if ( is_admin() ) {
    new WPAB_Admin();
}

// Initialize approval handler
require_once WPAB_PLUGIN_DIR . 'public/class-wpab-approval-handler.php';
new WPAB_Approval_Handler();

// Initialize public functionality for admin notices
require_once WPAB_PLUGIN_DIR . 'public/class-wpab-public.php';
$wpab_public = new WPAB_Public();
add_action( 'admin_notices', array( $wpab_public, 'add_admin_notices' ) );


/**
 * Note:
 * - We've removed all license code.
 * - If you originally had a schedule or content generator that also runs on the front end,
 *   you can include those classes here. For example:
 *     require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-content-generator.php';
 *     require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-schedule-handler.php';
 * - But if you only use them in the admin, you can let `class-wpab-admin.php` handle it.
 */