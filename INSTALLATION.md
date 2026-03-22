# Mail Chronicle - Installation Guide

## Prerequisites

Before installing Mail Chronicle, ensure you have:

- WordPress 6.0 or higher
- PHP 7.2 or higher (PHP 8.1+ recommended)
- Composer installed on your system
- Node.js 14+ and npm installed
- Access to your WordPress installation via SSH or FTP

## Step-by-Step Installation

### 1. Navigate to Plugin Directory

```bash
cd /path/to/your/wordpress/wp-content/plugins/mail-chronicle
```

### 2. Install PHP Dependencies

```bash
composer install --no-dev
```

For development (includes testing tools):
```bash
composer install
```

### 3. Install JavaScript Dependencies

```bash
npm install
```

### 4. Build Assets

For production:
```bash
npm run build
```

For development (with watch mode):
```bash
npm start
```

### 5. Activate the Plugin

#### Via WordPress Admin:
1. Log in to your WordPress admin panel
2. Navigate to **Plugins > Installed Plugins**
3. Find "Mail Chronicle" in the list
4. Click **Activate**

#### Via WP-CLI:
```bash
wp plugin activate mail-chronicle
```

## Configuration

### Basic Setup

1. Navigate to **Mail Chronicle > Settings** in WordPress admin
2. Check **Enable Logging** to start logging emails
3. Select your email provider (Mailgun, WordPress, etc.)
4. Configure provider-specific settings

### Mailgun Configuration

1. **Get Mailgun Credentials:**
   - Sign up at [mailgun.com](https://www.mailgun.com)
   - Go to **Settings > API Keys**
   - Copy your Private API Key
   - Go to **Sending > Domains**
   - Copy your domain name

2. **Configure in WordPress:**
   - Go to **Mail Chronicle > Settings**
   - Select **Mailgun** as provider
   - Paste your API Key
   - Paste your Domain
   - Select your region (US or EU)
   - Click **Save Changes**

3. **Set Up Webhooks (Optional but Recommended):**
   - In Mailgun dashboard, go to **Sending > Webhooks**
   - Click **Add webhook**
   - Enter webhook URL:
     ```
     https://your-site.com/wp-json/mail-chronicle/v1/webhook/mailgun
     ```
   - Select events to track:
     - ✅ Delivered
     - ✅ Opened
     - ✅ Clicked
     - ✅ Permanent Failure
     - ✅ Temporary Failure
     - ✅ Unsubscribed
     - ✅ Complained
   - Click **Create webhook**

## Verification

### Test Email Logging

1. Send a test email from WordPress:
   ```php
   wp_mail( 'test@example.com', 'Test Subject', 'Test message' );
   ```

2. Check **Mail Chronicle > Email Logs**
3. You should see the test email in the list

### Test Mailgun Integration

1. Send a test email
2. Check that the provider is set to "mailgun"
3. Wait a few moments for delivery
4. Check the email detail to see events (delivered, opened, etc.)

### Test REST API

```bash
curl -X GET "https://your-site.com/wp-json/mail-chronicle/v1/emails" \
  -H "Authorization: Bearer YOUR_WP_AUTH_TOKEN"
```

## Troubleshooting

### Composer Dependencies Not Found

**Error:** "Mail Chronicle requires Composer dependencies"

**Solution:**
```bash
cd wp-content/plugins/mail-chronicle
composer install --no-dev
```

### Assets Not Loading

**Error:** Admin interface not showing or broken

**Solution:**
```bash
cd wp-content/plugins/mail-chronicle
npm install
npm run build
```

### Mailgun Connection Failed

**Error:** "Failed to initialize Mailgun client"

**Solution:**
1. Verify API key is correct
2. Verify domain is correct
3. Check region setting (US vs EU)
4. Ensure your server can connect to Mailgun API

### Webhooks Not Working

**Error:** Events not being tracked

**Solution:**
1. Verify webhook URL is correct
2. Check that webhook is active in Mailgun dashboard
3. Test webhook manually:
   ```bash
   curl -X POST "https://your-site.com/wp-json/mail-chronicle/v1/webhook/mailgun" \
     -H "Content-Type: application/json" \
     -d '{"signature": {...}, "event-data": {...}}'
   ```
4. Check WordPress error logs

### Database Tables Not Created

**Error:** Plugin activated but not working

**Solution:**
1. Deactivate and reactivate the plugin
2. Or manually run:
   ```php
   $schema = new \MailChronicle\Infrastructure\Database\Schema();
   $schema->create_tables();
   ```

## Uninstallation

### Clean Uninstall

1. Deactivate the plugin
2. Delete the plugin
3. Database tables and options will be automatically removed

### Manual Cleanup (if needed)

```sql
DROP TABLE IF EXISTS wp_mail_chronicle_logs;
DROP TABLE IF EXISTS wp_mail_chronicle_events;
DELETE FROM wp_options WHERE option_name LIKE 'mail_chronicle%';
```

## Support

For issues and questions:
- Check the [README.md](README.md) for documentation
- Review the [CHANGELOG.md](CHANGELOG.md) for recent changes
- Contact support at your organization

## Next Steps

- Configure log retention settings
- Set up automated cleanup (optional)
- Integrate with your monitoring system
- Review email logs regularly

