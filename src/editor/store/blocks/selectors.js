// Shared so the selector hands back the same reference every time. A fresh `[]` per call is
// a new identity, which makes `useSelect` report the store as changed on every render.
const EMPTY_LIST = Object.freeze([]);

export function getHighlightBlocks(state) {
	return state?.highlightBlocks || EMPTY_LIST;
}
