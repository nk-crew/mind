import cleanBlockJSON from '../clean-block-json';

export default function getSelectedBlocksJSON(stringify) {
	const { getBlock, getSelectedBlockClientIds } =
		wp.data.select('core/block-editor');

	const ids = getSelectedBlockClientIds();
	const blocksJSON = [];

	ids.forEach((id) => {
		const blockData = getBlock(id);

		if (blockData?.name && blockData?.attributes) {
			blocksJSON.push(cleanBlockJSON(blockData));
		}
	});

	if (stringify) {
		return JSON.stringify(blocksJSON);
	}

	return blocksJSON;
}
