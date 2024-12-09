/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import AIStreamProcessor from '../../../utils/ai-stream-processor';
import getSelectedBlocksContent from '../../../utils/get-selected-blocks-content';
import { isConnected } from '../core/selectors';

export function open() {
	return {
		type: 'OPEN',
	};
}

export function close() {
	return {
		type: 'CLOSE',
	};
}

export function toggle() {
	return {
		type: 'TOGGLE',
	};
}

export function setInput(input) {
	return {
		type: 'SET_INPUT',
		input,
	};
}

export function setContext(context) {
	return {
		type: 'SET_CONTEXT',
		context,
	};
}

export function setInsertionPlace(insertionPlace) {
	return {
		type: 'SET_INSERTION_PLACE',
		insertionPlace,
	};
}

export function setScreen(screen) {
	return {
		type: 'SET_SCREEN',
		screen,
	};
}

export function setLoading(loading) {
	return {
		type: 'SET_LOADING',
		loading,
	};
}

export function setResponse(response) {
	return {
		type: 'SET_RESPONSE',
		response,
	};
}

export function setError(error) {
	return {
		type: 'SET_ERROR',
		error,
	};
}

export function reset() {
	return {
		type: 'RESET',
	};
}

/**
 * Process stream data from the API
 *
 * @param {ReadableStream} reader Stream reader
 * @return {Function} Redux-style action function
 */
export function processStream(reader) {
	return async ({ dispatch }) => {
		const decoder = new TextDecoder();
		let buffer = '';
		let responseText = '';

		// Create artificial delay for smoother updates
		const delay = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

		try {
			while (true) {
				const { value, done } = await reader.read();
				if (done) break;

				// Process smaller chunks.
				buffer += decoder.decode(value, { stream: true });
				const lines = buffer.split('\n');
				buffer = lines.pop() || '';

				for (const line of lines) {
					if (line.startsWith('data: ')) {
						try {
							const data = JSON.parse(line.slice(6));

							if (data.error) {
								dispatch({
									type: 'REQUEST_AI_ERROR',
									payload: data.message,
								});
								return;
							}

							if (data.done) {
								dispatch({
									type: 'REQUEST_AI_SUCCESS',
									payload: responseText,
								});
								return;
							}

							if (data.content) {
								responseText += data.content;

								dispatch({
									type: 'REQUEST_AI_CHUNK',
									payload: responseText,
								});

								// Add small delay between chunks for smoother appearance.
								await delay(50);
							}
						} catch (error) {
							dispatch({
								type: 'REQUEST_AI_ERROR',
								payload: __(
									'Failed to parse stream data',
									'mind'
								),
							});
						}
					}
				}
			}
		} catch (error) {
			dispatch({
				type: 'REQUEST_AI_ERROR',
				payload: error.message,
			});
		}
	};
}

export function requestAI() {
	return async ({ dispatch, select }) => {
		if (!isConnected) {
			return;
		}

		const loading = select.getLoading();

		if (loading) {
			return;
		}

		dispatch({ type: 'REQUEST_AI_PENDING' });

		const context = select.getContext();
		const data = { request: select.getInput() };

		if (context === 'selected-blocks') {
			data.context = getSelectedBlocksContent();
		}

		try {
			// Initialize StreamProcessor with dispatch
			const streamProcessor = new AIStreamProcessor(dispatch);

			const response = await apiFetch({
				path: '/mind/v1/request_ai',
				method: 'POST',
				data,
				// Important: don't parse the response automatically
				parse: false,
			});

			if (!response.ok) {
				const errorData = await response.json();
				throw new Error(
					errorData.message ||
						__('Failed to fetch AI response', 'mind')
				);
			}

			// Process the stream
			await streamProcessor.processStream(response.body.getReader());
		} catch (error) {
			dispatch({
				type: 'REQUEST_AI_ERROR',
				payload:
					error.message || __('Failed to fetch AI response', 'mind'),
			});
		}
	};
}
