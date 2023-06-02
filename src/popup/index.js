/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from '../utils/icon';

const POPUP_CONTAINER_CLASS = 'mind-popup-container';

const prompts = [
	// Base.
	{
		type: 'prompt',
		label: __('Improve', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Paraphrase', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Simplify', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Expand', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Shorten', 'mind'),
	},

	// Formality.
	{
		type: 'category',
		label: __('Formality', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Casual', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Neutral', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Formal', 'mind'),
	},

	// Tone.
	{
		type: 'category',
		label: __('Tone', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Friendly', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Professional', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Witty', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Heartfelt', 'mind'),
	},
	{
		type: 'prompt',
		label: __('Educational', 'mind'),
	},
];

export default function Popup(props) {
	const { onClose } = props;

	const { close } = useDispatch('mind/popup');

	const { isOpen } = useSelect((select) => {
		const { isOpen: checkIsOpen } = select('mind/popup');

		return { isOpen: checkIsOpen() };
	});

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={false}
			className="mind-popup"
			overlayClassName="mind-popup-overlay"
			onRequestClose={() => {
				close();

				if (onClose) {
					onClose();
				}
			}}
			__experimentalHideHeader
		>
			<div className="mind-popup-content">
				<div className="mind-popup-prompts">
					{prompts.map((data) => {
						if (data.type === 'category') {
							return (
								<span
									key={data.type + data.label}
									className="mind-popup-prompts-category"
								>
									{data.label}
								</span>
							);
						}

						return (
							<button
								key={data.type + data.label}
								className="mind-popup-prompts-button"
							>
								{data.label}
							</button>
						);
					})}
				</div>
			</div>
			<div className="mind-popup-footer">
				<div className="mind-popup-footer-logo">
					{TOOLBAR_ICON}
					{__('Mind', '@@text_domain')}
				</div>
			</div>
		</Modal>
	);
}

// .block-editor
// Insert popup renderer in editor.
domReady(() => {
	// Check if popup exists already.
	if (document.querySelector(`.${POPUP_CONTAINER_CLASS}`)) {
		return;
	}

	const blockEditor = document.querySelector('.block-editor');

	if (!blockEditor) {
		return;
	}

	const toggleContainer = document.createElement('div');
	toggleContainer.classList.add(POPUP_CONTAINER_CLASS);

	blockEditor.appendChild(toggleContainer);

	const root = createRoot(toggleContainer);
	root.render(<Popup />);
});
