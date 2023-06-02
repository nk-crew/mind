/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';

console.log(2);

/**
 * Internal dependencies
 */
import './editor-toolbar-toggle';
import { Toolbar, isToolbarAllowed } from './toolbar';

/**
 * Override the default edit UI to include a new block inspector control for
 * assigning the custom attribute if needed.
 *
 * @param {Function} BlockEdit Original component.
 *
 * @return {string} Wrapped component.
 */
const withToolbarControl = createHigherOrderComponent((OriginalComponent) => {
	function MindToolbarToggle(props) {
		const allow = isToolbarAllowed(props);

		if (!allow) {
			return <OriginalComponent {...props} />;
		}

		return (
			<>
				<OriginalComponent {...props} />
				<BlockControls group="other">
					<Toolbar />
				</BlockControls>
			</>
		);
	}

	return MindToolbarToggle;
}, 'withToolbarControl');

addFilter('editor.BlockEdit', 'mind/block-toolbar-toggle', withToolbarControl);
