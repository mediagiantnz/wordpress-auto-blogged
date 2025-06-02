<?php
// File: wp-auto-blogger/admin/classes/class-wpab-ai-provider.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Abstract base class for AI providers
 */
abstract class WPAB_AI_Provider {
    protected $api_key;
    protected $model;
    protected $options;

    public function __construct($api_key, $model, $options = array()) {
        $this->api_key = $api_key;
        $this->model = $model;
        $this->options = $options;
    }

    /**
     * Generate content based on prompt
     * @param string $prompt The prompt to send to the AI
     * @return array|false Array with 'content' key on success, false on failure
     */
    abstract public function generate_content($prompt);

    /**
     * Get available models for this provider
     * @return array Array of model names
     */
    abstract public function get_available_models();

    /**
     * Validate API key
     * @return bool True if valid, false otherwise
     */
    abstract public function validate_api_key();
}

/**
 * OpenAI Provider
 */
class WPAB_OpenAI_Provider extends WPAB_AI_Provider {
    
    public function generate_content($prompt) {
        if (empty($this->api_key)) {
            error_log('WPAB: OpenAI API key is missing.');
            return false;
        }

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode(array(
                'model'       => $this->model,
                'messages'    => array(
                    array(
                        'role'    => 'system',
                        'content' => 'You are a professional content writer specializing in SEO-friendly blog posts. Always return content in HTML format, not markdown.',
                    ),
                    array(
                        'role'    => 'user',
                        'content' => $prompt,
                    ),
                ),
                'temperature' => isset($this->options['temperature']) ? $this->options['temperature'] : 0.7,
                'max_tokens'  => isset($this->options['max_tokens']) ? $this->options['max_tokens'] : 4096,
            )),
            'timeout' => 120,
        );

        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        
        if (is_wp_error($response)) {
            error_log('WPAB: Error generating content: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            error_log('WPAB: OpenAI API Error: ' . $error_msg);
            return false;
        }

        if (!isset($data['choices'][0]['message']['content'])) {
            error_log('WPAB: Unexpected OpenAI response. Body: ' . $body);
            return false;
        }

        return array(
            'content' => trim($data['choices'][0]['message']['content'])
        );
    }

    public function get_available_models() {
        $cached_models = get_transient('wpab_openai_models');
        if ($cached_models !== false) {
            return $cached_models;
        }

        if (empty($this->api_key)) {
            return array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
        }

        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!isset($data['data']) || !is_array($data['data'])) {
            return array('gpt-3.5-turbo', 'gpt-4', 'gpt-4-turbo');
        }

        $models = array();
        foreach ($data['data'] as $model) {
            if (isset($model['id']) && strpos($model['id'], 'gpt') === 0) {
                $models[] = $model['id'];
            }
        }

        set_transient('wpab_openai_models', $models, HOUR_IN_SECONDS);
        return $models;
    }

    public function validate_api_key() {
        $response = wp_remote_get('https://api.openai.com/v1/models', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
            ),
            'timeout' => 10,
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    }
}

/**
 * Claude (Anthropic) Provider
 */
class WPAB_Claude_Provider extends WPAB_AI_Provider {
    
    public function generate_content($prompt) {
        if (empty($this->api_key)) {
            error_log('WPAB: Claude API key is missing.');
            return false;
        }

        $args = array(
            'headers' => array(
                'x-api-key'     => $this->api_key,
                'Content-Type'  => 'application/json',
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode(array(
                'model'       => $this->model,
                'messages'    => array(
                    array(
                        'role'    => 'user',
                        'content' => "You are a professional content writer specializing in SEO-friendly blog posts. Always return content in HTML format, not markdown.\n\n" . $prompt,
                    ),
                ),
                'max_tokens'  => isset($this->options['max_tokens']) ? $this->options['max_tokens'] : 4096,
                'temperature' => isset($this->options['temperature']) ? $this->options['temperature'] : 0.7,
            )),
            'timeout' => 120,
        );

        $response = wp_remote_post('https://api.anthropic.com/v1/messages', $args);
        
        if (is_wp_error($response)) {
            error_log('WPAB: Error generating content with Claude: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            error_log('WPAB: Claude API Error: ' . $error_msg);
            return false;
        }

        if (!isset($data['content'][0]['text'])) {
            error_log('WPAB: Unexpected Claude response. Body: ' . $body);
            return false;
        }

        return array(
            'content' => trim($data['content'][0]['text'])
        );
    }

    public function get_available_models() {
        return array(
            'claude-opus-4-20250514',
            'claude-sonnet-4-20250514',
            'claude-3-5-sonnet-20241022',
            'claude-3-5-haiku-20241022',
            'claude-3-opus-20240229',
            'claude-3-sonnet-20240229',
            'claude-3-haiku-20240307'
        );
    }

    public function validate_api_key() {
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
            'headers' => array(
                'x-api-key'     => $this->api_key,
                'Content-Type'  => 'application/json',
                'anthropic-version' => '2023-06-01',
            ),
            'body' => json_encode(array(
                'model'    => 'claude-3-haiku-20240307',
                'messages' => array(array('role' => 'user', 'content' => 'Hi')),
                'max_tokens' => 10,
            )),
            'timeout' => 10,
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 401;
    }
}

/**
 * Google Gemini Provider
 */
class WPAB_Gemini_Provider extends WPAB_AI_Provider {
    
    public function generate_content($prompt) {
        if (empty($this->api_key)) {
            error_log('WPAB: Gemini API key is missing.');
            return false;
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $this->api_key;

        $args = array(
            'headers' => array(
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array(
                                'text' => "You are a professional content writer specializing in SEO-friendly blog posts. Always return content in HTML format, not markdown.\n\n" . $prompt
                            )
                        )
                    )
                ),
                'generationConfig' => array(
                    'temperature' => isset($this->options['temperature']) ? $this->options['temperature'] : 0.7,
                    'maxOutputTokens' => isset($this->options['max_tokens']) ? $this->options['max_tokens'] : 4096,
                )
            )),
            'timeout' => 120,
        );

        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            error_log('WPAB: Error generating content with Gemini: ' . $response->get_error_message());
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (isset($data['error'])) {
            $error_msg = isset($data['error']['message']) ? $data['error']['message'] : 'Unknown error';
            error_log('WPAB: Gemini API Error: ' . $error_msg);
            return false;
        }

        if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            error_log('WPAB: Unexpected Gemini response. Body: ' . $body);
            return false;
        }

        return array(
            'content' => trim($data['candidates'][0]['content']['parts'][0]['text'])
        );
    }

    public function get_available_models() {
        return array(
            'gemini-pro',
            'gemini-pro-vision',
            'gemini-1.5-pro-latest',
            'gemini-1.5-flash-latest'
        );
    }

    public function validate_api_key() {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $this->api_key;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
            ),
            'body' => json_encode(array(
                'contents' => array(
                    array(
                        'parts' => array(
                            array('text' => 'Hi')
                        )
                    )
                )
            )),
            'timeout' => 10,
        ));

        return !is_wp_error($response) && wp_remote_retrieve_response_code($response) !== 403;
    }
}

/**
 * Factory class to create AI providers
 */
class WPAB_AI_Provider_Factory {
    
    /**
     * Create an AI provider instance
     * @param string $provider Provider name (openai, claude, gemini)
     * @param string $api_key API key
     * @param string $model Model name
     * @param array $options Additional options
     * @return WPAB_AI_Provider|false Provider instance or false on failure
     */
    public static function create($provider, $api_key, $model, $options = array()) {
        switch (strtolower($provider)) {
            case 'openai':
                return new WPAB_OpenAI_Provider($api_key, $model, $options);
            case 'claude':
            case 'anthropic':
                return new WPAB_Claude_Provider($api_key, $model, $options);
            case 'gemini':
            case 'google':
                return new WPAB_Gemini_Provider($api_key, $model, $options);
            default:
                error_log('WPAB: Unknown AI provider: ' . $provider);
                return false;
        }
    }

    /**
     * Get list of available providers
     * @return array Provider names and labels
     */
    public static function get_providers() {
        return array(
            'openai' => 'OpenAI (GPT)',
            'claude' => 'Anthropic (Claude)',
            'gemini' => 'Google (Gemini)'
        );
    }
}