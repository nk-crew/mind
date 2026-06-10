/**
 * Styles
 */
import './style.scss';

/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
// eslint-disable-next-line import/no-extraneous-dependencies
import { isEqual } from 'lodash';
import { useState, useEffect, useMemo } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';

function getModelOptions(aiOptions, providerId) {
	return aiOptions?.models?.[providerId] || aiOptions?.models?.[''] || [];
}

export default function PageSettings() {
	const [pendingSettings, setPendingSettings] = useState({});
	const [settingsChanged, setSettingsChanged] = useState(false);

	const { updateSettings } = useDispatch('mind/settings');
	const {
		connectorsPageURL,
		aiOptions = { providers: [], models: { '': [] } },
		setupState = {},
	} = window.mindAdminData;

	const { settings, updating, error } = useSelect((select) => {
		const settingsSelect = select('mind/settings');

		return {
			settings: settingsSelect.getSettings(),
			updating: settingsSelect.getUpdating(),
			error: settingsSelect.getError(),
		};
	});

	useEffect(() => {
		setPendingSettings({
			ai_provider: settings.ai_provider || '',
			ai_model: settings.ai_model || '',
		});
	}, [settings]);

	useEffect(() => {
		setSettingsChanged(!isEqual(settings, pendingSettings));
	}, [settings, pendingSettings]);

	const providerId = pendingSettings.ai_provider || '';
	const modelOptions = useMemo(
		() => getModelOptions(aiOptions, providerId),
		[aiOptions, providerId]
	);
	const hasConnectedProviders = (aiOptions.providers || []).length > 1;
	const needsProviderConnection = setupState.needsProviderConnection;
	const needsModelSelection = setupState.needsModelSelection;

	function onProviderChange(event) {
		const nextProvider = event.target.value;

		setPendingSettings({
			...pendingSettings,
			ai_provider: nextProvider,
			ai_model: '',
		});
	}

	function onModelChange(event) {
		setPendingSettings({
			...pendingSettings,
			ai_model: event.target.value,
		});
	}

	return (
		<>
			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-card-name">
					<label htmlFor="mind-settings-ai-provider">
						{__('Provider', 'mind')}
					</label>
				</div>
				<div className="mind-admin-settings-card-input">
					<select
						id="mind-settings-ai-provider"
						value={providerId}
						onChange={onProviderChange}
						disabled={!hasConnectedProviders}
					>
						{(aiOptions.providers || []).map((provider) => (
							<option
								key={provider.id || 'default'}
								value={provider.id}
							>
								{provider.title}
							</option>
						))}
					</select>
				</div>
			</div>

			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-card-name">
					<label htmlFor="mind-settings-ai-model">
						{__('Model', 'mind')}
					</label>
				</div>
				<div className="mind-admin-settings-card-input">
					<select
						id="mind-settings-ai-model"
						value={pendingSettings.ai_model || ''}
						onChange={onModelChange}
						disabled={!hasConnectedProviders}
					>
						{modelOptions.map((model) => (
							<option
								key={model.id || 'default'}
								value={model.id}
							>
								{model.title}
							</option>
						))}
					</select>
				</div>
			</div>

			{needsProviderConnection && (
				<div className="mind-admin-settings-notice mind-admin-settings-notice-warning">
					{__(
						'Connect an AI provider in WordPress Connectors before Mind can send requests.',
						'mind'
					)}
				</div>
			)}

			{needsModelSelection && (
				<div className="mind-admin-settings-notice mind-admin-settings-notice-warning">
					{__(
						'The selected provider and model are not available. Choose another option or switch back to Default.',
						'mind'
					)}
				</div>
			)}

			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-connector">
					<div
						className={clsx(
							'mind-admin-settings-connector-status',
							hasConnectedProviders &&
								'mind-admin-settings-connector-status-connected'
						)}
					>
						<span />
						{hasConnectedProviders
							? __(
									'At least one AI provider is connected.',
									'mind'
							  )
							: __('No AI providers are connected yet.', 'mind')}
					</div>
					<div className="mind-admin-settings-card-description">
						{__(
							'Mind works through WordPress Connectors. API keys are managed there and can be shared across plugins.',
							'mind'
						)}
					</div>
					<a
						className="mind-admin-settings-connector-link"
						href={connectorsPageURL}
					>
						{__('Manage in WordPress Connectors', 'mind')}
					</a>
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
