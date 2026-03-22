# Mail Chronicle - Quick Start Guide

Get up and running with Mail Chronicle in 5 minutes!

## 🚀 Quick Installation

```bash
# Navigate to plugin directory
cd wp-content/plugins/mail-chronicle

# Install dependencies
composer install --no-dev
npm install

# Build assets
npm run build
```

Then activate the plugin in WordPress admin.

## ⚙️ Quick Configuration

### Option 1: WordPress Default (No External Service)

1. Go to **Mail Chronicle > Settings**
2. Check **Enable Logging** ✅
3. Select **WordPress (default)** as provider
4. Click **Save Changes**

Done! All emails are now being logged.

### Option 2: Mailgun Integration

1. Get Mailgun credentials:
   - API Key: https://app.mailgun.com/settings/api_security
   - Domain: https://app.mailgun.com/mg/sending/domains

2. Configure in WordPress:
   - Go to **Mail Chronicle > Settings**
   - Check **Enable Logging** ✅
   - Select **Mailgun** as provider
   - Paste API Key and Domain
   - Select Region (US or EU)
   - Click **Save Changes**

3. (Optional) Set up webhooks for event tracking:
   ```
   Webhook URL: https://your-site.com/wp-json/mail-chronicle/v1/webhook/mailgun
   ```

## 📧 View Email Logs

1. Go to **Mail Chronicle > Email Logs**
2. Browse, search, and filter emails
3. Click on any email to view details
4. See delivery events in the Events tab

## 🔧 Common Tasks

### Send a Test Email

```php
wp_mail( 'test@example.com', 'Test Subject', 'Test message from Mail Chronicle' );
```

### Check Logs via REST API

```bash
curl "https://your-site.com/wp-json/mail-chronicle/v1/emails?per_page=10"
```

### Filter Emails by Status

In the Email Logs page:
- Use the **Status** dropdown
- Select: Pending, Sent, Delivered, Opened, Clicked, Failed, or Bounced

### Search Emails

In the Email Logs page:
- Use the **Search** field
- Search by recipient email or subject

### View Email Details

1. Click on any email subject in the logs table
2. View tabs:
   - **Details**: Recipient, status, headers, attachments
   - **Content**: HTML and plain text versions
   - **Events**: Delivery timeline (if using Mailgun)

## 🎯 Best Practices

### 1. Set Log Retention

Go to **Settings** and set **Log Retention (days)** to avoid database bloat:
- Development: 7 days
- Production: 30-90 days

### 2. Monitor Failed Emails

Regularly check for failed emails:
1. Go to **Email Logs**
2. Filter by **Status: Failed**
3. Investigate and fix issues

### 3. Use Webhooks for Real-Time Tracking

Set up Mailgun webhooks to track:
- ✅ Delivery confirmation
- ✅ Email opens
- ✅ Link clicks
- ✅ Bounces and failures

### 4. Secure Your API Keys

- Never commit API keys to version control
- Use environment variables for sensitive data
- Rotate keys regularly

## 🐛 Troubleshooting

### Plugin Not Working?

```bash
# Reinstall dependencies
composer install --no-dev
npm install
npm run build

# Reactivate plugin
wp plugin deactivate mail-chronicle
wp plugin activate mail-chronicle
```

### Emails Not Logging?

1. Check **Settings** → **Enable Logging** is checked ✅
2. Verify database tables exist:
   ```sql
   SHOW TABLES LIKE 'wp_mail_chronicle%';
   ```
3. Check WordPress debug log for errors

### Mailgun Not Connecting?

1. Verify API key and domain are correct
2. Check region setting (US vs EU)
3. Test connection:
   ```bash
   curl -X GET "https://api.mailgun.net/v3/YOUR_DOMAIN" \
     -u "api:YOUR_API_KEY"
   ```

## 📚 Next Steps

- Read the full [README.md](README.md)
- Review [INSTALLATION.md](INSTALLATION.md) for detailed setup
- Check [CHANGELOG.md](CHANGELOG.md) for updates
- Explore the REST API documentation

## 💡 Pro Tips

1. **Use Filters**: Combine multiple filters for precise searches
2. **Export Data**: Use REST API to export logs programmatically
3. **Monitor Trends**: Track email delivery rates over time
4. **Set Alerts**: Integrate with monitoring tools for failed emails
5. **Test Regularly**: Send test emails to verify configuration

## 🆘 Need Help?

- Check the documentation files in this directory
- Review WordPress error logs
- Contact your system administrator

---

**Happy Email Logging! 📬**

