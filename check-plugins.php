<?php
// Upload this file to your WordPress root directory
// Access it via: yoursite.com/check-plugins.php
// Delete it after use!

require_once('wp-load.php');

if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h2>Active Plugins Check</h2>";
echo "<pre>";

// Get active plugins
$active_plugins = get_option('active_plugins');
echo "Active Plugins:\n";
foreach ($active_plugins as $plugin) {
    echo "  - $plugin\n";
}

echo "\n\nWP Auto Blogger Related:\n";
foreach ($active_plugins as $plugin) {
    if (stripos($plugin, 'auto-blogger') !== false) {
        echo "  Found: $plugin\n";
        $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
        echo "    Version: " . $plugin_data['Version'] . "\n";
        echo "    Path: " . WP_PLUGIN_DIR . '/' . $plugin . "\n";
    }
}

// Check for duplicate menu registrations
echo "\n\nMenu Registration Hooks:\n";
global $wp_filter;
if (isset($wp_filter['admin_menu'])) {
    foreach ($wp_filter['admin_menu']->callbacks as $priority => $callbacks) {
        foreach ($callbacks as $callback) {
            if (is_array($callback['function']) && 
                isset($callback['function'][0]) && 
                is_object($callback['function'][0]) &&
                stripos(get_class($callback['function'][0]), 'WPAB') !== false) {
                echo "  Priority $priority: " . get_class($callback['function'][0]) . "::" . $callback['function'][1] . "\n";
            }
        }
    }
}

echo "</pre>";

// Security reminder
echo "<p style='color:red;'><strong>Remember to delete this file after use!</strong></p>";