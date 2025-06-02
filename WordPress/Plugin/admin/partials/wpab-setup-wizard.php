<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Check if we have API keys configured
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

// Determine current step
$current_step = isset($_GET['step']) ? intval($_GET['step']) : 1;

// If API key exists and we're on step 1, skip to step 3
if ($has_api_key && $current_step == 1 && !isset($_GET['force'])) {
    $current_step = 3;
}
?>

<div class="wrap wpab-setup-wizard">
    <h1>WP Auto Blogger Setup</h1>
    
    <div class="wpab-wizard-progress">
        <div class="wpab-wizard-steps">
            <div class="wpab-step <?php echo $current_step >= 1 ? 'active' : ''; ?> <?php echo $current_step > 1 ? 'completed' : ''; ?>">
                <span class="step-number">1</span>
                <span class="step-title">Choose AI Provider</span>
            </div>
            <div class="wpab-step <?php echo $current_step >= 2 ? 'active' : ''; ?> <?php echo $current_step > 2 ? 'completed' : ''; ?>">
                <span class="step-number">2</span>
                <span class="step-title">Configure API</span>
            </div>
            <div class="wpab-step <?php echo $current_step >= 3 ? 'active' : ''; ?>">
                <span class="step-number">3</span>
                <span class="step-title">Plugin Settings</span>
            </div>
        </div>
    </div>

    <?php if ($has_api_key && $current_step == 3): ?>
    <div class="notice notice-info">
        <p>AI provider already configured. <a href="<?php echo admin_url('admin.php?page=wpab&step=1&force=1'); ?>">Click here to change AI provider</a></p>
    </div>
    <?php endif; ?>

    <form method="post" action="options.php" id="wpab-wizard-form">
        <?php settings_fields( 'wpab_options' ); ?>
        
        <!-- Step 1: Choose AI Provider -->
        <div class="wpab-wizard-step" id="step-1" <?php echo $current_step != 1 ? 'style="display:none;"' : ''; ?>>
            <h2>Step 1: Choose Your AI Provider</h2>
            <p>Select which AI service you'd like to use for content generation.</p>
            
            <?php 
            if (!class_exists('WPAB_AI_Provider_Factory')) {
                require_once WPAB_PLUGIN_DIR . 'admin/classes/class-wpab-ai-provider.php';
            }
            $providers = WPAB_AI_Provider_Factory::get_providers();
            ?>
            
            <div class="wpab-provider-cards">
                <?php foreach ($providers as $key => $label): ?>
                <div class="wpab-provider-card <?php echo $ai_provider === $key ? 'selected' : ''; ?>" data-provider="<?php echo esc_attr($key); ?>">
                    <h3><?php echo esc_html($label); ?></h3>
                    <div class="provider-features">
                        <?php if ($key === 'openai'): ?>
                            <ul>
                                <li>GPT-3.5 and GPT-4 models</li>
                                <li>Excellent content quality</li>
                                <li>Wide language support</li>
                            </ul>
                        <?php elseif ($key === 'claude'): ?>
                            <ul>
                                <li>Claude 2 and Claude 3 models</li>
                                <li>Strong analytical capabilities</li>
                                <li>Excellent for technical content</li>
                            </ul>
                        <?php elseif ($key === 'gemini'): ?>
                            <ul>
                                <li>Gemini Pro models</li>
                                <li>Google's latest AI technology</li>
                                <li>Multimodal capabilities</li>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <input type="radio" name="wpab_options[ai_provider]" value="<?php echo esc_attr($key); ?>" 
                           <?php checked($ai_provider, $key); ?> class="wpab-provider-radio">
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="wpab-wizard-buttons">
                <button type="button" class="button button-primary" onclick="wpabNextStep(2)">Next: Configure API</button>
            </div>
        </div>

        <!-- Step 2: Configure API -->
        <div class="wpab-wizard-step" id="step-2" <?php echo $current_step != 2 ? 'style="display:none;"' : ''; ?>>
            <h2>Step 2: Configure API Access</h2>
            <p>Enter your API credentials for the selected provider.</p>
            
            <table class="form-table" role="presentation">
                <!-- OpenAI Configuration -->
                <tbody class="ai-provider-config" id="config-openai" <?php echo $ai_provider !== 'openai' ? 'style="display:none;"' : ''; ?>>
                    <tr>
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <?php WPAB_Settings_Fields::openai_api_key_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <?php WPAB_Settings_Fields::openai_model_callback(); ?>
                        </td>
                    </tr>
                </tbody>
                
                <!-- Claude Configuration -->
                <tbody class="ai-provider-config" id="config-claude" <?php echo $ai_provider !== 'claude' ? 'style="display:none;"' : ''; ?>>
                    <tr>
                        <th scope="row">Claude API Key</th>
                        <td>
                            <?php WPAB_Settings_Fields::claude_api_key_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <?php WPAB_Settings_Fields::claude_model_callback(); ?>
                        </td>
                    </tr>
                </tbody>
                
                <!-- Gemini Configuration -->
                <tbody class="ai-provider-config" id="config-gemini" <?php echo $ai_provider !== 'gemini' ? 'style="display:none;"' : ''; ?>>
                    <tr>
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <?php WPAB_Settings_Fields::gemini_api_key_callback(); ?>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Model</th>
                        <td>
                            <?php WPAB_Settings_Fields::gemini_model_callback(); ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div class="wpab-wizard-buttons">
                <button type="button" class="button" onclick="wpabPrevStep(1)">Previous</button>
                <button type="button" class="button button-primary" onclick="wpabValidateAndNext()">Next: Plugin Settings</button>
            </div>
        </div>

        <!-- Step 3: Plugin Settings -->
        <div class="wpab-wizard-step" id="step-3" <?php echo $current_step != 3 ? 'style="display:none;"' : ''; ?>>
            <h2>Step 3: Configure Plugin Settings</h2>
            <p>Set up your content generation preferences.</p>
            
            <?php 
            // Include settings sections except API settings (which are in step 2)
            // We'll render each section manually to exclude API settings
            ?>
            <h2>Content Generation Settings</h2>
            <table class="form-table" role="presentation">
                <?php do_settings_fields( 'wpab', 'wpab_content_generation_settings' ); ?>
            </table>
            
            <h2>Notification Preferences</h2>
            <table class="form-table" role="presentation">
                <?php do_settings_fields( 'wpab', 'wpab_notification_settings' ); ?>
            </table>
            
            <h2>Email Configuration</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Email Provider</th>
                    <td>
                        <?php WPAB_Settings_Fields::email_provider_callback(); ?>
                    </td>
                </tr>
                <tr class="mailgun-field">
                    <th scope="row">Mailgun API Key</th>
                    <td>
                        <?php WPAB_Settings_Fields::mailgun_api_key_callback(); ?>
                    </td>
                </tr>
                <tr class="mailgun-field">
                    <th scope="row">Mailgun Domain</th>
                    <td>
                        <?php WPAB_Settings_Fields::mailgun_domain_callback(); ?>
                    </td>
                </tr>
                <?php do_settings_fields( 'wpab', 'wpab_email_settings' ); ?>
            </table>
            
            <h2>General Plugin Settings</h2>
            <table class="form-table" role="presentation">
                <?php do_settings_fields( 'wpab', 'wpab_general_settings' ); ?>
            </table>
            
            <h2>Post Settings</h2>
            <table class="form-table" role="presentation">
                <?php do_settings_fields( 'wpab', 'wpab_post_settings' ); ?>
            </table>
            
            <div class="wpab-wizard-buttons">
                <?php if (!$has_api_key): ?>
                <button type="button" class="button" onclick="wpabPrevStep(2)">Previous</button>
                <?php endif; ?>
                <?php submit_button( 'Save Settings', 'primary', 'submit', false ); ?>
            </div>
        </div>
    </form>
</div>

<style>
.wpab-setup-wizard {
    max-width: 800px;
    margin: 20px 0;
}

.wpab-wizard-progress {
    margin: 30px 0;
}

.wpab-wizard-steps {
    display: flex;
    justify-content: space-between;
    position: relative;
}

.wpab-wizard-steps::before {
    content: '';
    position: absolute;
    top: 20px;
    left: 0;
    right: 0;
    height: 2px;
    background: #ddd;
    z-index: 0;
}

.wpab-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: #ddd;
    color: #666;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 10px;
}

.wpab-step.active .step-number {
    background: #2271b1;
    color: white;
}

.wpab-step.completed .step-number {
    background: #00a32a;
    color: white;
}

.step-title {
    font-size: 14px;
    text-align: center;
}

.wpab-provider-cards {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin: 30px 0;
}

.wpab-provider-card {
    border: 2px solid #ddd;
    border-radius: 8px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s;
    position: relative;
}

.wpab-provider-card:hover {
    border-color: #2271b1;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.wpab-provider-card.selected {
    border-color: #2271b1;
    background: #f0f8ff;
}

.wpab-provider-card h3 {
    margin-top: 0;
}

.provider-features ul {
    margin: 10px 0;
    padding-left: 20px;
}

.provider-features li {
    margin: 5px 0;
    font-size: 13px;
}

.wpab-provider-radio {
    position: absolute;
    bottom: 20px;
    right: 20px;
}

.wpab-wizard-buttons {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #ddd;
}

.wpab-wizard-buttons button {
    margin-right: 10px;
}

.ai-provider-config {
    display: none;
}

#wpab-wizard-form .form-table th {
    width: 200px;
}

/* Remove hiding of individual fields - let the tbody handle it */
.ai-provider-field {
    display: block !important;
}

.api-key-notice {
    margin-top: 10px;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Handle provider card clicks
    $('.wpab-provider-card').on('click', function() {
        $('.wpab-provider-card').removeClass('selected');
        $(this).addClass('selected');
        $(this).find('.wpab-provider-radio').prop('checked', true);
        
        // Update provider configs visibility
        var provider = $(this).data('provider');
        $('.ai-provider-config').hide();
        $('#config-' + provider).show();
    });
    
    // Handle provider radio changes
    $('input[name="wpab_options[ai_provider]"]').on('change', function() {
        var provider = $(this).val();
        $('.ai-provider-config').hide();
        $('#config-' + provider).show();
    });
});

function wpabNextStep(step) {
    jQuery('.wpab-wizard-step').hide();
    jQuery('#step-' + step).show();
    updateStepIndicators(step);
}

function wpabPrevStep(step) {
    jQuery('.wpab-wizard-step').hide();
    jQuery('#step-' + step).show();
    updateStepIndicators(step);
}

function updateStepIndicators(currentStep) {
    jQuery('.wpab-step').each(function(index) {
        var stepNum = index + 1;
        if (stepNum < currentStep) {
            jQuery(this).addClass('completed').addClass('active');
        } else if (stepNum === currentStep) {
            jQuery(this).addClass('active').removeClass('completed');
        } else {
            jQuery(this).removeClass('active').removeClass('completed');
        }
    });
}

function wpabValidateAndNext() {
    var provider = jQuery('input[name="wpab_options[ai_provider]"]:checked').val();
    var apiKeyField = jQuery('#' + provider + '_api_key');
    
    if (!apiKeyField.val()) {
        alert('Please enter your API key before proceeding.');
        apiKeyField.focus();
        return;
    }
    
    wpabNextStep(3);
}
</script>