<?php
/**
 * Plugin Name: WP Auto Blogger
 * Description: Automatically generates blog content using AI with multi-provider support (OpenAI, Claude, Gemini).
 * Version: 2.0.5
 * Author: WP Auto Blogger Team
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * License: Proprietary
 * Text Domain: wp-auto-blogger
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define constants
define( 'WPAB_VERSION', '2.0.5' );
define( 'WPAB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load admin functionality only when in admin area
add_action('plugins_loaded', function() {
    if (is_admin()) {
        // Delay loading until WordPress is more fully initialized
        add_action('init', function() {
            require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-admin.php';
            new WPAB_Admin();
        }, 20); // Load later with priority 20
    }
});

// Ensure defaults are set
add_action('admin_init', function() {
    if (get_option('wpab_defaults_set')) {
        return;
    }
    
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
    
    if (false === get_option('wpab_topics')) {
        update_option('wpab_topics', array());
    }
    
    if (false === get_option('wpab_service_pages')) {
        update_option('wpab_service_pages', array());
    }
    
    update_option('wpab_defaults_set', true);
}, 30); // Run even later