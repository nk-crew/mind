import { isEqual } from 'lodash';

/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { useEffect, useRef, memo } from '@wordpress/element';
import { BlockPreview } from '@wordpress/block-editor';

const AIResponse = memo(
	function AIResponse({ response, loading }) {
		const responseRef = useRef();

		useEffect(() => {
			const responseElement = responseRef.current;
			if (!responseElement) {
				return;
			}

			const popupContent = responseElement.closest('.mind-popup-content');

			if (!popupContent) {
				return;
			}

			const handleResize = () => {
				const { scrollHeight, clientHeight } = popupContent;
				const shouldScroll = scrollHeight - clientHeight < 1000;

				if (shouldScroll) {
					popupContent.scrollTo({
						top: scrollHeight,
						behavior: 'instant',
					});
				}
			};

			const observer = new window.ResizeObserver(handleResize);

			if (responseElement) {
				observer.observe(responseElement);
			}

			return () => {
				if (responseElement) {
					observer.unobserve(responseElement);
				}
			};
		}, [responseRef]);

		if (!response.length && !loading) {
			return null;
		}

		return (
			<div
				ref={responseRef}
				className="mind-popup-response"
				style={{
					opacity: loading ? 0.85 : 1,
				}}
			>
				{response.length > 0 && (
					<div
						className="mind-popup-response__preview"
						style={{
							opacity: loading ? 0.85 : 1,
						}}
					>
						<BlockPreview blocks={response} viewportWidth={800} />
					</div>
				)}
				{loading && <div className="mind-popup-cursor" />}
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
