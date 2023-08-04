import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useRef, useEffect } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from '../../../utils/icon';

export default function Input(props) {
	const { onInsert } = props;

	const ref = useRef();

	const { reset, setInput, setScreen, requestAI } = useDispatch('mind/popup');

	const { isOpen, input, context, screen, loading, response } = useSelect(
		(select) => {
			const {
				isOpen: checkIsOpen,
				getInput,
				getContext,
				getScreen,
				getLoading,
				getResponse,
			} = select('mind/popup');

			return {
				isOpen: checkIsOpen(),
				input: getInput(),
				context: getContext(),
				screen: getScreen(),
				loading: getLoading(),
				response: getResponse(),
			};
		}
	);

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

	function onKeyDown(e) {
		// Go back to starter screen.
		if (screen !== '' && e.key === 'Backspace' && !e.target.value) {
			reset();
			return;
		}

		// Insert request to post.
		if (response && e.key === 'Enter') {
			onInsert();
			return;
		}

		// Send request to AI.
		if (screen === 'request' && e.key === 'Enter') {
			requestAI();
		}
	}

	function focusInput() {
		if (ref?.current) {
			const inputEl = ref.current.querySelector('input');

			if (inputEl) {
				inputEl.focus();
			}
		}
	}

	// Set focus on Input.
	useEffect(() => {
		if (isOpen && !loading && ref?.current) {
			focusInput();
		}
	}, [isOpen, loading, ref]);

	// Open request page if something is in input.
	useEffect(() => {
		if (screen === '' && input) {
			setScreen('request');
		}
	}, [screen, input, setScreen]);

	return (
		<div className="mind-popup-input" ref={ref}>
			{TOOLBAR_ICON}
			<TextControl
				placeholder={__('Ask AI to write anything…', 'mind')}
				value={input}
				onChange={(val) => {
					setInput(val);
				}}
				onKeyDown={onKeyDown}
				disabled={loading}
			/>
			{contextLabel ? (
				<span className="mind-popup-input-context">{contextLabel}</span>
			) : null}
		</div>
	);
}
