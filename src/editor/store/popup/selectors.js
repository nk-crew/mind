// Shared so the array-returning selectors below hand back the same reference every time.
// A fresh `[]` per call is a new identity, which makes `useSelect` report the store as
// changed on every render - WordPress 7.1 logs that as "returns different values when
// called with the same state and parameters" and re-renders the popup for nothing.
const EMPTY_LIST = Object.freeze([]);

export function isOpen(state) {
	return state?.isOpen || false;
}

export function getInput(state) {
	return state?.input || '';
}

export function getContext(state) {
	return state?.context || EMPTY_LIST;
}

export function getInsertionPlace(state) {
	return state?.insertionPlace || '';
}

export function getScreen(state) {
	return state?.screen || '';
}

export function getLoading(state) {
	return state?.loading || false;
}

export function getProgress(state) {
	return state?.progress || false;
}

export function getResponse(state) {
	return state?.response || EMPTY_LIST;
}

export function getError(state) {
	return state?.error || false;
}
