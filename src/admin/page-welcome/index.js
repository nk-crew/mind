/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';

export default function PageWelcome() {
	const { setActivePage } = useDispatch('mind/admin');

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
			<div>
				{__('To get started, enter your', 'mind')}
				<button
					onClick={(e) => {
						e.preventDefault();
						setActivePage('settings');
					}}
				>
					{__('OpenAI API key →', 'mind')}
				</button>
			</div>
		</>
	);
}
