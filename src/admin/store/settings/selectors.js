export function getSettings(state) {
	return state?.settings || {};
}

export function getSetting(state, name) {
	return state?.settings[name] || '';
}

export function getUpdating(state) {
	return state?.updating || false;
}

export function getError(state) {
	return state?.error || false;
}
