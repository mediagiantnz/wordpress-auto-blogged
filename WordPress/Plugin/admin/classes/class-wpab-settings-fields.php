<?php
// File: wp-auto-blogger/admin/classes/class-wpab-settings-fields.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Settings field callbacks
 */
class WPAB_Settings_Fields {
    
    /**
     * AI Provider Selection
     */
    public static function ai_provider_callback() {
        $options = get_option( 'wpab_options' );
        $provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        
        if (!class_exists('WPAB_AI_Provider_Factory')) {
            require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-ai-provider.php';
        }
        
        $providers = WPAB_AI_Provider_Factory::get_providers();
        ?>
        <select name="wpab_options[ai_provider]" id="wpab_ai_provider" class="regular-text">
            <?php foreach ($providers as $key => $label) : ?>
                <option value="<?php echo esc_attr($key); ?>" <?php selected($provider, $key); ?>>
                    <?php echo esc_html($label); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <p class="description">Select which AI service to use for content generation. <strong>Note: Changing this will reload the page to show the correct settings.</strong></p>
        
        <script>
        jQuery(document).ready(function($) {
            var originalProvider = $('#wpab_ai_provider').val();
            
            $('#wpab_ai_provider').on('change', function() {
                if ($(this).val() !== originalProvider) {
                    if (confirm('Changing the AI provider will reload the page. Any unsaved changes will be lost. Continue?')) {
                        // Save the provider selection first
                        var data = {
                            action: 'wpab_save_provider',
                            provider: $(this).val(),
                            nonce: '<?php echo wp_create_nonce('wpab_save_provider'); ?>'
                        };
                        
                        $.post(ajaxurl, data, function(response) {
                            window.location.reload();
                        });
                    } else {
                        $(this).val(originalProvider);
                    }
                }
            });
        });
        </script>
        
        <?php
    }
    
    /**
     * OpenAI API Key
     */
    public static function openai_api_key_callback() {
        $options = get_option( 'wpab_options' );
        $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
        $masked_key = self::mask_api_key( $api_key );
        ?>
        <input type="password" id="openai_api_key" name="wpab_options[openai_api_key]" 
               value="<?php echo esc_attr( $api_key ); ?>" class="regular-text ai-api-key" 
               placeholder="<?php echo $api_key ? esc_attr( $masked_key ) : 'Enter your OpenAI API key'; ?>" />
        <button type="button" class="button toggle-api-key" data-target="openai_api_key">Show</button>
        <p class="description">Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Dashboard</a></p>
        <?php
    }
    
    /**
     * Claude API Key
     */
    public static function claude_api_key_callback() {
        $options = get_option( 'wpab_options' );
        $api_key = isset( $options['claude_api_key'] ) ? $options['claude_api_key'] : '';
        $masked_key = self::mask_api_key( $api_key );
        ?>
        <input type="password" id="claude_api_key" name="wpab_options[claude_api_key]" 
               value="<?php echo esc_attr( $api_key ); ?>" class="regular-text ai-api-key" 
               placeholder="<?php echo $api_key ? esc_attr( $masked_key ) : 'Enter your Claude API key'; ?>" />
        <button type="button" class="button toggle-api-key" data-target="claude_api_key">Show</button>
        <p class="description">Get your API key from <a href="https://console.anthropic.com/settings/keys" target="_blank">Anthropic Console</a></p>
        <?php
    }
    
    /**
     * Gemini API Key
     */
    public static function gemini_api_key_callback() {
        $options = get_option( 'wpab_options' );
        $api_key = isset( $options['gemini_api_key'] ) ? $options['gemini_api_key'] : '';
        $masked_key = self::mask_api_key( $api_key );
        ?>
        <input type="password" id="gemini_api_key" name="wpab_options[gemini_api_key]" 
               value="<?php echo esc_attr( $api_key ); ?>" class="regular-text ai-api-key" 
               placeholder="<?php echo $api_key ? esc_attr( $masked_key ) : 'Enter your Gemini API key'; ?>" />
        <button type="button" class="button toggle-api-key" data-target="gemini_api_key">Show</button>
        <p class="description">Get your API key from <a href="https://makersuite.google.com/app/apikey" target="_blank">Google AI Studio</a></p>
        <?php
    }
    
    /**
     * OpenAI Model Selection
     */
    public static function openai_model_callback() {
        $options = get_option( 'wpab_options' );
        $selected_model = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-3.5-turbo';
        $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
        
        ?>
        <select name="wpab_options[openai_model]" id="openai_model" class="regular-text">
            <option value="gpt-3.5-turbo" <?php selected($selected_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo</option>
            <option value="gpt-4" <?php selected($selected_model, 'gpt-4'); ?>>GPT-4</option>
            <option value="gpt-4-turbo" <?php selected($selected_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
            <option value="gpt-4o" <?php selected($selected_model, 'gpt-4o'); ?>>GPT-4o</option>
            <option value="gpt-4o-mini" <?php selected($selected_model, 'gpt-4o-mini'); ?>>GPT-4o Mini</option>
        </select>
        <p class="description">Select the OpenAI model to use for content generation</p>
        <?php
    }
    
    /**
     * Claude Model Selection
     */
    public static function claude_model_callback() {
        $options = get_option( 'wpab_options' );
        $selected_model = isset( $options['claude_model'] ) ? $options['claude_model'] : 'claude-3-5-sonnet-20241022';
        
        ?>
        <select name="wpab_options[claude_model]" id="claude_model" class="regular-text">
            <option value="claude-opus-4-20250514" <?php selected($selected_model, 'claude-opus-4-20250514'); ?>>Claude Opus 4</option>
            <option value="claude-sonnet-4-20250514" <?php selected($selected_model, 'claude-sonnet-4-20250514'); ?>>Claude Sonnet 4</option>
            <option value="claude-3-5-sonnet-20241022" <?php selected($selected_model, 'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet</option>
            <option value="claude-3-5-haiku-20241022" <?php selected($selected_model, 'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku</option>
            <option value="claude-3-opus-20240229" <?php selected($selected_model, 'claude-3-opus-20240229'); ?>>Claude 3 Opus</option>
            <option value="claude-3-sonnet-20240229" <?php selected($selected_model, 'claude-3-sonnet-20240229'); ?>>Claude 3 Sonnet</option>
            <option value="claude-3-haiku-20240307" <?php selected($selected_model, 'claude-3-haiku-20240307'); ?>>Claude 3 Haiku</option>
        </select>
        <p class="description">Select the Claude model to use for content generation</p>
        <?php
    }
    
    /**
     * Gemini Model Selection
     */
    public static function gemini_model_callback() {
        $options = get_option( 'wpab_options' );
        $selected_model = isset( $options['gemini_model'] ) ? $options['gemini_model'] : 'gemini-pro';
        
        ?>
        <select name="wpab_options[gemini_model]" id="gemini_model" class="regular-text">
            <option value="gemini-pro" <?php selected($selected_model, 'gemini-pro'); ?>>Gemini Pro</option>
            <option value="gemini-pro-vision" <?php selected($selected_model, 'gemini-pro-vision'); ?>>Gemini Pro Vision</option>
            <option value="gemini-1.5-pro-latest" <?php selected($selected_model, 'gemini-1.5-pro-latest'); ?>>Gemini 1.5 Pro</option>
            <option value="gemini-1.5-flash-latest" <?php selected($selected_model, 'gemini-1.5-flash-latest'); ?>>Gemini 1.5 Flash</option>
        </select>
        <p class="description">Select the Gemini model to use for content generation</p>
        <?php
    }
    
    /**
     * Approval Settings
     */
    public static function require_approval_callback() {
        $options = get_option( 'wpab_options' );
        $checked = isset( $options['require_approval'] ) && $options['require_approval'] == 1;
        ?>
        <label>
            <input type="checkbox" name="wpab_options[require_approval]" value="1" <?php checked( $checked, true ); ?> />
            Save generated posts as drafts requiring approval before publishing
        </label>
        <?php
    }
    
    public static function send_approval_emails_callback() {
        $options = get_option( 'wpab_options' );
        $checked = isset( $options['send_approval_emails'] ) && $options['send_approval_emails'] == 1;
        ?>
        <label>
            <input type="checkbox" name="wpab_options[send_approval_emails]" value="1" <?php checked( $checked, true ); ?> />
            Send email notifications when posts are ready for approval
        </label>
        <?php
    }
    
    public static function approval_email_address_callback() {
        $options = get_option( 'wpab_options' );
        $email = isset( $options['approval_email_address'] ) ? $options['approval_email_address'] : get_option('admin_email');
        ?>
        <input type="email" name="wpab_options[approval_email_address]" value="<?php echo esc_attr( $email ); ?>" class="regular-text" />
        <p class="description">Email address to receive approval notifications</p>
        <?php
    }
    
    /**
     * Calendar Alert Settings
     */
    public static function send_calendar_alerts_callback() {
        $options = get_option( 'wpab_options' );
        $checked = isset( $options['send_calendar_alerts'] ) && $options['send_calendar_alerts'] == 1;
        ?>
        <label>
            <input type="checkbox" name="wpab_options[send_calendar_alerts]" value="1" <?php checked( $checked, true ); ?> />
            Send alerts when content calendar is running low (7, 3, and 0 topics)
        </label>
        <?php
    }
    
    public static function alert_email_address_callback() {
        $options = get_option( 'wpab_options' );
        $email = isset( $options['alert_email_address'] ) ? $options['alert_email_address'] : get_option('admin_email');
        ?>
        <input type="email" name="wpab_options[alert_email_address]" value="<?php echo esc_attr( $email ); ?>" class="regular-text" />
        <p class="description">Email address to receive calendar alerts</p>
        <?php
    }
    
    /**
     * Consolidated notification email address
     */
    public static function notification_email_address_callback() {
        $options = get_option( 'wpab_options' );
        $email = isset( $options['notification_email_address'] ) ? $options['notification_email_address'] : get_option('admin_email');
        ?>
        <input type="email" name="wpab_options[notification_email_address]" value="<?php echo esc_attr( $email ); ?>" class="regular-text" />
        <p class="description">Email address to receive all notifications (approvals and calendar alerts)</p>
        <?php
    }
    
    /**
     * Email Provider Selection
     */
    public static function email_provider_callback() {
        $options = get_option( 'wpab_options' );
        $provider = isset( $options['email_provider'] ) ? $options['email_provider'] : 'wordpress';
        ?>
        <select name="wpab_options[email_provider]" id="wpab_email_provider" class="regular-text">
            <option value="wordpress" <?php selected($provider, 'wordpress'); ?>>WordPress Default (wp_mail)</option>
            <option value="mailgun" <?php selected($provider, 'mailgun'); ?>>Mailgun</option>
        </select>
        <p class="description">Choose how to send email notifications</p>
        
        <script>
        jQuery(document).ready(function($) {
            function toggleMailgunFields() {
                var provider = $('#wpab_email_provider').val();
                var mailgunFields = $('tr').filter(function() {
                    return $(this).find('[id^="mailgun_"]').length > 0;
                });
                
                if (provider === 'mailgun') {
                    mailgunFields.show();
                } else {
                    mailgunFields.hide();
                }
            }
            
            toggleMailgunFields();
            $('#wpab_email_provider').on('change', toggleMailgunFields);
        });
        </script>
        <?php
    }
    
    /**
     * Mailgun API Key
     */
    public static function mailgun_api_key_callback() {
        $options = get_option( 'wpab_options' );
        $api_key = isset( $options['mailgun_api_key'] ) ? $options['mailgun_api_key'] : '';
        $masked_key = self::mask_api_key( $api_key );
        ?>
        <input type="password" id="mailgun_api_key" name="wpab_options[mailgun_api_key]" 
               value="<?php echo esc_attr( $api_key ); ?>" class="regular-text ai-api-key" 
               placeholder="<?php echo $api_key ? esc_attr( $masked_key ) : 'Enter your Mailgun API key'; ?>" />
        <button type="button" class="button toggle-api-key" data-target="mailgun_api_key">Show</button>
        <p class="description">Get your API key from <a href="https://app.mailgun.com/app/sending/domains" target="_blank">Mailgun Dashboard</a></p>
        <?php
    }
    
    /**
     * Mailgun Domain
     */
    public static function mailgun_domain_callback() {
        $options = get_option( 'wpab_options' );
        $domain = isset( $options['mailgun_domain'] ) ? $options['mailgun_domain'] : '';
        ?>
        <input type="text" id="mailgun_domain" name="wpab_options[mailgun_domain]" 
               value="<?php echo esc_attr( $domain ); ?>" class="regular-text" 
               placeholder="mg.yourdomain.com" />
        <p class="description">Your Mailgun sending domain</p>
        <?php
    }
    
    /**
     * Default Post Status
     */
    public static function default_post_status_callback() {
        $options = get_option( 'wpab_options' );
        $status = isset( $options['default_post_status'] ) ? $options['default_post_status'] : 'publish';
        ?>
        <select name="wpab_options[default_post_status]" id="default_post_status" class="regular-text">
            <option value="publish" <?php selected($status, 'publish'); ?>>Published</option>
            <option value="draft" <?php selected($status, 'draft'); ?>>Draft</option>
            <option value="pending" <?php selected($status, 'pending'); ?>>Pending Review</option>
            <option value="private" <?php selected($status, 'private'); ?>>Private</option>
        </select>
        <p class="description">Default status for generated posts (overridden by approval settings)</p>
        <?php
    }
    
    /**
     * Helper function to mask API keys
     */
    private static function mask_api_key( $key ) {
        if ( empty( $key ) ) {
            return '';
        }
        
        $length = strlen( $key );
        if ( $length <= 8 ) {
            return str_repeat( '*', $length );
        }
        
        return substr( $key, 0, 8 ) . str_repeat( '*', $length - 8 );
    }
    
    /**
     * Add JavaScript for toggling API key visibility
     */
    public static function add_api_key_toggle_script() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.toggle-api-key').on('click', function() {
                var targetId = $(this).data('target');
                var input = $('#' + targetId);
                var button = $(this);
                
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    button.text('Hide');
                } else {
                    input.attr('type', 'password');
                    button.text('Show');
                }
            });
        });
        </script>
        <?php
    }
}