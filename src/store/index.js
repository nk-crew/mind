/**
 * Internal dependencies
 */
import reducer from './reducer';
import * as selectors from './selectors';
import * as actions from './actions';

/**
 * WordPress dependencies
 */
import { createReduxStore, register } from '@wordpress/data';

const store = createReduxStore('mind/popup', {
	reducer,
	selectors,
	actions,
});

register(store);
