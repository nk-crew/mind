/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useSelect, useDispatch } from '@wordpress/data';
import {
	ToolbarGroup,
	DropdownMenu,
	MenuGroup,
	MenuItem,
	Dashicon,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from '../../utils/icon';

const ALLOWED_BLOCKS = ['core/paragraph', 'core/heading'];

/**
 * Check if Mind allowed in block toolbar.
 *
 * @param {Object} data - block data.
 * @return {boolean} allowed.
 */
function isToolbarAllowed(data) {
	return ALLOWED_BLOCKS.includes(data.name);
}

function Toolbar() {
	const { selectedBlocks, selectedClientIds, canRemove } = useSelect(
		(select) => {
			const {
				getBlockNamesByClientId,
				getSelectedBlockClientIds,
				canRemoveBlocks,
			} = select('core/block-editor');
			const ids = getSelectedBlockClientIds();

			return {
				selectedBlocks: getBlockNamesByClientId(ids),
				selectedClientIds: ids,
				canRemove: canRemoveBlocks(ids),
			};
		},
		[]
	);

	const { open } = useDispatch('mind/popup');

	console.log(selectedClientIds);

	// const { replaceBlocks } = useDispatch( blockEditorStore );
	// const onConvertToGroup = () => {
	//   // Activate the `transform` on the Grouping Block which does the conversion.
	//   const newBlocks = switchToBlockType(
	//     blocksSelection,
	//     groupingBlockName
	//   );
	//   if ( newBlocks ) {
	//     replaceBlocks( clientIds, newBlocks );
	//   }
	// };

	return (
		<ToolbarGroup>
			<DropdownMenu
				icon={TOOLBAR_ICON}
				label={__('Mind', '@@text_domain')}
			>
				{() => {
					return (
						<>
							<MenuGroup>
								<MenuItem onClick={open}>
									{__('Improve', 'mind')}
								</MenuItem>
								<MenuItem onClick={open}>
									{__('Paraphrase', 'mind')}
								</MenuItem>
								<MenuItem onClick={open}>
									{__('Simplify', 'mind')}
								</MenuItem>
								<MenuItem onClick={open}>
									{__('Expand', 'mind')}
								</MenuItem>
								<MenuItem onClick={open}>
									{__('Shorten', 'mind')}
								</MenuItem>
							</MenuGroup>
							<MenuGroup>
								<DropdownMenu
									icon={null}
									toggleProps={{
										children: (
											<>
												{__('Formality', 'mind')}
												<Dashicon icon="arrow-right" />
											</>
										),
									}}
									popoverProps={{ placement: 'right-end' }}
									className="mind-toolbar-dropdown-wrapper"
								>
									{() => {
										return (
											<>
												<MenuGroup>
													<MenuItem onClick={open}>
														{__('Casual', 'mind')}
													</MenuItem>
													<MenuItem onClick={open}>
														{__('Neutral', 'mind')}
													</MenuItem>
													<MenuItem onClick={open}>
														{__('Formal', 'mind')}
													</MenuItem>
												</MenuGroup>
											</>
										);
									}}
								</DropdownMenu>
								<DropdownMenu
									icon={null}
									toggleProps={{
										children: (
											<>
												{__('Tone', 'mind')}
												<Dashicon icon="arrow-right" />
											</>
										),
									}}
									popoverProps={{ placement: 'right-end' }}
									className="mind-toolbar-dropdown-wrapper"
								>
									{() => {
										return (
											<>
												<MenuGroup>
													<MenuItem onClick={open}>
														{__('Friendly', 'mind')}
													</MenuItem>
													<MenuItem onClick={open}>
														{__(
															'Professional',
															'mind'
														)}
													</MenuItem>
													<MenuItem onClick={open}>
														{__('Witty', 'mind')}
													</MenuItem>
													<MenuItem onClick={open}>
														{__(
															'Heartfelt',
															'mind'
														)}
													</MenuItem>
													<MenuItem onClick={open}>
														{__(
															'Educational',
															'mind'
														)}
													</MenuItem>
												</MenuGroup>
											</>
										);
									}}
								</DropdownMenu>
							</MenuGroup>
						</>
					);
				}}
			</DropdownMenu>
		</ToolbarGroup>
	);
}

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
