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
	const { settingsPageURL } = useSelect((select) => {
		const { getSettingsPageURL } = select('mind');

		return {
			settingsPageURL: getSettingsPageURL(),
		};
	});

	return (
		<div className="mind-popup-connected-screen">
			<h2>
				<KeyIcon />
				{__('AI API Key', 'mind')}
			</h2>
			<div>
				<p>
					{__(
						'In order to use Mind, you will need to connect your Anthropic or OpenAI API key in WordPress Connectors.',
						'mind'
					)}
				</p>
			</div>
			<div>
				<a
					className="mind-popup-connected-screen-button"
					href={settingsPageURL}
					target="_blank"
					rel="noreferrer"
				>
					{__('Open Connectors', 'mind')}
				</a>
			</div>
		</div>
	);
}
