/**
 * Email Logs Table Component
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import {
	Button,
	Modal,
} from '@wordpress/components';
import EmailDetailModal from './EmailDetailModal';
import EmptyState from './EmptyState';

const EmailLogsTable = ( {
	emails,
	total,
	totalPages,
	currentPage,
	perPage,
	onPageChange,
	onDelete,
	onRefresh,
	onSync,
	syncing,
	filters,
	onClearFilters,
} ) => {
	const [ selectedEmail, setSelectedEmail ] = useState( null );
	const [ confirmDelete, setConfirmDelete ] = useState( null );

	const openModal = ( email ) => setSelectedEmail( email );
	const closeModal = () => setSelectedEmail( null );

	const requestDelete = ( email ) => setConfirmDelete( { id: email.id, subject: email.subject } );
	const cancelDelete = () => setConfirmDelete( null );

	const confirmAndDelete = () => {
		onDelete( confirmDelete.id );
		setConfirmDelete( null );
	};

	const extractEmail = ( value ) => {
		if ( ! value ) return '';
		const match = value.match( /<([^>]+)>/ );
		return match ? match[ 1 ] : value.trim();
	};

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

	const serverLabels = window.mailChronicle?.statusLabels ?? {};
	const STATUS_LABELS = {
		pending:    __( 'Pending', 'mail-chronicle' ),
		sent:       __( 'Sent', 'mail-chronicle' ),
		delivered:  __( 'Delivered', 'mail-chronicle' ),
		opened:     __( 'Opened', 'mail-chronicle' ),
		clicked:    __( 'Clicked', 'mail-chronicle' ),
		failed:     __( 'Failed', 'mail-chronicle' ),
		bounced:    __( 'Bounced', 'mail-chronicle' ),
		complained: __( 'Complained', 'mail-chronicle' ),
		...serverLabels,
	};

	const getStatusBadge = ( status ) => (
		<span className={ `mc-status-badge mc-status-badge--${ status }` }>
			{ STATUS_LABELS[ status ] || status }
		</span>
	);

	const hasActiveFilters = filters && (
		filters.status ||
		filters.provider ||
		filters.search ||
		filters.date_from ||
		filters.date_to
	);

	return (
		<div className="mc-table-wrapper">

			{ /* ── Toolbar ── */ }
			<div className="mc-toolbar">
				<p className="mc-toolbar__count">
					{ total > 0
						? `${ total } ${ __( 'emails', 'mail-chronicle' ) }`
						: __( 'No emails', 'mail-chronicle' )
					}
				</p>
				<div className="mc-toolbar__actions">
					<Button variant="primary" onClick={ onSync } isBusy={ syncing } disabled={ syncing }>
						{ __( 'Sync Latest', 'mail-chronicle' ) }
					</Button>
					<Button variant="secondary" onClick={ onRefresh }>
						{ __( 'Refresh', 'mail-chronicle' ) }
					</Button>
				</div>
			</div>

			{ /* ── Table or empty state ── */ }
			{ emails.length === 0 ? (
				<EmptyState hasFilters={ hasActiveFilters } onClearFilters={ onClearFilters } />
			) : (
				<>
					<table className="wp-list-table widefat striped" style={ { tableLayout: 'auto' } }>
						<thead>
							<tr>
								<th scope="col" style={ { width: '15%' } }>{ __( 'From', 'mail-chronicle' ) }</th>
								<th scope="col" style={ { width: '15%' } }>{ __( 'To', 'mail-chronicle' ) }</th>
								<th scope="col">{ __( 'Subject', 'mail-chronicle' ) }</th>
								<th scope="col" style={ { width: '10%' } }>{ __( 'Status', 'mail-chronicle' ) }</th>
								<th scope="col" style={ { width: '13%' } }>{ __( 'Sent At', 'mail-chronicle' ) }</th>
								<th scope="col" style={ { width: '7%' } }>{ __( 'Actions', 'mail-chronicle' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ emails.map( ( email ) => (
								<tr key={ email.id }>
									<td>
										<span className="mc-cell-truncate" title={ extractEmail( email.sender ) }>
											{ extractEmail( email.sender ) || '—' }
										</span>
									</td>
									<td>
										<span className="mc-cell-truncate" title={ extractEmail( email.recipient ) }>
											{ extractEmail( email.recipient ) }
										</span>
									</td>
									<td>
										<Button
											variant="link"
											onClick={ () => openModal( email ) }
											className="mc-subject-link"
										>
											{ email.subject || __( '(no subject)', 'mail-chronicle' ) }
										</Button>
									</td>
									<td>{ getStatusBadge( email.status ) }</td>
									<td>{ formatDate( email.sent_at ) }</td>
									<td>
										<Button
											variant="link"
											isDestructive
											onClick={ () => requestDelete( email ) }
										>
											{ __( 'Delete', 'mail-chronicle' ) }
										</Button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ /* ── Pagination ── */ }
					{ totalPages > 1 && (
						<div className="tablenav bottom">
							<div className="tablenav-pages">
								<span className="displaying-num">
									{ total } { __( 'items', 'mail-chronicle' ) }
								</span>
								<span className="pagination-links">
									<Button
										className="first-page button"
										disabled={ currentPage === 1 }
										onClick={ () => onPageChange( 1 ) }
										label={ __( 'First page', 'mail-chronicle' ) }
									>
										<span aria-hidden="true">«</span>
									</Button>
									<Button
										className="prev-page button"
										disabled={ currentPage === 1 }
										onClick={ () => onPageChange( currentPage - 1 ) }
										label={ __( 'Previous page', 'mail-chronicle' ) }
									>
										<span aria-hidden="true">‹</span>
									</Button>
									<span className="paging-input">
										<span className="tablenav-paging-text">
											{ currentPage } { __( 'of', 'mail-chronicle' ) }{ ' ' }
											<span className="total-pages">{ totalPages }</span>
										</span>
									</span>
									<Button
										className="next-page button"
										disabled={ currentPage === totalPages }
										onClick={ () => onPageChange( currentPage + 1 ) }
										label={ __( 'Next page', 'mail-chronicle' ) }
									>
										<span aria-hidden="true">›</span>
									</Button>
									<Button
										className="last-page button"
										disabled={ currentPage === totalPages }
										onClick={ () => onPageChange( totalPages ) }
										label={ __( 'Last page', 'mail-chronicle' ) }
									>
										<span aria-hidden="true">»</span>
									</Button>
								</span>
							</div>
						</div>
					) }
				</>
			) }

			{ /* ── Email detail modal ── */ }
			{ selectedEmail && (
				<EmailDetailModal email={ selectedEmail } onClose={ closeModal } />
			) }

			{ /* ── Delete confirmation modal ── */ }
			{ confirmDelete && (
				<Modal
					title={ __( 'Delete email?', 'mail-chronicle' ) }
					onRequestClose={ cancelDelete }
					size="small"
					className="mc-confirm-modal"
				>
					<p>
						{ __( 'This will permanently delete the email log. This action cannot be undone.', 'mail-chronicle' ) }
					</p>
					{ confirmDelete.subject && (
						<p className="mc-confirm-modal__subject">
							<strong>{ confirmDelete.subject }</strong>
						</p>
					) }
					<div className="mc-confirm-modal__actions">
						<Button variant="primary" isDestructive onClick={ confirmAndDelete }>
							{ __( 'Delete', 'mail-chronicle' ) }
						</Button>
						<Button variant="secondary" onClick={ cancelDelete }>
							{ __( 'Cancel', 'mail-chronicle' ) }
						</Button>
					</div>
				</Modal>
			) }
		</div>
	);
};

export default EmailLogsTable;
