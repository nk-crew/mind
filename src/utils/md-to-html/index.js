import { marked } from 'marked';

marked.use({ headerIds: false });

export default function mdToHtml(string) {
	let result = marked.parse(string);

	// Remove <code> tag from <pre> elements.
	result = result
		.replace(/<pre><code/g, '<pre')
		.replace(/<\/code><\/pre>/g, '</pre>');

	return result;
}
