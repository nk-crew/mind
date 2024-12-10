import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ReactComponent as MindLogoIcon } from '../../../../icons/mind-logo.svg';

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
		if (response?.length > 0 && e.key === 'Enter' && !e.shiftKey) {
			onInsert();
			return;
		}

		// Send request to AI.
		if (screen === 'request' && e.key === 'Enter' && !e.shiftKey) {
			requestAI();
		}
	}

	// Set focus on Input.
	useEffect(() => {
		if (isOpen && !loading && ref?.current) {
			ref.current.focus();
		}
	}, [isOpen, loading, ref]);

	// Open request page if something is in input.
	useEffect(() => {
		if (screen === '' && input) {
			setScreen('request');
		}
	}, [screen, input, setScreen]);

	// Automatic height.
	useEffect(() => {
		if (ref?.current) {
			// We need to reset the height momentarily to get the correct scrollHeight for the textarea
			ref.current.style.height = '0px';
			const scrollHeight = ref.current.scrollHeight;

			// We then set the height directly, outside of the render loop
			// Trying to set this with state or a ref will product an incorrect value.
			ref.current.style.height = scrollHeight + 'px';
		}
	}, [ref, input]);

	return (
		<div className="mind-popup-input">
			<MindLogoIcon />
			<textarea
				ref={ref}
				placeholder={__('Ask AI to write anything…', 'mind')}
				value={input}
				onChange={(e) => {
					setInput(e.target.value);
				}}
				onKeyDown={onKeyDown}
				disabled={loading}
				rows={1}
			/>
			{contextLabel ? (
				<span className="mind-popup-input-context">{contextLabel}</span>
			) : null}
		</div>
	);
}
