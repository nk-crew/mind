/**
 * Internal dependencies
 */
import * as selectors from './selectors';

/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore('mind', {
	selectors,
	reducer(state) {
		return state;
	},
});

register(store);
