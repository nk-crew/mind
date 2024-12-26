/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import EditorStyles from '../../components/editor-styles';

/**
 * Add new blocks highlight to see what exactly added by the AI.
 *
 * @param {Function} OriginalComponent Original component.
 *
 * @return {Function} Wrapped component.
 */
const withMindAIEditorStyles = createHigherOrderComponent(
	(OriginalComponent) => {
		function MindHighlightInsertedBlocks(props) {
			const { clientId } = props;

			const [animateOpacity, setAnimateOpacity] = useState(false);

			const { removeHighlightBlocks } = useDispatch('mind/blocks');

			const { highlightBlocks } = useSelect((select) => {
				const { getHighlightBlocks } = select('mind/blocks');

				return {
					highlightBlocks: getHighlightBlocks(),
				};
			});

			const allowHighlight =
				highlightBlocks &&
				highlightBlocks.length &&
				highlightBlocks.includes(clientId);

			// Remove highlight after 5 seconds.
			useEffect(() => {
				if (!allowHighlight) {
					return;
				}

				setTimeout(() => {
					setAnimateOpacity(true);

					setTimeout(() => {
						setAnimateOpacity(false);
						removeHighlightBlocks([clientId]);
					}, 1200);
				}, 200);
			}, [allowHighlight, clientId, removeHighlightBlocks]);

			// Skip this block as not needed to highlight.
			if (!allowHighlight) {
				return <OriginalComponent {...props} />;
			}

			return (
				<>
					<OriginalComponent {...props} />
					<EditorStyles
						styles={`
							[data-block="${clientId}"] {
								filter: blur(15px);
								${animateOpacity ? 'transition: 0.5s filter;' : ''}
							}
							${
								animateOpacity
									? `
										[data-block="${clientId}"] {
											filter: blur(0px);
										}
									`
									: ''
							}
						`}
					/>
				</>
			);
		}

		return MindHighlightInsertedBlocks;
	},
	'withMindAIEditorStyles'
);

addFilter('editor.BlockEdit', 'mind/editor-styles', withMindAIEditorStyles);
