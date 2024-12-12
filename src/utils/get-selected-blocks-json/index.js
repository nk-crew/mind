export default function getSelectedBlocksJSON() {
	const { getBlock, getSelectedBlockClientIds } =
		wp.data.select('core/block-editor');

	const ids = getSelectedBlockClientIds();
	const blocksJSON = [];

	ids.forEach((id) => {
		const blockData = getBlock(id);

		if (blockData?.attributes?.content) {
			blocksJSON.push(blockData);
		}
	});

	return JSON.stringify(blocksJSON);
}
