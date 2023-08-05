/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export function updateSettings(settings) {
	return ({ dispatch }) => {
		if (!settings || !Object.keys(settings).length) {
			return;
		}

		dispatch({ type: 'UPDATE_SETTINGS_PENDING' });

		const data = { settings };

		apiFetch({
			path: '/mind/v1/update_settings',
			method: 'POST',
			data,
		})
			.then((res) => {
				dispatch({
					type: 'UPDATE_SETTINGS_SUCCESS',
					settings,
				});
				return res.response;
			})
			.catch((err) => {
				dispatch({
					type: 'UPDATE_SETTINGS_ERROR',
					error:
						err?.response ||
						err?.error_code ||
						__('Something went wrong, please, try again…', 'mind'),
				});
			});
	};
}
