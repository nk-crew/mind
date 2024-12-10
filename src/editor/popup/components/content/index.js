import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useRef, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';

/**
 * Internal dependencies
 */
import Notice from '../notice';
import AIResponse from '../ai-response';
import { ReactComponent as PopupPostTitleAboutIcon } from '../../../../icons/popup-post-title-about.svg';
import { ReactComponent as PopupPostAboutIcon } from '../../../../icons/popup-post-about.svg';
import { ReactComponent as PopupOutlineAboutIcon } from '../../../../icons/popup-outline-about.svg';
import { ReactComponent as PopupParagraphAboutIcon } from '../../../../icons/popup-paragraph-about.svg';
import { ReactComponent as PopupListAboutIcon } from '../../../../icons/popup-list-about.svg';
import { ReactComponent as PopupTableAboutIcon } from '../../../../icons/popup-table-about.svg';

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

export default function Content() {
	const ref = useRef();

	const { setInput, setScreen } = useDispatch('mind/popup');

	const { isOpen, input, screen, loading, response, progress, error } =
		useSelect((select) => {
			const {
				isOpen: checkIsOpen,
				getInput,
				getContext,
				getScreen,
				getLoading,
				getResponse,
				getProgress,
				getError,
			} = select('mind/popup');

			return {
				isOpen: checkIsOpen(),
				input: getInput(),
				context: getContext(),
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
