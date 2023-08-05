/**
 * Styles
 */
import './style.scss';

/**
 * External dependencies
 */
import clsx from 'clsx';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { render, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';

/**
 * Internal dependencies
 */
import './store/admin';
import './store/settings';

import pages from './pages';
import { ReactComponent as MindLogoIcon } from '../icons/mind-logo.svg';

function PageWrapper() {
	const { setActivePage } = useDispatch('mind/admin');

	const { activePage } = useSelect((select) => {
		const { getActivePage } = select('mind/admin');

		return {
			activePage: getActivePage(),
		};
	});

	useEffect(() => {
		// disable active links.
		document
			.querySelectorAll('.toplevel_page_mind .current')
			.forEach(($el) => {
				$el.classList.remove('current');
			});

		// find new active link.
		let $links = document.querySelectorAll(
			`.toplevel_page_mind [href="admin.php?page=mind&sub_page=${activePage}"]`
		);

		if (!$links || !$links.length) {
			$links = document.querySelectorAll(
				'.toplevel_page_mind [href="admin.php?page=mind"]'
			);
		}

		$links.forEach(($link) => {
			$link.parentNode.classList.add('current');
		});

		// Change body class.
		document.body.classList.forEach((className) => {
			if (/mind-admin-page-/.test(className)) {
				document.body.classList.remove(className);
			}
		});
		document.body.classList.add(`mind-admin-page-${activePage}`);

		// change address bar link
		if ($links && $links.length) {
			window.history.pushState(
				document.title,
				document.title,
				$links[0].href
			);
		}
	}, [activePage]);

	const resultTabs = [];
	let resultContent = '';

	Object.keys(pages).forEach((k) => {
		resultTabs.push(
			<li key={k}>
				{/* eslint-disable-next-line react/button-has-type */}
				<button
					className={clsx(
						'mind-admin-tabs-button',
						activePage === k && 'mind-admin-tabs-button-active'
					)}
					onClick={() => {
						setActivePage(k);
					}}
				>
					{pages[k].label}
				</button>
			</li>
		);
	});

	if (activePage && pages[activePage]) {
		const NewBlock = pages[activePage].block;

		resultContent = <NewBlock />;
	}

	return (
		<>
			<div className="mind-admin-head">
				<div className="mind-admin-head-container">
					<div className="mind-admin-head-logo">
						<MindLogoIcon />
						<h1>{__('Mind', 'mind')}</h1>
					</div>
					<ul className="mind-admin-tabs">{resultTabs}</ul>
				</div>
			</div>
			<div className="mind-admin-content">{resultContent}</div>
		</>
	);
}

window.addEventListener('load', () => {
	render(<PageWrapper />, document.querySelector('.mind-admin-root'));
});
