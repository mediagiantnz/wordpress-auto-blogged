<?php
/**
 * Manual Activation Script for WP Auto Blogger
 * 
 * Upload this file to your WordPress root directory and access it via browser
 * This bypasses the normal activation process to avoid conflicts with other plugins
 */

// Load WordPress
require_once('wp-load.php');

// Check if user is admin
if (!current_user_can('manage_options')) {
    die('You must be logged in as an administrator to run this script.');
}

// Start output buffering to catch any errors
ob_start();

echo "<h1>WP Auto Blogger Manual Activation</h1>";

// Set default options
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
echo "<p>✓ Default options set</p>";

// Initialize empty topics array if it doesn't exist
if (false === get_option('wpab_topics')) {
    update_option('wpab_topics', array());
    echo "<p>✓ Topics array initialized</p>";
}

// Initialize service pages array if it doesn't exist
if (false === get_option('wpab_service_pages')) {
    update_option('wpab_service_pages', array());
    echo "<p>✓ Service pages array initialized</p>";
}

// Activate the plugin programmatically
$plugin_file = 'wp-auto-blogger/wp-auto-blogger.php';
$current = get_option('active_plugins', array());
if (!in_array($plugin_file, $current)) {
    $current[] = $plugin_file;
    update_option('active_plugins', $current);
    echo "<p>✓ Plugin activated in database</p>";
} else {
    echo "<p>ℹ️ Plugin was already active</p>";
}

// Clear any caches
wp_cache_flush();
echo "<p>✓ Cache cleared</p>";

echo "<h2>Activation Complete!</h2>";
echo "<p><a href='" . admin_url('admin.php?page=wpab') . "'>Go to WP Auto Blogger Settings</a></p>";
echo "<p style='color:red;'><strong>Important:</strong> Delete this file after use!</p>";

// Clean up output
ob_end_flush();
?>