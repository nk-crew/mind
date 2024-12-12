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
					<div className="mind-popup-response__preview">
						<BlockPreview
							blocks={response}
							viewportWidth={0}
							// TODO: the following container style is hardcoded and should be changed.
							additionalStyles={[
								{
									css: `
									.is-root-container > :where(:not(.alignleft):not(.alignright):not(.alignfull)) {
										max-width: var(--wp--style--global--content-size);
										margin-left: auto !important;
										margin-right: auto !important;
									}
									.is-root-container > .alignwide {
										max-width: var(--wp--style--global--wide-size);
										margin-left: auto;
										margin-right: auto;
									}
									.is-root-container > .alignfull {
										max-width: none;
									}
									:root :where(.is-layout-flow) > *,
									.is-root-container > * {
										margin-block-start: 1.2rem;
										margin-block-end: 0;
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
