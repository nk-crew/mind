/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

const { settingsURL } = window.mindData;

export default function NotConnectedScreen() {
	return (
		<div className="mind-popup-connected-screen">
			<h2>{__('OpenAI Key', 'mind')}</h2>
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
					href={settingsURL}
				>
					{__('Go to Settings', 'mind')}
				</a>
			</div>
		</div>
	);
}
