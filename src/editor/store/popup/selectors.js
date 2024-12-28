export function isOpen(state) {
	return state?.isOpen || false;
}

export function getInput(state) {
	return state?.input || '';
}

export function getContext(state) {
	return state?.context || [];
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
	return state?.response || [];
}

export function getError(state) {
	return state?.error || false;
}
