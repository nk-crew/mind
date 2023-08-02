import { marked } from 'marked';

export default function mdToHtml(string) {
	return marked.parse(string);
}
