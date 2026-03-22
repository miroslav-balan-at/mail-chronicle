=== Mail Chronicle ===
Contributors: miroslavbalan
Tags: email, logging, mailgun, sendgrid, smtp
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Professional email logging with multi-provider support (Mailgun, SendGrid, etc.), event tracking, and comprehensive admin interface.

== Description ==

Mail Chronicle is a professional WordPress plugin that automatically logs all outgoing emails from your WordPress site. It provides comprehensive tracking, beautiful admin interface, and multi-provider support for advanced email analytics.

= Key Features =

* **Automatic Email Logging** - Captures all outgoing WordPress emails automatically
* **Multi-Provider Support** - Works with WordPress default, Mailgun, SendGrid, and more
* **Event Tracking** - Track delivery status, opens, clicks, bounces, and complaints (with Mailgun)
* **Beautiful Admin Interface** - Modern React-based UI with filtering, search, and pagination
* **Email Details** - View full email content, headers, attachments, and delivery events
* **Webhook Support** - Real-time event updates from Mailgun webhooks
* **Search & Filter** - Find emails by recipient, subject, status, provider, or date
* **Bulk Actions** - Delete multiple emails at once
* **Translation Ready** - Fully internationalized with German translation included
* **Developer Friendly** - Clean code, comprehensive tests, and extensible architecture

= Perfect For =

* Debugging email delivery issues
* Monitoring transactional emails
* Compliance and record-keeping
* Customer support teams
* E-commerce sites
* Membership sites
* Any WordPress site that sends emails

= Technical Highlights =

* Built with Vertical Slice Architecture for maintainability
* React-based admin interface
* RESTful API
* Comprehensive PHPUnit test coverage (39 tests, 100% passing)
* WordPress coding standards compliant
* Secure and performant

== Installation ==

1. Upload the `mail-chronicle` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **Mail Chronicle → Settings** to configure the plugin
4. Enable email logging and optionally configure your email provider

= Mailgun Configuration (Optional) =

1. Sign up for a Mailgun account at https://www.mailgun.com
2. Get your API key from the Mailgun dashboard
3. Enter your API key in **Mail Chronicle → Settings**
4. Configure webhooks in Mailgun to point to your site

== Frequently Asked Questions ==

= Does this plugin send emails? =

No, Mail Chronicle only logs emails that are sent by WordPress or other plugins. It doesn't send emails itself.

= Will this slow down my site? =

No, email logging happens asynchronously and has minimal performance impact.

= Can I delete old logs? =

Yes, you can manually delete individual emails or configure automatic log retention in settings.

= Does it work with SMTP plugins? =

Yes, Mail Chronicle works with any SMTP plugin or email service that uses WordPress's `wp_mail()` function.

= Is it GDPR compliant? =

Mail Chronicle stores email data locally in your WordPress database. You're responsible for ensuring compliance with your local privacy laws.

= Can I export email logs? =

Currently, you can view and delete emails through the admin interface. Export functionality may be added in future versions.

== Screenshots ==

1. Email Logs - Beautiful admin interface with filtering and search
2. Email Details - View full email content, headers, and events
3. Settings Page - Configure logging and provider settings
4. Empty State - Helpful onboarding for first-time users
5. Event Tracking - Track delivery, opens, clicks, and more (Mailgun)

== Changelog ==

= 1.0.0 - 2026-03-19 =
* Initial release
* Automatic email logging for all WordPress emails
* Multi-provider support (WordPress, Mailgun, SendGrid)
* Event tracking with Mailgun webhooks
* Beautiful React-based admin interface
* Search and filter functionality
* Email detail modal with full content view
* Settings page with provider configuration
* Translation support (German included)
* Comprehensive test coverage (39 tests)
* Empty state with onboarding guide

== Upgrade Notice ==

= 1.0.0 =
Initial release of Mail Chronicle. Professional email logging for WordPress.

== Additional Information ==

= Support =

For support, please visit the [plugin support forum](https://wordpress.org/support/plugin/mail-chronicle/) or [GitHub repository](https://github.com/your-repo/mail-chronicle).

= Contributing =

We welcome contributions! Please visit our [GitHub repository](https://github.com/your-repo/mail-chronicle) to submit issues or pull requests.

= Privacy Policy =

Mail Chronicle stores email data in your WordPress database. This includes:
* Recipient email addresses
* Email subject and content
* Headers and attachments
* Delivery status and events

You are responsible for ensuring compliance with applicable privacy laws (GDPR, CCPA, etc.) when using this plugin.

== Credits ==

Developed by Miroslav Balan
Email: miroslav@balan.at

