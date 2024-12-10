import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

import LoadingText from '../loading-text';

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

	const showFooter =
		response?.length > 0 ||
		loading ||
		(input && !loading && response?.length === 0);

	if (!showFooter) {
		return null;
	}

	return (
		<div className="mind-popup-footer">
			{loading && <LoadingText>{__('Writing', 'mind')}</LoadingText>}
			<div className="mind-popup-footer-actions">
				{input && !loading && response?.length === 0 && (
					<Button
						onClick={() => {
							requestAI();
						}}
					>
						{__('Get Answer', 'mind')} <kbd>⏎</kbd>
					</Button>
				)}
				{response?.length > 0 && !loading && (
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
								window.navigator.clipboard.writeText(
									JSON.stringify(response)
								);

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
