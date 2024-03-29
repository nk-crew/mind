/**
 * WordPress dependencies
 */
// eslint-disable-next-line import/no-extraneous-dependencies
import { compact, map } from 'lodash';
import { createPortal, useContext, useMemo } from '@wordpress/element';
import { transformStyles, BlockList } from '@wordpress/block-editor';

const { elementContext: __stableElementContext, __unstableElementContext } =
	BlockList;

const elementContext = __stableElementContext || __unstableElementContext;

const EDITOR_STYLES_SELECTOR = '.editor-styles-wrapper';

export default function EditorStyles(props) {
	const { styles } = props;

	const renderStyles = useMemo(() => {
		const transformedStyles = transformStyles(
			[
				{
					css: styles,
				},
			],
			EDITOR_STYLES_SELECTOR
		);

		let resultStyles = '';

		map(compact(transformedStyles), (updatedCSS) => {
			resultStyles += updatedCSS;
		});

		return resultStyles;
	}, [styles]);

	const element = useContext(elementContext);

	return (
		renderStyles &&
		element &&
		createPortal(
			<style
				// eslint-disable-next-line react/no-danger
				dangerouslySetInnerHTML={{
					__html: renderStyles,
				}}
			/>,
			element
		)
	);
}
