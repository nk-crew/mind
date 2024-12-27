/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import FirstLoadingAnimation from './first-loading-animation';
import isAIConnected from '../../utils/is-ai-connected';

export default function PageWelcome() {
	const { setActivePage } = useDispatch('mind/admin');

	const { settings } = useSelect((select) => {
		const { getSettings } = select('mind/settings');

		return {
			settings: getSettings(),
		};
	});
	const isConnected = isAIConnected(settings);

	return (
		<>
			<p
				dangerouslySetInnerHTML={{
					__html: sprintf(
						// translators: %s - Mind logo.
						__('Hello, my name is %s', 'mind'),
						`<span class="mind-inline-logo">Mind</span>`
					),
				}}
			/>
			<p>
				{__(
					'I am an AI assistant designed to help you in writing content for your blog',
					'mind'
				)}
			</p>
			{isConnected ? (
				<div
					dangerouslySetInnerHTML={{
						__html: __(
							'To get started, <em>open the page editor</em> and click on the <br /><span class="mind-inline-logo">Open Mind</span> button in the toolbar',
							'mind'
						),
					}}
				/>
			) : (
				<div>
					{__('To get started,', 'mind')}
					<button
						onClick={(e) => {
							e.preventDefault();
							setActivePage('settings');
						}}
					>
						{__('select the model and API key →', 'mind')}
					</button>
				</div>
			)}
			<FirstLoadingAnimation />
		</>
	);
}
