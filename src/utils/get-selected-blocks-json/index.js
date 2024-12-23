export default function getSelectedBlocksJSON() {
	const { getBlock, getSelectedBlockClientIds } =
		wp.data.select('core/block-editor');

	const ids = getSelectedBlockClientIds();
	const blocksJSON = [];

	ids.forEach((id) => {
		const blockData = getBlock(id);

		if (blockData?.name && blockData?.attributes) {
			blocksJSON.push({
				clientId: blockData.clientId,
				name: blockData.name,
				attributes: blockData.attributes,
				innerBlocks: blockData?.innerBlocks || [],
			});
		}
	});

	return JSON.stringify(blocksJSON);
}
