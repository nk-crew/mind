export default class AIStreamProcessor {
	constructor(dispatch) {
		this.dispatch = dispatch;
		this.buffer = '';
		this.responseText = '';
		this.decoder = new TextDecoder();
		this.lastUpdate = Date.now();
		this.updateQueue = [];
		this.isProcessing = false;

		// Configuration
		this.CONFIG = {
			// ms between chunks.
			CHUNK_DELAY: 30,
			// ms minimum between updates.
			UPDATE_INTERVAL: 50,
			// number of chunks to batch.
			BATCH_SIZE: 3,
			// maximum queue size.
			MAX_QUEUE_SIZE: 100,
			TYPING_SPEED: {
				FAST: 20,
				MEDIUM: 35,
				SLOW: 50,
			},
		};
	}

	async processStream(reader) {
		try {
			this.startQueueProcessor();

			while (true) {
				const { value, done } = await reader.read();
				if (done) break;

				await this.processChunk(value);
			}

			await this.flushQueue();
			this.sendCompletion();
		} catch (error) {
			this.handleError(error);
		}
	}

	async processChunk(value) {
		const text = this.decoder.decode(value, { stream: true });
		const chunks = this.parseChunks(text);

		for (const chunk of chunks) {
			await this.queueUpdate(chunk);
		}
	}

	parseChunks(text) {
		const chunks = [];
		const lines = text.split('\n');

		for (const line of lines) {
			if (line.startsWith('data: ')) {
				try {
					const data = JSON.parse(line.slice(6));
					if (data.content) {
						chunks.push(data.content);
					}
				} catch (error) {
					// eslint-disable-next-line no-console
					console.debug('Chunk parse error:', error);
				}
			}
		}

		return chunks;
	}

	async queueUpdate(content) {
		this.updateQueue.push(content);

		// Prevent queue from growing too large
		if (this.updateQueue.length > this.CONFIG.MAX_QUEUE_SIZE) {
			await this.flushQueue();
		}
	}

	async startQueueProcessor() {
		if (this.isProcessing) return;

		this.isProcessing = true;
		while (this.updateQueue.length > 0) {
			const batch = this.updateQueue.splice(0, this.CONFIG.BATCH_SIZE);
			await this.processBatch(batch);
		}
		this.isProcessing = false;
	}

	async processBatch(batch) {
		const content = batch.join('');
		this.responseText += content;

		// Throttle updates
		const now = Date.now();
		if (now - this.lastUpdate >= this.CONFIG.UPDATE_INTERVAL) {
			this.dispatch({
				type: 'REQUEST_AI_CHUNK',
				payload: {
					content: this.responseText,
					progress: this.calculateProgress(),
				},
			});
			this.lastUpdate = now;
		}

		await new Promise((resolve) =>
			setTimeout(resolve, this.CONFIG.CHUNK_DELAY)
		);
	}

	calculateProgress() {
		// Implement your progress calculation logic
		return {
			charsProcessed: this.responseText.length,
			queueSize: this.updateQueue.length,
			isComplete: false,
		};
	}

	async flushQueue() {
		while (this.updateQueue.length > 0) {
			await this.startQueueProcessor();
		}
	}

	sendCompletion() {
		this.dispatch({
			type: 'REQUEST_AI_SUCCESS',
			payload: {
				content: this.responseText,
				progress: { isComplete: true },
			},
		});
	}

	handleError(error) {
		this.dispatch({
			type: 'REQUEST_AI_ERROR',
			payload: error.message,
		});
	}
}
