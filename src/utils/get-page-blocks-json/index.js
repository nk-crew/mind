import cleanBlockJSON from '../clean-block-json';

export default function getPageBlocksJSON(stringify) {
	const { getBlocks } = wp.data.select('core/block-editor');

	const allBlocks = getBlocks();
	const blocksJSON = [];

	allBlocks.forEach((blockData) => {
		if (blockData?.name && blockData?.attributes) {
			blocksJSON.push(cleanBlockJSON(blockData));
		}
	});

	if (stringify) {
		return JSON.stringify(blocksJSON);
	}

	return blocksJSON;
}
