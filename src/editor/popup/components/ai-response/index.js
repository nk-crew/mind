import { isEqual } from 'lodash';

/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { memo } from '@wordpress/element';
import { BlockPreview } from '@wordpress/block-editor';

const AIResponse = memo(
	function AIResponse({ response, loading }) {
		if (!response.length && !loading) {
			return null;
		}

		return (
			<div
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
