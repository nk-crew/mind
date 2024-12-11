import untruncateJson from 'untruncate-json';

import { createBlock } from '@wordpress/blocks';

export default class BlocksStreamProcessor {
	constructor(dispatch) {
		this.dispatch = dispatch;
		this.contentBuffer = '';
		this.decoder = new TextDecoder();
		this.isJsonStarted = false;
		this.jsonBuffer = '';

		// Add throttled dispatch
		this.throttledDispatch = this.throttle(
			this.performDispatch.bind(this),
			150
		);
	}

	throttle(func, limit) {
		let inThrottle;
		return function (...args) {
			if (!inThrottle) {
				func.apply(this, args);
				inThrottle = true;
				setTimeout(() => (inThrottle = false), limit);
			}
		};
	}

	async processStream(reader) {
		try {
			while (true) {
				const { value, done } = await reader.read();
				if (done) break;

				await this.processChunk(value);
			}
		} catch (error) {
			this.handleError(error);
		}
	}

	async processChunk(value) {
		const text = this.decoder.decode(value, { stream: true });
		const lines = text.split('\n');

		for (const line of lines) {
			if (!line.startsWith('data: ')) continue;

			try {
				const dataContent = line.slice(6);
				const data = JSON.parse(dataContent);

				if (data.done === true) {
					if (this.jsonBuffer) {
						await this.parseAndDispatchBlocks(
							this.jsonBuffer,
							true
						);
					}
					return;
				}

				if (!data.content) continue;

				await this.processContent(data.content);
			} catch (e) {
				// console.error('Error processing line:', e);
			}
		}
	}

	async processContent(content) {
		this.contentBuffer += content;

		if (!this.isJsonStarted) {
			if (this.contentBuffer.includes('```json')) {
				this.isJsonStarted = true;
				const [, json] = this.contentBuffer.split('```json');
				this.jsonBuffer = json || '';
			}
		} else if (content.includes('```')) {
			const endIndex = content.indexOf('```');
			this.jsonBuffer += content.substring(0, endIndex);
			await this.parseAndDispatchBlocks(this.jsonBuffer, true);
			this.isJsonStarted = false;
			this.jsonBuffer = '';
		} else {
			this.jsonBuffer += content;
			await this.tryParseIncomplete(this.jsonBuffer);
		}
	}

	async tryParseIncomplete(jsonContent) {
		try {
			// If empty or not starting with [, return minimal valid JSON
			if (!jsonContent || !jsonContent.trim().startsWith('[')) {
				return;
			}

			const completedJson = untruncateJson(jsonContent);

			if (!completedJson) {
				return;
			}

			const parsed = JSON.parse(completedJson);

			if (Array.isArray(parsed) && parsed.length > 0) {
				const transformedBlocks = parsed
					.map((block) => this.transformToBlock(block))
					.filter(Boolean);

				if (transformedBlocks.length > 0) {
					await this.dispatchBlocks(transformedBlocks, false);
				}
			}
		} catch (e) {
			// Expected error for incomplete JSON
		}
	}

	async parseAndDispatchBlocks(jsonContent, isFinal = false) {
		try {
			const blocks = JSON.parse(jsonContent);

			const transformedBlocks = Array.isArray(blocks)
				? blocks
						.map((block) => this.transformToBlock(block))
						.filter(Boolean)
				: [this.transformToBlock(blocks)].filter(Boolean);

			if (transformedBlocks.length > 0) {
				await this.dispatchBlocks(transformedBlocks, isFinal);
			}
		} catch (e) {
			if (!isFinal) {
				await this.tryParseIncomplete(jsonContent);
			}
		}
	}

	transformToBlock(blockData) {
		if (!blockData?.name) return null;

		try {
			const innerBlocks = Array.isArray(blockData.innerBlocks)
				? blockData.innerBlocks
						.map((inner) => this.transformToBlock(inner))
						.filter(Boolean)
				: [];

			const attributes = blockData.attributes || {};

			return createBlock(blockData.name, attributes, innerBlocks);
		} catch (error) {
			// console.log('Error transforming block:', error);
			return null;
		}
	}

	performDispatch(blocks, isFinal) {
		this.dispatch({
			type: isFinal ? 'REQUEST_AI_SUCCESS' : 'REQUEST_AI_CHUNK',
			payload: {
				response: blocks,
				progress: {
					charsProcessed: this.contentBuffer.length,
					blocksCount: blocks.length,
					isComplete: isFinal,
				},
			},
		});
	}

	async dispatchBlocks(blocks, isFinal = false) {
		if (isFinal) {
			// Final dispatch should always happen immediately
			this.performDispatch(blocks, true);
		} else {
			// Use throttled dispatch for streaming updates
			this.throttledDispatch(blocks, false);
		}
	}

	handleError(error) {
		// console.error('Stream processor error:', error);
		this.dispatch({
			type: 'REQUEST_AI_ERROR',
			payload: error.message,
		});
	}
}
