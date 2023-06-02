/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Button, Modal } from '@wordpress/components';

/**
 * Internal dependencies
 */
import TOOLBAR_ICON from './icon';

export default function Popup(props) {
	const { onClose } = props;

	return (
		<Modal
			title={
				<>
					{TOOLBAR_ICON}
					{__('Mind', '@@text_domain')}
				</>
			}
			className="mind-popup"
			overlayClassName="mind-popup-overlay"
			onRequestClose={onClose}
		>
			{__('Hello there :)', '@@text_domain')}
		</Modal>
	);
}
