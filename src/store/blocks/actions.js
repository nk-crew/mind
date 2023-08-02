export function setHighlightBlocks(blocks) {
	return {
		type: 'SET_HIGHLIGHT_BLOCKS',
		highlightBlocks: blocks,
	};
}

export function removeHighlightBlocks(blocks) {
	return {
		type: 'REMOVE_HIGHLIGHT_BLOCKS',
		removeBlocks: blocks,
	};
}
