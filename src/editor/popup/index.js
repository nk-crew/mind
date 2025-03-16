/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { createRoot, useEffect, useState, useRef } from '@wordpress/element';
import { Modal } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
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

	const [isFullscreen, setIsFullscreen] = useState(false);
	const [fullScreenTransitionStyles, setFullScreenTransitionStyles] =
		useState(null);

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

	// Change modal size with transition.
	const modalRef = useRef();
	useEffect(() => {
		if (!isOpen || !modalRef.current) {
			return;
		}

		const allowTransition =
			// Set fullscreen true.
			((loading || response?.length) &&
				!isFullscreen &&
				!fullScreenTransitionStyles) ||
			// Set fullscreen false.
			(!(loading || response?.length) &&
				isFullscreen &&
				!fullScreenTransitionStyles);

		if (!allowTransition) {
			return;
		}

		const { height } = modalRef.current.children[0].getBoundingClientRect();

		setFullScreenTransitionStyles({
			height: `${height}px`,
		});

		setTimeout(() => {
			setFullScreenTransitionStyles(null);
			setIsFullscreen(!isFullscreen);
		}, 10);
	}, [isFullscreen, loading, response, isOpen, fullScreenTransitionStyles]);

	const { insertBlocks: wpInsertBlocks, replaceBlocks } =
		useDispatch('core/block-editor');

	function insertBlocks(customPlace) {
		if (response.length) {
			if (customPlace && customPlace === 'insert') {
				wpInsertBlocks(response);
			} else if (
				insertionPlace === 'selected-blocks' &&
				selectedClientIds &&
				selectedClientIds.length
			) {
				replaceBlocks(selectedClientIds, response);
			} else {
				wpInsertBlocks(response);
			}

			setHighlightBlocks(
				response.map((data) => {
					return data.clientId;
				})
			);
		}
	}

	function onInsert() {
		insertBlocks();

		reset();
		close();
	}

	if (!isOpen) {
		return null;
	}

	return (
		<Modal
			ref={modalRef}
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
			isFullScreen={isFullscreen}
			style={fullScreenTransitionStyles}
			__experimentalHideHeader
		>
			{connected ? (
				<>
					<Input onInsert={onInsert} isFullscreen={isFullscreen} />
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
