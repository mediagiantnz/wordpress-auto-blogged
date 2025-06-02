# WP Auto Blogger Email Triggers Documentation

## Overview
WP Auto Blogger can send email notifications for two main purposes: content approvals and calendar alerts. Emails can be sent via WordPress's built-in wp_mail() function or through Mailgun for better deliverability.

## Email Configuration

### Email Provider Selection
- **WordPress Default (wp_mail)**: Uses your server's email configuration
- **Mailgun**: Professional email service with better deliverability and tracking

### Unified Notification Email
All notifications are sent to a single email address configured in:
**Settings → General Plugin Settings → Notification Email Address**

## When Emails Are Triggered

### 1. Content Approval Emails

**Trigger**: When a new blog post is generated with approval required
- Occurs immediately after content generation
- Only sent if "Send Approval Emails" is enabled
- Only sent if "Require Approval" is enabled (posts saved as drafts)

**Email Contains**:
- Post title and excerpt
- Three action buttons:
  - ✓ Approve & Publish - One-click publish the post
  - ✗ Reject - Delete the draft post
  - ✎ Edit Post - Open in WordPress editor
- Direct link to view the post

**Configuration**:
- Enable/Disable: Settings → General Plugin Settings → Send Approval Emails
- Email Address: Settings → General Plugin Settings → Notification Email Address

### 2. Content Calendar Alerts

**Trigger**: Daily check via WordPress cron (wp_schedule_event)
- Runs once per day
- Checks the number of approved topics remaining

**Alert Levels**:
1. **Warning (7 topics)**: When 7 or fewer approved topics remain
   - Subject: "⚠️ Low Content Alert: 7 topics remaining"
   - Sent maximum once per week

2. **Critical (3 topics)**: When 3 or fewer approved topics remain
   - Subject: "🚨 Critical Content Alert: 3 topics remaining"
   - Sent maximum once per week

3. **Empty (0 topics)**: When no approved topics remain
   - Subject: "❗ Empty Content Calendar: 0 topics remaining"
   - Sent maximum once per week

**Email Contains**:
- Current topic count
- Alert level message
- Link to content calendar
- Call to action to generate more topics

**Configuration**:
- Enable/Disable: Settings → General Plugin Settings → Send Calendar Alerts
- Email Address: Settings → General Plugin Settings → Notification Email Address

## Email Trigger Flow

### Approval Email Flow:
1. User clicks "Blog Now" or scheduled publish runs
2. Content is generated via selected AI provider
3. If "Require Approval" is enabled → Post saved as draft
4. If "Send Approval Emails" is enabled → Email sent
5. Recipient receives email with action buttons
6. Clicking approve/reject triggers action via secure URLs

### Calendar Alert Flow:
1. WordPress cron runs daily check (wpab_check_content_calendar)
2. System counts approved topics
3. Compares with alert thresholds (7, 3, 0)
4. Checks if alert was sent in last 7 days
5. If threshold met and time elapsed → Send alert
6. Updates last alert timestamp to prevent spam

## Technical Implementation

### Hooks Used:
- `wpab_post_generated` - Fired after post generation for approval emails
- `wpab_check_content_calendar` - Scheduled event for calendar alerts

### Security:
- Approval/reject URLs use secure random keys (32 characters)
- Keys stored as post meta and validated on action
- One-time use - keys removed after action

### Email Templates:
- HTML emails with responsive design
- Fallback text version for Mailgun
- Branded with site name and custom signature

## Troubleshooting

### Emails Not Sending:
1. Check email provider configuration (Settings → API Settings)
2. Verify notification email address is valid
3. Check WordPress/server logs for errors
4. For Mailgun: Verify API key and domain are correct

### Calendar Alerts Not Working:
1. Ensure WordPress cron is running (use WP Crontrol plugin to check)
2. Verify "Send Calendar Alerts" is enabled
3. Check that you have topics in the calendar
4. Remember alerts are limited to once per week per level

### Approval Links Not Working:
1. Ensure permalink structure is not "Plain"
2. Check that rewrite rules are flushed (re-save permalinks)
3. Verify the wpab-approval endpoint is registered