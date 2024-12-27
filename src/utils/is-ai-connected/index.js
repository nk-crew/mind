/**
 * Check if AI is connected
 * The same function is placed in /classes/class-ai-api.php
 *
 * @param {Object} settings Settings object
 *
 * @return {boolean} is connected
 */
export default function isAIConnected(settings) {
	const model = settings.ai_model || '';
	let result = false;

	if (model) {
		if ('gpt-4o' === model || 'gpt-4o-mini' === model) {
			result = !!settings?.openai_api_key;
		} else if (settings?.anthropic_api_key) {
			result = !!settings?.anthropic_api_key;
		}
	}

	return result;
}
