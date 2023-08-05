import mdToHtml from '../../../utils/md-to-html';

function reducer(
	state = {
		isOpen: false,
		input: '',
		context: '',
		insertionPlace: '',
		screen: '',
		loading: false,
		response: false,
		error: false,
	},
	action = {}
) {
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
				return {
					...state,
					isOpen: true,
				};
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
				loading: true,
				isOpen: true,
				screen: 'request',
			};
		case 'REQUEST_AI_SUCCESS':
			return {
				...state,
				loading: false,
				response: action.payload ? mdToHtml(action.payload) : false,
			};
		case 'REQUEST_AI_ERROR':
			return {
				...state,
				loading: false,
				error: action.payload || '',
				response: false,
			};
		case 'RESET':
			return {
				...state,
				input: '',
				context: '',
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
