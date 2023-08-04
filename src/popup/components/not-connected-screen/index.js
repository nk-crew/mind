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
import { ReactComponent as KeyIcon } from '../../../icons/key.svg';

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
				{__('OpenAI Key', 'mind')}
			</h2>
			<div>
				<p>
					{__(
						'In order to use Mind, you will need to provide your OpenAI API key. Please insert your API key in the plugin settings to get started.',
						'mind'
					)}
				</p>
			</div>
			<div>
				<a
					className="mind-popup-connected-screen-button"
					href={settingsPageURL}
				>
					{__('Go to Settings', 'mind')}
				</a>
			</div>
		</div>
	);
}
