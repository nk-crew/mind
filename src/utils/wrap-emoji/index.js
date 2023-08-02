export default function wrapEmoji(text, wrapData) {
	wrapData = {
		tagName: 'span',
		className: '',
		...wrapData,
	};

	const reEmoji =
		/\p{RI}\p{RI}|\p{Emoji}(\p{EMod}+|\u{FE0F}\u{20E3}?|[\u{E0020}-\u{E007E}]+\u{E007F})?(\u{200D}\p{Emoji}(\p{EMod}+|\u{FE0F}\u{20E3}?|[\u{E0020}-\u{E007E}]+\u{E007F})?)+|\p{EPres}(\p{EMod}+|\u{FE0F}\u{20E3}?|[\u{E0020}-\u{E007E}]+\u{E007F})?|\p{Emoji}(\p{EMod}+|\u{FE0F}\u{20E3}?|[\u{E0020}-\u{E007E}]+\u{E007F})/gu;

	return text.replace(
		reEmoji,
		`<${wrapData.tagName}${
			wrapData.className ? ` class="${wrapData.className}"` : ''
		} role="img" aria-hidden="true">$&</${wrapData.tagName}>`
	);
}
