export default function isValidOpenAIApiKey(apiKey) {
	const regex = /^sk-[a-zA-Z0-9]{32,}/;
	return regex.test(apiKey);
}
