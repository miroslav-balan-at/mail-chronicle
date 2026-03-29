<?php
/**
 * Settings page template.
 *
 * Loaded via load_template(); all variables are available as $args['key'].
 *
 * @var array{
 *   active_tab:        string,
 *   settings:          array<string, mixed>,
 *   settings_url:      string,
 *   providers:         \MailChronicle\Common\Entities\Email_Provider[],
 *   regions:           \MailChronicle\Common\Entities\Mailgun_Region[],
 *   sync_intervals:    array<int|string, string>,
 *   sync_days_options: array<int, string>,
 *   retention_options: array<int, string>,
 *   default_retention: int,
 *   default_sync_days: int,
 * } $args
 *
 * @package MailChronicle
 */

defined( 'ABSPATH' ) || exit;

$active_tab        = $args['active_tab'];
$settings          = $args['settings'];
$settings_url      = $args['settings_url'];
$providers         = $args['providers'];
$regions           = $args['regions'];
$sync_intervals    = $args['sync_intervals'];
$sync_days_options = $args['sync_days_options'];
$retention_options = $args['retention_options'];
$default_retention = $args['default_retention'];
$default_sync_days = $args['default_sync_days'];
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php settings_errors( 'mail_chronicle_settings' ); ?>

	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'general', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General', 'mail-chronicle' ); ?>
		</a>
		<a href="<?php echo esc_url( add_query_arg( 'tab', 'maintenance', $settings_url ) ); ?>"
			class="nav-tab <?php echo 'maintenance' === $active_tab ? 'nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'Maintenance', 'mail-chronicle' ); ?>
		</a>
	</nav>

	<?php if ( 'general' === $active_tab ) : ?>

		<form method="post" action="">
			<?php wp_nonce_field( 'mail_chronicle_settings', 'mail_chronicle_settings_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="enabled"><?php esc_html_e( 'Enable Logging', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<input type="checkbox" name="enabled" id="enabled" value="1" <?php checked( isset( $settings['enabled'] ) && (bool) $settings['enabled'] ); ?>>
						<p class="description"><?php esc_html_e( 'Enable email logging', 'mail-chronicle' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="provider"><?php esc_html_e( 'Email Provider', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<select name="provider" id="provider">
							<?php foreach ( $providers as $provider ) : ?>
								<option value="<?php echo esc_attr( $provider->value ); ?>" <?php selected( $settings['provider'], $provider->value ); ?>>
									<?php echo esc_html( $provider->label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="mailgun-settings">
					<th scope="row">
						<label for="mailgun_api_key"><?php esc_html_e( 'Mailgun API Key', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<input type="password" name="mailgun_api_key" id="mailgun_api_key"
							value="<?php echo esc_attr( is_string( $settings['mailgun_api_key'] ?? null ) ? $settings['mailgun_api_key'] : '' ); ?>"
							class="regular-text">
					</td>
				</tr>

				<tr class="mailgun-settings">
					<th scope="row">
						<label for="mailgun_domain"><?php esc_html_e( 'Mailgun Domain', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<input type="text" name="mailgun_domain" id="mailgun_domain"
							value="<?php echo esc_attr( is_string( $settings['mailgun_domain'] ?? null ) ? $settings['mailgun_domain'] : '' ); ?>"
							class="regular-text">
					</td>
				</tr>

				<tr class="mailgun-settings">
					<th scope="row">
						<label for="mailgun_region"><?php esc_html_e( 'Mailgun Region', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<select name="mailgun_region" id="mailgun_region">
							<?php foreach ( $regions as $region ) : ?>
								<option value="<?php echo esc_attr( $region->value ); ?>" <?php selected( $settings['mailgun_region'], $region->value ); ?>>
									<?php echo esc_html( $region->label() ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>

				<tr class="mailgun-settings">
					<th scope="row">
						<label for="sync_interval"><?php esc_html_e( 'Auto-Sync Interval', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<select name="sync_interval" id="sync_interval">
							<?php foreach ( $sync_intervals as $interval_key => $interval_label ) : ?>
								<option value="<?php echo esc_attr( (string) $interval_key ); ?>" <?php selected( $settings['sync_interval'] ?? 'disabled', $interval_key ); ?>>
									<?php echo esc_html( $interval_label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description">
							<?php esc_html_e( 'Automatically pull delivery events from Mailgun on a schedule. Requires at least one page visit per interval to fire — for reliable short intervals, point a real server cron at wp-cron.php.', 'mail-chronicle' ); ?>
						</p>
					</td>
				</tr>

				<tr class="mailgun-settings">
					<th scope="row">
						<label for="sync_days"><?php esc_html_e( 'Sync Look-back Window', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<select name="sync_days" id="sync_days">
							<?php foreach ( $sync_days_options as $days => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( is_numeric( $settings['sync_days'] ?? null ) ? (int) $settings['sync_days'] : $default_sync_days, $days ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'How far back to look when the "Sync" button is clicked or the background job runs.', 'mail-chronicle' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="default_domain"><?php esc_html_e( 'Default Domain Filter', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<input type="text" name="default_domain" id="default_domain"
							value="<?php echo esc_attr( is_string( $settings['default_domain'] ?? null ) ? $settings['default_domain'] : '' ); ?>"
							class="regular-text"
							placeholder="<?php esc_attr_e( 'e.g. example.com', 'mail-chronicle' ); ?>">
						<p class="description"><?php esc_html_e( 'The email logs page will default to showing only emails where the recipient matches this domain. Leave empty to show all emails by default.', 'mail-chronicle' ); ?></p>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="log_retention_days"><?php esc_html_e( 'Log Retention', 'mail-chronicle' ); ?></label>
					</th>
					<td>
						<select name="log_retention_days" id="log_retention_days">
							<?php foreach ( $retention_options as $days => $label ) : ?>
								<option value="<?php echo esc_attr( (string) $days ); ?>" <?php selected( is_numeric( $settings['log_retention_days'] ?? null ) ? (int) $settings['log_retention_days'] : $default_retention, $days ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php esc_html_e( 'Older logs are automatically removed on a daily schedule.', 'mail-chronicle' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>

		<div id="mc-webhook-instructions">

			<div class="mc-webhook-section mc-webhook-WordPress" style="display:none;">
				<div class="mc-setup-card mc-setup-card--info">
					<div class="mc-setup-card__icon">&#8505;</div>
					<div>
						<strong><?php esc_html_e( 'No webhook setup required', 'mail-chronicle' ); ?></strong>
						<p><?php esc_html_e( 'The WordPress provider logs emails at send time. Delivery status updates (delivered, opened, bounced) are not available without a transactional email provider.', 'mail-chronicle' ); ?></p>
					</div>
				</div>
			</div>

			<div class="mc-webhook-section mc-webhook-Mailgun" style="display:none;">
				<div class="mc-setup-header">
					<span class="mc-setup-header__badge">Mailgun</span>
					<h2><?php esc_html_e( 'Webhook Setup', 'mail-chronicle' ); ?></h2>
					<p><?php esc_html_e( 'Follow these steps to receive real-time delivery status updates in your email logs.', 'mail-chronicle' ); ?></p>
				</div>

				<div class="mc-steps">

					<div class="mc-step">
						<div class="mc-step__number">1</div>
						<div class="mc-step__body">
							<h3><?php esc_html_e( 'Get your Webhook Signing Key', 'mail-chronicle' ); ?></h3>
							<p><?php esc_html_e( 'In Mailgun, go to Sending → Webhooks. Click the eye icon next to "HTTP webhook signing key" to reveal it, then copy and paste it into the Mailgun API Key field above and save.', 'mail-chronicle' ); ?></p>
							<div class="mc-setup-card mc-setup-card--warning">
								<span class="mc-setup-card__icon">&#9888;</span>
								<span><?php esc_html_e( 'The Webhook Signing Key is separate from your regular Mailgun API key — it is only found on the Webhooks page.', 'mail-chronicle' ); ?></span>
							</div>
						</div>
					</div>

					<div class="mc-step">
						<div class="mc-step__number">2</div>
						<div class="mc-step__body">
							<h3><?php esc_html_e( 'Register the Webhook URL', 'mail-chronicle' ); ?></h3>
							<p><?php esc_html_e( 'On the Mailgun Webhooks page, click "Add Webhook" and set the URL below.', 'mail-chronicle' ); ?></p>

							<div class="mc-url-row">
								<code class="mc-url-code"><?php echo esc_html( rest_url( 'mail-chronicle/v1/webhook/mailgun' ) ); ?></code>
								<button type="button" class="mc-copy-btn"
										data-clipboard-text="<?php echo esc_attr( rest_url( 'mail-chronicle/v1/webhook/mailgun' ) ); ?>">
									<span class="mc-copy-btn__icon">&#10697;</span>
									<span class="mc-copy-btn__label"><?php esc_html_e( 'Copy', 'mail-chronicle' ); ?></span>
								</button>
							</div>

							<p style="margin-top:12px;"><?php esc_html_e( 'Enable the following event types:', 'mail-chronicle' ); ?></p>
							<div class="mc-event-tags">
								<span class="mc-tag">accepted</span>
								<span class="mc-tag">delivered</span>
								<span class="mc-tag">failed</span>
								<span class="mc-tag">opened</span>
								<span class="mc-tag">clicked</span>
								<span class="mc-tag">unsubscribed</span>
								<span class="mc-tag">complained</span>
							</div>
						</div>
					</div>

					<div class="mc-step">
						<div class="mc-step__number">3</div>
						<div class="mc-step__body">
							<h3><?php esc_html_e( 'Test the Webhook', 'mail-chronicle' ); ?></h3>
							<p><?php esc_html_e( 'Back on the Webhooks page, click the test (play) icon next to your webhook. If Mail Chronicle receives it correctly the email status in your logs will update automatically.', 'mail-chronicle' ); ?></p>
						</div>
					</div>

				</div>
			</div>

			<div class="mc-webhook-section mc-webhook-SendGrid" style="display:none;">
				<div class="mc-setup-card mc-setup-card--info">
					<div class="mc-setup-card__icon">&#128679;</div>
					<div>
						<strong><?php esc_html_e( 'SendGrid — coming soon', 'mail-chronicle' ); ?></strong>
						<p><?php esc_html_e( 'Webhook configuration instructions will appear here once SendGrid support is available.', 'mail-chronicle' ); ?></p>
					</div>
				</div>
			</div>

		</div>

	<?php elseif ( 'maintenance' === $active_tab ) : ?>

		<div class="mc-maintenance">
			<h2><?php esc_html_e( 'Danger Zone', 'mail-chronicle' ); ?></h2>

			<div class="mc-danger-box">
				<h3><?php esc_html_e( 'Delete All Email Logs', 'mail-chronicle' ); ?></h3>
				<p><?php esc_html_e( 'Permanently delete every email log and all associated delivery events. This cannot be undone.', 'mail-chronicle' ); ?></p>

				<form method="post" action=""
						onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete ALL email logs? This cannot be undone.', 'mail-chronicle' ) ); ?>')">
					<?php wp_nonce_field( 'mail_chronicle_delete_all', 'mail_chronicle_delete_all_nonce' ); ?>
					<input type="submit"
							class="button button-danger"
							value="<?php esc_attr_e( 'Delete All Logs', 'mail-chronicle' ); ?>">
				</form>
			</div>
		</div>

	<?php endif; ?>
</div>
