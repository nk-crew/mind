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
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import isValidOpenAIApiKey from '../../utils/is-valid-openai-api-key';
import isValidAnthropicApiKey from '../../utils/is-valid-anthropic-api-key';
import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';

const models = [
	{
		title: __('Claude 3.5 Sonnet', 'mind'),
		name: 'claude-3-5-sonnet',
		description: __('Best quality and recommended', 'mind'),
	},
	{
		title: __('Claude 3.5 Haiku', 'mind'),
		name: 'claude-3-5-haiku',
		description: __('Fast and accurate', 'mind'),
	},
	{
		title: __('GPT-4o', 'mind'),
		name: 'gpt-4o',
		description: __('Quick and reliable', 'mind'),
	},
	{
		title: __('GPT-4o mini', 'mind'),
		name: 'gpt-4o-mini',
		description: __('Basic and fastest', 'mind'),
	},
];

export default function PageSettings() {
	const [pendingSettings, setPendingSettings] = useState({});
	const [settingsChanged, setSettingsChanged] = useState(false);
	const [isInvalidAnthropicAPIKey, setIsInvalidAnthropicAPIKey] =
		useState(false);
	const [isInvalidOpenAIAPIKey, setIsInvalidOpenAIAPIKey] = useState(false);

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
					<label htmlFor="mind-settings-ai-model">
						{__('Model', 'mind')}
					</label>
				</div>
				<div className="mind-admin-settings-card-button-group">
					{models.map((model) => (
						<button
							key={model.title}
							onClick={(e) => {
								e.preventDefault();
								setPendingSettings({
									...pendingSettings,
									ai_model: model.name,
								});
							}}
							className={clsx(
								'mind-admin-settings-card-button',
								pendingSettings.ai_model === model.name &&
									'mind-admin-settings-card-button-active'
							)}
						>
							{model.title}
							<span>{model.description}</span>
						</button>
					))}
				</div>
			</div>
			{pendingSettings.ai_model?.includes('claude') && (
				<div className="mind-admin-settings-card">
					<div className="mind-admin-settings-card-name">
						<label htmlFor="mind-settings-anthropic-api-key">
							{__('Anthropic API Key', 'mind')}
						</label>
					</div>
					<div
						className={clsx(
							'mind-admin-settings-card-input',
							isInvalidAnthropicAPIKey &&
								'mind-admin-settings-card-input-error'
						)}
					>
						<input
							id="mind-settings-anthropic-api-key"
							type="text"
							placeholder={__('Enter API key', 'mind')}
							value={pendingSettings.anthropic_api_key || ''}
							onChange={(e) => {
								e.preventDefault();
								setPendingSettings({
									...pendingSettings,
									anthropic_api_key: e.target.value,
								});
							}}
						/>
						{isInvalidAnthropicAPIKey && (
							<div className="mind-admin-setting-error">
								{__('Please enter a valid API key', 'mind')}
							</div>
						)}
					</div>
					<div className="mind-admin-settings-card-description">
						{__(
							'This setting is required to use Anthropic models.',
							'mind'
						)}{' '}
						<a
							href="https://console.anthropic.com/settings/keys"
							target="_blank"
							rel="noreferrer"
						>
							{__('Create API key', 'mind')}
						</a>
					</div>
				</div>
			)}

			{pendingSettings.ai_model?.includes('gpt') && (
				<div className="mind-admin-settings-card">
					<div className="mind-admin-settings-card-name">
						<label htmlFor="mind-settings-openai-api-key">
							{__('OpenAI API Key', 'mind')}
						</label>
					</div>
					<div
						className={clsx(
							'mind-admin-settings-card-input',
							isInvalidOpenAIAPIKey &&
								'mind-admin-settings-card-input-error'
						)}
					>
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
						{isInvalidOpenAIAPIKey && (
							<div className="mind-admin-setting-error">
								{__('Please enter a valid API key', 'mind')}
							</div>
						)}
					</div>
					<div className="mind-admin-settings-card-description">
						{__(
							'This setting is required to use OpenAI models.',
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
			)}

			{error && <div className="mind-admin-settings-error">{error}</div>}
			<div className="mind-admin-settings-actions">
				<button
					disabled={!settingsChanged}
					onClick={(e) => {
						e.preventDefault();

						// Check if Anthropic API key is valid.
						if (
							pendingSettings.anthropic_api_key &&
							!isValidAnthropicApiKey(
								pendingSettings.anthropic_api_key
							)
						) {
							setIsInvalidAnthropicAPIKey(true);

							// Check if OpenAI API key is valid.
						} else if (
							pendingSettings.openai_api_key &&
							!isValidOpenAIApiKey(pendingSettings.openai_api_key)
						) {
							setIsInvalidOpenAIAPIKey(true);

							// Update settings.
						} else {
							setIsInvalidOpenAIAPIKey(false);
							setIsInvalidAnthropicAPIKey(false);
							updateSettings(pendingSettings);
						}
					}}
				>
					{__('Save Changes', 'mind')}
					{updating && <LoadingIcon viewBox="0 0 24 24" />}
				</button>
			</div>
		</>
	);
}
