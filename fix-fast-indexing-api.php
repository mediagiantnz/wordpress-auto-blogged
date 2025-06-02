<?php
/**
 * Fix for Fast Indexing API Plugin
 * 
 * This script fixes the issue with the fast-indexing-api plugin loading translations too early
 * 
 * Upload this to your WordPress root and run it once
 */

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('You must be logged in as an administrator.');
}

echo "<h1>Fast Indexing API Plugin Fix</h1>";

// Check if the plugin is active
$plugin_file = 'fast-indexing-api/fast-indexing-api.php';
if (!is_plugin_active($plugin_file)) {
    echo "<p>Fast Indexing API plugin is not active. No fix needed.</p>";
    exit;
}

// Try to fix the load order
$active_plugins = get_option('active_plugins', array());
$key = array_search($plugin_file, $active_plugins);

if ($key !== false) {
    // Remove it from current position
    unset($active_plugins[$key]);
    // Add it to the end (load last)
    $active_plugins[] = $plugin_file;
    // Re-index array
    $active_plugins = array_values($active_plugins);
    // Update option
    update_option('active_plugins', $active_plugins);
    
    echo "<p>✓ Moved Fast Indexing API to load last in the plugin order.</p>";
    echo "<p>This may help reduce conflicts with other plugins.</p>";
} else {
    echo "<p>Could not find Fast Indexing API in active plugins list.</p>";
}

// Also try to suppress its early loading
$upload_dir = wp_upload_dir();
$mu_plugin_dir = ABSPATH . 'wp-content/mu-plugins/';

if (!file_exists($mu_plugin_dir)) {
    wp_mkdir_p($mu_plugin_dir);
}

$suppress_code = '<?php
// Suppress Fast Indexing API early loading issues
add_action("plugins_loaded", function() {
    remove_action("init", "_load_textdomain_just_in_time", 0);
    add_action("init", "_load_textdomain_just_in_time", 10);
}, 1);';

file_put_contents($mu_plugin_dir . 'fix-fast-indexing-api.php', $suppress_code);
echo "<p>✓ Created must-use plugin to suppress early loading issues.</p>";

echo "<h2>Fix Applied!</h2>";
echo '<p><a href="' . admin_url('plugins.php') . '">Go back to Plugins</a></p>';
echo "<p style='color:red;'>Remember to delete this file!</p>";