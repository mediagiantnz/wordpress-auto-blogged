<?php
// Add these lines to your wp-config.php file:

define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

// Then check the debug log at:
// wp-content/debug.log

// To see which plugins are active and their paths:
$active_plugins = get_option('active_plugins');
echo "<pre>Active Plugins:\n";
print_r($active_plugins);
echo "</pre>";

// To check for duplicate menu registrations:
global $menu;
echo "<pre>Admin Menu Structure:\n";
print_r($menu);
echo "</pre>";