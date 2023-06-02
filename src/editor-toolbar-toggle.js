/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot, useState } from '@wordpress/element';
import { subscribe } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
// eslint-disable-next-line import/no-extraneous-dependencies
import { throttle } from 'lodash';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from './icon';
import Popup from './popup';

const TOOLBAR_TOGGLE_CONTAINER_CLASS = 'mind-editor-toolbar-toggle';

function Toggle() {
	const [isOpened, setIsOpened] = useState(false);

	return (
		<>
			<button
				type="button"
				className="components-button components-icon-button"
				onClick={(e) => {
					e.preventDefault();

					setIsOpened(!isOpened);
				}}
			>
				{TOOLBAR_ICON}
				{__('Open Mind', '@@text_domain')}
			</button>
			{isOpened ? (
				<Popup
					onClose={() => {
						setIsOpened(!isOpened);
					}}
				/>
			) : null}
		</>
	);
}

const mountEditorToolbarToggle = () => {
	const createToggle = (postHeader) => {
		const toggleContainer = document.createElement('div');
		toggleContainer.classList.add(TOOLBAR_TOGGLE_CONTAINER_CLASS);

		postHeader.appendChild(toggleContainer);

		const root = createRoot(toggleContainer);
		root.render(<Toggle />);
	};

	// Always check if toggle is inserted, because post header sometimes gets unmounted.
	subscribe(
		throttle(
			() => {
				// Check if toggle exists already.
				if (
					document.querySelector(`.${TOOLBAR_TOGGLE_CONTAINER_CLASS}`)
				) {
					return;
				}

				const postHeader = document.querySelector(
					'.edit-post-header__toolbar'
				);

				if (postHeader) {
					createToggle(postHeader);
				}
			},
			200,
			{ trailing: true }
		)
	);
};

domReady(mountEditorToolbarToggle);
