export default function cleanBlockJSON(block) {
	if (block?.attributes?.metadata) {
		delete block.attributes.metadata;
	}

	if (block?.attributes?.patternName) {
		delete block.attributes.patternName;
	}

	const cleanedBlock = {
		clientId: block.clientId,
		name: block.name,
		attributes: block.attributes,
		innerBlocks: [],
	};

	if (block.innerBlocks.length) {
		block.innerBlocks.forEach((innerBlock) => {
			cleanedBlock.innerBlocks.push(cleanBlockJSON(innerBlock));
		});
	}

	return cleanedBlock;
}
