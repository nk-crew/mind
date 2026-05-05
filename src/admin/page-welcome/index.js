/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import FirstLoadingAnimation from './first-loading-animation';

export default function PageWelcome() {
	const { setActivePage } = useDispatch('mind/admin');
	const isConnected = !!window.mindAdminData.connected;

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
						{__('select the model and connect API key →', 'mind')}
					</button>
				</div>
			)}
			<FirstLoadingAnimation />
		</>
	);
}
