<?php
// File: wp-auto-blogger/admin/partials/wpab-admin-display.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

?>
<div class="wrap">
    <h1>WP Auto Blogger Settings</h1>
    <?php
    // Check current AI provider
    $options = get_option( 'wpab_options', array() );
    $ai_provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
    ?>
    <div class="notice notice-info">
        <p>AI Provider: <strong><?php echo ucfirst($ai_provider); ?></strong> | 
           <a href="<?php echo admin_url('admin.php?page=wpab&wizard=1'); ?>">Change Provider</a></p>
    </div>
    <form method="post" action="options.php">
        <?php
        settings_fields( 'wpab_options' );
        do_settings_sections( 'wpab' );
        submit_button();
        ?>
    </form>
</div>
