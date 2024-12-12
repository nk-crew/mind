/**
 * Styles
 */
import './style.scss';

/**
 * WordPress dependencies
 */
import { RawHTML } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { addFilter } from '@wordpress/hooks';
import { BlockControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { useDispatch } from '@wordpress/data';
import {
	ToolbarGroup,
	DropdownMenu,
	MenuGroup,
	MenuItem,
} from '@wordpress/components';

/**
 * Internal dependencies
 */
import { ReactComponent as ArrowRightIcon } from '../../../icons/arrow-right.svg';
import { ReactComponent as AIImproveIcon } from '../../../icons/ai-improve.svg';
import { ReactComponent as AIFixSpellingIcon } from '../../../icons/ai-fix-spelling.svg';
import { ReactComponent as AIShorterIcon } from '../../../icons/ai-shorter.svg';
import { ReactComponent as AILongerIcon } from '../../../icons/ai-longer.svg';
import { ReactComponent as AIMessage } from '../../../icons/ai-message.svg';
import { ReactComponent as AISummarizeIcon } from '../../../icons/ai-summarize.svg';
import { ReactComponent as AIToneIcon } from '../../../icons/ai-tone.svg';
import { ReactComponent as AIParaphraseIcon } from '../../../icons/ai-paraphrase.svg';
import { ReactComponent as AITranslateIcon } from '../../../icons/ai-translate.svg';
import { ReactComponent as MindLogoIcon } from '../../../icons/mind-logo.svg';
import wrapEmoji from '../../../utils/wrap-emoji';

const TONE = [
	[__('professional', 'mind'), __('🧐 Professional', 'mind')],
	[__('friendly', 'mind'), __('😀 Friendly', 'mind')],
	[__('straightforward', 'mind'), __('🙂 Straightforward', 'mind')],
	[__('educational', 'mind'), __('🎓 Educational', 'mind')],
	[__('confident', 'mind'), __('😎 Confident', 'mind')],
	[__('witty', 'mind'), __('🤣 Witty', 'mind')],
	[__('heartfelt', 'mind'), __('🤗 Heartfelt', 'mind')],
];

const LANGUAGE = [
	[__('chinese', 'mind'), __('🇨🇳 Chinese', 'mind')],
	[__('dutch', 'mind'), __('🇳🇱 Dutch', 'mind')],
	[__('english', 'mind'), __('🇺🇸 English', 'mind')],
	[__('filipino', 'mind'), __('🇵🇭 Filipino', 'mind')],
	[__('french', 'mind'), __('🇫🇷 French', 'mind')],
	[__('german', 'mind'), __('🇩🇪 German', 'mind')],
	[__('indonesian', 'mind'), __('🇮🇩 Indonesian', 'mind')],
	[__('italian', 'mind'), __('🇮🇹 Italian', 'mind')],
	[__('japanese', 'mind'), __('🇯🇵 Japanese', 'mind')],
	[__('korean', 'mind'), __('🇰🇷 Korean', 'mind')],
	[__('portuguese', 'mind'), __('🇵🇹 Portuguese', 'mind')],
	[__('russian', 'mind'), __('🇷🇺 Russian', 'mind')],
	[__('spanish', 'mind'), __('🇪🇸 Spanish', 'mind')],
	[__('vietnamese', 'mind'), __('🇻🇳 Vietnamese', 'mind')],
];

function Toolbar() {
	const { open, setInput, setContext, setInsertionPlace, requestAI } =
		useDispatch('mind/popup');

	function openModal(prompt) {
		open();
		setInput(prompt);
		setContext('selected-blocks');
		setInsertionPlace('selected-blocks');

		if (prompt) {
			requestAI();
		}
	}

	return (
		<ToolbarGroup>
			<DropdownMenu
				icon={<MindLogoIcon />}
				label={__('Mind', '@@text_domain')}
				className="mind-toolbar-toggle"
				popoverProps={{ className: 'mind-toolbar-dropdown' }}
			>
				{() => {
					return (
						<>
							<MenuGroup>
								<MenuItem
									icon={<AIMessage />}
									iconPosition="left"
									onClick={() => {
										openModal();
									}}
								>
									{__('Ask AI', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AIImproveIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(
											__(
												'Improve writing language',
												'mind'
											)
										);
									}}
								>
									{__('Improve', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AIFixSpellingIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(
											__(
												'Fix spelling and grammar',
												'mind'
											)
										);
									}}
								>
									{__('Fix Spelling & Grammar', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AIShorterIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(__('Make shorter', 'mind'));
									}}
								>
									{__('Make Shorter', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AILongerIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(__('Make longer', 'mind'));
									}}
								>
									{__('Make Longer', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AISummarizeIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(__('Summarize', 'mind'));
									}}
								>
									{__('Summarize', 'mind')}
								</MenuItem>
								<MenuItem
									icon={<AIParaphraseIcon />}
									iconPosition="left"
									onClick={() => {
										openModal(__('Paraphrase', 'mind'));
									}}
								>
									{__('Paraphrase', 'mind')}
								</MenuItem>
							</MenuGroup>
							<MenuGroup>
								<DropdownMenu
									icon={<AIToneIcon />}
									iconPosition="left"
									toggleProps={{
										children: (
											<>
												{__('Adjust Tone', 'mind')}
												<ArrowRightIcon />
											</>
										),
									}}
									popoverProps={{
										placement: 'right-end',
										className: 'mind-toolbar-dropdown',
									}}
									className="mind-toolbar-dropdown-toggle"
								>
									{() => {
										return (
											<>
												<MenuGroup
													label={__(
														'Select Tone',
														'@@text_domain'
													)}
												>
													{TONE.map((data) => (
														<MenuItem
															key={data[0]}
															onClick={() => {
																openModal(
																	sprintf(
																		// translators: %s - tone.
																		__(
																			'Change tone to %s',
																			'mind'
																		),
																		data[0]
																	)
																);
															}}
														>
															<RawHTML>
																{wrapEmoji(
																	data[1]
																)}
															</RawHTML>
														</MenuItem>
													))}
												</MenuGroup>
											</>
										);
									}}
								</DropdownMenu>
								<DropdownMenu
									icon={<AITranslateIcon />}
									iconPosition="left"
									toggleProps={{
										children: (
											<>
												{__('Translate', 'mind')}
												<ArrowRightIcon />
											</>
										),
									}}
									popoverProps={{
										placement: 'right-end',
										className: 'mind-toolbar-dropdown',
									}}
									className="mind-toolbar-dropdown-toggle"
								>
									{() => {
										return (
											<>
												<MenuGroup
													label={__(
														'Select Language',
														'@@text_domain'
													)}
												>
													{LANGUAGE.map((data) => (
														<MenuItem
															key={data[0]}
															onClick={() => {
																openModal(
																	sprintf(
																		// translators: %s - tone.
																		__(
																			'Translate to %s',
																			'mind'
																		),
																		data[0]
																	)
																);
															}}
														>
															<RawHTML>
																{wrapEmoji(
																	data[1]
																)}
															</RawHTML>
														</MenuItem>
													))}
												</MenuGroup>
											</>
										);
									}}
								</DropdownMenu>
							</MenuGroup>
						</>
					);
				}}
			</DropdownMenu>
		</ToolbarGroup>
	);
}

/**
 * Override the default edit UI to include a new block inspector control for
 * assigning the custom attribute if needed.
 *
 * @param {Function} BlockEdit Original component.
 *
 * @return {string} Wrapped component.
 */
const withToolbarControl = createHigherOrderComponent((OriginalComponent) => {
	function MindToolbarToggle(props) {
		return (
			<>
				<OriginalComponent {...props} />
				<BlockControls group="other">
					<Toolbar />
				</BlockControls>
			</>
		);
	}

	return MindToolbarToggle;
}, 'withToolbarControl');

addFilter('editor.BlockEdit', 'mind/block-toolbar-toggle', withToolbarControl);
