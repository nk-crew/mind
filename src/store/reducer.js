function reducer(state = { isOpen: false }, action = {}) {
	switch (action.type) {
		case 'CLOSE':
			if (state.isOpen) {
				return {
					isOpen: false,
				};
			}
			break;
		case 'OPEN':
			if (!state.isOpen) {
				return {
					isOpen: true,
				};
			}
			break;
		case 'TOGGLE':
			return {
				isOpen: !state.isOpen,
			};
	}

	return state;
}

export default reducer;
