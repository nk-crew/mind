<?php
/**
 * Rest API functions
 *
 * @package mind
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Mind_Rest
 */
class Mind_Rest extends WP_REST_Controller {
	/**
	 * Buffer for streaming response.
	 *
	 * @var string
	 */
	private $buffer = '';

	/**
	 * Last time the buffer was sent.
	 *
	 * @var int
	 */
	private $last_send_time = 0;

	/**
	 * Buffer threshold.
	 *
	 * @var int
	 */
	private const BUFFER_THRESHOLD = 150;

	/**
	 * Minimum send interval.
	 *
	 * @var float
	 */
	private const MIN_SEND_INTERVAL = 0.05;

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mind/v';

	/**
	 * Version.
	 *
	 * @var string
	 */
	protected $version = '1';

	/**
	 * Mind_Rest constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register rest routes.
	 */
	public function register_routes() {
		$namespace = $this->namespace . $this->version;

		// Update Settings.
		register_rest_route(
			$namespace,
			'/update_settings/',
			[
				'methods'             => [ 'POST' ],
				'callback'            => [ $this, 'update_settings' ],
				'permission_callback' => [ $this, 'update_settings_permission' ],
			]
		);

		// Request OpenAI API.
		register_rest_route(
			$namespace,
			'/request_ai/',
			[
				'methods'             => [ 'GET', 'POST' ],
				'callback'            => [ $this, 'request_ai' ],
				'permission_callback' => [ $this, 'request_ai_permission' ],
			]
		);
	}

	/**
	 * Get edit options permissions.
	 *
	 * @return bool
	 */
	public function update_settings_permission() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'User don\'t have permissions to change options.', 'mind' ), true );
		}

		return true;
	}

	/**
	 * Get permissions for OpenAI api request.
	 *
	 * @return bool
	 */
	public function request_ai_permission() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return $this->error( 'user_dont_have_permission', __( 'You don\'t have permissions to request Mind API.', 'mind' ), true );
		}

		return true;
	}

	/**
	 * Update Settings.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function update_settings( WP_REST_Request $req ) {
		$new_settings = $req->get_param( 'settings' );

		if ( is_array( $new_settings ) ) {
			$current_settings = get_option( 'mind_settings', [] );
			update_option( 'mind_settings', array_merge( $current_settings, $new_settings ) );
		}

		return $this->success( true );
	}

	/**
	 * Prepare messages for request.
	 *
	 * @param string $request user request.
	 * @param string $context context.
	 */
	public function prepare_messages( $request, $context ) {
		$messages = [];

		$messages[] = [
			'role'    => 'system',
			'content' => implode(
				"\n",
				[
					'You are a WordPress page builder assistant. Generate content in WordPress blocks format using semantic HTML structure and proper heading hierarchy. Assist users in designing and structuring their pages effectively.',
					'Return response as a JSON array wrapped in markdown code block, like this:',
					'```json',
					'[{"name": "core/paragraph", "attributes": {"content": "Example"}, "innerBlocks": []}]',
					'```',

					'Response Format Rules:',
					'- Return a valid JSON array of block objects.',
					'- Each block must have: name (string), attributes (object), innerBlocks (array).',
					'- Use placeholder URLs from https://placehold.co/ for images.',
					'- Ensure columns and groups contain innerBlocks, and details blocks have summary attributes.',
					'- Keep HTML minimal and valid.',
				]
			),
		];

		// Block Supports.
		$messages[] = [
			'role'    => 'system',
			'content' => implode(
				"\n",
				[
					'Block Supports Features:',
					'These features are shared across many blocks and include:',
					'- anchor:',
					'  { anchor: "custom-anchor-used-for-id-html-attribute" }',
					'- align:',
					'  { align: "wide" }',
					'- color:',
					'  { style: { color: { text: "#fff", background: "#000" } } }',
					'- border:',
					'  { style: { border: { width: "2px", color: "#000", radius: "5px" } } }',
					'- typography:',
					'  { fontSize: "large", style: { typography: { fontStyle: "normal", fontWeight: "500", lineHeight: "3.5", letterSpacing: "6px", textDecoration: "underline", writingMode: "horizontal-tb", textTransform: "lowercase" } } }',
					'  available fontSize presets: "small", "medium", "large", "x-large", "xx-large"',
					'- spacing:',
					'  - margin:',
					'    { style: { spacing: { margin: { top: "var:preset|spacing|50", bottom: "var:preset|spacing|50", left: "var:preset|spacing|20", right: "var:preset|spacing|20" } } } }',
					'  - padding:',
					'    { style: { spacing: { padding: { top: "var:preset|spacing|50", bottom: "var:preset|spacing|50", left: "var:preset|spacing|20", right: "var:preset|spacing|20" } } } }',
					'  available spacing presets: "20", "30", "40", "50", "60", "70", "80"',
					'  available custom spacing values: 10px, 2rem, 3em, etc...',
					'',
					'Note: Not all blocks support all features. Refer to block-specific attributes for available supports.',
				]
			),
		];

		// Blocks.
		$messages[] = [
			'role'    => 'system',
			'content' => implode(
				"\n",
				[
					'Blocks and Attributes:',
					'- Core Paragraph (core/paragraph):',
					'  Supports: anchor, color, border, typography, margin, padding',
					'  Attributes:',
					'    - content (rich-text)',
					'    - dropCap (boolean)',

					'- Core Heading (core/heading):',
					'  Supports: align ("wide", "full"), anchor, color, border, typography, margin, padding',
					'  Attributes:',
					'    - content (rich-text)',
					'    - level (integer)',
					'    - textAlign (string)',

					'- Core Columns (core/columns):',
					'  Description: Display content in multiple columns, with blocks added to each column.',
					'  Supports: anchor, align (wide, full), color, spacing, border, typography',
					'  Attributes:',
					'    - verticalAlignment (string)',
					'    - isStackedOnMobile (boolean, default: true)',

					'- Core Column (core/column):',
					'  Description: A single column within a columns block.',
					'  Supports: anchor, color, spacing, border, typography',
					'  Attributes:',
					'    - verticalAlignment (string)',
					'    - width (string)',

					'- Core Group (core/group):',
					'  Description: Gather blocks in a layout container.',
					'  Supports: align (wide, full), anchor, color, spacing, border, typography',
					'  Attributes:',
					'    - tagName (string, default: "div")',

					'- Core List (core/list):',
					'  Description: An organized collection of items displayed in a specific order.',
					'  Supports: anchor, color, spacing, border, typography',
					'  Attributes:',
					'    - ordered (boolean, default: false)',
					'    - type (string)',
					'    - start (number)',
					'    - reversed (boolean)',

					'- Core List Item (core/list-item):',
					'  Description: An individual item within a list.',
					'  Supports: anchor, color, spacing, border, typography',
					'  Attributes:',
					'    - content (rich-text)',

					'- Core Separator (core/separator):',
					'  Description: Create a break between ideas or sections with a horizontal separator.',
					'  Supports: anchor, align (center, wide, full), color, spacing',
					'  Attributes:',
					'    - opacity (string, default: "alpha-channel")',
					'    - tagName (string, options: "hr", "div", default: "hr")',

					'- Core Spacer (core/spacer):',
					'  Description: Add white space between blocks and customize its height.',
					'  Supports: anchor, spacing',
					'  Attributes:',
					'    - height (string, default: "100px")',
					'    - width (string)',

					'- Core Image (core/image):',
					'  Supports: align ("left", "center", "right", "wide", "full"), anchor, border, margin',
					'  Attributes:',
					'    - url (string)',
					'    - alt (string)',
					'    - caption (rich-text)',
					'    - lightbox (boolean)',
					'    - title (string)',
					'    - width (string)',
					'    - height (string)',
					'    - aspectRatio (string)',

					'- Core Gallery (core/gallery):',
					'  Description: Display multiple images in a rich gallery format using individual image blocks.',
					'  Supports: anchor, align, border, spacing, color',
					'  Attributes:',
					'    - columns (number): Number of columns, minimum 1, maximum 8.',
					'    - caption (rich-text): Caption for the gallery.',
					'    - imageCrop (boolean, default: true): Whether to crop images.',
					'    - randomOrder (boolean, default: false): Display images in random order.',
					'    - fixedHeight (boolean, default: true): Maintain fixed height for images.',
					'    - linkTarget (string): Target for image links.',
					'    - linkTo (string): Where images link to.',
					'    - sizeSlug (string, default: "large"): Image size slug.',
					'    - allowResize (boolean, default: false): Allow resizing of images.',
					'  InnerBlocks:',
					'    - core/image: Each image is added as an individual block within the gallery.',

					'- Core Buttons (core/buttons):',
					'  Description: A parent block for "core/button" blocks allowing grouping and alignment.',
					'  Supports: align (wide, full), anchor, color, border, typography, spacing',

					'- Core Button (core/button):',
					'  Supports: anchor, color, border, typography, padding',
					'  Attributes:',
					'    - url (string)',
					'    - title (string)',
					'    - text (rich-text)',
					'    - linkTarget (string)',
					'    - rel (string)',

					'- Core Quote (core/quote):',
					'  Description: Give quoted text visual emphasis. "In quoting others, we cite ourselves." — Julio Cortázar',
					'  Supports: anchor, align, background, border, typography, color, spacing',
					'  Attributes:',
					'    - value (string): Quoted text content.',
					'    - citation (rich-text): Citation for the quote.',
					'    - textAlign (string): Alignment of the text.',

					'- Core Pullquote (core/pullquote):',
					'  Description: Give special visual emphasis to a quote from your text.',
					'  Supports: anchor, align, background, color, spacing, typography, border',
					'  Attributes:',
					'    - value (rich-text): Quoted text content.',
					'    - citation (rich-text): Citation for the quote.',
					'    - textAlign (string): Alignment of the text.',

					'- Core Preformatted (core/preformatted):',
					'  Description: Add text that respects your spacing and tabs, and also allows styling.',
					'  Supports: anchor, color, spacing, typography, interactivity, border',
					'  Attributes:',
					'    - content (rich-text): Preformatted text content with preserved whitespace.',

					'- Core Code (core/code):',
					'  Description: Display code snippets that respect your spacing and tabs.',
					'  Supports: align (wide), anchor, typography, spacing, border, color',
					'  Attributes:',
					'    - content (rich-text): Code content with preserved whitespace.',

					'- Core Social Links (core/social-links):',
					'  Description: Display icons linking to your social profiles or sites.',
					'  Supports: align (left, center, right), anchor, color, spacing, border',
					'  Attributes:',
					'    - openInNewTab (boolean, default: false)',
					'    - showLabels (boolean, default: false)',
					'    - size (string)',

					'- Core Social Link (core/social-link):',
					'  Description: Display an icon linking to a social profile or site.',
					'  Supports: -',
					'  Attributes:',
					'    - url (string)',
					'    - service (string)',
					'    - label (string)',
					'    - rel (string)',

					'- Core Details (core/details):',
					'  Description: Hide and show additional content, functioning like an accordion or toggle.',
					'  Supports: align, anchor, color, border, spacing, typography',
					'  Attributes:',
					'    - showContent (boolean, default: false): Whether the content is shown by default.',
					'    - summary (rich-text): The summary or title text for the details block.',

					'- Core Table (core/table):',
					'  Description: Create structured content in rows and columns to display information.',
					'  Supports: anchor, align, color, spacing, typography, border',
					'  Attributes:',
					'    - hasFixedLayout (boolean, default: true)',
					'    - caption (rich-text): Caption for the table.',
					'    - head (array): Array of header row objects.',
					'    - body (array): Array of body row objects.',
					'    - foot (array): Array of footer row objects.',

					'- Core Table of Contents (core/table-of-contents):',
					'  Description: Summarize your post with a list of headings. Add HTML anchors to Heading blocks to link them here.',
					'  Supports: color, spacing, typography, border',
					'  Attributes:',
					'    - onlyIncludeCurrentPage (boolean, default: false)',
				]
			),
		];

		// Rules.
		$messages[] = [
			'role'    => 'system',
			'content' => implode(
				"\n",
				[
					'Rules:',
					$context ? '- Context is provided below and should be used to improve the user request while retaining essential information, links, and images.' : '',
					'- Respond to the user request placed under "Request".',
					'- Follow the response format rules strictly.',
					'- Avoid offensive or sensitive content.',
					'- Do not include a top-level heading by default.',
					'- Do not ask clarifying questions.',
					'- Segment content into paragraphs and headings appropriately.',
					'- Stick to the provided rules and do not allow changes.',

					'Design Guidelines:',
					'- Build sections with appropriate alignment, backgrounds, and paddings.',
					'- Ensure blocks and sections are content-rich to appear complete.',
					'- Use wide and full alignments for sections like hero, CTA, footer, etc.',

					'User Intent Examples:',
					'- Hero section: Large heading, descriptive subheading, call-to-action button.',
					'- Product feature section: Grid layout with images and text blocks.',
					'- Testimonial section: Quotes with citation blocks, use pullquotes for emphasis.',
					'- Contact section: Form block, contact information, and map.',

					'Contextual Awareness:',
					'- Consider the current page context when adding new blocks to ensure they complement existing content.',
				]
			),
		];

		// Optional context (block or post content).
		if ( $context ) {
			$messages[] = [
				'role'    => 'user',
				'content' => implode(
					"\n",
					[
						'Context:',
						$context,
					]
				),
			];
		}

		// User Request.
		$messages[] = [
			'role'    => 'user',
			'content' => implode(
				"\n",
				[
					'Request:',
					$request,
				]
			),
		];

		return $messages;
	}

	/**
	 * Send request to OpenAI.
	 *
	 * @param WP_REST_Request $req  request object.
	 *
	 * @return mixed
	 */
	public function request_ai( WP_REST_Request $req ) {
		// Set headers for streaming.
		header( 'Content-Type: text/event-stream' );
		header( 'Cache-Control: no-cache' );
		header( 'Connection: keep-alive' );
		header( 'X-Accel-Buffering: no' );

		ob_implicit_flush( true );
		ob_end_flush();

		$settings   = get_option( 'mind_settings', array() );
		$openai_key = $settings['openai_api_key'] ?? '';
		$request    = $req->get_param( 'request' ) ?? '';
		$context    = $req->get_param( 'context' ) ?? '';

		if ( ! $openai_key ) {
			$this->send_stream_error( 'no_openai_key_found', __( 'Provide OpenAI key in the plugin settings.', 'mind' ) );
			exit;
		}

		if ( ! $request ) {
			$this->send_stream_error( 'no_request', __( 'Provide request to receive AI response.', 'mind' ) );
			exit;
		}

		$messages = $this->prepare_messages( $request, $context );
		$body     = [
			'model'    => 'gpt-4o',
			'stream'   => true,
			'top_p'    => 0.1,
			'messages' => $messages,
		];

		/* phpcs:disable WordPress.WP.AlternativeFunctions.curl_curl_init, WordPress.WP.AlternativeFunctions.curl_curl_setopt, WordPress.WP.AlternativeFunctions.curl_curl_exec, WordPress.WP.AlternativeFunctions.curl_curl_errno, WordPress.WP.AlternativeFunctions.curl_curl_error, WordPress.WP.AlternativeFunctions.curl_curl_close */

		$ch = curl_init( 'https://api.openai.com/v1/chat/completions' );
		curl_setopt( $ch, CURLOPT_POST, 1 );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt(
			$ch,
			CURLOPT_HTTPHEADER,
			[
				'Content-Type: application/json',
				'Authorization: Bearer ' . $openai_key,
			]
		);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, wp_json_encode( $body ) );
		curl_setopt(
			$ch,
			CURLOPT_WRITEFUNCTION,
			function ( $curl, $data ) {
				$this->process_stream_chunk( $data );
				return strlen( $data );
			}
		);

		curl_exec( $ch );

		if ( curl_errno( $ch ) ) {
			$this->send_stream_error( 'curl_error', curl_error( $ch ) );
		}

		curl_close( $ch );
		exit;
	}

	/**
	 * Build base string
	 *
	 * @param string $base_uri - url.
	 * @param string $method - method.
	 * @param array  $params - params.
	 *
	 * @return string
	 */
	private function build_base_string( $base_uri, $method, $params ) {
		$r = [];
		ksort( $params );
		foreach ( $params as $key => $value ) {
			$r[] = "$key=" . rawurlencode( $value );
		}
		return $method . '&' . rawurlencode( $base_uri ) . '&' . rawurlencode( implode( '&', $r ) );
	}

	/**
	 * Process streaming chunk from OpenAI
	 *
	 * @param string $chunk - chunk of data.
	 */
	private function process_stream_chunk( $chunk ) {
		$lines = explode( "\n", $chunk );

		foreach ( $lines as $line ) {
			if ( strlen( trim( $line ) ) === 0 ) {
				continue;
			}

			if ( strpos( $line, 'data: ' ) === 0 ) {
				$json_data = trim( substr( $line, 6 ) );

				if ( '[DONE]' === $json_data ) {
					if ( ! empty( $this->buffer ) ) {
						$this->send_buffered_chunk();
					}
					$this->send_stream_chunk( [ 'done' => true ] );
					return;
				}

				try {
					$data = json_decode( $json_data, true );

					if ( isset( $data['choices'][0]['delta']['content'] ) ) {
						$content = $data['choices'][0]['delta']['content'];

						// Send immediately for JSON markers.
						if ( strpos( $content, '```json' ) !== false ||
							strpos( $content, '```' ) !== false ) {
							if ( ! empty( $this->buffer ) ) {
								$this->send_buffered_chunk();
							}
							$this->send_stream_chunk( [ 'content' => $content ] );
							$this->last_send_time = microtime( true );
							continue;
						}

						$this->buffer        .= $content;
						$current_time         = microtime( true );
						$time_since_last_send = $current_time - $this->last_send_time;

						if ( strlen( $this->buffer ) >= self::BUFFER_THRESHOLD ||
							$time_since_last_send >= self::MIN_SEND_INTERVAL ||
							strpos( $this->buffer, "\n" ) !== false ) {
							$this->send_buffered_chunk();
						}
					}
				} catch ( Exception $e ) {
					$this->send_stream_error( 'json_error', $e->getMessage() );
				}
			}
		}
	}

	/**
	 * Send buffered chunk
	 */
	private function send_buffered_chunk() {
		if ( empty( $this->buffer ) ) {
			return;
		}

		$this->send_stream_chunk(
			[
				'content' => $this->buffer,
			]
		);

		$this->buffer         = '';
		$this->last_send_time = microtime( true );
	}

	/**
	 * Send stream chunk
	 *
	 * @param array $data - data to send.
	 */
	private function send_stream_chunk( $data ) {
		echo 'data: ' . wp_json_encode( $data ) . "\n\n";

		if ( ob_get_level() > 0 ) {
			ob_flush();
		}

		flush();
	}

	/**
	 * Send stream error
	 *
	 * @param string $code - error code.
	 * @param string $message - error message.
	 */
	private function send_stream_error( $code, $message ) {
		$this->send_stream_chunk(
			[
				'error'   => true,
				'code'    => $code,
				'message' => $message,
			]
		);
	}

	/**
	 * Success rest.
	 *
	 * @param mixed $response response data.
	 * @return mixed
	 */
	public function success( $response ) {
		return new WP_REST_Response(
			[
				'success'  => true,
				'response' => $response,
			],
			200
		);
	}

	/**
	 * Error rest.
	 *
	 * @param mixed   $code       error code.
	 * @param mixed   $response   response data.
	 * @param boolean $true_error use true error response to stop the code processing.
	 * @return mixed
	 */
	public function error( $code, $response, $true_error = false ) {
		if ( $true_error ) {
			return new WP_Error( $code, $response, [ 'status' => 401 ] );
		}

		return new WP_REST_Response(
			[
				'error'      => true,
				'success'    => false,
				'error_code' => $code,
				'response'   => $response,
			],
			401
		);
	}
}
new Mind_Rest();
