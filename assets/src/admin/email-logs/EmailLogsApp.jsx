/**
 * Email Logs App Component
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import {
	Card,
	CardBody,
	Spinner,
	Notice,
} from '@wordpress/components';
import EmailLogsTable from './components/EmailLogsTable';
import EmailFilters from './components/EmailFilters';

const siteDomain = window.mailChronicle?.siteDomain || '';

const defaultFilters = {
	page: 1,
	per_page: 20,
	orderby: 'sent_at',
	order: 'DESC',
	status: '',
	provider: '',
	search: '',
	date_from: '',
	date_to: '',
	domain: siteDomain,
};

const EmailLogsApp = () => {
	const [ emails, setEmails ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ syncing, setSyncing ] = useState( false );
	const [ error, setError ] = useState( null );
	const [ filters, setFilters ] = useState( defaultFilters );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 0 );

	const fetchEmails = async () => {
		setLoading( true );
		setError( null );

		try {
			const queryParams = new URLSearchParams( filters ).toString();
			const response = await apiFetch( {
				path: `/mail-chronicle/v1/emails?${ queryParams }`,
				parse: false,
			} );

			const data = await response.json();
			setEmails( data );
			setTotal( parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || 0 );
			setTotalPages( parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 0 );
		} catch ( err ) {
			setError( err.message || __( 'Failed to fetch emails', 'mail-chronicle' ) );
		} finally {
			setLoading( false );
		}
	};

	useEffect( () => {
		fetchEmails();
	}, [ filters ] );

	// Resume fetching pending bodies on page load.
	useEffect( () => {
		fetchPendingBodies();
	}, [] );

	const handleFilterChange = ( newFilters ) => {
		setFilters( ( prev ) => ( { ...prev, ...newFilters, page: 1 } ) );
	};

	const handlePageChange = ( newPage ) => {
		setFilters( ( prev ) => ( { ...prev, page: newPage } ) );
	};

	const handleClearFilters = () => {
		setFilters( defaultFilters );
	};

	const handleDelete = async ( id ) => {
		try {
			await apiFetch( {
				path: `/mail-chronicle/v1/emails/${ id }`,
				method: 'DELETE',
			} );
			fetchEmails();
		} catch ( err ) {
			setError( err.message || __( 'Failed to delete email', 'mail-chronicle' ) );
		}
	};

	const fetchPendingBodies = async () => {
		let done = false;
		while ( ! done ) {
			try {
				const result = await apiFetch( {
					path: '/mail-chronicle/v1/emails/fetch-body',
					method: 'POST',
				} );
				done = result.done;
			} catch {
				done = true;
			}
		}
	};

	const handleSync = async () => {
		setSyncing( true );
		setError( null );
		try {
			const response = await apiFetch( {
				path: '/mail-chronicle/v1/sync',
				method: 'POST',
			} );

			if ( response.success ) {
				fetchEmails();

				if ( response.data?.pending_bodies > 0 ) {
					fetchPendingBodies().then( fetchEmails );
				}
			}
		} catch ( err ) {
			setError( err.message || __( 'Sync failed', 'mail-chronicle' ) );
		} finally {
			setSyncing( false );
		}
	};

	return (
		<div className="mail-chronicle-email-logs">
			{ error && (
				<Notice status="error" isDismissible onRemove={ () => setError( null ) }>
					{ error }
				</Notice>
			) }

			<Card>
				<CardBody>
					<EmailFilters filters={ filters } onChange={ handleFilterChange } />
				</CardBody>
			</Card>

			<Card>
				<CardBody>
					{ loading ? (
						<div className="mail-chronicle-loading">
							<Spinner />
							<p>{ __( 'Loading emails…', 'mail-chronicle' ) }</p>
						</div>
					) : (
						<EmailLogsTable
							emails={ emails }
							total={ total }
							totalPages={ totalPages }
							currentPage={ filters.page }
							perPage={ filters.per_page }
							onPageChange={ handlePageChange }
							onDelete={ handleDelete }
							onRefresh={ fetchEmails }
							onSync={ handleSync }
							syncing={ syncing }
							filters={ filters }
							onClearFilters={ handleClearFilters }
						/>
					) }
				</CardBody>
			</Card>
		</div>
	);
};

export default EmailLogsApp;
