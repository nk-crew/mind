import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useRef, useEffect, useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { ReactComponent as MindLogoIcon } from '../../../../icons/mind-logo.svg';

export default function Input(props) {
	const { onInsert, isFullscreen } = props;

	const ref = useRef();
	const prevIsFullscreenRef = useRef(isFullscreen);
	const [isForceResize, setIsForceResize] = useState(0);

	const { reset, setInput, setScreen, requestAI } = useDispatch('mind/popup');

	const { isOpen, input, screen, loading, response } = useSelect((select) => {
		const {
			isOpen: checkIsOpen,
			getInput,
			getScreen,
			getLoading,
			getResponse,
		} = select('mind/popup');

		return {
			isOpen: checkIsOpen(),
			input: getInput(),
			screen: getScreen(),
			loading: getLoading(),
			response: getResponse(),
		};
	});

	const hasResponse = response?.length > 0;

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
			e.preventDefault();

			if (input) {
				requestAI();
			}
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
	}, [ref, input, loading, hasResponse, isForceResize]);

	// Automatic height after fullscreen transition.
	// Trigger resize 3 times during fullscreen transition
	useEffect(() => {
		// Only run when transitioning from false to true
		const allowRezise = isFullscreen && !prevIsFullscreenRef.current;

		prevIsFullscreenRef.current = isFullscreen;

		if (allowRezise) {
			// Array of delays for the three resizes
			const resizeDelays = [100, 200, 300];
			const timeoutIds = [];

			// Schedule the three resizes
			resizeDelays.forEach((delay) => {
				const timeoutId = setTimeout(() => {
					// Using functional update to avoid dependency on current state
					setIsForceResize((prev) => prev + 1);
				}, delay);

				timeoutIds.push(timeoutId);
			});

			// Cleanup function to clear all timeouts
			return () => {
				timeoutIds.forEach((id) => clearTimeout(id));
			};
		}
	}, [isFullscreen]);

	return (
		<div className="mind-popup-input">
			<MindLogoIcon />
			<textarea
				ref={ref}
				placeholder={__('Ask AI to build or change blocks…', 'mind')}
				value={input}
				onChange={(e) => {
					setInput(e.target.value);
				}}
				onKeyDown={onKeyDown}
				disabled={loading}
				rows={1}
			/>
		</div>
	);
}
