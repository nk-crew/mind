const {
	connected,
	setupState = {},
	connectorsPageURL,
	mindSettingsPageURL,
	connectorApprovalsURL,
} = window.mindData;

export function isConnected() {
	return connected === true || connected === '1';
}

export function getSetupState() {
	return setupState;
}

export function canManageConnectors() {
	return setupState.canManageConnectors === true;
}

export function needsProviderConnection() {
	return setupState.needsProviderConnection === true;
}

export function needsModelSelection() {
	return setupState.needsModelSelection === true;
}

export function isMindBlocked() {
	return needsProviderConnection() || needsModelSelection();
}

export function getConnectorsPageURL() {
	return connectorsPageURL;
}

export function getMindSettingsPageURL() {
	return mindSettingsPageURL;
}

export function getConnectorApprovalsURL() {
	return connectorApprovalsURL;
}
