export default function getPageContextJSON(stringify) {
	const { getCurrentPost } = wp.data.select('core/editor');

	const currentPost = getCurrentPost();

	const result = {
		title: currentPost.title,
		type: currentPost.type,
		slug: currentPost.slug,
		link: currentPost.link,
		status: currentPost.status,
		date: currentPost.date_gmt,
		modified: currentPost.modified_gmt,
		excerpt: currentPost.excerpt,
	};

	if (stringify) {
		return JSON.stringify(result);
	}

	return result;
}
