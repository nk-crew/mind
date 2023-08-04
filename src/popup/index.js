/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { rawHandler } from '@wordpress/blocks';
import domReady from '@wordpress/dom-ready';
import clsx from 'clsx';

/**
 * Internal dependencies
 */
import Input from './components/input';
import LoadingLine from './components/loading-line';
import Content from './components/content';
import Footer from './components/footer';
import NotConnectedScreen from './components/not-connected-screen';

const POPUP_CONTAINER_CLASS = 'mind-popup-container';

export default function Popup() {
	const { setHighlightBlocks } = useDispatch('mind/blocks');
	const { close, reset } = useDispatch('mind/popup');

	const { connected, isOpen, insertionPlace, loading, response } = useSelect(
		(select) => {
			const { isConnected } = select('mind');
			const {
				isOpen: checkIsOpen,
				getInsertionPlace,
				getLoading,
				getResponse,
			} = select('mind/popup');

			return {
				connected: isConnected(),
				isOpen: checkIsOpen(),
				insertionPlace: getInsertionPlace(),
				loading: getLoading(),
				response: getResponse(),
			};
		}
	);

	const { selectedClientIds } = useSelect((select) => {
		const { getSelectedBlockClientIds } = select('core/block-editor');

		const ids = getSelectedBlockClientIds();

		return {
			selectedClientIds: ids,
		};
	}, []);

	const { insertBlocks, replaceBlocks } = useDispatch('core/block-editor');

	function insertResponse() {
		const parsedBlocks = rawHandler({ HTML: response });

		if (parsedBlocks.length) {
			if (insertionPlace === 'selected-blocks') {
				replaceBlocks(selectedClientIds, parsedBlocks);
			} else {
				insertBlocks(parsedBlocks);
			}

			setHighlightBlocks(
				parsedBlocks.map((data) => {
					return data.clientId;
				})
			);
		}
	}

	function onInsert() {
		insertResponse();

		reset();
		close();
	}

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			title={false}
			className={clsx(
				'mind-popup',
				!connected && 'mind-popup-not-connected'
			)}
			overlayClassName="mind-popup-overlay"
			onRequestClose={() => {
				reset();
				close();
			}}
			__experimentalHideHeader
		>
			{connected ? (
				<>
					<Input onInsert={onInsert} />
					{loading && <LoadingLine />}
					<Content />
					<Footer onInsert={onInsert} />
				</>
			) : (
				<NotConnectedScreen />
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
