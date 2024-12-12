/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
import BlocksStreamProcessor from '../../processors/blocks-stream-processor';
import getSelectedBlocksJSON from '../../../utils/get-selected-blocks-json';
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

export function requestAI() {
	return async ({ dispatch, select }) => {
		if (!isConnected) {
			dispatch(setError(__('Not connected', 'mind')));
			return;
		}

		if (select.getLoading()) {
			return;
		}

		try {
			dispatch({ type: 'REQUEST_AI_PENDING' });

			// Prepare request data
			const data = {
				request: select.getInput(),
			};

			// Add context if needed
			if (select.getContext() === 'selected-blocks') {
				data.context = getSelectedBlocksJSON();
			}

			// Initialize stream processor
			const streamProcessor = new BlocksStreamProcessor(dispatch);

			// Make API request
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
