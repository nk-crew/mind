import { getLocaleData, setLocaleData } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { usePrevious, createHigherOrderComponent } from '@wordpress/compose';
import { useEffect } from '@wordpress/element';
import { useDispatch } from '@wordpress/data';

/**
 * Change Paragraph block placeholder.
 */
const localeData = getLocaleData();
const localeDefault = 'Type / to choose a block';
const localeTranslated =
	localeData && typeof localeData[localeDefault] !== 'undefined'
		? localeData[localeDefault]
		: localeDefault;

setLocaleData(
	{
		[localeDefault]: [`${localeTranslated}... Press \`space\` for AI`],
	},
	'default'
);

/**
 * Listen for `space` inside an empty paragraph block.
 * And open the Mind Popup.
 *
 * @param {Function} OriginalComponent Original component.
 *
 * @return {Function} Wrapped component.
 */
const withMindAI = createHigherOrderComponent((OriginalComponent) => {
	function MindParagraphAI(props) {
		const { name, attributes } = props;
		const { content } = attributes;

		const previousContent = usePrevious(content);
		const { open, setInsertionPlace } = useDispatch('mind/popup');

		useEffect(() => {
			// Convert content to string, because it is a RichText value.
			if (
				name === 'core/paragraph' &&
				!`${previousContent || ''}` &&
				`${content || ''}` === ' '
			) {
				open();
				setInsertionPlace('selected-blocks');
			}
		}, [name, previousContent, content, open, setInsertionPlace]);

		return <OriginalComponent {...props} />;
	}

	return MindParagraphAI;
}, 'withMindAI');

addFilter('editor.BlockEdit', 'mind/open-popup', withMindAI);
