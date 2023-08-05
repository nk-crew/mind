import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { createRoot } from '@wordpress/element';
import { subscribe, useDispatch } from '@wordpress/data';
import domReady from '@wordpress/dom-ready';
// eslint-disable-next-line import/no-extraneous-dependencies
import { throttle } from 'lodash';

/**
 * Internal dependencies
 */
import { ReactComponent as MindLogoIcon } from '../../../icons/mind-logo.svg';

const TOOLBAR_TOGGLE_CONTAINER_CLASS = 'mind-post-toolbar-toggle';

function Toggle() {
	const { toggle } = useDispatch('mind/popup');

	return (
		<button
			type="button"
			className="components-button components-icon-button"
			onClick={(e) => {
				e.preventDefault();

				toggle();
			}}
		>
			<MindLogoIcon />
			{__('Open Mind', '@@text_domain')}
		</button>
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
