<?php
/**
 * Plugin Name: WPAB Error Suppressor
 * Description: Temporarily suppresses errors from other plugins during WP Auto Blogger operations
 * Version: 1.0
 * 
 * INSTRUCTIONS:
 * 1. Upload this file to: /wp-content/mu-plugins/
 * 2. If the mu-plugins directory doesn't exist, create it
 * 3. This will load automatically before all other plugins
 */

// Only run in admin area
if (!is_admin()) {
    return;
}

// Start output buffering very early to catch all errors
add_action('muplugins_loaded', function() {
    if (
        // During plugin activation
        (isset($_GET['action']) && $_GET['action'] === 'activate') ||
        // On our plugin pages
        (isset($_GET['page']) && strpos($_GET['page'], 'wpab') === 0) ||
        // During our admin-post actions
        (isset($_POST['action']) && strpos($_POST['action'], 'wpab_') === 0) ||
        // On plugins page
        (isset($GLOBALS['pagenow']) && $GLOBALS['pagenow'] === 'plugins.php')
    ) {
        ob_start();
        
        // Set custom error handler to suppress non-fatal errors
        set_error_handler(function($errno, $errstr, $errfile, $errline) {
            // Only suppress notices and warnings from fast-indexing-api
            if (($errno === E_NOTICE || $errno === E_WARNING) && strpos($errstr, 'fast-indexing-api') !== false) {
                return true; // Suppress the error
            }
            // Let other errors through
            return false;
        });
    }
}, 1);

// Clean output buffer before headers are sent
add_action('send_headers', function() {
    if (ob_get_level() > 0) {
        ob_clean();
    }
}, 1);