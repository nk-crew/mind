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
import { BlockPreview } from '@wordpress/block-editor';

function RenderPreview({ response }) {
	return (
		<div className="mind-popup-response__preview">
			<BlockPreview
				// Since the preview does not render properly first block align full, we need to create the wrapper Group block with our custom styles.
				// Align classes rendered properly only for the inner blocks.
				blocks={[
					{
						name: 'core/group',
						clientId: 'a9b75f7e-55c7-4f2b-93bb-00cf24181278',
						isValid: true,
						attributes: {
							align: 'full',
							layout: {
								type: 'constrained',
							},
							className: 'alignfull',
						},
						innerBlocks: response,
					},
				]}
				viewportWidth={0}
				additionalStyles={[
					{
						css: `
							.is-root-container > div {
								margin-top: 0;
							}
						`,
					},
				]}
			/>
		</div>
	);
}

const AIResponse = memo(
	function AIResponse({ response, loading }) {
		const [activePreview, setActivePreview] = useState(1);
		const [preview1Data, setPreview1Data] = useState([]);
		const [preview2Data, setPreview2Data] = useState([]);
		const transitionTimeoutRef = useRef(null);

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
			if (activePreview === 1) {
				setPreview2Data(response);
			} else {
				setPreview1Data(response);
			}

			// Wait for the next frame to start transition.
			// Small delay to ensure new content is rendered.
			transitionTimeoutRef.current = setTimeout(() => {
				setActivePreview(activePreview === 1 ? 2 : 1);
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
						<RenderPreview response={preview1Data} />
						<RenderPreview response={preview2Data} />
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
