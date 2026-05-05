const { connected, settingsPageURL } = window.mindData;

export function isConnected() {
	return connected === true || connected === '1';
}

export function getSettingsPageURL() {
	return settingsPageURL;
}
