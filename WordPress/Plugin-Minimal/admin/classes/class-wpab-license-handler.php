<?php
// File: wp-auto-blogger/admin/classes/class-wpab-license-handler.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_License_Handler {
    private $license_key;
    private $license_status;
    private $activate_api_url;
    private $validate_api_url;

    /**
     * Constructor
     */
    public function __construct() {
        // Log entry to the constructor
        error_log('[WPAB_License_Handler::__construct] Entering constructor');

        // Retrieve any existing license key/status from WP options
        $this->license_key    = get_option( 'wpab_license_key', '' );
        $this->license_status = get_option( 'wpab_license_status', 'inactive' );

        /**
         * Set the URLs for AWS API Gateway endpoints
         * These will be replaced with actual URLs after terraform deployment
         */
        $this->activate_api_url = get_option('wpab_license_activation_url', 'https://YOUR-API-GATEWAY-URL/prod/license/activate');
        $this->validate_api_url = get_option('wpab_license_validation_url', 'https://YOUR-API-GATEWAY-URL/prod/license/validate');

        // Log initial state
        error_log('[WPAB_License_Handler::__construct] License key from DB: ' . ( $this->license_key ?: 'EMPTY' ));
        error_log('[WPAB_License_Handler::__construct] License status from DB: ' . $this->license_status);
        error_log('[WPAB_License_Handler::__construct] Activate URL: ' . $this->activate_api_url);
        error_log('[WPAB_License_Handler::__construct] Validate URL: ' . $this->validate_api_url);

        /**
         * Add a single notice for license status:
         *   - If $license_status is 'inactive', we show an inactive message.
         *   - If 'active', we show success.
         */
        add_action( 'admin_notices', array( $this, 'display_license_status_notice' ) );

        /**
         * Add the license settings section via the WP Settings API
         */
        add_action( 'admin_init', array( $this, 'add_license_settings' ) );

        /**
         * Hook for manual activation form submission
         */
        add_action( 'admin_post_wpab_activate_license', array( $this, 'handle_activate_license' ) );
    }

    /**
     * Checks if the license is active by calling the ValidateLicense function on Azure
     */
    public function is_license_active() {
        error_log('[WPAB_License_Handler::is_license_active] Checking license status...');

        // If we have no license key saved, we assume not active
        if ( empty( $this->license_key ) ) {
            error_log('[WPAB_License_Handler::is_license_active] No license key found in database. Marking inactive.');
            $this->license_status = 'inactive';
            update_option( 'wpab_license_status', 'inactive' );
            return false;
        }

        error_log('[WPAB_License_Handler::is_license_active] License key present (' . $this->license_key . '). Validating via: ' . $this->validate_api_url);

        // Make the POST request to validate license via AWS
        $response = wp_remote_post( $this->validate_api_url, array(
            'method'  => 'POST',
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => json_encode( array(
                'licenseKey' => $this->license_key,
                'siteUrl'    => get_site_url()
            ) ),
            'timeout' => 20,
        ) );

        // Check if WP had an error performing the request
        if ( is_wp_error( $response ) ) {
            $error_msg = $response->get_error_message();
            error_log('[WPAB_License_Handler::is_license_active] WP_Error encountered during validation: ' . $error_msg);
            $this->license_status = 'error';
            update_option( 'wpab_license_status', 'error' );
            return false;
        }

        // Retrieve and log the raw response body
        $body = wp_remote_retrieve_body( $response );
        error_log('[WPAB_License_Handler::is_license_active] Raw ValidateLicense response body: ' . print_r($body, true));

        // Attempt to parse JSON
        $data = json_decode( $body, true );
        error_log('[WPAB_License_Handler::is_license_active] JSON-decoded response: ' . print_r($data, true));

        // Check if we have a valid license from the server
        if ( isset( $data['valid'] ) && $data['valid'] === true ) {
            error_log('[WPAB_License_Handler::is_license_active] Server reported license is valid. Setting status to active.');
            $this->license_status = 'active';
            update_option( 'wpab_license_status', 'active' );
            return true;
        } else {
            // Not valid. Mark as inactive.
            error_log('[WPAB_License_Handler::is_license_active] License not valid. Marking inactive.');
            $this->license_status = 'inactive';
            update_option( 'wpab_license_status', 'inactive' );
            return false;
        }
    }

    /**
     * Show an admin notice if the license is active or inactive
     */
    public function display_license_status_notice() {
        // Only show if user can manage options
        if ( ! current_user_can( 'manage_options' ) ) {
            error_log('[WPAB_License_Handler::display_license_status_notice] Current user lacks manage_options capability. No notice shown.');
            return;
        }

        // Log the current license status for debugging
        error_log('[WPAB_License_Handler::display_license_status_notice] Current license_status is ' . $this->license_status);

        // If active, show success. Otherwise show error
        if ( $this->license_status === 'active' ) {
            echo '<div class="notice notice-success is-dismissible"><p>✅ WP Auto Blogger license is active.</p></div>';
            error_log('[WPAB_License_Handler::display_license_status_notice] Displayed ACTIVE notice.');
        } else {
            echo '<div class="notice notice-error is-dismissible"><p>❌ WP Auto Blogger license is inactive. Please activate your license in the plugin settings.</p></div>';
            error_log('[WPAB_License_Handler::display_license_status_notice] Displayed INACTIVE notice.');
        }
    }

    /**
     * Registers the license section and fields via the WP Settings API
     */
    public function add_license_settings() {
        error_log('[WPAB_License_Handler::add_license_settings] Adding license settings section and fields.');

        add_settings_section(
            'wpab_license_section',
            'License Settings',
            array( $this, 'license_section_callback' ),
            'wpab-settings'
        );

        add_settings_field(
            'wpab_license_key',
            'License Key',
            array( $this, 'license_key_callback' ),
            'wpab-settings',
            'wpab_license_section'
        );

        register_setting(
            'wpab-settings',
            'wpab_license_key',
            array(
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );
    }

    /**
     * The callback for the license section description
     */
    public function license_section_callback() {
        error_log('[WPAB_License_Handler::license_section_callback] Rendering license section description.');
        echo '<p>Enter your license key below to activate WP Auto Blogger.</p>';
    }

    /**
     * Renders the license key input field in the WP Settings form
     * (We do NOT create a form here, just the field. The form is in the main plugin settings screen.)
     */
    public function license_key_callback() {
        $license_key = get_option( 'wpab_license_key', '' );
        error_log('[WPAB_License_Handler::license_key_callback] Current wpab_license_key from DB is: ' . ($license_key ?: 'EMPTY'));
        printf(
            '<input type="text" id="wpab_license_key" name="wpab_license_key" value="%s" size="50" />',
            esc_attr( $license_key )
        );
        echo '<p class="description">Enter your license key here. Then click the "Activate License" button below.</p>';
    }

    /**
     * Handles the manual "Activate License" form submission from WP Admin
     */
    public function handle_activate_license() {
        error_log('[WPAB_License_Handler::handle_activate_license] Entering handle_activate_license().');

        // Security checks
        check_admin_referer( 'wpab_activate_license', 'wpab_license_nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        // Grab license key from the form submission
        $license_key = isset( $_POST['wpab_license_key'] ) ? sanitize_text_field( $_POST['wpab_license_key'] ) : '';
        error_log('[WPAB_License_Handler::handle_activate_license] License key from POST is: ' . ($license_key ?: 'EMPTY'));

        // If empty, send back an error
        if ( empty( $license_key ) ) {
            error_log('[WPAB_License_Handler::handle_activate_license] No license key. Redirecting with "license_empty".');
            wp_redirect( admin_url( 'admin.php?page=wpab&message=license_empty' ) );
            exit;
        }

        // Save the license key in WP so we can validate later
        update_option( 'wpab_license_key', $license_key );
        error_log('[WPAB_License_Handler::handle_activate_license] Saved new license key "' . $license_key . '" to wp_options. Attempting activation now...');

        // Make the remote call to the AWS activation endpoint
        error_log('[WPAB_License_Handler::handle_activate_license] Calling: ' . $this->activate_api_url);
        $response = wp_remote_post( $this->activate_api_url, array(
            'method'  => 'POST',
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'body'    => json_encode( array(
                'licenseKey' => $license_key,
                'siteUrl'    => get_site_url(),
                'email'      => wp_get_current_user()->user_email,
            ) ),
            'timeout' => 20,
        ) );

        // Check for WP errors
        if ( is_wp_error( $response ) ) {
            $err = $response->get_error_message();
            error_log('[WPAB_License_Handler::handle_activate_license] WP_Error encountered: ' . $err);
            wp_redirect( admin_url( 'admin.php?page=wpab&message=license_activation_error' ) );
            exit;
        }

        // Retrieve the response body
        $body = wp_remote_retrieve_body( $response );
        error_log('[WPAB_License_Handler::handle_activate_license] Raw activate-license response: ' . print_r($body, true));
        
        // Parse JSON response
        $data = json_decode( $body, true );

        // Check if the response indicates success
        if ( isset( $data['success'] ) && $data['success'] === true ) {
            error_log('[WPAB_License_Handler::handle_activate_license] License activated successfully. Marking license as active.');
            $this->license_status = 'active';
            update_option( 'wpab_license_status', 'active' );
            wp_redirect( admin_url( 'admin.php?page=wpab&message=license_activated' ) );
        } else {
            // Otherwise, treat it as a failure
            $error_message = isset($data['message']) ? $data['message'] : 'Unknown error';
            error_log('[WPAB_License_Handler::handle_activate_license] Activation failed: ' . $error_message);
            $this->license_status = 'inactive';
            update_option( 'wpab_license_status', 'inactive' );
            wp_redirect( admin_url( 'admin.php?page=wpab&message=license_activation_failed' ) );
        }

        exit;
    }

    /**
     * This is called by your daily WP cron job or other hooks if scheduled
     */
    public function check_license_status() {
        error_log('[WPAB_License_Handler::check_license_status] Cron or scheduled check triggered.');
        $this->is_license_active();
    }
}
