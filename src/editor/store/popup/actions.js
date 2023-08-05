/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies.
 */
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

export function requestAI() {
	return ({ dispatch, select }) => {
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

		apiFetch({
			path: '/mind/v1/request_ai',
			method: 'POST',
			data,
		})
			.then((res) => {
				dispatch({
					type: 'REQUEST_AI_SUCCESS',
					payload: res.response,
				});
				return res.response;
			})
			.catch((err) => {
				dispatch({
					type: 'REQUEST_AI_ERROR',
					payload:
						err?.response ||
						err?.error_code ||
						__('Something went wrong, please, try again…', 'mind'),
				});
			});
	};
}

export function reset() {
	return {
		type: 'RESET',
	};
}
