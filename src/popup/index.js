/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot, useRef, useEffect, RawHTML } from '@wordpress/element';
import { Modal, Button, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { rawHandler } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from '../utils/icon';
import LoadingLine from './components/loading-line';
import LoadingText from './components/loading-text';
import Notice from './components/notice';

import { ReactComponent as PopupPostTitleAboutIcon } from '../icons/popup-post-title-about.svg';
import { ReactComponent as PopupPostAboutIcon } from '../icons/popup-post-about.svg';
import { ReactComponent as PopupOutlineAboutIcon } from '../icons/popup-outline-about.svg';
import { ReactComponent as PopupParagraphAboutIcon } from '../icons/popup-paragraph-about.svg';
import { ReactComponent as PopupListAboutIcon } from '../icons/popup-list-about.svg';
import { ReactComponent as PopupTableAboutIcon } from '../icons/popup-table-about.svg';

const POPUP_CONTAINER_CLASS = 'mind-popup-container';

const commands = [
	{
		type: 'category',
		label: __('Post Presets', 'mind'),
	},
	{
		type: 'request',
		label: __('Post title about…', 'mind'),
		request: __('Write a post title about ', 'mind'),
		icon: <PopupPostTitleAboutIcon />,
	},
	{
		type: 'request',
		label: __('Post about…', 'mind'),
		request: __('Write a blog post about ', 'mind'),
		icon: <PopupPostAboutIcon />,
	},
	{
		type: 'request',
		label: __('Outline about…', 'mind'),
		request: __('Write a blog post outline about ', 'mind'),
		icon: <PopupOutlineAboutIcon />,
	},

	{
		type: 'category',
		label: __('Content Presets', 'mind'),
	},
	{
		type: 'request',
		label: __('Paragraph about…', 'mind'),
		request: __('Create a paragraph about ', 'mind'),
		icon: <PopupParagraphAboutIcon />,
	},
	{
		type: 'request',
		label: __('List about…', 'mind'),
		request: __('Create a list about ', 'mind'),
		icon: <PopupListAboutIcon />,
	},
	{
		type: 'request',
		label: __('Table about…', 'mind'),
		request: __('Create a table about ', 'mind'),
		icon: <PopupTableAboutIcon />,
	},
];

export default function Popup(props) {
	const { onClose } = props;

	const ref = useRef();

	const { setHighlightBlocks } = useDispatch('mind/blocks');

	const { close, reset, setInput, setScreen, setError, requestAI } =
		useDispatch('mind/popup');

	const {
		isOpen,
		input,
		context,
		replaceBlocks,
		screen,
		loading,
		response,
		error,
	} = useSelect((select) => {
		const {
			isOpen: checkIsOpen,
			getInput,
			getContext,
			getReplaceBlocks,
			getScreen,
			getLoading,
			getResponse,
			getError,
		} = select('mind/popup');

		return {
			isOpen: checkIsOpen(),
			input: getInput(),
			context: getContext(),
			replaceBlocks: getReplaceBlocks(),
			screen: getScreen(),
			loading: getLoading(),
			response: getResponse(),
			error: getError(),
		};
	});

	let contextLabel = context;

	switch (context) {
		case 'selected-blocks':
			contextLabel = __('Selected Blocks');
			break;
		case 'post-title':
			contextLabel = __('Post Title');
			break;
		// no default
	}

	const { insertBlocks: wpInsertBlocks, replaceBlocks: wpReplaceBlocks } =
		useDispatch('core/block-editor');

	function focusInput() {
		if (ref?.current) {
			const inputEl = ref.current.querySelector(
				'.mind-popup-input input'
			);

			if (inputEl) {
				inputEl.focus();
			}
		}
	}

	function copyToClipboard() {
		window.navigator.clipboard.writeText(response);
	}

	function insertResponse() {
		const parsedBlocks = rawHandler({ HTML: response });

		if (parsedBlocks.length) {
			if (replaceBlocks && replaceBlocks.length) {
				wpReplaceBlocks(replaceBlocks, parsedBlocks);
			} else {
				wpInsertBlocks(parsedBlocks);
			}

			setHighlightBlocks(
				parsedBlocks.map((data) => {
					return data.clientId;
				})
			);
		}
	}

	// Set focus on Input.
	useEffect(() => {
		if (isOpen && ref?.current) {
			focusInput();
		}
	}, [isOpen, ref]);

	// Open request page if something is in input.
	useEffect(() => {
		if (screen === '' && input) {
			setScreen('request');
		}
	}, [screen, input, setScreen]);

	if (!isOpen) {
		return null;
	}

	const showFooter = response || (input && !loading && !response);

	return (
		<Modal
			ref={ref}
			title={false}
			className="mind-popup"
			overlayClassName="mind-popup-overlay"
			onRequestClose={() => {
				reset();
				close();

				if (onClose) {
					onClose();
				}
			}}
			__experimentalHideHeader
		>
			<div className="mind-popup-input">
				{TOOLBAR_ICON}
				<TextControl
					placeholder={__('Ask AI to write anything…', 'mind')}
					value={input}
					onChange={(val) => {
						setInput(val);
					}}
					onKeyDown={(e) => {
						// Go back to starter screen.
						if (
							screen !== '' &&
							e.key === 'Backspace' &&
							!e.target.value
						) {
							reset();
							return;
						}

						// Send request to AI.
						if (screen === 'request' && e.key === 'Enter') {
							requestAI();
						}
					}}
					disabled={loading}
				/>
				{contextLabel ? (
					<span className="mind-popup-input-context">
						{contextLabel}
					</span>
				) : (
					''
				)}
			</div>
			{loading && <LoadingLine />}
			<div className="mind-popup-content">
				{screen === '' ? (
					<div className="mind-popup-commands">
						{commands.map((data) => {
							if (data.type === 'category') {
								return (
									<span
										key={data.type + data.label}
										className="mind-popup-commands-category"
									>
										{data.label}
									</span>
								);
							}

							return (
								<Button
									key={data.type + data.label}
									className="mind-popup-commands-button"
									onClick={() => {
										setInput(data.request);
										setScreen('request');
										focusInput();
									}}
								>
									{data.icon || ''}
									{data.label}
								</Button>
							);
						})}
					</div>
				) : null}

				{screen === 'request' && (
					<div className="mind-popup-request">
						{loading && (
							<LoadingText>
								{__('Waiting for AI response', 'mind')}
							</LoadingText>
						)}
						{!loading && response && <RawHTML>{response}</RawHTML>}
						{!loading && error && (
							<Notice type="error">{error}</Notice>
						)}
					</div>
				)}
			</div>
			{showFooter && (
				<div className="mind-popup-footer">
					<div className="mind-popup-footer-actions">
						{input && !loading && !response && (
							<Button
								onClick={() => {
									requestAI();
								}}
							>
								{__('Get Answer', 'mind')} <kbd>⏎</kbd>
							</Button>
						)}
						{response && (
							<>
								<Button
									onClick={() => {
										insertResponse();

										reset();
										close();

										if (onClose) {
											onClose();
										}
									}}
								>
									{__('Insert', 'mind')} <kbd>⏎</kbd>
								</Button>
								<Button
									onClick={() => {
										copyToClipboard();

										reset();
										close();

										if (onClose) {
											onClose();
										}
									}}
								>
									{__('Copy', 'mind')}
								</Button>
								<Button
									onClick={() => {
										setError('');
										requestAI();
									}}
								>
									{__('Regenerate', 'mind')} <kbd>↻</kbd>
								</Button>
							</>
						)}
					</div>
				</div>
			)}
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
