<?php
// Exit if accessed directly.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
delete_option( 'wpab_options' );

// Optionally, delete any custom tables or data here.
// For example:
// global $wpdb;
// $table_name = $wpdb->prefix . 'wpab_content_logs';
// $wpdb->query( "DROP TABLE IF EXISTS $table_name" );
