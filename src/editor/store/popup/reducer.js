import mdToHtml from '../../../utils/md-to-html';

const initialState = {
	isOpen: false,
	input: '',
	context: '',
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
	renderBuffer: {
		content: '',
		lastUpdate: 0,
	},
};

// throttle in ms.
const RENDER_THROTTLE = 50;

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
				isOpen: true,
				loading: true,
				response: '',
				error: null,
				screen: 'request',
				progress: initialState.progress,
				renderBuffer: initialState.renderBuffer,
			};
		case 'REQUEST_AI_CHUNK':
			const now = Date.now();
			const shouldUpdate =
				now - state.renderBuffer.lastUpdate >= RENDER_THROTTLE;

			if (!shouldUpdate) {
				return {
					...state,
					renderBuffer: {
						content: action.payload.content,
						lastUpdate: state.renderBuffer.lastUpdate,
					},
				};
			}

			return {
				...state,
				loading: true,
				response: action.payload.content
					? mdToHtml(action.payload.content)
					: false,
				progress: action.payload.progress,
				renderBuffer: {
					content: action.payload.content,
					lastUpdate: now,
				},
			};
		case 'REQUEST_AI_SUCCESS':
			return {
				...state,
				loading: false,
				response: action.payload.content
					? mdToHtml(action.payload.content)
					: false,
				progress: { ...action.payload.progress, isComplete: true },
				renderBuffer: {
					content: action.payload.content,
					lastUpdate: Date.now(),
				},
			};
		case 'REQUEST_AI_ERROR':
			return {
				...state,
				loading: false,
				response: false,
				error: action.payload || '',
				progress: initialState.progress,
				renderBuffer: initialState.renderBuffer,
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
