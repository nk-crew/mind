const { settings } = window.mindAdminData;

function reducer(
	state = {
		settings,
		updating: false,
		error: '',
	},
	action = {}
) {
	switch (action.type) {
		case 'UPDATE_SETTINGS_PENDING':
			return {
				...state,
				updating: true,
			};
		case 'UPDATE_SETTINGS_SUCCESS':
			return {
				...state,
				updating: false,
				settings: {
					...state.settings,
					...action.settings,
				},
			};
		case 'UPDATE_SETTINGS_ERROR':
			return {
				...state,
				updating: false,
				error: action.error || '',
			};
	}

	return state;
}

export default reducer;
