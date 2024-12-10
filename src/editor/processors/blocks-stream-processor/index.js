import { createBlock } from '@wordpress/blocks';

export default class BlocksStreamProcessor {
	constructor(dispatch) {
		this.dispatch = dispatch;
		this.contentBuffer = '';
		this.decoder = new TextDecoder();
		this.lastUpdate = Date.now();
		this.isJsonStarted = false;
		this.jsonBuffer = '';
		this.lastDispatchedBlocks = null;

		this.CONFIG = {
			UPDATE_INTERVAL: 50,
			CHUNK_DELAY: 20,
		};
	}

	async processStream(reader) {
		try {
			while (true) {
				const { value, done } = await reader.read();

				if (done) {
					break;
				}

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

				// Handle completion signal
				if (data.done === true) {
					// If we have any remaining content, parse and dispatch it
					if (this.jsonBuffer) {
						await this.parseAndDispatchBlocks(
							this.jsonBuffer,
							true
						);
					} else {
						// If no remaining content, dispatch the last known blocks as final
						await this.dispatchBlocks(
							this.lastDispatchedBlocks || [],
							true
						);
					}

					// Reset only processing state
					this.isJsonStarted = false;
					this.jsonBuffer = '';
					continue;
				}

				if (!data.content) continue;

				// Accumulate content
				this.contentBuffer += data.content;

				if (!this.isJsonStarted) {
					if (this.contentBuffer.includes('```json')) {
						this.isJsonStarted = true;
						const parts = this.contentBuffer.split('```json');
						this.jsonBuffer = parts[1] || '';
					}
				} else if (data.content.includes('```')) {
					const endIndex = data.content.indexOf('```');
					this.jsonBuffer += data.content.substring(0, endIndex);
					await this.parseAndDispatchBlocks(this.jsonBuffer, true);
					this.isJsonStarted = false;
					this.jsonBuffer = '';
				} else {
					this.jsonBuffer += data.content;
					await this.tryParseIncomplete(this.jsonBuffer);
				}
			} catch (e) {
				// console.log('Error processing line:', e);
			}
		}
	}

	async tryParseIncomplete(jsonContent) {
		try {
			const completedJson = this.balanceJsonStructure(jsonContent);
			const parsed = JSON.parse(completedJson);

			if (Array.isArray(parsed) && parsed.length > 0) {
				const blocks = parsed
					.map((block) => this.transformToBlock(block))
					.filter(Boolean);

				if (blocks.length > 0) {
					// Reuse dispatchBlocks for incomplete content
					await this.dispatchBlocks(blocks, false);
				}
			}
		} catch (e) {
			// console.log('Error in tryParseIncomplete:', e);
		}
	}

	balanceJsonStructure(partial) {
		let openBrackets = 0;
		let openBraces = 0;

		// Count opening and closing brackets
		for (const char of partial) {
			if (char === '[') openBrackets++;
			if (char === ']') openBrackets--;
			if (char === '{') openBraces++;
			if (char === '}') openBraces--;
		}

		// Complete the structure
		let completed = partial;
		while (openBraces > 0) {
			completed += '}';
			openBraces--;
		}
		while (openBrackets > 0) {
			completed += ']';
			openBrackets--;
		}

		return completed;
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
			// console.log('Error parsing complete JSON:', e);
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

			return createBlock(
				blockData.name,
				blockData.attributes || {},
				innerBlocks
			);
		} catch (error) {
			// console.log('Error transforming block:', error);
			return null;
		}
	}

	async dispatchBlocks(blocks, isFinal = false) {
		const now = Date.now();
		if (now - this.lastUpdate >= this.CONFIG.UPDATE_INTERVAL || isFinal) {
			// Store the last dispatched blocks
			this.lastDispatchedBlocks = blocks;

			// Single dispatch point for both streaming and completion
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

			this.lastUpdate = now;
			if (!isFinal) {
				await new Promise((resolve) =>
					setTimeout(resolve, this.CONFIG.CHUNK_DELAY)
				);
			}
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
