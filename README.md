# WP Auto Blogger

An AI-powered WordPress plugin that automatically generates and publishes blog content using OpenAI, Claude, or Google Gemini.

## Features

- **Multi-AI Support**: Choose between OpenAI (GPT), Anthropic (Claude), or Google (Gemini) for content generation
- **Automated Content Generation**: Generate blog posts based on your service pages or custom topics
- **Smart Scheduling**: Automatically publish content on a daily, weekly, or monthly schedule
- **Approval Workflow**: Optional email-based approval system for generated content
- **SEO Integration**: Automatic SEO optimization with Yoast SEO support
- **Content Calendar**: Visual calendar showing scheduled and published content
- **Email Notifications**: 
  - Approval requests for new content
  - Alerts when content calendar is running low (7, 3, or 0 topics)
- **License Management**: AWS-based licensing system with DynamoDB and Lambda

## Project Structure

```
wordpress-auto-blogged/
├── WordPress/
│   └── Plugin/           # WordPress plugin files
│       ├── admin/        # Admin interface and classes
│       ├── includes/     # Core plugin functionality
│       ├── public/       # Public-facing functionality
│       └── wp-auto-blogger.php  # Main plugin file
├── Backend/              # AWS infrastructure
│   ├── lambda/           # Lambda functions for licensing
│   ├── infrastructure/   # Terraform configuration
│   └── deploy.sh        # Deployment script
└── README.md
```

## Installation

### WordPress Plugin

1. Upload the `WordPress/Plugin` folder to your WordPress `/wp-content/plugins/` directory
2. Rename the folder to `wp-auto-blogger`
3. Activate the plugin through the WordPress admin panel

### AWS Backend Setup

1. Install AWS CLI and Terraform
2. Configure AWS credentials
3. Run the deployment script:
   ```bash
   cd Backend
   ./deploy.sh
   ```
4. Note the API Gateway URLs from the output
5. Add the URLs to your WordPress settings

## Configuration

### AI Provider Setup

1. Go to **WP Auto Blogger > Settings** in WordPress admin
2. Select your preferred AI provider
3. Enter the appropriate API key:
   - **OpenAI**: Get from [OpenAI Dashboard](https://platform.openai.com/api-keys)
   - **Claude**: Get from [Anthropic Console](https://console.anthropic.com/settings/keys)
   - **Gemini**: Get from [Google AI Studio](https://makersuite.google.com/app/apikey)

### Email Configuration

1. Configure Mailgun settings:
   - Enter your Mailgun API key
   - Enter your Mailgun domain
2. Set up email notifications:
   - Enable approval emails
   - Enable content calendar alerts
   - Configure recipient email addresses

### Content Generation

1. Add service pages for topic inspiration
2. Set default word count, tone, and context
3. Configure post author and categories
4. Enable approval workflow if desired

## Usage

### Generating Topics

1. Go to **WP Auto Blogger > Content Calendar**
2. Click "Generate Topics"
3. Select number of topics and auto-approval preference
4. Review and approve generated topics

### Publishing Content

**Manual Publishing:**
- Click "Blog Now" on approved topics

**Automated Publishing:**
- Go to **WP Auto Blogger > Schedule**
- Configure publishing frequency
- System will automatically publish approved topics

### Email Approval

When approval workflow is enabled:
1. Generated posts are saved as drafts
2. Approval email is sent with preview
3. Click "Approve" to publish or "Reject" to delete

## API Endpoints

### License Activation
```
POST /license/activate
{
  "licenseKey": "string",
  "siteUrl": "string",
  "email": "string"
}
```

### License Validation
```
POST /license/validate
{
  "licenseKey": "string",
  "siteUrl": "string"
}
```

## Development

### Adding New AI Providers

1. Extend `WPAB_AI_Provider` class
2. Implement required methods:
   - `generate_content()`
   - `get_available_models()`
   - `validate_api_key()`
3. Register in `WPAB_AI_Provider_Factory`

### Customizing Email Templates

Edit the `get_email_template()` method in `class-wpab-email-handler.php`

## Troubleshooting

### Common Issues

1. **API Key Errors**: Ensure API keys are valid and have proper permissions
2. **Email Not Sending**: Check Mailgun configuration and domain verification
3. **Content Not Generating**: Verify AI provider settings and API limits
4. **Schedule Not Working**: Ensure WordPress cron is running properly

### Debug Mode

Enable logging in plugin settings to track issues

## License

This plugin is proprietary software. License required for use.

## Support

For support, please contact support@wpautoblogger.com