import getSelectedBlocksJSON from '../get-selected-blocks-json';

export default function hasNonEmptySelectedBlocks() {
	const selectedBlocks = getSelectedBlocksJSON();

	if (!selectedBlocks || !selectedBlocks.length) {
		return false;
	}

	// In case selected an empty paragraph, return false.
	if (
		selectedBlocks.length === 1 &&
		selectedBlocks[0].name === 'core/paragraph' &&
		!selectedBlocks[0].attributes.content.trim()
	) {
		return false;
	}

	return true;
}
