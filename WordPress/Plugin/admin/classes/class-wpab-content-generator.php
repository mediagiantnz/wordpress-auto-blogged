<?php
// File: wp-auto-blogger/admin/classes/class-wpab-content-generator.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Content_Generator {

    /**
     * Generate the final HTML for a topic using the selected AI provider.
     *
     * @param array $topic An array like ['title'=>'...', 'description'=>'...', ...].
     * @return string|false The HTML content on success, or false on failure.
     */
    private function generate_content_for_topic( $topic ) {
        // Load AI provider class if not already loaded
        if (!class_exists('WPAB_AI_Provider_Factory')) {
            require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-ai-provider.php';
        }
        
        // 1) Get plugin settings
        $options        = get_option( 'wpab_options', array() );
        $provider       = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        $tone_style     = isset( $options['default_tone_style'] ) ? $options['default_tone_style'] : '';
        $context_prompt = isset( $options['default_context_prompt'] ) ? $options['default_context_prompt'] : '';
        $word_count     = isset( $options['default_word_count'] ) ? intval( $options['default_word_count'] ) : 1000;
        
        // Get provider-specific settings
        $api_key = '';
        $model = '';
        
        switch ($provider) {
            case 'openai':
                $api_key = isset( $options['openai_api_key'] ) ? $options['openai_api_key'] : '';
                $model   = isset( $options['openai_model'] ) ? $options['openai_model'] : 'gpt-3.5-turbo';
                break;
            case 'claude':
                $api_key = isset( $options['claude_api_key'] ) ? $options['claude_api_key'] : '';
                $model   = isset( $options['claude_model'] ) ? $options['claude_model'] : 'claude-3-5-sonnet-20241022';
                break;
            case 'gemini':
                $api_key = isset( $options['gemini_api_key'] ) ? $options['gemini_api_key'] : '';
                $model   = isset( $options['gemini_model'] ) ? $options['gemini_model'] : 'gemini-pro';
                break;
        }

        if ( empty( $api_key ) ) {
            error_log( 'WPAB: API key is missing for provider: ' . $provider );
            return false;
        }

        // 2) Build a custom prompt
        $prompt = "Write a detailed blog post on the following topic in HTML format (not markdown):\n\n";
        $prompt .= "Title: " . $topic['title'] . "\n";
        $prompt .= "Description: " . $topic['description'] . "\n";
        if ( ! empty( $context_prompt ) ) {
            $prompt .= "\nContext: $context_prompt\n";
        }
        if ( ! empty( $tone_style ) ) {
            $prompt .= "Tone and Style: $tone_style\n";
        }
        $prompt .= "Word Count: Approximately $word_count words.\n";
        $prompt .= "\nPlease ensure the content is SEO-friendly and follows best practices. Return the content in HTML format with proper heading tags (h2, h3), paragraphs, and lists where appropriate.";

        // 3) Create AI provider and generate content
        $ai_provider = WPAB_AI_Provider_Factory::create($provider, $api_key, $model, array(
            'temperature' => 0.7,
            'max_tokens'  => 4096,
        ));
        
        if (!$ai_provider) {
            error_log('WPAB: Failed to create AI provider: ' . $provider);
            return false;
        }
        
        $result = $ai_provider->generate_content($prompt);
        
        if (!$result || !isset($result['content'])) {
            error_log('WPAB: Failed to generate content with provider: ' . $provider);
            return false;
        }

        $html_content = trim( $result['content'] );

        // Clean up any HTML code fences if present
        if (strpos($html_content, '```html') === 0 && substr($html_content, -3) === '```') {
            $html_content = substr($html_content, 7, -3);
            $html_content = trim($html_content);
        }

        return $html_content; // Already in HTML format from AI provider
    }

    /**
     * Convert a string to plain text by stripping HTML tags.
     * Used for final post titles that might contain HTML.
     */
    private function sanitize_markdown( $text ) {
        return trim( strip_tags($text) );
    }

    /**
     * generate_post($topic):
     * - Calls generate_content_for_topic() to get final HTML
     * - Sanitizes the topic title
     * - Inserts the post
     */
    public function generate_post( $topic ) {
        // 1) Get the final HTML from generate_content_for_topic()
        $html_content = $this->generate_content_for_topic( $topic );
        if ( ! $html_content ) {
            return new WP_Error( 'no_content_generated', 'Error generating content for this topic.' );
        }

        // 2) Also sanitize the title in case it has leftover markdown
        $safe_title = $this->sanitize_markdown( $topic['title'] );

        // 3) Insert post with your plugin settings
        $options    = get_option( 'wpab_options', array() );
        $author_id  = ! empty( $options['default_post_author'] )
            ? absint( $options['default_post_author'] )
            : get_current_user_id();
        $categories = ! empty( $options['default_post_categories'] )
            ? array_map( 'absint', $options['default_post_categories'] )
            : array();

        // Check if we should save as draft for approval
        $require_approval = isset( $options['require_approval'] ) && $options['require_approval'] == '1';
        
        // Use default post status from settings, or 'publish' if not set
        $default_status = isset( $options['default_post_status'] ) ? $options['default_post_status'] : 'publish';
        
        // If approval is required AND approval emails are enabled, override to draft
        $send_approval_emails = isset( $options['send_approval_emails'] ) && $options['send_approval_emails'] == '1';
        $post_status = ( $require_approval && $send_approval_emails ) ? 'draft' : $default_status;
        
        $post_id = wp_insert_post( array(
            'post_title'    => $safe_title,
            'post_content'  => $html_content, // Already HTML
            'post_status'   => $post_status,
            'post_type'     => 'post',
            'post_author'   => $author_id,
            'post_category' => $categories,
        ), true );

        if ( is_wp_error( $post_id ) ) {
            return $post_id; // pass along the error
        }

        // 4) Optionally set SEO meta
        if ( class_exists( 'WPAB_SEO_Handler' ) ) {
            $seo_handler = new WPAB_SEO_Handler();
            $seo_handler->set_yoast_seo_meta( $post_id, $topic, $html_content, $categories );
        }
        
        // 5) Trigger email notification if approval required
        if ( $require_approval ) {
            do_action( 'wpab_post_generated', $post_id, $topic );
        }

        return $post_id;
    }
}
