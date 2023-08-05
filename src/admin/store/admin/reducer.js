/**
 * Internal dependencies
 */
import pages from '../../pages';

// get variable.
const $_GET = [];
window.location.href.replace(/[?&]+([^=&]+)=([^&]*)/gi, (a, name, value) => {
	$_GET[name] = value;
});

function reducer(
	state = {
		activePage: $_GET.sub_page || Object.keys(pages)[0],
	},
	action = {}
) {
	switch (action.type) {
		case 'SET_ACTIVE_PAGE':
			if (state.activePage !== action.activePage) {
				return {
					...state,
					activePage: action.activePage,
				};
			}
			break;
	}

	return state;
}

export default reducer;
