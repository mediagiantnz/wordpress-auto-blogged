<?php
/**
 * API Settings page
 *
 * @package WordPress_Auto_Blogger
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get current API key
$api_key = get_option('wpab_api_key');

// Generate new key if requested
if (isset($_POST['generate_api_key']) && check_admin_referer('wpab_api_settings')) {
    $api_key = wp_generate_password(32, false);
    update_option('wpab_api_key', $api_key);
    echo '<div class="notice notice-success"><p>' . esc_html__('New API key generated successfully!', 'wp-auto-blogger') . '</p></div>';
}

// If no API key exists, generate one
if (empty($api_key)) {
    $api_key = wp_generate_password(32, false);
    update_option('wpab_api_key', $api_key);
}
?>

<div class="wrap">
    <h1><?php echo esc_html__('API Settings', 'wp-auto-blogger'); ?></h1>
    
    <div class="wpab-settings-section">
        <h2><?php echo esc_html__('REST API Configuration', 'wp-auto-blogger'); ?></h2>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row"><?php echo esc_html__('API Key', 'wp-auto-blogger'); ?></th>
                    <td>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <input type="text" class="regular-text" value="<?php echo esc_attr($api_key); ?>" readonly style="font-family: monospace;">
                            <button type="button" class="button" onclick="copyApiKey()" id="copy-button">
                                <?php echo esc_html__('Copy', 'wp-auto-blogger'); ?>
                            </button>
                        </div>
                        <p class="description">
                            <?php echo esc_html__('Use this API key in the X-API-Key header when making REST API requests.', 'wp-auto-blogger'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row"><?php echo esc_html__('API Endpoints', 'wp-auto-blogger'); ?></th>
                    <td>
                        <div class="wpab-endpoint-list">
                            <div class="wpab-endpoint">
                                <strong><?php echo esc_html__('Publish Content:', 'wp-auto-blogger'); ?></strong><br>
                                <code><?php echo esc_url(rest_url('wpab-cloud/v1/publish')); ?></code>
                            </div>
                            <div class="wpab-endpoint" style="margin-top: 10px;">
                                <strong><?php echo esc_html__('Health Check:', 'wp-auto-blogger'); ?></strong><br>
                                <code><?php echo esc_url(rest_url('wpab-cloud/v1/health')); ?></code>
                            </div>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
        
        <form method="post" action="">
            <?php wp_nonce_field('wpab_api_settings'); ?>
            <p>
                <input type="submit" name="generate_api_key" class="button button-secondary" 
                       value="<?php echo esc_attr__('Generate New API Key', 'wp-auto-blogger'); ?>"
                       onclick="return confirm('<?php echo esc_js(__('Are you sure you want to generate a new API key? The old key will stop working immediately.', 'wp-auto-blogger')); ?>');">
            </p>
        </form>
    </div>
    
    <div class="wpab-settings-section">
        <h2><?php echo esc_html__('API Documentation', 'wp-auto-blogger'); ?></h2>
        
        <h3><?php echo esc_html__('Publishing Content', 'wp-auto-blogger'); ?></h3>
        <p><?php echo esc_html__('Send a POST request to the publish endpoint with the following JSON body:', 'wp-auto-blogger'); ?></p>
        
        <pre style="background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto;">
{
    "title": "Your Post Title",
    "content": "The HTML content of your post",
    "excerpt": "Optional excerpt",
    "status": "draft", // or "publish", "pending", "private"
    "categories": [1, 2], // Array of category IDs
    "tags": ["tag1", "tag2"], // Array of tag names
    "featured_image_url": "https://example.com/image.jpg",
    "meta": {
        "_yoast_wpseo_title": "SEO Title",
        "_yoast_wpseo_metadesc": "SEO Description"
    }
}
        </pre>
        
        <h3><?php echo esc_html__('Example cURL Request', 'wp-auto-blogger'); ?></h3>
        <pre style="background: #f0f0f0; padding: 15px; border-radius: 5px; overflow-x: auto;">
curl -X POST <?php echo esc_url(rest_url('wpab-cloud/v1/publish')); ?> \
  -H "X-API-Key: <?php echo esc_attr($api_key); ?>" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Post",
    "content": "<p>This is a test post.</p>",
    "status": "draft"
  }'
        </pre>
    </div>
</div>

<style>
.wpab-settings-section {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid #ccd0d4;
    box-shadow: 0 1px 1px rgba(0,0,0,.04);
}

.wpab-endpoint {
    font-size: 14px;
}

.wpab-endpoint code {
    background: #f0f0f0;
    padding: 2px 5px;
    border-radius: 3px;
}
</style>

<script>
function copyApiKey() {
    const apiKeyInput = document.querySelector('input[type="text"][readonly]');
    const copyButton = document.getElementById('copy-button');
    
    // Create a temporary input to copy from (to avoid readonly issues)
    const tempInput = document.createElement('input');
    tempInput.value = apiKeyInput.value;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand('copy');
    document.body.removeChild(tempInput);
    
    // Update button text
    const originalText = copyButton.textContent;
    copyButton.textContent = '<?php echo esc_js(__('Copied!', 'wp-auto-blogger')); ?>';
    
    setTimeout(() => {
        copyButton.textContent = originalText;
    }, 2000);
}
</script>