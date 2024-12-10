import { createBlock, getBlockType } from '@wordpress/blocks';

export default class BlocksStreamProcessor {
	constructor(dispatch) {
		this.dispatch = dispatch;
		this.buffer = '';
		this.contentBuffer = '';
		this.parsedBlocksCount = 0;
		this.blocks = [];
		this.decoder = new TextDecoder();
		this.lastUpdate = Date.now();
		this.isJsonStarted = false;

		this.CONFIG = {
			UPDATE_INTERVAL: 50,
			CHUNK_DELAY: 20,
		};
	}

	async processStream(reader) {
		try {
			while (true) {
				const { value, done } = await reader.read();
				if (done) break;
				await this.processChunk(value);
			}

			// Try to parse any remaining content
			if (this.contentBuffer) {
				await this.tryParseJson(this.contentBuffer, true);
			}
			await this.sendCompletion();
		} catch (error) {
			this.handleError(error);
		}
	}

	async processChunk(value) {
		const text = this.decoder.decode(value, { stream: true });
		const lines = text.split('\n');

		for (const line of lines) {
			if (line.startsWith('data: ')) {
				try {
					const data = JSON.parse(line.slice(6));
					if (data.content) {
						await this.processContent(data.content);
					}
				} catch (e) {
					// Skip invalid JSON lines
				}
			}
		}
	}

	async processContent(content) {
		// Detect start of JSON
		if (!this.isJsonStarted) {
			this.buffer += content;

			if (this.buffer.includes('```') && this.buffer.includes('json')) {
				this.isJsonStarted = true;
				this.buffer = '';
				this.contentBuffer = '';
			}
		}
		// Detect end of JSON
		else if (content.includes('```')) {
			this.isJsonStarted = false;
			await this.tryParseJson(this.contentBuffer, true);
		}
		// Accumulate JSON content
		else if (this.isJsonStarted) {
			this.contentBuffer += content;
			await this.tryParseJson(this.contentBuffer);
		}
	}

	async tryParseJson(jsonString, isFinal = false) {
		if (!jsonString.trim()) {
			return;
		}

		try {
			// Clean up the JSON string
			const cleanJson = jsonString
				.replace(/\n/g, '')
				.replace(/\s+/g, ' ')
				.trim();

			// Only proceed if we have a starting array
			if (!cleanJson.startsWith('[')) {
				return;
			}

			// Find complete blocks by matching brackets
			const blocks = [];
			let depth = 0;
			let currentBlock = '';
			let inBlock = false;

			for (let i = 0; i < cleanJson.length; i++) {
				const char = cleanJson[i];

				if (char === '{') {
					if (depth === 0) {
						inBlock = true;
					}
					depth++;
				}

				if (inBlock) {
					currentBlock += char;
				}

				if (char === '}') {
					depth--;
					if (depth === 0 && inBlock) {
						// We found a complete block
						try {
							const block = JSON.parse(currentBlock);
							if (block.name && block.attributes) {
								blocks.push(block);
							}
						} catch (e) {
							// eslint-disable-next-line no-console
							console.error('Block parse error:', e);
						}
						currentBlock = '';
						inBlock = false;
					}
				}
			}

			// Only process if we found new blocks
			if (blocks.length > this.parsedBlocksCount) {
				const validBlocks = this.validateAndTransformBlocks(blocks);
				this.parsedBlocksCount = blocks.length;
				await this.updateBlocks(validBlocks);
			}
		} catch (e) {
			if (isFinal) {
				// eslint-disable-next-line no-console
				console.error('JSON parse error:', e);
			}
		}
	}

	validateAndTransformBlocks(blocks) {
		return blocks.map((block) => {
			try {
				// Validate block structure
				if (!block?.name || !block?.attributes) {
					// eslint-disable-next-line no-console
					console.error('Invalid block structure:', block);
					return createBlock('core/paragraph', {
						content: 'Invalid block content',
					});
				}

				// Verify block type exists
				const blockType = getBlockType(block.name);
				if (!blockType) {
					// eslint-disable-next-line no-console
					console.error('Unknown block type:', block.name);
					return createBlock('core/paragraph', {
						content: `Unknown block type: ${block.name}`,
					});
				}

				// Handle innerBlocks recursively
				const innerBlocks = Array.isArray(block.innerBlocks)
					? this.validateAndTransformBlocks(block.innerBlocks)
					: [];

				// Create block with validated data
				return createBlock(block.name, block.attributes, innerBlocks);
			} catch (error) {
				// eslint-disable-next-line no-console
				console.error('Block validation error:', error, block);
				return createBlock('core/paragraph', {
					content: 'Error processing block',
				});
			}
		});
	}

	async updateBlocks(validBlocks) {
		const now = Date.now();
		if (now - this.lastUpdate >= this.CONFIG.UPDATE_INTERVAL) {
			this.blocks = validBlocks;

			this.dispatch({
				type: 'REQUEST_AI_CHUNK',
				payload: {
					response: validBlocks,
					progress: {
						charsProcessed: this.contentBuffer.length,
						blocksCount: validBlocks.length,
						isComplete: false,
					},
				},
			});

			this.lastUpdate = now;
			await new Promise((resolve) =>
				setTimeout(resolve, this.CONFIG.CHUNK_DELAY)
			);
		}
	}

	async sendCompletion() {
		this.dispatch({
			type: 'REQUEST_AI_SUCCESS',
			payload: {
				response: this.blocks,
				progress: {
					charsProcessed: this.contentBuffer.length,
					blocksCount: this.blocks.length,
					isComplete: true,
				},
			},
		});
	}

	handleError(error) {
		// eslint-disable-next-line no-console
		console.error('Stream processor error:', error);
		this.dispatch({
			type: 'REQUEST_AI_ERROR',
			payload: error.message,
		});
	}
}
