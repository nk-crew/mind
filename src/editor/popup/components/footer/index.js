import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Tooltip } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';

import hasNonEmptySelectedBlocks from '../../../../utils/has-non-empty-selected-blocks';
import LoadingText from '../loading-text';

export default function Input(props) {
	const { onInsert } = props;

	const { close, reset, setContext, setError, requestAI } =
		useDispatch('mind/popup');

	const { input, context, loading, response, insertionPlace } = useSelect(
		(select) => {
			const {
				getInput,
				getContext,
				getLoading,
				getResponse,
				getInsertionPlace,
			} = select('mind/popup');

			return {
				input: getInput(),
				context: getContext(),
				loading: getLoading(),
				response: getResponse(),
				insertionPlace: getInsertionPlace(),
			};
		}
	);

	const availableContexts = [
		{
			name: __('Page', 'mind'),
			tooltip: __('Provide page context', 'mind'),
			value: 'page',
		},
		hasNonEmptySelectedBlocks()
			? {
					name: __('Blocks', 'mind'),
					tooltip: __('Provide selected blocks context', 'mind'),
					value: 'selected-blocks',
			  }
			: false,
	];
	const editableContexts = !loading && !response?.length;

	return (
		<div className="mind-popup-footer">
			<div>
				<div className="mind-popup-footer-context">
					{availableContexts.map((item) => {
						if (!item) {
							return null;
						}

						if (
							!editableContexts &&
							!context.includes(item.value)
						) {
							return null;
						}

						return (
							<Tooltip
								delay={500}
								placement="top"
								key={item.value}
								text={item.tooltip}
							>
								<button
									key={item.value}
									disabled={!editableContexts}
									className={
										context.includes(item.value)
											? 'active'
											: ''
									}
									onClick={() => {
										if (context.includes(item.value)) {
											setContext(
												context.filter(
													(ctx) => ctx !== item.value
												)
											);
										} else {
											setContext([
												...context,
												item.value,
											]);
										}
									}}
								>
									{item.name}
									<svg
										xmlns="http://www.w3.org/2000/svg"
										viewBox="0 0 24 24"
										width="14"
										height="14"
										fill="currentColor"
									>
										<path d="M19 11h-6V5h-2v6H5v2h6v6h2v-6h6z" />
									</svg>
								</button>
							</Tooltip>
						);
					})}
				</div>
			</div>
			<div className="mind-popup-footer-actions">
				{!loading && response?.length === 0 && (
					<Tooltip
						placement="top"
						text={__('Ask AI and get answer', 'mind')}
					>
						<Button
							className="mind-popup-footer-actions-primary mind-popup-footer-actions-icon"
							onClick={() => {
								requestAI();
							}}
							aria-label={__('Get Answer', 'mind')}
							disabled={!input}
						>
							→
						</Button>
					</Tooltip>
				)}
				{loading && (
					<Button
						className="mind-popup-footer-actions-primary"
						disabled
					>
						<LoadingText>{__('Loading', 'mind')}</LoadingText>
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
						{insertionPlace === 'selected-blocks' &&
							hasNonEmptySelectedBlocks() && (
								<Button onClick={() => onInsert('insert')}>
									{__('Insert', 'mind')} <kbd>⏎</kbd>
								</Button>
							)}
						<Button
							className="mind-popup-footer-actions-primary"
							onClick={onInsert}
						>
							{insertionPlace === 'selected-blocks' &&
							hasNonEmptySelectedBlocks()
								? __('Replace Selected Blocks', 'mind')
								: __('Insert', 'mind')}{' '}
							<kbd>⏎</kbd>
						</Button>
					</>
				)}
			</div>
		</div>
	);
}
