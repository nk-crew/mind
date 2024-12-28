const initialState = {
	isOpen: false,
	input: '',
	context: ['selected-blocks', 'page'],
	insertionPlace: '',
	screen: '',
	loading: false,
	response: '',
	error: null,
	progress: {
		charsProcessed: 0,
		queueSize: 0,
		isComplete: false,
	},
};

function reducer(state = initialState, action = {}) {
	switch (action.type) {
		case 'CLOSE':
			if (state.isOpen) {
				return {
					...state,
					isOpen: false,
				};
			}
			break;
		case 'OPEN':
			if (!state.isOpen) {
				const newState = {
					...state,
					isOpen: true,
				};

				// Always set context to selected blocks when open popup.
				// In case the blocks are not selected or a single empty paragraph,
				// we will not send the context to the AI.
				if (!newState.context.includes('selected-blocks')) {
					newState.context = [...newState.context, 'selected-blocks'];
				}

				return newState;
			}
			break;
		case 'TOGGLE':
			return {
				...state,
				isOpen: !state.isOpen,
			};
		case 'SET_INPUT':
			if (state.input !== action.input) {
				return {
					...state,
					input: action.input,
				};
			}
			break;
		case 'SET_CONTEXT':
			if (state.context !== action.context) {
				return {
					...state,
					context: action.context,
				};
			}
			break;
		case 'SET_INSERTION_PLACE':
			if (state.insertionPlace !== action.insertionPlace) {
				return {
					...state,
					insertionPlace: action.insertionPlace,
				};
			}
			break;
		case 'SET_SCREEN':
			if (state.screen !== action.screen) {
				return {
					...state,
					screen: action.screen,
				};
			}
			break;
		case 'SET_LOADING':
			if (state.loading !== action.loading) {
				return {
					...state,
					loading: action.loading,
				};
			}
			break;
		case 'SET_RESPONSE':
			if (state.response !== action.response) {
				return {
					...state,
					response: action.response,
				};
			}
			break;
		case 'SET_ERROR':
			if (state.error !== action.error) {
				return {
					...state,
					error: action.error,
				};
			}
			break;
		case 'REQUEST_AI_PENDING':
			return {
				...state,
				isOpen: true,
				loading: true,
				response: [],
				error: null,
				screen: 'request',
				progress: initialState.progress,
			};
		case 'REQUEST_AI_CHUNK':
			return {
				...state,
				loading: true,
				response: action.payload.response,
				progress: action.payload.progress,
			};
		case 'REQUEST_AI_SUCCESS':
			return {
				...state,
				loading: false,
				response: action.payload.response,
				progress: { ...action.payload.progress, isComplete: true },
			};
		case 'REQUEST_AI_ERROR':
			return {
				...state,
				loading: false,
				response: [],
				error: action.payload || '',
				progress: initialState.progress,
			};
		case 'RESET':
			return {
				...state,
				input: '',
				insertionPlace: '',
				screen: '',
				response: false,
				error: false,
				loading: false,
			};
	}

	return state;
}

export default reducer;
