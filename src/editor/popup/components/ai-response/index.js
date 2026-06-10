import { isEqual } from 'lodash';
import clsx from 'clsx';

/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { memo, useState, useEffect, useRef } from '@wordpress/element';
// WordPress does not expose a stable live block preview hook yet. The iframe
// based BlockPreview renders blank in the WP 7 editor modal, while this hook
// renders the exact WPBlock objects that will be inserted.
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalUseBlockPreview as useBlockPreview } from '@wordpress/block-editor';

function RenderPreview({ response, active }) {
	const previewProps = useBlockPreview({
		blocks: response,
		props: {
			className: clsx('mind-popup-response__preview', {
				'mind-popup-response__preview--active': active,
			}),
		},
	});

	return <div {...previewProps} />;
}

const AIResponse = memo(
	function AIResponse({ response, loading }) {
		const [activePreview, setActivePreview] = useState(1);
		const [preview1Data, setPreview1Data] = useState([]);
		const [preview2Data, setPreview2Data] = useState([]);
		const transitionTimeoutRef = useRef(null);
		const activePreviewRef = useRef(activePreview);

		useEffect(() => {
			activePreviewRef.current = activePreview;
		}, [activePreview]);

		// This implementation make me cry, but it works for now.
		// In short, when we have a single preview and update the response,
		// it rerenders and we see a blink. To avoid this, we have two previews
		// and we switch between them on each update.
		useEffect(() => {
			if (!response.length) {
				return;
			}

			// Clear any existing timeout
			if (transitionTimeoutRef.current) {
				clearTimeout(transitionTimeoutRef.current);
			}

			// Update the inactive preview with new data
			if (activePreviewRef.current === 1) {
				setPreview2Data(response);
			} else {
				setPreview1Data(response);
			}

			// Wait for the next frame to start transition.
			// Small delay to ensure new content is rendered.
			transitionTimeoutRef.current = setTimeout(() => {
				setActivePreview((currentPreview) =>
					currentPreview === 1 ? 2 : 1
				);
			}, 50);

			return () => {
				if (transitionTimeoutRef.current) {
					clearTimeout(transitionTimeoutRef.current);
				}
			};
		}, [response]);

		if (!response.length && !loading) {
			return null;
		}

		return (
			<div
				className={clsx(
					'mind-popup-response',
					`mind-popup-response--${activePreview}`
				)}
			>
				{(preview1Data.length > 0 || preview2Data.length > 0) && (
					<>
						<RenderPreview
							response={preview1Data}
							active={activePreview === 1}
						/>
						<RenderPreview
							response={preview2Data}
							active={activePreview === 2}
						/>
					</>
				)}
			</div>
		);
	},
	(prevProps, nextProps) => {
		return (
			isEqual(prevProps.response, nextProps.response) &&
			prevProps.loading === nextProps.loading
		);
	}
);

export default AIResponse;
