/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

export function updateSettings(settings) {
	return ({ dispatch }) => {
		const persistedSettings = {};

		if (settings?.ai_provider !== undefined) {
			persistedSettings.ai_provider = settings.ai_provider;
		}

		if (settings?.ai_model !== undefined) {
			persistedSettings.ai_model = settings.ai_model;
		}

		if (!Object.keys(persistedSettings).length) {
			return;
		}

		dispatch({ type: 'UPDATE_SETTINGS_PENDING' });

		const data = { settings: persistedSettings };

		apiFetch({
			path: '/mind/v1/update_settings',
			method: 'POST',
			data,
		})
			.then((res) => {
				dispatch({
					type: 'UPDATE_SETTINGS_SUCCESS',
					settings: persistedSettings,
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
