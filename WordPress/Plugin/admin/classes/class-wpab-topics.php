<?php
// File: wp-auto-blogger/admin/classes/class-wpab-topics.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Topics {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_post_wpab_add_service_pages', array( $this, 'handle_add_service_pages' ) );
        add_action( 'admin_post_wpab_generate_topics',    array( $this, 'handle_generate_topics' ) );
        add_action( 'admin_post_wpab_update_topics',      array( $this, 'handle_update_topics' ) );
        add_action( 'admin_post_wpab_blog_now',           array( $this, 'handle_blog_now' ) );
    }

    /**
     * Handle adding service pages (optional).
     */
    public function handle_add_service_pages() {
        ob_start();

        // Security checks
        if ( ! isset( $_POST['wpab_nonce'] ) || ! wp_verify_nonce( $_POST['wpab_nonce'], 'wpab_add_service_pages' ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $service_pages = isset( $_POST['service_pages'] ) ? sanitize_textarea_field( $_POST['service_pages'] ) : '';
        if ( ! empty( $service_pages ) ) {
            $urls       = array_filter( array_map( 'trim', explode( "\n", $service_pages ) ) );
            $valid_urls = array();
            foreach ( $urls as $url ) {
                if ( filter_var( $url, FILTER_VALIDATE_URL ) ) {
                    $valid_urls[] = esc_url_raw( $url );
                }
            }
            update_option( 'wpab_service_pages', $valid_urls );
        }

        ob_end_clean();
        $this->safe_redirect( admin_url( 'admin.php?page=wpab-content-calendar&message=service_pages_updated' ) );
    }

    /**
     * Handle generating topics via OpenAI (older approach referencing service pages).
     */
    public function handle_generate_topics() {
        ob_start();

        // Security checks
        if ( ! isset( $_POST['wpab_nonce'] ) || ! wp_verify_nonce( $_POST['wpab_nonce'], 'wpab_generate_topics' ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        // Number of topics
        $desired_topic_count = isset( $_POST['number_of_topics'] ) ? absint( $_POST['number_of_topics'] ) : 10;

        // Auto-approve setting
        $auto_approve = isset( $_POST['auto_approve_topics'] ) && $_POST['auto_approve_topics'] == '1';
        update_option( 'wpab_auto_approve_topics', $auto_approve );

        // Retrieve service pages
        $service_pages = get_option( 'wpab_service_pages', array() );
        if ( empty( $service_pages ) ) {
            wp_die( 'Please add service pages before generating topics.' );
        }

        // Load AI provider class if not already loaded
        if (!class_exists('WPAB_AI_Provider_Factory')) {
            require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-ai-provider.php';
        }
        
        // AI config
        $options       = get_option( 'wpab_options', array() );
        $provider      = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        $tone_style    = isset( $options['default_tone_style'] ) ? $options['default_tone_style'] : '';
        $context_prompt= isset( $options['default_context_prompt'] ) ? $options['default_context_prompt'] : '';
        
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
            wp_die( 'API key is missing for ' . ucfirst($provider) . '. Please configure it in the settings.' );
        }

        // Build a combined "service_content" for context
        $service_content = '';
        foreach ( $service_pages as $url ) {
            $response = wp_remote_get( $url );
            if ( is_wp_error( $response ) ) {
                continue;
            }
            $body = wp_remote_retrieve_body( $response );
            $service_content .= wp_strip_all_tags( $body ) . "\n";
        }

        // The prompt - more specific for AI models
        $prompt  = "Generate exactly $desired_topic_count engaging blog post ideas based on the following content. ";
        $prompt .= "Format your response as a numbered list with each item following this exact format:\n\n";
        $prompt .= "1. Title Here\nDescription of the blog post idea in about 50 words.\n\n";
        $prompt .= "2. Another Title\nDescription of this blog post idea in about 50 words.\n\n";
        $prompt .= "Important: Do not use any markdown formatting, HTML tags, or special characters. Just plain text with the number, period, title on first line, and description on the next line.\n\n";
        $prompt .= "Content to base ideas on:\n" . $service_content . "\n";
        if (!empty($context_prompt)) {
            $prompt .= "\nAdditional Context: $context_prompt\n";
        }
        if (!empty($tone_style)) {
            $prompt .= "Tone and Style: $tone_style\n";
        }

        // Create AI provider and generate topics
        $ai_provider = WPAB_AI_Provider_Factory::create($provider, $api_key, $model, array(
            'temperature' => 0.7,
            'max_tokens'  => 1000,
        ));
        
        if (!$ai_provider) {
            wp_die('Failed to create AI provider: ' . $provider);
        }
        
        $result = $ai_provider->generate_content($prompt);
        
        if (!$result || !isset($result['content'])) {
            wp_die('Error in generating topics with ' . ucfirst($provider));
        }

        $topics = array();
        $topics_text = $result['content'];
        
        // Log the raw response for debugging
        error_log('WPAB Topics: Raw AI response: ' . $topics_text);

        // Parse topics with multiple strategies
        // First, try to match numbered format with title and description
        if (preg_match_all('/(\d+)\.\s*([^\n]+)\n([^\n]+(?:\n(?!\d+\.)[^\n]+)*)/m', $topics_text, $matches, PREG_SET_ORDER)) {
            error_log('WPAB Topics: Using regex parser, found ' . count($matches) . ' matches');
            
            foreach ($matches as $match) {
                $title = trim($match[2]);
                $description = trim($match[3]);
                $title = $this->sanitize_title($title);
                
                error_log('WPAB Topics: Parsed - Title: ' . $title . ', Description: ' . substr($description, 0, 50) . '...');
                
                $status = $auto_approve ? 'approved' : 'pending';
                if (!empty($title) && !empty($description)) {
                    $topics[] = array(
                        'title'       => $title,
                        'description' => $description,
                        'status'      => $status,
                    );
                }
            }
        }
        
        // Fallback: Split by numbered items and parse each
        if (empty($topics)) {
            error_log('WPAB Topics: Using fallback parser');
            
            // Split by numbers at start of line
            $topic_entries = preg_split('/^\d+\.\s*/m', $topics_text, -1, PREG_SPLIT_NO_EMPTY);
            
            foreach ($topic_entries as $index => $entry) {
                $entry = trim($entry);
                if (empty($entry)) {
                    continue;
                }
                
                // Split into lines
                $lines = preg_split('/\r\n|\r|\n/', $entry);
                $title = trim(array_shift($lines));
                $title = $this->sanitize_title($title);
                $description = trim(implode(' ', $lines));
                
                error_log('WPAB Topics: Fallback Entry ' . $index . ' - Title: ' . $title . ', Description: ' . substr($description, 0, 50) . '...');
                
                $status = $auto_approve ? 'approved' : 'pending';
                if (!empty($title) && !empty($description)) {
                    $topics[] = array(
                        'title'       => $title,
                        'description' => $description,
                        'status'      => $status,
                    );
                }
            }
        }
        
        error_log('WPAB Topics: Total topics parsed: ' . count($topics));

        // Merge with existing
        $existing_topics = get_option( 'wpab_topics', array() );
        $updated_topics  = array_merge( $existing_topics, $topics );
        update_option( 'wpab_topics', $updated_topics );

        ob_end_clean();
        $this->safe_redirect( admin_url( 'admin.php?page=wpab-content-calendar&message=topics_generated' ) );
    }

    /**
     * Sanitize the title by removing any markdown/HTML syntax.
     */
    private function sanitize_title( $title ) {
        // Remove common markdown formatting
        $title = preg_replace('/\*\*(.*?)\*\*/', '$1', $title); // Bold
        $title = preg_replace('/\*(.*?)\*/', '$1', $title); // Italic
        $title = preg_replace('/\_(.*?)\_/', '$1', $title); // Italic alt
        $title = preg_replace('/\#+ ?/', '', $title); // Headers
        $title = preg_replace('/\[([^\]]+)\]\([^\)]+\)/', '$1', $title); // Links
        $title = preg_replace('/\`([^\`]+)\`/', '$1', $title); // Code
        
        // Strip any remaining HTML
        $title = strip_tags($title);
        
        // Clean up whitespace
        $title = trim($title);
        
        return $title;
    }

    /**
     * Approve/reject topics via bulk action.
     */
    public function handle_update_topics() {
        ob_start();

        if ( ! isset( $_POST['wpab_nonce'] ) || ! wp_verify_nonce( $_POST['wpab_nonce'], 'wpab_update_topics' ) ) {
            wp_die( 'Security check failed.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Insufficient permissions.' );
        }

        $selected_topics = isset( $_POST['selected_topics'] ) ? array_map( 'intval', $_POST['selected_topics'] ) : array();
        $bulk_action     = isset( $_POST['bulk_action'] ) ? sanitize_text_field( $_POST['bulk_action'] ) : '';

        if ( empty( $selected_topics ) || empty( $bulk_action ) ) {
            $this->safe_redirect( admin_url( 'admin.php?page=wpab-content-calendar&message=no_action_selected' ) );
        }

        $topics = get_option( 'wpab_topics', array() );
        foreach ( $selected_topics as $index ) {
            if ( isset( $topics[ $index ] ) ) {
                if ( 'approve' === $bulk_action ) {
                    $topics[ $index ]['status'] = 'approved';
                } elseif ( 'reject' === $bulk_action ) {
                    unset( $topics[ $index ] );
                } elseif ( 'pending' === $bulk_action ) {
                    $topics[ $index ]['status'] = 'pending';
                }
            }
        }

        $topics = array_values( $topics );
        update_option( 'wpab_topics', $topics );

        ob_end_clean();
        $this->safe_redirect( admin_url( 'admin.php?page=wpab-content-calendar&message=topics_updated' ) );
    }

    /**
     * "Blog Now" action: generate post => remove from calendar
     */
    public function handle_blog_now() {
        ob_start();

        // Check topic index
        $topic_index = isset( $_GET['topic_index'] ) ? absint( $_GET['topic_index'] ) : -1;
        if ( $topic_index < 0 ) {
            wp_die( 'Invalid topic index.' );
        }

        // Check nonce
        $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( $_GET['_wpnonce'] ) : '';
        if ( ! wp_verify_nonce( $nonce, 'wpab_blog_now_' . $topic_index ) ) {
            wp_die( 'Security check failed.' );
        }

        // Retrieve the topic
        $topics = get_option( 'wpab_topics', array() );
        if ( ! isset( $topics[ $topic_index ] ) ) {
            wp_die( 'Topic not found.' );
        }

        $topic = $topics[ $topic_index ];
        if ( 'approved' !== $topic['status'] ) {
            wp_die( 'Topic is not approved for blogging.' );
        }

        // If necessary, remove leftover markdown from the topic's fields
        $topic['title']       = $this->sanitize_title( $topic['title'] );
        $topic['description'] = $this->sanitize_title( $topic['description'] );

        // Generate the blog post
        require_once plugin_dir_path( __FILE__ ) . 'class-wpab-content-generator.php';
        $content_generator = new WPAB_Content_Generator();
        $post_id = $content_generator->generate_post( $topic );

        if ( is_wp_error( $post_id ) ) {
            wp_die( 'Error generating post: ' . $post_id->get_error_message() );
        }

        // **Remove** this topic from the array so it no longer appears
        unset( $topics[ $topic_index ] );
        $topics = array_values( $topics );
        update_option( 'wpab_topics', $topics );

        ob_end_clean();
        // Redirect to the new post
        $this->safe_redirect( get_permalink( $post_id ) );
    }

    /**
     * Display the topics table with bulk actions.
     */
    public function display_topics_table() {
        $topics = get_option( 'wpab_topics', array() );
        if ( empty( $topics ) ) {
            echo '<p>No topics generated yet.</p>';
            return;
        }
        ?>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="wpab_update_topics">
            <?php wp_nonce_field( 'wpab_update_topics', 'wpab_nonce' ); ?>

            <div class="alignleft actions bulkactions" style="margin-bottom: 10px;">
                <select name="bulk_action">
                    <option value="">Bulk Actions</option>
                    <option value="approve">Approve</option>
                    <option value="reject">Reject</option>
                    <option value="pending">Set to Pending</option>
                </select>
                <?php submit_button( 'Apply', 'secondary', 'submit_bulk', false ); ?>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                <tr>
                    <th class="check-column"><input type="checkbox" id="cb-select-all"></th>
                    <th>Title</th>
                    <th>Description</th>
                    <th>Status</th>
                    <th>Blog Now</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $topics as $index => $topic ) :
                    $status      = isset( $topic['status'] ) ? $topic['status'] : 'pending';
                    $title       = isset( $topic['title'] ) ? $topic['title'] : '';
                    $description = isset( $topic['description'] ) ? $topic['description'] : '';
                    ?>
                    <tr>
                        <th class="check-column">
                            <input type="checkbox" name="selected_topics[]" value="<?php echo esc_attr( $index ); ?>">
                        </th>
                        <td><?php echo esc_html( $title ); ?></td>
                        <td><?php echo esc_html( $description ); ?></td>
                        <td><?php echo esc_html( ucfirst( $status ) ); ?></td>
                        <td>
                            <?php if ( 'approved' === $status ) :
                                // Add a nonce to the "Blog Now" link
                                $blog_now_url = wp_nonce_url(
                                    admin_url( 'admin-post.php?action=wpab_blog_now&topic_index=' . $index ),
                                    'wpab_blog_now_' . $index
                                ); ?>
                                <a href="<?php echo esc_url( $blog_now_url ); ?>" class="button">Blog Now</a>
                            <?php else : ?>
                                (Approve first)
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <?php submit_button( 'Update Topics', 'secondary', 'submit_bulk2', false ); ?>
        </form>

        <script type="text/javascript">
            jQuery(document).ready(function($){
                $('#cb-select-all').click(function(){
                    $('input[name="selected_topics[]"]').prop('checked', this.checked);
                });
            });
        </script>
        <?php
    }
    
    /**
     * Safe redirect that handles cases where headers are already sent
     */
    private function safe_redirect($url) {
        // Clear any output buffers
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Use JavaScript redirect as fallback if headers already sent
        if (headers_sent()) {
            echo '<script>window.location.href = "' . esc_url($url) . '";</script>';
            echo '<meta http-equiv="refresh" content="0;url=' . esc_url($url) . '">';
            echo '<p>Redirecting... <a href="' . esc_url($url) . '">Click here if not redirected</a></p>';
        } else {
            wp_redirect($url);
        }
        exit;
    }
}
