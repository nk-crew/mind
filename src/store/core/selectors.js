const { connected, settingsPageURL } = window.mindData;

export function isConnected() {
	return connected === '1';
}

export function getSettingsPageURL() {
	return settingsPageURL;
}
