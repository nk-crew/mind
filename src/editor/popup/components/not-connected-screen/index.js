/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ReactComponent as KeyIcon } from '../../../../icons/key.svg';

export default function NotConnectedScreen() {
	const {
		canManage,
		showConnectors,
		showMindSettings,
		connectorsPageURL,
		mindSettingsPageURL,
	} = useSelect((select) => {
		const {
			canManageConnectors,
			needsProviderConnection,
			needsModelSelection,
			getConnectorsPageURL,
			getMindSettingsPageURL,
		} = select('mind');

		return {
			canManage: canManageConnectors(),
			showConnectors: needsProviderConnection(),
			showMindSettings: needsModelSelection(),
			connectorsPageURL: getConnectorsPageURL(),
			mindSettingsPageURL: getMindSettingsPageURL(),
		};
	});

	let message = __(
		'Mind is not ready to send requests yet. Ask an administrator to finish the AI setup.',
		'mind'
	);
	let actionURL = '';
	let actionLabel = '';

	if (showConnectors) {
		message = canManage
			? __(
					'In order to use Mind, connect an Anthropic or OpenAI provider in WordPress Connectors.',
					'mind'
			  )
			: __(
					'Mind is not ready yet. Ask an administrator to connect an Anthropic or OpenAI provider in WordPress Connectors.',
					'mind'
			  );
		actionURL = connectorsPageURL;
		actionLabel = __('Open Connectors', 'mind');
	} else if (showMindSettings) {
		message = canManage
			? __(
					'A valid AI model is not selected for Mind yet. Open Mind settings and choose one before sending requests.',
					'mind'
			  )
			: __(
					'Mind is not ready yet. Ask an administrator to select a valid AI model in Mind settings.',
					'mind'
			  );
		actionURL = mindSettingsPageURL;
		actionLabel = __('Open Mind settings', 'mind');
	}

	return (
		<div className="mind-popup-connected-screen">
			<h2>
				<KeyIcon />
				{__('Mind setup required', 'mind')}
			</h2>
			<div>
				<p>{message}</p>
			</div>
			{canManage && actionURL && (
				<div>
					<a
						className="mind-popup-connected-screen-button"
						href={actionURL}
						target="_blank"
						rel="noreferrer"
					>
						{actionLabel}
					</a>
				</div>
			)}
		</div>
	);
}
