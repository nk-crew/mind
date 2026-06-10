<?php
/**
 * Plugin AI request functions.
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mind AI Request class.
 */
class Mind_AI_Request {
	/**
	 * Handle an AI request and stream the response.
	 *
	 * @param string $request Request text.
	 * @param string $selected_blocks Selected blocks context.
	 * @param string $page_blocks Page blocks context.
	 * @param string $page_context Page context.
	 *
	 * @return void
	 */
	public function handle( $request, $selected_blocks = '', $page_blocks = '', $page_context = '' ) {
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		ob_implicit_flush( true );

		while ( ob_get_level() > 0 ) {
			ob_end_flush();
		}

		$stream = new Mind_AI_Stream();

		if ( ! $request ) {
			$stream->send_stream_error( 'no_request', __( 'Provide request to receive AI response.', 'mind' ) );
			exit;
		}

		$connected_model = Mind_AI_Settings::get_connected_model();

		if ( ! $connected_model ) {
			$stream->send_stream_error(
				'no_model_connected',
				__(
					'Mind could not resolve an AI provider and model. Connect a provider in WordPress Connectors or choose one in Mind settings.',
					'mind'
				)
			);
			exit;
		}

		$messages = $this->prepare_messages( $request, $selected_blocks, $page_blocks, $page_context );

		$stream->execute( $connected_model, $messages );

		exit;
	}
	/**
	 * Prepare messages for request.
	 *
	 * @param string $user_query user query.
	 * @param string $selected_blocks selected blocks context.
	 * @param string $page_blocks page blocks context.
	 * @param string $page_context page context.
	 */
	public function prepare_messages( $user_query, $selected_blocks, $page_blocks, $page_context ) {
		$user_query = '<user_query>' . $user_query . '</user_query>';

		if ( $selected_blocks ) {
			$user_query .= "\n";
			$user_query .= '<selected_blocks_context>' . $selected_blocks . '</selected_blocks_context>';
		}
		if ( $page_blocks ) {
			$user_query .= "\n";
			$user_query .= '<page_blocks_context>' . $page_blocks . '</page_blocks_context>';
		}
		if ( $page_context ) {
			$user_query .= "\n";
			$user_query .= '<page_context>' . $page_context . '</page_context>';
		}

		return [
			[
				'role'    => 'system',
				'content' => Mind_Prompts::get_system_prompt(),
			],
			[
				'role'    => 'user',
				'content' => $user_query,
			],
		];
	}
}
