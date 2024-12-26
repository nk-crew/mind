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
			<div className="mind-popup-response">
				{response.length > 0 && (
					<div className="mind-popup-response__preview">
						<BlockPreview
							// Since the preview does not render properly first block align full, we need to create the wrapper Group block with our custom styles.
							// Align classes rendered properly only for the inner blocks.
							blocks={[
								{
									name: 'core/group',
									clientId:
										'a9b75f7e-55c7-4f2b-93bb-00cf24181278',
									isValid: true,
									attributes: {
										// "tagName": "div",
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
