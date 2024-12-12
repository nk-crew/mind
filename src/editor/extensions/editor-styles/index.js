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
					}, 3000);
				}, 3000);
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
								background-color: rgba(228, 85, 223, 0.1);
								box-shadow: 0 0 0 0.75rem rgba(228, 85, 223, 0.1);
								${animateOpacity ? 'transition: 3s background-color, 3s box-shadow;' : ''}
							}
							${
								animateOpacity
									? `
										[data-block="${clientId}"] {
											background-color: rgba(228, 85, 223, 0);
											box-shadow: 0 0 0 0.75rem rgba(228, 85, 223, 0);
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
