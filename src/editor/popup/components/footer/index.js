import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

export default function Input(props) {
	const { onInsert } = props;

	const { close, reset, setError, requestAI } = useDispatch('mind/popup');

	const { input, loading, response } = useSelect((select) => {
		const { getInput, getContext, getScreen, getLoading, getResponse } =
			select('mind/popup');

		return {
			input: getInput(),
			context: getContext(),
			screen: getScreen(),
			loading: getLoading(),
			response: getResponse(),
		};
	});

	const showFooter = response || (input && !loading && !response);

	if (!showFooter) {
		return null;
	}

	return (
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
								setError('');
								requestAI();
							}}
						>
							{__('Regenerate', 'mind')} <kbd>↻</kbd>
						</Button>
						<Button
							onClick={() => {
								// Copy to clipboard.
								window.navigator.clipboard.writeText(response);

								reset();
								close();
							}}
						>
							{__('Copy', 'mind')}
						</Button>
						<Button onClick={onInsert}>
							{__('Insert', 'mind')} <kbd>⏎</kbd>
						</Button>
					</>
				)}
			</div>
		</div>
	);
}
