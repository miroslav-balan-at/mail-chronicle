/**
 * Empty State Component
 * 
 * Displays a friendly message when no emails are found
 * Following WordPress and modern UI/UX best practices
 */
import { __ } from '@wordpress/i18n';
import { Button, Icon } from '@wordpress/components';
import { inbox } from '@wordpress/icons';
import apiFetch from '@wordpress/api-fetch';
import { useState, useEffect } from '@wordpress/element';

const EmptyState = ( { hasFilters, onClearFilters } ) => {
	const [ isProviderConnected, setIsProviderConnected ] = useState( true ); // Default to true (hidden)
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		setIsLoading( true );
		// Check if any email provider is connected
		apiFetch( { path: '/mail-chronicle/v1/settings' } )
			.then( ( settings ) => {
				// Check Mailgun (API key is masked as '***' if set)
				const hasMailgun = settings?.mailgun_api_key === '***' &&
				                   settings?.mailgun_domain &&
				                   settings.mailgun_domain.length > 0;

				// Check SendGrid (API key is masked as '***' if set)
				const hasSendGrid = settings?.sendgrid_api_key === '***';

				// Check any other provider
				const hasAnyProvider = hasMailgun || hasSendGrid;

				setIsProviderConnected( hasAnyProvider );
				setIsLoading( false );
			} )
			.catch( () => {
				setIsProviderConnected( false );
				setIsLoading( false );
			} );
	}, [] );

	// Don't show anything while loading
	if ( isLoading ) {
		return null;
	}

	// Different messages based on whether filters are active
	if ( hasFilters ) {
		return (
			<div className="mail-chronicle-empty-state">
				<div className="empty-state-icon">
					<Icon icon={ inbox } size={ 80 } />
				</div>
				<h2 className="empty-state-title">
					{ __( 'No emails match your filters', 'mail-chronicle' ) }
				</h2>
				<p className="empty-state-description">
					{ __( 'Try adjusting your search criteria or filters to find what you\'re looking for.', 'mail-chronicle' ) }
				</p>
				<div className="empty-state-actions">
					<Button
						variant="primary"
						onClick={ onClearFilters }
					>
						{ __( 'Clear All Filters', 'mail-chronicle' ) }
					</Button>
				</div>
			</div>
		);
	}

	// No emails at all - show simple message if provider is connected
	if ( isProviderConnected ) {
		return (
			<div className="mail-chronicle-empty-state">
				<div className="empty-state-icon">
					<Icon icon={ inbox } size={ 80 } />
				</div>
				<h2 className="empty-state-title">
					{ __( 'No emails logged yet', 'mail-chronicle' ) }
				</h2>
				<p className="empty-state-description">
					{ __( 'Send an email from WordPress or click "Sync from Mailgun" to import existing emails.', 'mail-chronicle' ) }
				</p>
			</div>
		);
	}

	// No emails at all - first time use (show setup instructions)
	return (
		<div className="mail-chronicle-empty-state">
			<div className="empty-state-icon">
				<Icon icon={ inbox } size={ 80 } />
			</div>
			<h2 className="empty-state-title">
				{ __( 'No emails logged yet', 'mail-chronicle' ) }
			</h2>
			<p className="empty-state-description">
				{ __( 'Mail Chronicle will automatically log all outgoing emails from your WordPress site.', 'mail-chronicle' ) }
			</p>

			<div className="empty-state-steps">
				<div className="empty-state-step">
					<div className="step-number">1</div>
					<div className="step-content">
						<h3>{ __( 'Enable Logging', 'mail-chronicle' ) }</h3>
						<p>{ __( 'Make sure email logging is enabled in the plugin settings.', 'mail-chronicle' ) }</p>
					</div>
				</div>
				
				<div className="empty-state-step">
					<div className="step-number">2</div>
					<div className="step-content">
						<h3>{ __( 'Send a Test Email', 'mail-chronicle' ) }</h3>
						<p>{ __( 'Trigger any WordPress email (password reset, comment notification, etc.) to see it logged here.', 'mail-chronicle' ) }</p>
					</div>
				</div>
				
				<div className="empty-state-step">
					<div className="step-number">3</div>
					<div className="step-content">
						<h3>{ __( 'Configure Provider (Optional)', 'mail-chronicle' ) }</h3>
						<p>{ __( 'Connect Mailgun for advanced tracking like delivery status, opens, and clicks.', 'mail-chronicle' ) }</p>
					</div>
				</div>
			</div>

			<div className="empty-state-actions">
				<Button
					variant="primary"
					href="?page=mail-chronicle-settings"
				>
					{ __( 'Go to Settings', 'mail-chronicle' ) }
				</Button>
				<Button
					variant="secondary"
					href="https://wordpress.org/support/article/resetting-your-password/"
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __( 'Send Test Email', 'mail-chronicle' ) }
				</Button>
			</div>

			<div className="empty-state-help">
				<p>
					<strong>{ __( 'Need help?', 'mail-chronicle' ) }</strong>
					{' '}
					{ __( 'Check out the', 'mail-chronicle' ) }
					{' '}
					<a href="?page=mail-chronicle-docs" target="_blank" rel="noopener noreferrer">
						{ __( 'documentation', 'mail-chronicle' ) }
					</a>
					{' '}
					{ __( 'or', 'mail-chronicle' ) }
					{' '}
					<a href="https://github.com/your-repo/mail-chronicle/issues" target="_blank" rel="noopener noreferrer">
						{ __( 'report an issue', 'mail-chronicle' ) }
					</a>
					.
				</p>
			</div>
		</div>
	);
};

export default EmptyState;

