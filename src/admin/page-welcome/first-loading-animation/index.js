/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { useEffect } from '@wordpress/element';

let isFirstLoading = document.body.classList.contains(
	'mind-admin-first-loading'
);

export default function FirstLoadingAnimation() {
	// First loading animation.
	useEffect(() => {
		if (isFirstLoading) {
			isFirstLoading = false;

			document.body.classList.add('mind-admin-first-loading-start');

			setTimeout(() => {
				document.body.classList.remove(
					'mind-admin-first-loading-start',
					'mind-admin-first-loading'
				);
			}, 8000);
		}
	}, []);

	return null;
}
