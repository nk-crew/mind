/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import PageWelcome from '../page-welcome';
import PageSettings from '../page-settings';

export default {
	welcome: {
		label: __('Welcome', 'mind'),
		block: PageWelcome,
	},
	settings: {
		label: __('Settings', 'mind'),
		block: PageSettings,
	},
};
