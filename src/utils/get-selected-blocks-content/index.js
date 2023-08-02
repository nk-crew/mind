export default function getSelectedBlocksContent() {
	const { getBlock, getSelectedBlockClientIds } =
		wp.data.select('core/block-editor');

	const ids = getSelectedBlockClientIds();
	let blocksContent = '';

	ids.forEach((id) => {
		const blockData = getBlock(id);

		if (blockData?.attributes?.content) {
			blocksContent = `${blocksContent}<p>${blockData.attributes.content}</p>`;
		}
	});

	return blocksContent;
}
