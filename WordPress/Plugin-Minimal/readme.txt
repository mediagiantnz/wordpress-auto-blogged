=== WP Auto Blogger ===
Contributors: wpautoblogger
Tags: ai, content generation, auto blogging, openai, claude, gemini, seo
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 2.0.4
Requires PHP: 7.2
License: Proprietary
License URI: https://wpautoblogger.com/license

Automatically generate and publish SEO-optimized blog content using AI (OpenAI, Claude, or Gemini).

== Description ==

WP Auto Blogger is a powerful WordPress plugin that leverages artificial intelligence to automatically generate and publish high-quality blog content. With support for multiple AI providers including OpenAI (GPT), Anthropic (Claude), and Google (Gemini), you can create engaging, SEO-optimized content on autopilot.

= Key Features =

* **Multi-AI Provider Support** - Choose between OpenAI, Claude, or Gemini
* **Automated Content Generation** - Generate blog posts based on your topics
* **Smart Scheduling** - Publish content daily, weekly, or monthly
* **Email Approval Workflow** - Review content before publishing
* **SEO Integration** - Automatic optimization with Yoast SEO
* **Content Calendar** - Visual planning and management
* **Low Content Alerts** - Email notifications when running low on topics
* **License Management** - AWS-powered licensing system

= Perfect For =

* Content marketers who need consistent blog output
* Agencies managing multiple client websites
* Business owners wanting to maintain an active blog
* SEO professionals looking to scale content creation

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wp-auto-blogger` directory
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WP Auto Blogger > Settings to configure your AI provider
4. Enter your API keys and configure content preferences
5. Start generating content!

== Frequently Asked Questions ==

= Which AI providers are supported? =

WP Auto Blogger supports:
* OpenAI (GPT-3.5, GPT-4, GPT-4 Turbo)
* Anthropic (Claude 2, Claude 3)
* Google (Gemini Pro, Gemini 1.5)

= Do I need API keys? =

Yes, you'll need an API key from your chosen AI provider. The plugin provides direct links to obtain these keys.

= Can I review content before publishing? =

Yes! Enable the approval workflow to receive email notifications with preview and one-click approve/reject buttons.

= How does the scheduling work? =

The plugin uses WordPress cron to automatically publish approved topics based on your configured schedule (daily, weekly, or monthly).

= Is Mailgun required? =

Mailgun is only required if you want to use the email approval and alert features. The plugin can function without it.

== Screenshots ==

1. Main settings page with AI provider selection
2. Content calendar showing scheduled posts
3. Topic generation interface
4. Email approval notification
5. Schedule configuration

== Changelog ==

= 2.0.4 =
* Removed activation/deactivation hooks to prevent conflicts
* Moved initialization to admin_init for better compatibility
* Plugin now initializes settings on first admin access
* Eliminated all header modification during activation

= 2.0.3 =
* Enhanced activation process to handle other plugins' output errors
* Added automatic redirect to settings page after activation
* Improved output buffering to prevent blank screen on activation
* Added activation success notice

= 2.0.2 =
* Added activation and deactivation hooks with output buffering
* Fixed header errors during plugin activation
* Improved initialization of default options on activation
* Added cleanup of scheduled events on deactivation

= 2.0.1 =
* Fixed settings save redirect issue when other plugins output errors
* Improved topic parsing for Claude and other AI providers
* Added output buffering to prevent header conflicts
* Enhanced error logging for topic generation debugging
* Added settings saved confirmation message

= 2.0.0 =
* Added support for Claude (Anthropic) and Gemini (Google) AI providers
* Implemented AWS-based license management system
* Added Mailgun email integration for approvals
* Added content calendar low-topic alerts (7, 3, 0)
* Removed Parsedown dependency - AI returns HTML directly
* Improved admin UI with dynamic API key requirements
* Enhanced security with proper nonce verification
* Restructured codebase for better organization

= 1.0.0 =
* Initial release
* OpenAI integration
* Basic content generation
* Yoast SEO support

== Upgrade Notice ==

= 2.0.4 =
Completely redesigned activation to avoid all conflicts. No more white screens!

= 2.0.0 =
Major update with multi-AI support and email workflows. Backup your site before upgrading.

== License ==

This plugin requires a valid license for use. Visit https://wpautoblogger.com for licensing information.