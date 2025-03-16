import './style.scss';

/**
 * WordPress dependencies
 */
import { useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import Notice from '../notice';
import AIResponse from '../ai-response';

export default function Content() {
	const ref = useRef();

	const { setScreen } = useDispatch('mind/popup');

	const { isOpen, input, screen, loading, response, progress, error } =
		useSelect((select) => {
			const {
				isOpen: checkIsOpen,
				getInput,
				getScreen,
				getLoading,
				getResponse,
				getProgress,
				getError,
			} = select('mind/popup');

			return {
				isOpen: checkIsOpen(),
				input: getInput(),
				screen: getScreen(),
				loading: getLoading(),
				response: getResponse(),
				progress: getProgress(),
				error: getError(),
			};
		});

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
		<div className="mind-popup-content">
			{screen === 'request' && (
				<div className="mind-popup-request">
					{response?.length > 0 && (
						<AIResponse
							progress={progress}
							loading={loading}
							response={response}
						/>
					)}
					{!loading && error && <Notice type="error">{error}</Notice>}
				</div>
			)}
		</div>
	);
}
