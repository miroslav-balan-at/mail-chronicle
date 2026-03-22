/**
 * Email Filters Component
 */
import { __ } from '@wordpress/i18n';
import {
	TextControl,
	SelectControl,
	Button,
	Flex,
	FlexItem,
} from '@wordpress/components';

const EmailFilters = ( { filters, onChange } ) => {
	const handleChange = ( key, value ) => {
		onChange( { [ key ]: value } );
	};

	const handleReset = () => {
		onChange( {
			status: '',
			provider: '',
			search: '',
			date_from: '',
			date_to: '',
		} );
	};

	return (
		<div className="email-filters">
			<Flex gap={ 4 } wrap>
				<FlexItem>
					<TextControl
						label={ __( 'Search', 'mail-chronicle' ) }
						value={ filters.search }
						onChange={ ( value ) => handleChange( 'search', value ) }
						placeholder={ __( 'Search recipient or subject...', 'mail-chronicle' ) }
					/>
				</FlexItem>

				<FlexItem>
					<SelectControl
						label={ __( 'Status', 'mail-chronicle' ) }
						value={ filters.status }
						onChange={ ( value ) => handleChange( 'status', value ) }
						options={ [
							{ label: __( 'All Statuses', 'mail-chronicle' ), value: '' },
							{ label: __( 'Pending', 'mail-chronicle' ), value: 'pending' },
							{ label: __( 'Sent', 'mail-chronicle' ), value: 'sent' },
							{ label: __( 'Delivered', 'mail-chronicle' ), value: 'delivered' },
							{ label: __( 'Opened', 'mail-chronicle' ), value: 'opened' },
							{ label: __( 'Clicked', 'mail-chronicle' ), value: 'clicked' },
							{ label: __( 'Failed', 'mail-chronicle' ), value: 'failed' },
							{ label: __( 'Bounced', 'mail-chronicle' ), value: 'bounced' },
						] }
					/>
				</FlexItem>

				<FlexItem>
					<SelectControl
						label={ __( 'Provider', 'mail-chronicle' ) }
						value={ filters.provider }
						onChange={ ( value ) => handleChange( 'provider', value ) }
						options={ [
							{ label: __( 'All Providers', 'mail-chronicle' ), value: '' },
							{ label: __( 'WordPress', 'mail-chronicle' ), value: 'wordpress' },
							{ label: __( 'Mailgun', 'mail-chronicle' ), value: 'mailgun' },
							{ label: __( 'SendGrid', 'mail-chronicle' ), value: 'sendgrid' },
						] }
					/>
				</FlexItem>

				<FlexItem>
					<TextControl
						label={ __( 'From Date', 'mail-chronicle' ) }
						type="date"
						value={ filters.date_from }
						onChange={ ( value ) => handleChange( 'date_from', value ) }
					/>
				</FlexItem>

				<FlexItem>
					<TextControl
						label={ __( 'To Date', 'mail-chronicle' ) }
						type="date"
						value={ filters.date_to }
						onChange={ ( value ) => handleChange( 'date_to', value ) }
					/>
				</FlexItem>

				<FlexItem>
					<Button
						variant="secondary"
						onClick={ handleReset }
						style={ { marginTop: '28px' } }
					>
						{ __( 'Reset Filters', 'mail-chronicle' ) }
					</Button>
				</FlexItem>
			</Flex>
		</div>
	);
};

export default EmailFilters;

