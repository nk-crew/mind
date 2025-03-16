export default function isValidAnthropicApiKey(apiKey) {
	const regex = /^sk-ant-[a-zA-Z0-9]/;
	return regex.test(apiKey);
}
