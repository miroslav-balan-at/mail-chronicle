/**
 * Email Detail Modal Component
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Modal,
	TabPanel,
	Spinner,
	Notice,
} from '@wordpress/components';

const EmailDetailModal = ( { email, onClose } ) => {
	const [ events, setEvents ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( null );

		apiFetch( { path: `/mail-chronicle/v1/emails/${ email.id }/events` } )
			.then( ( data ) => { if ( ! cancelled ) setEvents( data ); } )
			.catch( ( err ) => { if ( ! cancelled ) setError( err.message || __( 'Failed to fetch events', 'mail-chronicle' ) ); } )
			.finally( () => { if ( ! cancelled ) setLoading( false ); } );

		return () => { cancelled = true; };
	}, [ email.id ] );

	const formatDate = ( dateString ) => {
		const date = new Date( dateString + 'Z' );
		return date.toLocaleString( undefined, {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: '2-digit',
			minute: '2-digit',
		} );
	};

	const STATUS_LABELS = {
		pending:    __( 'Pending', 'mail-chronicle' ),
		sent:       __( 'Sent', 'mail-chronicle' ),
		delivered:  __( 'Delivered', 'mail-chronicle' ),
		opened:     __( 'Opened', 'mail-chronicle' ),
		clicked:    __( 'Clicked', 'mail-chronicle' ),
		failed:     __( 'Failed', 'mail-chronicle' ),
		bounced:    __( 'Bounced', 'mail-chronicle' ),
		complained: __( 'Complained', 'mail-chronicle' ),
	};

	const tabs = [
		{ name: 'details', title: __( 'Details', 'mail-chronicle' ) },
		{ name: 'content', title: __( 'Content', 'mail-chronicle' ) },
		{ name: 'events',  title: __( 'Events', 'mail-chronicle' ) },
	];

	return (
		<Modal
			title={ email.subject || __( '(no subject)', 'mail-chronicle' ) }
			onRequestClose={ onClose }
			size="large"
			className="mc-detail-modal"
		>
			<TabPanel tabs={ tabs }>
				{ ( tab ) => {
					if ( tab.name === 'details' ) {
						return (
							<div className="mc-detail-modal__tab">
								<dl className="mc-detail-list">
									<div className="mc-detail-list__row">
										<dt>{ __( 'Recipient', 'mail-chronicle' ) }</dt>
										<dd>{ email.recipient }</dd>
									</div>
									<div className="mc-detail-list__row">
										<dt>{ __( 'Status', 'mail-chronicle' ) }</dt>
										<dd>
											<span className={ `mc-status-badge mc-status-badge--${ email.status }` }>
												{ STATUS_LABELS[ email.status ] || email.status }
											</span>
										</dd>
									</div>
									<div className="mc-detail-list__row">
										<dt>{ __( 'Provider', 'mail-chronicle' ) }</dt>
										<dd>{ email.provider }</dd>
									</div>
									{ email.provider_message_id && (
										<div className="mc-detail-list__row">
											<dt>{ __( 'Message ID', 'mail-chronicle' ) }</dt>
											<dd className="mc-detail-list__mono">{ email.provider_message_id }</dd>
										</div>
									) }
									<div className="mc-detail-list__row">
										<dt>{ __( 'Sent At', 'mail-chronicle' ) }</dt>
										<dd>{ formatDate( email.sent_at ) }</dd>
									</div>
									{ email.headers && (
										<div className="mc-detail-list__row mc-detail-list__row--full">
											<dt>{ __( 'Headers', 'mail-chronicle' ) }</dt>
											<dd>
												<pre className="mc-detail-list__pre">{ email.headers }</pre>
											</dd>
										</div>
									) }
									{ email.attachments && (
										<div className="mc-detail-list__row">
											<dt>{ __( 'Attachments', 'mail-chronicle' ) }</dt>
											<dd>{ email.attachments }</dd>
										</div>
									) }
								</dl>
							</div>
						);
					}

					if ( tab.name === 'content' ) {
						const hasHtml  = !! email.message_html;
						const hasPlain = !! email.message_plain;

						if ( ! hasHtml && ! hasPlain ) {
							return (
								<div className="mc-detail-modal__tab mc-detail-modal__empty">
									<p>{ __( 'No message content available.', 'mail-chronicle' ) }</p>
									<p className="description">
										{ __( 'Message content is only available when Mailgun message storage is enabled.', 'mail-chronicle' ) }
									</p>
								</div>
							);
						}

						return (
							<div className="mc-detail-modal__tab">
								{ hasHtml && (
									<>
										<h3 className="mc-detail-modal__section-title">
											{ __( 'HTML', 'mail-chronicle' ) }
										</h3>
										<div
											className="mc-email-html"
											// eslint-disable-next-line react/no-danger
											dangerouslySetInnerHTML={ { __html: email.message_html } }
										/>
									</>
								) }
								{ hasPlain && (
									<>
										<h3 className="mc-detail-modal__section-title">
											{ __( 'Plain text', 'mail-chronicle' ) }
										</h3>
										<pre className="mc-email-plain">{ email.message_plain }</pre>
									</>
								) }
							</div>
						);
					}

					if ( tab.name === 'events' ) {
						return (
							<div className="mc-detail-modal__tab">
								{ loading && (
									<div className="mc-detail-modal__loading">
										<Spinner />
									</div>
								) }
								{ ! loading && error && (
									<Notice status="error" isDismissible={ false }>{ error }</Notice>
								) }
								{ ! loading && ! error && events.length === 0 && (
									<p className="mc-detail-modal__empty-text">
										{ __( 'No delivery events recorded yet.', 'mail-chronicle' ) }
									</p>
								) }
								{ ! loading && ! error && events.length > 0 && (
									<table className="wp-list-table widefat fixed striped">
										<thead>
											<tr>
												<th scope="col">{ __( 'Event', 'mail-chronicle' ) }</th>
												<th scope="col">{ __( 'Occurred At', 'mail-chronicle' ) }</th>
											</tr>
										</thead>
										<tbody>
											{ events.map( ( event ) => (
												<tr key={ event.id }>
													<td>
														<span className={ `mc-status-badge mc-status-badge--${ event.event_type }` }>
															{ STATUS_LABELS[ event.event_type ] || event.event_type }
														</span>
													</td>
													<td>{ formatDate( event.occurred_at ) }</td>
												</tr>
											) ) }
										</tbody>
									</table>
								) }
							</div>
						);
					}

					return null;
				} }
			</TabPanel>
		</Modal>
	);
};

export default EmailDetailModal;
