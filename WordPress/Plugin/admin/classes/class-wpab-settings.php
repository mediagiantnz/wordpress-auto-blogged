<?php
// File: wp-auto-blogger/admin/classes/class-wpab-settings.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Settings {

    public function __construct() {
        // Load settings fields helper class
        if (!class_exists('WPAB_Settings_Fields')) {
            require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-settings-fields.php';
        }
    }

    /**
     * Register settings, sections, and fields.
     */
    public function register_settings() {
        register_setting( 'wpab_options', 'wpab_options', array( $this, 'sanitize' ) );

        // Add settings sections
        $this->add_settings_sections();

        // Add settings fields
        $this->add_settings_fields();
    }

    /**
     * Sanitize input data.
     */
    public function sanitize( $input ) {
        $new_input = array();

        // Sanitize AI Provider Settings
        if ( isset( $input['ai_provider'] ) ) {
            $new_input['ai_provider'] = sanitize_text_field( $input['ai_provider'] );
        }
        
        // OpenAI Settings
        if ( isset( $input['openai_api_key'] ) ) {
            $new_input['openai_api_key'] = sanitize_text_field( $input['openai_api_key'] );
        }
        if ( isset( $input['openai_model'] ) ) {
            $new_input['openai_model'] = sanitize_text_field( $input['openai_model'] );
        }
        
        // Claude Settings
        if ( isset( $input['claude_api_key'] ) ) {
            $new_input['claude_api_key'] = sanitize_text_field( $input['claude_api_key'] );
        }
        if ( isset( $input['claude_model'] ) ) {
            $new_input['claude_model'] = sanitize_text_field( $input['claude_model'] );
        }
        
        // Gemini Settings
        if ( isset( $input['gemini_api_key'] ) ) {
            $new_input['gemini_api_key'] = sanitize_text_field( $input['gemini_api_key'] );
        }
        if ( isset( $input['gemini_model'] ) ) {
            $new_input['gemini_model'] = sanitize_text_field( $input['gemini_model'] );
        }
        
        // Email Provider Settings
        if ( isset( $input['email_provider'] ) ) {
            $new_input['email_provider'] = sanitize_text_field( $input['email_provider'] );
        }
        
        // Mailgun Settings (only save if Mailgun is selected)
        if ( isset( $input['mailgun_api_key'] ) ) {
            $new_input['mailgun_api_key'] = sanitize_text_field( $input['mailgun_api_key'] );
        }
        if ( isset( $input['mailgun_domain'] ) ) {
            $new_input['mailgun_domain'] = sanitize_text_field( $input['mailgun_domain'] );
        }

        // Sanitize Content Generation Settings
        if ( isset( $input['default_word_count'] ) ) {
            $new_input['default_word_count'] = absint( $input['default_word_count'] );
        }
        if ( isset( $input['default_tone_style'] ) ) {
            $new_input['default_tone_style'] = sanitize_text_field( $input['default_tone_style'] );
        }
        if ( isset( $input['default_context_prompt'] ) ) {
            $new_input['default_context_prompt'] = sanitize_textarea_field( $input['default_context_prompt'] );
        }


        // Sanitize Notification Preferences
        $new_input['enable_email_notifications'] = isset( $input['enable_email_notifications'] ) ? 1 : 0;
        if ( isset( $input['default_reminder_interval'] ) ) {
            $new_input['default_reminder_interval'] = absint( $input['default_reminder_interval'] );
        }
        $new_input['admin_receive_notifications'] = isset( $input['admin_receive_notifications'] ) ? 1 : 0;

        // Sanitize Email Settings
        if ( isset( $input['email_from_name'] ) ) {
            $new_input['email_from_name'] = sanitize_text_field( $input['email_from_name'] );
        }
        if ( isset( $input['email_from_address'] ) ) {
            $new_input['email_from_address'] = sanitize_email( $input['email_from_address'] );
        }
        if ( isset( $input['email_signature'] ) ) {
            $new_input['email_signature'] = sanitize_textarea_field( $input['email_signature'] );
        }

        // Sanitize General Plugin Settings
        $new_input['require_approval'] = isset( $input['require_approval'] ) ? 1 : 0;
        $new_input['send_approval_emails'] = isset( $input['send_approval_emails'] ) ? 1 : 0;
        $new_input['send_calendar_alerts'] = isset( $input['send_calendar_alerts'] ) ? 1 : 0;
        
        // Single notification email address for all alerts
        $new_input['notification_email_address'] = isset( $input['notification_email_address'] ) ? sanitize_email( $input['notification_email_address'] ) : '';
        
        $new_input['enable_logging']  = isset( $input['enable_logging'] ) ? 1 : 0;
        if ( isset( $input['log_retention_period'] ) ) {
            $new_input['log_retention_period'] = absint( $input['log_retention_period'] );
        }
        $new_input['data_cleanup_on_uninstall'] = isset( $input['data_cleanup_on_uninstall'] ) ? 1 : 0;

        // Sanitize Post Settings
        if ( isset( $input['default_post_author'] ) ) {
            $new_input['default_post_author'] = absint( $input['default_post_author'] );
        }
        if ( isset( $input['default_post_categories'] ) ) {
            $new_input['default_post_categories'] = array_map( 'absint', $input['default_post_categories'] );
        }
        if ( isset( $input['default_post_status'] ) ) {
            $new_input['default_post_status'] = sanitize_text_field( $input['default_post_status'] );
        }

        return $new_input;
    }

    /**
     * Add settings sections.
     */
    private function add_settings_sections() {
        // API Settings
        add_settings_section( 'wpab_api_settings', 'API Settings', array($this, 'api_settings_section_callback'), 'wpab' );

        // Content Generation Settings
        add_settings_section( 'wpab_content_generation_settings', 'Content Generation Settings', null, 'wpab' );

        // Notification Preferences
        add_settings_section( 'wpab_notification_settings', 'Notification Preferences', null, 'wpab' );

        // Email Settings
        add_settings_section( 'wpab_email_settings', 'Email Settings', null, 'wpab' );

        // General Plugin Settings
        add_settings_section( 'wpab_general_settings', 'General Plugin Settings', null, 'wpab' );

        // Post Settings
        add_settings_section( 'wpab_post_settings', 'Post Settings', null, 'wpab' );
    }

    /**
     * Add settings fields.
     */
    private function add_settings_fields() {
        // Get current provider
        $options = get_option( 'wpab_options', array() );
        $ai_provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        
        // API Settings Fields
        add_settings_field(
            'ai_provider',
            'AI Provider',
            array( 'WPAB_Settings_Fields', 'ai_provider_callback' ),
            'wpab',
            'wpab_api_settings'
        );
        
        // Only show fields for the selected provider
        switch ($ai_provider) {
            case 'openai':
                add_settings_field(
                    'openai_api_key',
                    'OpenAI API Key',
                    array( 'WPAB_Settings_Fields', 'openai_api_key_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                add_settings_field(
                    'openai_model',
                    'OpenAI Model',
                    array( 'WPAB_Settings_Fields', 'openai_model_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                break;
                
            case 'claude':
                add_settings_field(
                    'claude_api_key',
                    'Claude API Key',
                    array( 'WPAB_Settings_Fields', 'claude_api_key_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                add_settings_field(
                    'claude_model',
                    'Claude Model',
                    array( 'WPAB_Settings_Fields', 'claude_model_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                break;
                
            case 'gemini':
                add_settings_field(
                    'gemini_api_key',
                    'Gemini API Key',
                    array( 'WPAB_Settings_Fields', 'gemini_api_key_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                add_settings_field(
                    'gemini_model',
                    'Gemini Model',
                    array( 'WPAB_Settings_Fields', 'gemini_model_callback' ),
                    'wpab',
                    'wpab_api_settings'
                );
                break;
        }
        
        // Email provider selection
        add_settings_field(
            'email_provider',
            'Email Provider',
            array( 'WPAB_Settings_Fields', 'email_provider_callback' ),
            'wpab',
            'wpab_api_settings'
        );
        
        // Only show Mailgun fields if Mailgun is selected
        $email_provider = isset( $options['email_provider'] ) ? $options['email_provider'] : 'wordpress';
        if ( $email_provider === 'mailgun' ) {
            add_settings_field(
                'mailgun_api_key',
                'Mailgun API Key',
                array( 'WPAB_Settings_Fields', 'mailgun_api_key_callback' ),
                'wpab',
                'wpab_api_settings'
            );
            add_settings_field(
                'mailgun_domain',
                'Mailgun Domain',
                array( 'WPAB_Settings_Fields', 'mailgun_domain_callback' ),
                'wpab',
                'wpab_api_settings'
            );
        }

        // Content Generation Settings Fields
        add_settings_field(
            'default_word_count',
            'Default Word Count',
            array( $this, 'default_word_count_callback' ),
            'wpab',
            'wpab_content_generation_settings'
        );
        add_settings_field(
            'default_tone_style',
            'Default Tone and Style',
            array( $this, 'default_tone_style_callback' ),
            'wpab',
            'wpab_content_generation_settings'
        );
        add_settings_field(
            'default_context_prompt',
            'Default Context Prompt',
            array( $this, 'default_context_prompt_callback' ),
            'wpab',
            'wpab_content_generation_settings'
        );

        // Notification Preferences Fields
        add_settings_field(
            'enable_email_notifications',
            'Enable Email Notifications',
            array( $this, 'enable_email_notifications_callback' ),
            'wpab',
            'wpab_notification_settings'
        );
        add_settings_field(
            'default_reminder_interval',
            'Default Reminder Interval (days)',
            array( $this, 'default_reminder_interval_callback' ),
            'wpab',
            'wpab_notification_settings'
        );
        add_settings_field(
            'admin_receive_notifications',
            'Admin Receive Notifications',
            array( $this, 'admin_receive_notifications_callback' ),
            'wpab',
            'wpab_notification_settings'
        );

        // Email Settings Fields
        add_settings_field(
            'email_from_name',
            'From Name',
            array( $this, 'email_from_name_callback' ),
            'wpab',
            'wpab_email_settings'
        );
        add_settings_field(
            'email_from_address',
            'From Email Address',
            array( $this, 'email_from_address_callback' ),
            'wpab',
            'wpab_email_settings'
        );
        add_settings_field(
            'email_signature',
            'Email Signature',
            array( $this, 'email_signature_callback' ),
            'wpab',
            'wpab_email_settings'
        );

        // General Plugin Settings Fields
        add_settings_field(
            'require_approval',
            'Require Approval',
            array( 'WPAB_Settings_Fields', 'require_approval_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'send_approval_emails',
            'Send Approval Emails',
            array( 'WPAB_Settings_Fields', 'send_approval_emails_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'send_calendar_alerts',
            'Send Calendar Alerts',
            array( 'WPAB_Settings_Fields', 'send_calendar_alerts_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'notification_email_address',
            'Notification Email Address',
            array( 'WPAB_Settings_Fields', 'notification_email_address_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'enable_logging',
            'Enable Logging',
            array( $this, 'enable_logging_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'log_retention_period',
            'Log Retention Period (days)',
            array( $this, 'log_retention_period_callback' ),
            'wpab',
            'wpab_general_settings'
        );
        add_settings_field(
            'data_cleanup_on_uninstall',
            'Data Cleanup on Uninstall',
            array( $this, 'data_cleanup_on_uninstall_callback' ),
            'wpab',
            'wpab_general_settings'
        );

        // Post Settings Fields
        add_settings_field(
            'default_post_author',
            'Default Post Author',
            array( $this, 'default_post_author_callback' ),
            'wpab',
            'wpab_post_settings'
        );
        add_settings_field(
            'default_post_categories',
            'Default Post Categories',
            array( $this, 'default_post_categories_callback' ),
            'wpab',
            'wpab_post_settings'
        );
        add_settings_field(
            'default_post_status',
            'Default Post Status',
            array( 'WPAB_Settings_Fields', 'default_post_status_callback' ),
            'wpab',
            'wpab_post_settings'
        );
    }

    // ---------------------- Callbacks ----------------------

    // API Settings Callbacks
    public function openai_api_key_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['openai_api_key'] ) ? esc_attr( $options['openai_api_key'] ) : '';
        printf(
            '<input type="text" id="openai_api_key" name="wpab_options[openai_api_key]" value="%s" />',
            $value
        );
        echo '<p class="description">Enter your OpenAI API key.</p>';
    }

    public function mailgun_api_key_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['mailgun_api_key'] ) ? esc_attr( $options['mailgun_api_key'] ) : '';
        printf(
            '<input type="text" id="mailgun_api_key" name="wpab_options[mailgun_api_key]" value="%s" />',
            $value
        );
        echo '<p class="description">Enter your Mailgun API key.</p>';
    }

    public function mailgun_domain_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['mailgun_domain'] ) ? esc_attr( $options['mailgun_domain'] ) : '';
        printf(
            '<input type="text" id="mailgun_domain" name="wpab_options[mailgun_domain]" value="%s" />',
            $value
        );
        echo '<p class="description">Enter your Mailgun domain (e.g., mg.yourdomain.com).</p>';
    }

    // Content Generation Settings Callbacks
    public function default_word_count_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['default_word_count'] ) ? esc_attr( $options['default_word_count'] ) : '1000';
        printf(
            '<input type="number" id="default_word_count" name="wpab_options[default_word_count]" value="%s" min="100" max="5000" />',
            $value
        );
        echo '<p class="description">Set the default word count for generated content.</p>';
    }

    public function default_tone_style_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['default_tone_style'] ) ? esc_attr( $options['default_tone_style'] ) : '';
        printf(
            '<input type="text" id="default_tone_style" name="wpab_options[default_tone_style]" value="%s" />',
            $value
        );
        echo '<p class="description">Define the default tone and style (e.g., formal, conversational).</p>';
    }

    public function default_context_prompt_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['default_context_prompt'] ) ? esc_textarea( $options['default_context_prompt'] ) : '';
        printf(
            '<textarea id="default_context_prompt" name="wpab_options[default_context_prompt]" rows="5" cols="50">%s</textarea>',
            $value
        );
        echo '<p class="description">Set a default context prompt to guide the AI in content generation.</p>';
    }

    /**
     * openai_model_callback() - Dynamically lists available models from OpenAI, if an API key is present.
     */
    public function openai_model_callback() {
        $options = get_option( 'wpab_options', array() );
        $api_key = isset( $options['openai_api_key'] ) ? trim( $options['openai_api_key'] ) : '';
        $selected_model = isset( $options['openai_model'] ) ? esc_attr( $options['openai_model'] ) : '';

        // If no key is set, show a disabled dropdown with a message
        if ( empty( $api_key ) ) {
            echo '<select id="openai_model" name="wpab_options[openai_model]" disabled="disabled">';
            echo '<option value="">No API key: please enter one and save first.</option>';
            echo '</select>';
            echo '<p class="description" style="color: #cc0000;">Enter an API key and save to enable model selection.</p>';
            return;
        }

        // If we do have a key, attempt to get the available models
        $models = $this->get_openai_models( $api_key );
        if ( is_wp_error( $models ) ) {
            $err_msg = $models->get_error_message();
            echo '<select id="openai_model" name="wpab_options[openai_model]" disabled="disabled">';
            echo '<option value="">Error fetching models: ' . esc_html( $err_msg ) . '</option>';
            echo '</select>';
            echo '<p class="description" style="color: #cc0000;">Could not fetch models. Check your API key or logs.</p>';
            return;
        }

        if ( empty( $models ) ) {
            echo '<select id="openai_model" name="wpab_options[openai_model]" disabled="disabled">';
            echo '<option value="">No models found. Check your OpenAI account or logs.</option>';
            echo '</select>';
            return;
        }

        // Otherwise, let's build the dropdown from $models
        echo '<select id="openai_model" name="wpab_options[openai_model]">';
        foreach ( $models as $model_id ) {
            // Mark it selected if matches $selected_model
            printf(
                '<option value="%1$s" %2$s>%1$s</option>',
                esc_attr( $model_id ),
                selected( $selected_model, $model_id, false )
            );
        }
        echo '</select>';
        echo '<p class="description">Select the OpenAI model to use for content generation.</p>';
    }

    // Notification Preferences Callbacks
    public function enable_email_notifications_callback() {
        $options = get_option( 'wpab_options', array() );
        $checked = ! empty( $options['enable_email_notifications'] ) ? 'checked' : '';
        echo '<input type="checkbox" id="enable_email_notifications" name="wpab_options[enable_email_notifications]" ' . $checked . ' />';
        echo '<p class="description">Enable or disable email notifications globally.</p>';
    }

    public function default_reminder_interval_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['default_reminder_interval'] ) ? esc_attr( $options['default_reminder_interval'] ) : '3';
        printf(
            '<input type="number" id="default_reminder_interval" name="wpab_options[default_reminder_interval]" value="%s" min="1" max="30" />',
            $value
        );
        echo '<p class="description">Set the default interval for reminder emails (in days).</p>';
    }

    public function admin_receive_notifications_callback() {
        $options = get_option( 'wpab_options', array() );
        $checked = ! empty( $options['admin_receive_notifications'] ) ? 'checked' : '';
        echo '<input type="checkbox" id="admin_receive_notifications" name="wpab_options[admin_receive_notifications]" ' . $checked . ' />';
        echo '<p class="description">Enable to have admins receive notifications for approvals/rejections.</p>';
    }

    // Email Settings Callbacks
    public function email_from_name_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['email_from_name'] ) ? esc_attr( $options['email_from_name'] ) : get_bloginfo( 'name' );
        printf(
            '<input type="text" id="email_from_name" name="wpab_options[email_from_name]" value="%s" />',
            $value
        );
        echo '<p class="description">Set the "From" name for emails.</p>';
    }

    public function email_from_address_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['email_from_address'] ) ? esc_attr( $options['email_from_address'] ) : get_bloginfo( 'admin_email' );
        printf(
            '<input type="email" id="email_from_address" name="wpab_options[email_from_address]" value="%s" />',
            $value
        );
        echo '<p class="description">Set the "From" email address.</p>';
    }

    public function email_signature_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['email_signature'] ) ? esc_textarea( $options['email_signature'] ) : '';
        printf(
            '<textarea id="email_signature" name="wpab_options[email_signature]" rows="5" cols="50">%s</textarea>',
            $value
        );
        echo '<p class="description">Set a custom email signature.</p>';
    }

    // General Plugin Settings Callbacks
    public function enable_approval_callback() {
        $options = get_option( 'wpab_options', array() );
        $checked = ! empty( $options['enable_approval'] ) ? 'checked' : '';
        echo '<input type="checkbox" id="enable_approval" name="wpab_options[enable_approval]" ' . $checked . ' />';
        echo '<p class="description">Enable or disable an approval workflow for generated topics.</p>';
    }

    public function enable_logging_callback() {
        $options = get_option( 'wpab_options', array() );
        $checked = ! empty( $options['enable_logging'] ) ? 'checked' : '';
        echo '<input type="checkbox" id="enable_logging" name="wpab_options[enable_logging]" ' . $checked . ' />';
        echo '<p class="description">Enable detailed logging for troubleshooting.</p>';
    }

    public function log_retention_period_callback() {
        $options = get_option( 'wpab_options', array() );
        $value   = isset( $options['log_retention_period'] ) ? esc_attr( $options['log_retention_period'] ) : '30';
        printf(
            '<input type="number" id="log_retention_period" name="wpab_options[log_retention_period]" value="%s" min="1" max="365" />',
            $value
        );
        echo '<p class="description">Set how many days logs are kept before deletion.</p>';
    }

    public function data_cleanup_on_uninstall_callback() {
        $options = get_option( 'wpab_options', array() );
        $checked = ! empty( $options['data_cleanup_on_uninstall'] ) ? 'checked' : '';
        echo '<input type="checkbox" id="data_cleanup_on_uninstall" name="wpab_options[data_cleanup_on_uninstall]" ' . $checked . ' />';
        echo '<p class="description">If enabled, plugin data is removed on uninstall.</p>';
    }

    // Post Settings Callbacks
    public function default_post_author_callback() {
        $options = get_option( 'wpab_options', array() );
        $selected_author = isset( $options['default_post_author'] ) ? absint( $options['default_post_author'] ) : get_current_user_id();
        // Use 'capability' instead of deprecated 'who' parameter
        $users = get_users( array( 
            'capability' => array('edit_posts'),
            'orderby' => 'display_name',
            'order' => 'ASC'
        ) );

        echo '<select id="default_post_author" name="wpab_options[default_post_author]">';
        foreach ( $users as $user ) {
            echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $selected_author, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Select the default author for new posts.</p>';
    }

    public function default_post_categories_callback() {
        $options = get_option( 'wpab_options', array() );
        $selected_categories = isset( $options['default_post_categories'] )
            ? (array) $options['default_post_categories']
            : array();
        $categories = get_categories( array( 'hide_empty' => false ) );

        echo '<select id="default_post_categories" name="wpab_options[default_post_categories][]" multiple="multiple" style="height: 100px;">';
        foreach ( $categories as $category ) {
            echo '<option value="' . esc_attr( $category->term_id ) . '"'
                . ( in_array( $category->term_id, $selected_categories ) ? ' selected' : '' )
                . '>' . esc_html( $category->name ) . '</option>';
        }
        echo '</select>';
        echo '<p class="description">Select one or more default categories for generated posts.</p>';
    }

    // ------------------------------------------------------------
    //         DYNAMIC MODEL FETCH: get_openai_models($api_key)
    // ------------------------------------------------------------
    /**
     * Attempt to fetch the list of model IDs from the OpenAI /v1/models endpoint,
     * storing results in a transient for e.g. 12 hours to avoid excessive calls.
     *
     * @param string $api_key The user’s OpenAI API key.
     * @return array|WP_Error An array of model IDs on success, or WP_Error on failure.
     */
    private function get_openai_models( $api_key ) {
        $transient_key = 'wpab_openai_models_' . md5( $api_key );

        // Attempt to read from transient
        $cached = get_transient( $transient_key );
        if ( $cached !== false ) {
            return $cached;
        }

        // Make the request
        $response = wp_remote_get( 'https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 20,
        ) );

        if ( is_wp_error( $response ) ) {
            return $response; // WP_Error
        }

        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( ! is_array( $data ) || ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
            return new WP_Error( 'api_error', 'Unexpected response from OpenAI /v1/models.' );
        }

        $model_ids = array();
        foreach ( $data['data'] as $model_obj ) {
            if ( isset( $model_obj['id'] ) ) {
                $model_ids[] = $model_obj['id'];
            }
        }

        // Sort them
        sort( $model_ids );

        // Cache for 12 hours
        set_transient( $transient_key, $model_ids, 12 * HOUR_IN_SECONDS );

        return $model_ids;
    }
    
    /**
     * API Settings section callback
     */
    public function api_settings_section_callback() {
        $options = get_option( 'wpab_options', array() );
        $ai_provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        $has_api_key = false;
        $current_model = '';
        
        switch ($ai_provider) {
            case 'openai':
                $has_api_key = !empty($options['openai_api_key']);
                $current_model = isset($options['openai_model']) ? $options['openai_model'] : 'gpt-3.5-turbo';
                $provider_name = 'OpenAI';
                break;
            case 'claude':
                $has_api_key = !empty($options['claude_api_key']);
                $current_model = isset($options['claude_model']) ? $options['claude_model'] : 'claude-3-5-sonnet-20241022';
                $provider_name = 'Claude (Anthropic)';
                break;
            case 'gemini':
                $has_api_key = !empty($options['gemini_api_key']);
                $current_model = isset($options['gemini_model']) ? $options['gemini_model'] : 'gemini-pro';
                $provider_name = 'Gemini (Google)';
                break;
        }
        
        echo '<div style="background: #f0f8ff; border: 1px solid #2271b1; border-radius: 4px; padding: 15px; margin-bottom: 20px;">';
        echo '<p style="margin: 0; font-size: 16px;"><strong>Active AI Provider:</strong> ' . esc_html($provider_name) . '</p>';
        
        if ($has_api_key) {
            echo '<p style="margin: 5px 0 0 0;"><strong>Current Model:</strong> ' . esc_html($current_model) . '</p>';
            echo '<p style="margin: 10px 0 0 0;"><em>All content will be generated using this provider and model.</em></p>';
        } else {
            echo '<p style="margin: 10px 0 0 0; color: #d63638;"><strong>⚠️ API Key Required:</strong> Please configure your ' . esc_html($provider_name) . ' API key below.</p>';
        }
        
        echo '<p style="margin: 10px 0 0 0;"><a href="' . admin_url('admin.php?page=wpab&wizard=1') . '" class="button button-secondary">Change Provider</a></p>';
        echo '</div>';
    }
}
