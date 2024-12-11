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
		this.renderDelay = 150;

		// In Nginx server we have the true steaming experience and receive chunks in JS as soon as they are available.
		// In Apache server we receive the butches of chunks and then JS process them. So, we can use this flag to detect the mode.
		this.isBatchMode = false;
	}

	async processStream(reader) {
		try {
			while (true) {
				const { value, done } = await reader.read();
				if (done) break;

				// Detect batch mode by analyzing first chunks timing
				if (!this.firstChunkTime) {
					this.firstChunkTime = Date.now();
				} else if (!this.secondChunkTime) {
					this.secondChunkTime = Date.now();
					// If chunks come with big delay, it's probably Apache with batching
					this.isBatchMode =
						this.secondChunkTime - this.firstChunkTime > 500;
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
				const parts = this.contentBuffer.split('```json');
				this.jsonBuffer = parts[1] || '';
			}
		} else if (content.includes('```')) {
			const endIndex = content.indexOf('```');
			this.jsonBuffer += content.substring(0, endIndex);
			await this.parseAndDispatchBlocks(this.jsonBuffer, true);
			this.isJsonStarted = false;
			this.jsonBuffer = '';
		} else {
			this.jsonBuffer += content;
			if (!this.isBatchMode) {
				// For Nginx - process immediately
				await this.tryParseIncomplete(this.jsonBuffer);
			} else {
				// For Apache - add delay for typing effect
				await this.tryParseWithDelay(this.jsonBuffer);
			}
		}
	}

	async tryParseWithDelay(jsonContent) {
		const now = Date.now();
		if (now - this.lastUpdate >= this.renderDelay) {
			await this.tryParseIncomplete(jsonContent);
		}
	}

	async tryParseIncomplete(jsonContent) {
		try {
			const completedJson = this.balanceJsonStructure(jsonContent);
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

	balanceJsonStructure(partial) {
		// If empty or not starting with [, return minimal valid JSON
		if (!partial || !partial.trim().startsWith('[')) {
			return '[]';
		}

		let result = '';
		let inString = false;
		let inEscape = false;
		let openBrackets = 0;
		let openBraces = 0;
		let lastPropertyName = '';
		let inPropertyName = false;
		let currentProperty = '';
		const arrayStack = [];
		const braceStack = [];

		// Process character by character
		for (let i = 0; i < partial.length; i++) {
			const char = partial[i];
			const nextChar = partial[i + 1];
			result += char;

			// Handle escape sequences
			if (char === '\\\\' && inString) {
				inEscape = true;
				continue;
			}

			if (inEscape) {
				inEscape = false;
				continue;
			}

			// Track string boundaries
			if (char === '"' && !inEscape) {
				if (!inString) {
					inString = true;

					// Check if this is a property name
					if (!inPropertyName && nextChar === ':') {
						inPropertyName = true;
					}
				} else {
					inString = false;

					if (inPropertyName) {
						lastPropertyName = currentProperty;
						currentProperty = '';
						inPropertyName = false;
					}
				}
			} else if (inString) {
				if (inPropertyName) {
					currentProperty += char;
				}
			}

			// Only count structure characters outside strings
			if (!inString) {
				if (char === '[') {
					openBrackets++;
					arrayStack.push(i);
				}
				if (char === ']') {
					openBrackets--;
					if (arrayStack.length > 0) {
						arrayStack.pop();
					}
				}
				if (char === '{') {
					openBraces++;
					braceStack.push(i);
				}
				if (char === '}') {
					openBraces--;
					if (braceStack.length > 0) {
						braceStack.pop();
					}
				}
			}
		}

		// Complete the structure
		let completed = result;

		// Handle unclosed strings
		if (inString) {
			completed += '"';
		}

		// Complete property values if needed
		if (lastPropertyName === 'content' && inString) {
			completed += '"';
		}

		// Complete objects and arrays from inside out
		while (openBraces > 0) {
			completed += '}';
			openBraces--;
		}

		while (openBrackets > 0) {
			completed += ']';
			openBrackets--;
		}

		// Validate and fix common issues
		try {
			JSON.parse(completed);
			return completed;
		} catch (e) {
			// Try to fix common issues
			let fixed = completed;

			// Fix unclosed content property
			const contentMatch = fixed.match(
				/"content"\\s*:\\s*"([^"]*)(?:[^"]*)?$/
			);
			if (contentMatch) {
				const contentEndIndex =
					fixed.lastIndexOf(contentMatch[1]) + contentMatch[1].length;
				fixed =
					fixed.substring(0, contentEndIndex) +
					'"' +
					fixed.substring(contentEndIndex);
			}

			// Fix missing commas between blocks
			fixed = fixed.replace(/}(\\s*){/g, '},\\n{');

			// Fix trailing commas
			fixed = fixed.replace(/,(\\s*[}\\]])/g, '$1');

			try {
				JSON.parse(fixed);
				return fixed;
			} catch (e2) {
				// If still invalid, try to extract valid parts
				const blockMatch = fixed.match(
					/\[\s*{[^{]*"name"\s*:\s*"[^"]+"\s*,\s*"attributes"\s*:\s*{[^}]+}/
				);
				if (blockMatch) {
					return blockMatch[0] + '}]';
				}

				// Return minimal valid structure as last resort
				return '[{"name":"core/paragraph","attributes":{"content":""},"innerBlocks":[]}]';
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

			// Recursively process attributes
			const processedAttributes = this.processAttributes(attributes);

			return createBlock(
				blockData.name,
				processedAttributes,
				innerBlocks
			);
		} catch (error) {
			// console.log('Error transforming block:', error);
			return null;
		}
	}

	processAttributes(attributes) {
		const processedAttributes = {};

		for (const [key, value] of Object.entries(attributes)) {
			if (typeof value === 'object' && value !== null) {
				processedAttributes[key] = this.processAttributes(value);
			} else {
				processedAttributes[key] = value;
			}
		}

		return processedAttributes;
	}

	async dispatchBlocks(blocks, isFinal = false) {
		const now = Date.now();
		const timeSinceLastUpdate = now - this.lastUpdate;

		// Apply render delay only in batch mode or if it's too soon
		if (
			(this.isBatchMode || timeSinceLastUpdate < this.renderDelay) &&
			!isFinal
		) {
			await new Promise((resolve) =>
				setTimeout(resolve, this.renderDelay - timeSinceLastUpdate)
			);
		}

		this.lastDispatchedBlocks = blocks;
		this.lastUpdate = Date.now();

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

	handleError(error) {
		// console.error('Stream processor error:', error);
		this.dispatch({
			type: 'REQUEST_AI_ERROR',
			payload: error.message,
		});
	}
}
