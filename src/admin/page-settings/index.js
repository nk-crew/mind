/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
// eslint-disable-next-line import/no-extraneous-dependencies
import { isEqual } from 'lodash';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';

export default function PageSettings() {
	const [pendingSettings, setPendingSettings] = useState({});
	const [settingsChanged, setSettingsChanged] = useState(false);

	const { updateSettings } = useDispatch('mind/settings');

	const { settings, updating, error } = useSelect((select) => {
		const settingsSelect = select('mind/settings');

		return {
			settings: settingsSelect.getSettings(),
			updating: settingsSelect.getUpdating(),
			error: settingsSelect.getError(),
		};
	});

	// Update pending settings from actual settings object.
	useEffect(() => {
		setPendingSettings(settings);
	}, [settings]);

	// Check if settings changed.
	useEffect(() => {
		setSettingsChanged(!isEqual(settings, pendingSettings));
	}, [settings, pendingSettings]);

	return (
		<>
			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-card-name">
					<label htmlFor="mind-settings-openai-api-key">
						{__('OpenAI API Key', 'mind')}
					</label>
					<div className="mind-admin-settings-card-description">
						{__(
							'This setting is required, since our plugin works with OpenAI.',
							'mind'
						)}{' '}
						<a
							href="https://platform.openai.com/account/api-keys"
							target="_blank"
							rel="noreferrer"
						>
							{__('Create API key', 'mind')}
						</a>
					</div>
				</div>
				<div className="mind-admin-settings-card-input">
					<input
						id="mind-settings-openai-api-key"
						type="text"
						placeholder={__('Enter API key', 'mind')}
						value={pendingSettings.openai_api_key || ''}
						onChange={(e) => {
							e.preventDefault();
							setPendingSettings({
								...pendingSettings,
								openai_api_key: e.target.value,
							});
						}}
					/>
				</div>
			</div>
			{error && <div className="mind-admin-settings-error">{error}</div>}
			<div className="mind-admin-settings-actions">
				<button
					disabled={!settingsChanged}
					onClick={(e) => {
						e.preventDefault();

						updateSettings(pendingSettings);
					}}
				>
					{__('Save Changes', 'mind')}
					{updating && <LoadingIcon viewBox="0 0 24 24" />}
				</button>
			</div>
		</>
	);
}
