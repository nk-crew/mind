import './style.scss';

export default function Notice(props) {
	const { type, children } = props;

	return (
		<div
			className={`mind-popup-notice ${
				type ? `mind-popup-notice-${type}` : ''
			}`}
		>
			{children}
		</div>
	);
}
