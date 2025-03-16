export default function isValidOpenAIApiKey(apiKey) {
	const regex = /^sk-[a-zA-Z0-9]/;
	return regex.test(apiKey);
}
