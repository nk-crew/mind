function reducer(
	state = {
		highlightBlocks: [],
	},
	action = {}
) {
	switch (action.type) {
		case 'SET_HIGHLIGHT_BLOCKS':
			if (action.highlightBlocks && action.highlightBlocks.length) {
				return {
					...state,
					highlightBlocks: [
						...state.highlightBlocks,
						...action.highlightBlocks,
					],
				};
			}
			break;
		case 'REMOVE_HIGHLIGHT_BLOCKS':
			if (
				state.highlightBlocks &&
				state.highlightBlocks.length &&
				action.removeBlocks &&
				action.removeBlocks.length
			) {
				return {
					...state,
					highlightBlocks: state.highlightBlocks.filter((val) => {
						return !action.removeBlocks.includes(val);
					}),
				};
			}
			break;
	}

	return state;
}

export default reducer;
