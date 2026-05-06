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
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { ReactComponent as LoadingIcon } from '../../icons/loading.svg';

const providerNames = {
	anthropic: 'Anthropic',
	openai: 'OpenAI',
};

function getModelIdentity(model) {
	return model?.canonicalName || model?.name;
}

function getModelForSlot(slot, selectedModel) {
	if (slot.selectedModel?.name === selectedModel) {
		return slot.selectedModel;
	}

	return slot.model || slot.selectedModel;
}

function getSelectedSlot(modelSlots, selectedModel) {
	return modelSlots.find((slot) =>
		[slot.model?.name, slot.selectedModel?.name].includes(selectedModel)
	);
}

export default function PageSettings() {
	const [pendingSettings, setPendingSettings] = useState({});
	const [settingsChanged, setSettingsChanged] = useState(false);

	const { updateSettings } = useDispatch('mind/settings');
	const {
		connectors = {},
		connectorsPageURL,
		modelSlots = [],
	} = window.mindAdminData;

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

	const selectedSlot =
		getSelectedSlot(modelSlots, pendingSettings.ai_model) || modelSlots[0];
	const selectedModel = selectedSlot
		? getModelForSlot(selectedSlot, pendingSettings.ai_model)
		: null;
	const currentSlotModel = selectedSlot?.model;
	const selectedProvider = selectedSlot?.provider || 'openai';
	const selectedProviderName =
		providerNames[selectedProvider] || selectedProvider;
	const isProviderConnected = !!connectors?.[selectedProvider]?.connected;
	const hasValidSelectedModel = selectedModel?.runtimeAvailable !== false;
	const hasCurrentSlotAlternative =
		getModelIdentity(selectedModel) &&
		getModelIdentity(currentSlotModel) &&
		selectedModel.provider === currentSlotModel.provider &&
		selectedModel.family === currentSlotModel.family &&
		getModelIdentity(selectedModel) !== getModelIdentity(currentSlotModel);

	return (
		<>
			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-card-name">
					<label htmlFor="mind-settings-ai-model">
						{__('Model', 'mind')}
					</label>
				</div>
				<div className="mind-admin-settings-card-button-group">
					{modelSlots.map((slot) => {
						const model = getModelForSlot(
							slot,
							pendingSettings.ai_model
						);
						const isSelected =
							model?.name === pendingSettings.ai_model;
						const isDisabled = !model?.name;

						return (
							<button
								key={`${slot.provider}-${slot.family}`}
								disabled={isDisabled}
								onClick={(e) => {
									e.preventDefault();

									if (!model?.name) {
										return;
									}

									setPendingSettings({
										...pendingSettings,
										ai_model: model.name,
									});
								}}
								className={clsx(
									'mind-admin-settings-card-button',
									isSelected &&
										'mind-admin-settings-card-button-active'
								)}
							>
								{model?.title || slot.title}
								<span>{slot.description}</span>
								{!model && (
									<span className="mind-admin-settings-card-button-note">
										{slot.connected
											? __(
													'No matching model found',
													'mind'
											  )
											: __(
													'Connect provider to load models',
													'mind'
											  )}
									</span>
								)}
								{model && !model.available && (
									<span className="mind-admin-settings-card-button-note">
										{__('Saved model', 'mind')}
									</span>
								)}
							</button>
						);
					})}
				</div>
			</div>

			{selectedModel && !hasValidSelectedModel && (
				<div className="mind-admin-settings-notice mind-admin-settings-notice-warning">
					{__(
						'The selected model cannot be used with the current WordPress AI provider configuration. Select another model before sending requests.',
						'mind'
					)}
				</div>
			)}

			{selectedModel?.deprecated && (
				<div className="mind-admin-settings-notice mind-admin-settings-notice-warning">
					{selectedModel.deprecationDate
						? sprintf(
								// translators: %s: deprecation date.
								__(
									'The selected model is deprecated and is scheduled to be disabled on %s.',
									'mind'
								),
								selectedModel.deprecationDate
						  )
						: __(
								'The selected model is marked as deprecated by the provider.',
								'mind'
						  )}
				</div>
			)}

			{selectedModel && !selectedModel.available && (
				<div className="mind-admin-settings-notice mind-admin-settings-notice-warning">
					{__(
						'The selected model is not in the current provider model list. You can keep it for now, but it may stop working if the provider has removed it.',
						'mind'
					)}
				</div>
			)}

			{hasCurrentSlotAlternative && (
				<div className="mind-admin-settings-notice">
					<span>
						{sprintf(
							// translators: %s: AI model title.
							__(
								'A different current model is available for this slot: %s. You can keep the saved model or switch to this one.',
								'mind'
							),
							currentSlotModel.title
						)}
					</span>
					<button
						onClick={(e) => {
							e.preventDefault();
							setPendingSettings({
								...pendingSettings,
								ai_model: currentSlotModel.name,
							});
						}}
					>
						{__('Use current model', 'mind')}
					</button>
				</div>
			)}

			<div className="mind-admin-settings-card">
				<div className="mind-admin-settings-connector">
					<div
						className={clsx(
							'mind-admin-settings-connector-status',
							isProviderConnected &&
								'mind-admin-settings-connector-status-connected'
						)}
					>
						<span />
						{isProviderConnected
							? sprintf(
									// translators: %s: AI provider name.
									__('%s is connected.', 'mind'),
									selectedProviderName
							  )
							: sprintf(
									// translators: %s: AI provider name.
									__('%s is not connected yet.', 'mind'),
									selectedProviderName
							  )}
					</div>
					<div className="mind-admin-settings-card-description">
						{__(
							'API keys are managed by WordPress Connectors and can be shared across plugins.',
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
