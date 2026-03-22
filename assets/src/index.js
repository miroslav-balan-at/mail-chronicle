/**
 * Mail Chronicle Admin App
 */
import { render } from '@wordpress/element';
import EmailLogsApp from './admin/email-logs/EmailLogsApp';
import './styles/admin.scss';

// Render Email Logs App
const emailLogsRoot = document.getElementById( 'mail-chronicle-email-logs-root' );
if ( emailLogsRoot ) {
	render( <EmailLogsApp />, emailLogsRoot );
}

