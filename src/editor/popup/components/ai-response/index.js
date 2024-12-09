/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { useRef, useEffect, RawHTML, memo } from '@wordpress/element';

const AIResponse = memo(
	function AIResponse({ response, loading }) {
		const responseRef = useRef();

		useEffect(() => {
			if (!responseRef.current) {
				return;
			}

			const popupContent = responseRef.current.closest(
				'.mind-popup-content'
			);

			if (!popupContent) {
				return;
			}

			// Smooth scroll to bottom of response.
			const { scrollHeight, clientHeight } = popupContent;

			// Only auto-scroll for shorter contents.
			const shouldScroll = scrollHeight - clientHeight < 1000;

			if (shouldScroll) {
				popupContent.scrollTo({
					top: scrollHeight,
					behavior: 'smooth',
				});
			}
		}, [response]);

		if (!response && !loading) {
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
				<RawHTML>{response}</RawHTML>
				{loading && <div className="mind-popup-cursor" />}
			</div>
		);
	},
	(prevProps, nextProps) => {
		// Custom memoization to prevent unnecessary rerenders.
		return (
			prevProps.renderBuffer.lastUpdate ===
				nextProps.renderBuffer.lastUpdate &&
			prevProps.loading === nextProps.loading &&
			prevProps.progress.isComplete === nextProps.progress.isComplete
		);
	}
);

export default AIResponse;
