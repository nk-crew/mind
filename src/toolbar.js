/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
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
import TOOLBAR_ICON from './icon';

const ALLOWED_BLOCKS = ['core/paragraph', 'core/heading'];

/**
 * Check if Mind allowed in block toolbar.
 *
 * @param {Object} data - block data.
 * @return {boolean} allowed.
 */
export function isToolbarAllowed(data) {
	return ALLOWED_BLOCKS.includes(data.name);
}

export function Toolbar() {
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
								<MenuItem>{__('Improve', 'mind')}</MenuItem>
								<MenuItem>{__('Paraphrase', 'mind')}</MenuItem>
								<MenuItem>{__('Simplify', 'mind')}</MenuItem>
								<MenuItem>{__('Expand', 'mind')}</MenuItem>
								<MenuItem>{__('Shorten', 'mind')}</MenuItem>
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
													<MenuItem>
														{__('Casual', 'mind')}
													</MenuItem>
													<MenuItem>
														{__('Neutral', 'mind')}
													</MenuItem>
													<MenuItem>
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
													<MenuItem>
														{__('Friendly', 'mind')}
													</MenuItem>
													<MenuItem>
														{__(
															'Professional',
															'mind'
														)}
													</MenuItem>
													<MenuItem>
														{__('Witty', 'mind')}
													</MenuItem>
													<MenuItem>
														{__(
															'Heartfelt',
															'mind'
														)}
													</MenuItem>
													<MenuItem>
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
