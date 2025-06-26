<?php
// File: wp-auto-blogger/admin/classes/class-wpab-admin.php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WPAB_Admin {
    private $settings;
    private $topics;
    private $content_generator;
    private $seo_handler;
    private $schedule_handler;
    private $email_handler;

    /**
     * Constructor
     */
    public function __construct() {
        // Start output buffering early to catch any errant output from other plugins
        add_action('admin_init', array($this, 'start_output_buffer'), 1);
        
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required dependencies.
     */
    private function load_dependencies() {
        // Example: We include the classes we need for the admin side
        require_once __DIR__ . '/class-wpab-settings.php';
        require_once __DIR__ . '/class-wpab-topics.php';
        require_once __DIR__ . '/class-wpab-content-generator.php';
        require_once __DIR__ . '/class-wpab-seo-handler.php';
        require_once __DIR__ . '/class-wpab-schedule-handler.php';
        require_once __DIR__ . '/class-wpab-email-handler.php';
        // We do NOT require the license handler here
    }

    /**
     * Initialize hooks.
     */
    private function init_hooks() {
        // Admin Menu and Pages
        add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

        // Settings Registration (via WPAB_Settings)
        $this->settings = new WPAB_Settings();
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );

        // Topics Management
        $this->topics = new WPAB_Topics();
        add_action( 'admin_post_wpab_add_service_pages', array( $this->topics, 'handle_add_service_pages' ) );
        add_action( 'admin_post_wpab_generate_topics', array( $this->topics, 'handle_generate_topics' ) );
        add_action( 'admin_post_wpab_update_topics', array( $this->topics, 'handle_update_topics' ) );

        // Content Generation
        $this->content_generator = new WPAB_Content_Generator();
        add_action( 'admin_post_wpab_blog_now', array( $this->content_generator, 'handle_blog_now' ) );

        // SEO Handling
        $this->seo_handler = new WPAB_SEO_Handler();

        // Schedule Handling
        $this->schedule_handler = new WPAB_Schedule_Handler();
        add_action( 'wpab_publish_scheduled_post', array( $this->schedule_handler, 'publish_scheduled_post' ) );
        
        // Email Handling
        $this->email_handler = new WPAB_Email_Handler();

        // Enqueue Scripts and Styles
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

        // Display Admin Pages
        add_action( 'wp_loaded', array( $this, 'display_admin_pages' ) );

        // Schedule Content Generation
        add_action( 'wpab_generate_content_event', array( $this->content_generator, 'generate_content_from_topics' ) );
        
        // AJAX handler for provider change
        add_action( 'wp_ajax_wpab_save_provider', array( $this, 'ajax_save_provider' ) );
    }

    /**
     * Add admin menus and submenus.
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'WP Auto Blogger Settings',
            'Auto Blogger',
            'manage_options',
            'wpab',
            array( $this, 'display_plugin_settings_page' ),
            'dashicons-admin-generic',
            81
        );

        // Add submenu for Content Calendar
        add_submenu_page(
            'wpab',
            'Content Calendar',
            'Content Calendar',
            'manage_options',
            'wpab-content-calendar',
            array( $this, 'display_content_calendar_page' )
        );

        // Add submenu for Schedule
        add_submenu_page(
            'wpab',
            'Schedule',
            'Schedule',
            'manage_options',
            'wpab-schedule',
            array( $this->schedule_handler, 'display_schedule_page' )
        );
        
        // Add submenu for API Settings
        add_submenu_page(
            'wpab',
            'API Settings',
            'API Settings',
            'manage_options',
            'wpab-api-settings',
            array( $this, 'display_api_settings_page' )
        );
    }

    /**
     * Display the plugin settings page (Main page).
     */
    public function display_plugin_settings_page() {
        // Check if this is first setup or if user wants wizard
        $options = get_option( 'wpab_options', array() );
        $ai_provider = isset( $options['ai_provider'] ) ? $options['ai_provider'] : 'openai';
        $has_api_key = false;
        
        switch ($ai_provider) {
            case 'openai':
                $has_api_key = !empty($options['openai_api_key']);
                break;
            case 'claude':
                $has_api_key = !empty($options['claude_api_key']);
                break;
            case 'gemini':
                $has_api_key = !empty($options['gemini_api_key']);
                break;
        }
        
        // Show wizard if no API key or if requested
        if (!$has_api_key || isset($_GET['wizard'])) {
            include WPAB_PLUGIN_DIR . 'admin/partials/wpab-setup-wizard.php';
            return;
        }
        
        ?>
        <div class="wrap">
            <h1>WP Auto Blogger Settings</h1>
            
            <?php if (isset($_GET['activated']) && $_GET['activated']) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>WP Auto Blogger has been successfully activated!</p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['settings-updated']) && $_GET['settings-updated']) : ?>
                <div class="notice notice-success is-dismissible">
                    <p>Settings saved successfully!</p>
                </div>
            <?php endif; ?>
            
            <div class="notice notice-info">
                <p>AI Provider: <strong><?php echo ucfirst($ai_provider); ?></strong> | 
                   <a href="<?php echo admin_url('admin.php?page=wpab&wizard=1'); ?>">Run Setup Wizard</a></p>
            </div>
            <form method="post" action="options.php" id="wpab-settings-form">
                <?php
                // This is the normal WordPress settings form for fields registered under 'wpab_options'.
                settings_fields( 'wpab_options' );
                do_settings_sections( 'wpab' );
                submit_button();
                ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            // Add output buffering protection for settings save
            $('#wpab-settings-form').on('submit', function() {
                // Show a loading message
                $(this).find('input[type="submit"]').val('Saving...').prop('disabled', true);
                
                // If headers are already sent (due to other plugin errors), use AJAX
                <?php if (headers_sent()) : ?>
                    var formData = $(this).serialize();
                    $.post($(this).attr('action'), formData, function() {
                        window.location.href = '<?php echo admin_url('admin.php?page=wpab&settings-updated=true'); ?>';
                    });
                    return false;
                <?php endif; ?>
            });
        });
        </script>
        <?php
        
        // Add API key toggle script
        WPAB_Settings_Fields::add_api_key_toggle_script();
    }

    /**
     * Display the content calendar page.
     */
    public function display_content_calendar_page() {
        include_once plugin_dir_path( __FILE__ ) . '../partials/wpab-content-calendar.php';
    }
    
    /**
     * Display the API settings page.
     */
    public function display_api_settings_page() {
        include_once plugin_dir_path( __FILE__ ) . '../views/api-settings.php';
    }

    /**
     * Enqueue admin scripts and styles.
     */
    public function enqueue_scripts( $hook ) {
        // Only enqueue on our plugin pages
        if (
            'toplevel_page_wpab' !== $hook
            && 'wpab_page_wpab-content-calendar' !== $hook
            && 'wpab_page_wpab-schedule' !== $hook
            && 'wpab_page_wpab-api-settings' !== $hook
        ) {
            return;
        }
        
        // Ensure jQuery is loaded
        wp_enqueue_script( 'jquery' );
        
        wp_enqueue_style(
            'wpab-admin-css',
            plugin_dir_url( __FILE__ ) . '../css/admin.css',
            array(),
            WPAB_VERSION
        );
        wp_enqueue_script(
            'wpab-admin-js',
            plugin_dir_url( __FILE__ ) . '../js/admin.js',
            array( 'jquery' ),
            WPAB_VERSION,
            true
        );
    }

    /**
     * Display admin pages based on current page.
     */
    public function display_admin_pages() {
        // If you have any dynamic content logic, do it here.
    }
    
    /**
     * AJAX handler to save provider selection
     */
    public function ajax_save_provider() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wpab_save_provider')) {
            wp_die('Security check failed');
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        // Get and save the provider
        $provider = sanitize_text_field($_POST['provider']);
        $options = get_option('wpab_options', array());
        $options['ai_provider'] = $provider;
        update_option('wpab_options', $options);
        
        wp_send_json_success();
    }
    
    /**
     * Start output buffering on admin pages to prevent header issues
     */
    public function start_output_buffer() {
        // Only buffer on our plugin pages
        if (isset($_GET['page']) && strpos($_GET['page'], 'wpab') === 0) {
            ob_start();
        }
    }
}
