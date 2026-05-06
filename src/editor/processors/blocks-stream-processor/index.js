import untruncateJson from 'untruncate-json';

import { createBlock } from '@wordpress/blocks';

export default class BlocksStreamProcessor {
	constructor(dispatch) {
		this.dispatch = dispatch;
		this.contentBuffer = '';
		this.sseBuffer = '';
		this.decoder = new TextDecoder();
		this.isJsonStarted = false;
		this.jsonBuffer = '';
		this.hasDispatchedBlocks = false;
		this.hasFinalDispatched = false;

		// Add throttled dispatch
		this.throttledDispatch = this.throttle(
			this.performDispatch.bind(this),
			200
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

			const tail = this.decoder.decode();
			if (tail) {
				await this.processChunkText(tail);
			}
		} catch (error) {
			this.handleError(error);
		}
	}

	async processChunk(value) {
		const text = this.decoder.decode(value, { stream: true });
		await this.processChunkText(text);
	}

	async processChunkText(text) {
		this.sseBuffer += text;
		const lines = this.sseBuffer.split('\n');
		this.sseBuffer = lines.pop() || '';

		for (const line of lines) {
			if (!line.startsWith('data: ')) continue;

			try {
				const dataContent = line.slice(6);
				const data = JSON.parse(dataContent);

				if (data.error) {
					this.handleError(data);
					break;
				} else if (data.done === true) {
					if (this.hasFinalDispatched) {
						return;
					}

					if (this.jsonBuffer) {
						await this.parseAndDispatchBlocks(
							this.jsonBuffer,
							true
						);
					} else {
						await this.parseFallbackContent(
							this.contentBuffer,
							true
						);
					}

					if (!this.hasDispatchedBlocks) {
						this.handleError({
							message:
								'AI response did not contain valid block JSON.',
						});
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
			const fenceMatch = this.contentBuffer.match(
				/```(?:json)?\s*([\s\S]*)/i
			);

			if (!fenceMatch) {
				await this.parseFallbackContent(this.contentBuffer, false);
				return;
			}

			this.isJsonStarted = true;
			await this.processJsonChunk(fenceMatch[1] || '');
			return;
		}

		await this.processJsonChunk(content);
	}

	async processJsonChunk(content) {
		if (content.includes('```')) {
			const endIndex = content.indexOf('```');
			this.jsonBuffer += content.substring(0, endIndex);
			await this.parseAndDispatchBlocks(this.jsonBuffer, true);
			this.isJsonStarted = false;
			this.jsonBuffer = '';
			return;
		}

		this.jsonBuffer += content;
		await this.tryParseIncomplete(this.jsonBuffer);
	}

	async tryParseIncomplete(jsonContent) {
		try {
			jsonContent = this.normalizeJsonContent(jsonContent);

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
			jsonContent = this.normalizeJsonContent(jsonContent);

			const blocks = JSON.parse(jsonContent);

			const transformedBlocks = Array.isArray(blocks)
				? blocks
						.map((block) => this.transformToBlock(block))
						.filter(Boolean)
				: [this.transformToBlock(blocks)].filter(Boolean);

			if (transformedBlocks.length > 0) {
				await this.dispatchBlocks(transformedBlocks, isFinal);
				return true;
			}
		} catch (e) {
			if (!isFinal) {
				await this.tryParseIncomplete(jsonContent);
			}
		}

		return false;
	}

	normalizeJsonContent(jsonContent) {
		return jsonContent.replace(/^\s*json\s*/i, '');
	}

	async parseFallbackContent(content, isFinal = false) {
		if (!content) {
			return false;
		}

		const start = content.indexOf('[');
		const end = content.lastIndexOf(']');

		if (start < 0 || end < start) {
			return false;
		}

		const candidate = content.slice(start, end + 1);
		return this.parseAndDispatchBlocks(candidate, isFinal);
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
		this.hasDispatchedBlocks = true;

		if (isFinal) {
			this.hasFinalDispatched = true;
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
