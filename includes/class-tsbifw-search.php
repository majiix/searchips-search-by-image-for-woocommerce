<?php
/**
 * REST API search handler and similarity logic.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class TSBIFW_Search {

	/**
	 * Singleton instance.
	 *
	 * @var TSBIFW_Search|null
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return TSBIFW_Search
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Registers hooks.
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_search_route' ) );
		// Shortcode to render search bar.
		add_shortcode( 'tsbifw_search_bar', array( $this, 'render_search_bar_shortcode' ) );
		// Script enqueuing for frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Modify search query loop for visual search tokens
		add_action( 'pre_get_posts', array( $this, 'modify_search_query' ) );
		add_filter( 'query_vars', array( $this, 'register_query_vars' ) );
		add_filter( 'posts_search', array( $this, 'clear_search_keyword_sql' ), 10, 2 );
	}

	/**
	 * Enqueue scripts and styles.
	 */
	public function enqueue_frontend_assets() {
		wp_register_script( 'tsbifw-cropperjs', TSBIFW_PLUGIN_URL . 'assets/js/cropper.min.js', array(), '2.1.1', true );
		wp_enqueue_style( 'tsbifw-frontend-css', TSBIFW_PLUGIN_URL . 'assets/css/frontend.css', array(), TSBIFW_VERSION );

		$left_val  = trim( get_option( 'tsbifw_camera_left', 'auto' ) );
		$right_val = trim( get_option( 'tsbifw_camera_right', '14px' ) );
		$bg_val    = trim( get_option( 'tsbifw_camera_bg_color', 'transparent' ) );
		$size_val  = trim( get_option( 'tsbifw_camera_icon_size', '20px' ) );

		if ( is_numeric( $size_val ) ) {
			$size_val .= 'px';
		}

		$size_num = (int) preg_replace( '/[^0-9]/', '', $size_val );
		if ( $size_num <= 0 ) {
			$size_num = 20;
		}
		$padding_val = ( $size_num + 25 ) . 'px';

		$custom_css = "
		.tsbifw-camera-trigger,
		.tsbifw-search-form .tsbifw-camera-trigger {
			left: " . esc_attr( $left_val ) . " !important;
			right: " . esc_attr( $right_val ) . " !important;
			background: " . esc_attr( $bg_val ) . " !important;
			background-color: " . esc_attr( $bg_val ) . " !important;
			width: " . esc_attr( $size_val ) . " !important;
			height: " . esc_attr( $size_val ) . " !important;
		}
		.tsbifw-camera-trigger svg,
		.tsbifw-search-form .tsbifw-camera-trigger svg {
			width: " . esc_attr( $size_val ) . " !important;
			height: " . esc_attr( $size_val ) . " !important;
		}
		";

		if ( 'transparent' !== $bg_val && '' !== $bg_val ) {
			$custom_css .= "
			.tsbifw-camera-trigger,
			.tsbifw-search-form .tsbifw-camera-trigger {
				padding: 4px !important;
				border-radius: 4px !important;
				box-sizing: border-box !important;
			}
			";
		}

		if ( 'auto' !== $left_val && '' !== $left_val && '0' !== $left_val && '0px' !== $left_val ) {
			$custom_css .= "
			.tsbifw-search-input-wrapper input[type='search'],
			.tsbifw-search-input-wrapper input.search-field,
			.tsbifw-search-form .search-field {
				padding-left: " . esc_attr( $padding_val ) . " !important;
				padding-right: 16px !important;
			}
			";
		}
		if ( 'auto' !== $right_val && '' !== $right_val && '0' !== $right_val && '0px' !== $right_val ) {
			$custom_css .= "
			.tsbifw-search-input-wrapper input[type='search'],
			.tsbifw-search-input-wrapper input.search-field,
			.tsbifw-search-form .search-field {
				padding-right: " . esc_attr( $padding_val ) . " !important;
			}
			";
		}

		wp_add_inline_style( 'tsbifw-frontend-css', $custom_css );

		wp_enqueue_script( 'tsbifw-frontend-js', TSBIFW_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), TSBIFW_VERSION, true );

		$enable_auto_inject = get_option( 'tsbifw_enable_auto_inject', 'yes' );
		$max_mb             = (int) get_option( 'tsbifw_max_upload_size', 2 );
		if ( $max_mb <= 0 ) {
			$max_mb = 2;
		}

		wp_localize_script(
			'tsbifw-frontend-js',
			'tsbifw_frontend_params',
			array(
				'search_endpoint' => esc_url_raw( rest_url( 'tsbifw/v1/search' ) ),
				'cropper_src'     => esc_url_raw( TSBIFW_PLUGIN_URL . 'assets/js/cropper.min.js' ),
				'auto_inject'     => ( 'yes' === $enable_auto_inject ),
				'nonce'           => wp_create_nonce( 'tsbifw_frontend_search' ),
				'max_upload_size' => $max_mb * 1024 * 1024,
				'strings'         => array(
					'modal_title'    => esc_html__( 'Search by Image', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'drag_drop_text' => sprintf( esc_html__( 'Drag and drop an image here or click to browse (Max size: %dMB)', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
					'scanning'       => esc_html__( 'Searching...', 'searchips-search-by-image-for-woocommerce' ),
					'error'          => esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
					'search_btn_text'=> esc_html__( 'Start Search', 'searchips-search-by-image-for-woocommerce' ),
					'select_another' => esc_html__( 'Select Another', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'file_too_large' => sprintf( esc_html__( 'Selected file is too large. Maximum allowed size is %dMB.', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
				),
			)
		);
	}

	/**
	 * Register query variables.
	 *
	 * @param array $vars Registered query vars.
	 * @return array Updated list.
	 */
	public function register_query_vars( $vars ) {
		$vars[] = 'tsbifw_vquery';
		$vars[] = 'vquery';
		return $vars;
	}

	/**
	 * Extract visual search token from query or GET parameters.
	 *
	 * @param WP_Query $query Query object.
	 * @return string Sanitized token or empty string.
	 */
	private function get_request_token( $query ) {
		$token = $query->get( 'tsbifw_vquery' );
		if ( empty( $token ) ) {
			$token = $query->get( 'vquery' );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) && isset( $_GET['tsbifw_vquery'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = sanitize_key( wp_unslash( $_GET['tsbifw_vquery'] ) );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $token ) && isset( $_GET['vquery'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$token = sanitize_key( wp_unslash( $_GET['vquery'] ) );
		}

		return is_string( $token ) ? $token : '';
	}

	/**
	 * Modify standard search query to show visual search results.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function modify_search_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
			return;
		}

		$token = $this->get_request_token( $query );
		if ( empty( $token ) ) {
			return;
		}

		// Clear the dummy keyword so input fields and archive titles are clean.
		$dummy_keyword = _x( 'image-search', 'default search term for visual search', 'searchips-search-by-image-for-woocommerce' );
		$current_s     = (string) $query->get( 's' );
		if ( 'image-search' === $current_s || $dummy_keyword === $current_s ) {
			$query->set( 's', '' );
		}

		// Retrieve matching product IDs from transient
		$product_ids = get_transient( 'tsbifw_vquery_' . $token );
		if ( false === $product_ids || ! is_array( $product_ids ) ) {
			// Token is expired, invalid, or forged. Force zero results to prevent full catalog dump.
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'post_type', 'product' );
			return;
		}

		if ( empty( $product_ids ) ) {
			// Force zero results if no products matched
			$query->set( 'post__in', array( 0 ) );
			$query->set( 'post_type', 'product' );
			return;
		}

		// Restrict query to matching products only
		$query->set( 'post__in', $product_ids );
		$query->set( 'post_type', 'product' );

		// Order results by the matched IDs order (sorted by similarity score descending)
		$query->set( 'orderby', 'post__in' );
	}

	/**
	 * Clear the standard SQL search clause if visual search token is present.
	 *
	 * @param string   $search   Search SQL clause.
	 * @param WP_Query $wp_query Query object.
	 * @return string Modified SQL search clause.
	 */
	public function clear_search_keyword_sql( $search, $wp_query ) {
		if ( is_admin() || ! $wp_query->is_main_query() ) {
			return $search;
		}

		$token = $this->get_request_token( $wp_query );
		if ( empty( $token ) ) {
			return $search;
		}

		// Only suppress keyword search clause if the visual search token is valid and active.
		$product_ids = get_transient( 'tsbifw_vquery_' . $token );
		if ( false !== $product_ids && is_array( $product_ids ) ) {
			return ''; // Suppress the keyword search WHERE clause
		}

		return $search;
	}



	/**
	 * Render custom search bar via shortcode [tsbifw_search_bar].
	 *
	 * @return string Search bar HTML.
	 */
	public function render_search_bar_shortcode() {
		ob_start();
		?>
		<div class="tsbifw-search-bar-container">
			<form role="search" method="get" class="woocommerce-product-search tsbifw-search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
				<label class="screen-reader-text" for="woocommerce-product-search-field-<?php echo esc_attr( uniqid() ); ?>"><?php esc_html_e( 'Search for:', 'searchips-search-by-image-for-woocommerce' ); ?></label>
				<div class="tsbifw-search-input-wrapper">
					<input type="search" class="search-field" placeholder="<?php echo esc_attr__( 'Search products&hellip;', 'searchips-search-by-image-for-woocommerce' ); ?>" value="<?php echo esc_attr( get_search_query() ); ?>" name="s" />
					<button type="button" class="tsbifw-camera-trigger" title="<?php echo esc_attr__( 'Search by Image', 'searchips-search-by-image-for-woocommerce' ); ?>">
						<svg viewBox="0 0 24 24" width="20" height="20" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round" class="tsbifw-camera-icon"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"></path><circle cx="12" cy="13" r="4"></circle></svg>
					</button>
				</div>
				<button type="submit" value="<?php echo esc_attr__( 'Search', 'searchips-search-by-image-for-woocommerce' ); ?>"><?php echo esc_html__( 'Search', 'searchips-search-by-image-for-woocommerce' ); ?></button>
				<input type="hidden" name="post_type" value="product" />
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register the REST API endpoint.
	 */
	public function register_search_route() {
		register_rest_route(
			'tsbifw/v1',
			'/search',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_search_request' ),
				'permission_callback' => array( $this, 'check_frontend_search_permission' ),
				'args'                => array(
					'sandbox'  => array(
						'type'              => 'boolean',
						'required'          => false,
						'default'           => false,
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'security' => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Verify permissions and rate limit for REST search queries.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return bool|WP_Error
	 */
	public function check_frontend_search_permission( $request ) {
		$sandbox = ! empty( $request->get_param( 'sandbox' ) );

		if ( $sandbox ) {
			if ( current_user_can( 'manage_options' ) ) {
				$nonce = $request->get_param( 'security' );
				if ( wp_verify_nonce( $nonce, 'tsbifw_admin_nonce' ) ) {
					return true;
				}
			}
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'Forbidden: administrator access required.', 'searchips-search-by-image-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( 'security' );
		}

		$valid = wp_verify_nonce( $nonce, 'tsbifw_frontend_search' );

		// Fallback check for logged-in users when standard REST cookie authentication header (X-WP-Nonce) is not sent.
		if ( ! $valid && function_exists( 'wp_validate_auth_cookie' ) ) {
			$logged_in_user_id = wp_validate_auth_cookie( '', 'logged_in' );
			if ( $logged_in_user_id ) {
				$current_user_id = get_current_user_id();
				wp_set_current_user( $logged_in_user_id );
				$valid = (bool) wp_verify_nonce( $nonce, 'tsbifw_frontend_search' );
				wp_set_current_user( $current_user_id );
			}
		}

		if ( ! $valid ) {
			return new WP_Error(
				'rest_forbidden',
				esc_html__( 'Forbidden: invalid security token.', 'searchips-search-by-image-for-woocommerce' ),
				array( 'status' => 403 )
			);
		}

		// Rate limit: max 20 search requests per minute per IP address.
		$client_ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		$rl_key    = 'tsbifw_rl_' . md5( $client_ip );
		$hits      = (int) get_transient( $rl_key );

		if ( $hits >= 20 ) {
			return new WP_Error(
				'rest_rate_limited',
				esc_html__( 'Too many search requests. Please wait a minute and try again.', 'searchips-search-by-image-for-woocommerce' ),
				array( 'status' => 429 )
			);
		}

		set_transient( $rl_key, $hits + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * Handle incoming REST search request.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response|WP_Error REST response or WP_Error.
	 */
	public function handle_search_request( $request ) {
		$files = $request->get_file_params();
		if ( empty( $files ) || ! isset( $files['image'] ) ) {
			return new WP_Error( 'tsbifw_missing_image', esc_html__( 'No image file uploaded in the request.', 'searchips-search-by-image-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$uploaded_file = $files['image'];

		// Verify binary file type against real magic bytes and allowed mimes.
		$checked_file = wp_check_filetype_and_ext(
			$uploaded_file['tmp_name'],
			$uploaded_file['name'],
			array(
				'jpg|jpeg|jpe' => 'image/jpeg',
				'png'          => 'image/png',
				'webp'         => 'image/webp',
			)
		);
		if ( ! $checked_file['type'] || ! in_array( $checked_file['type'], array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ) {
			return new WP_Error(
				'tsbifw_invalid_format',
				esc_html__( 'Unsupported image format. Please upload a valid JPEG, PNG, or WEBP image.', 'searchips-search-by-image-for-woocommerce' ),
				array( 'status' => 400 )
			);
		}

		$strategy = get_option( 'tsbifw_strategy', 'embeddings' );
		$limit    = (int) get_option( 'tsbifw_results_limit', 12 );
		$sandbox  = ! empty( $request->get_param( 'sandbox' ) );

		TSBIFW_Logger::log(
			'Incoming REST search request.',
			array(
				'filename' => $uploaded_file['name'],
				'size'     => $uploaded_file['size'],
				'strategy' => $strategy,
			),
			$sandbox
		);

		$api = TSBIFW_API::instance();
		$base64 = $api->prepare_raw_file( $uploaded_file['tmp_name'] );
		if ( is_wp_error( $base64 ) ) {
			TSBIFW_Logger::log( 'Search request failed preparing image file.', array( 'error' => $base64->get_error_message() ), $sandbox );
			$base64->add_data( array( 'status' => 400 ) );
			return $base64;
		}

		$exclude_percent = get_option( 'tsbifw_exclude_below_percent', '' );
		if ( '' === $exclude_percent ) {
			$threshold = (float) get_option( 'tsbifw_similarity_threshold', 0.40 );
		} else {
			$threshold = (int) $exclude_percent / 100;
		}

		$matched_posts = array();

		try {
			if ( function_exists( 'wp_raise_memory_limit' ) ) {
				wp_raise_memory_limit( 'admin' );
			}
			if ( function_exists( 'set_time_limit' ) ) {
				@set_time_limit( 120 );
			}

			if ( 'embeddings' === $strategy ) {
				// Get query vector.
				$query_vector = $api->get_embeddings( $base64 );
				if ( is_wp_error( $query_vector ) ) {
					if ( ! $sandbox ) {
						return new WP_Error(
							'tsbifw_search_failed',
							esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
							array( 'status' => 503 )
						);
					}
					$status_code = ( 'tsbifw_missing_api_key' === $query_vector->get_error_code() ) ? 400 : 422;
					$query_vector->add_data( array( 'status' => $status_code ) );
					return $query_vector;
				}

				// Calculate similarity scores in cursor batches to prevent memory and packet limits.
				$scores = $this->calculate_vector_scores( $query_vector, $threshold );
			} else {
				// Get text description.
				$description = $api->get_description( $base64 );
				if ( is_wp_error( $description ) ) {
					if ( ! $sandbox ) {
						return new WP_Error(
							'tsbifw_search_failed',
							esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
							array( 'status' => 503 )
						);
					}
					$status_code = ( 'tsbifw_missing_api_key' === $description->get_error_code() ) ? 400 : 422;
					$description->add_data( array( 'status' => $status_code ) );
					return $description;
				}

				// Calculate similarity scores in cursor batches.
				$scores = $this->calculate_description_scores( $description, $threshold );
			}

			if ( ! empty( $scores ) ) {
				// Sort scores descending.
				arsort( $scores );

				// Pre-prime post, postmeta, and taxonomy caches for candidates to prevent N+1 queries.
				$candidate_ids = array_slice( array_keys( $scores ), 0, max( $limit * 3, 50 ) );
				if ( function_exists( '_prime_post_caches' ) ) {
					_prime_post_caches( $candidate_ids, true, true );
				}

				$resolved_products = array();
				foreach ( $scores as $product_id => $score ) {
					$product = wc_get_product( $product_id );
					if ( ! $this->is_product_viewable_and_visible( $product, $sandbox ) ) {
						continue;
					}

					$matched_posts[] = array(
						'id'    => $product_id,
						'score' => $score,
					);
					if ( $sandbox && $product ) {
						$resolved_products[ $product_id ] = $product;
					}

					if ( count( $matched_posts ) >= $limit ) {
						break;
					}
				}
			} elseif ( 'embeddings' !== $strategy && ! empty( $description ) ) {
				// Fallback to standard text search query for description strategy when no jaccard matches found.
				$args = array(
					'post_type'      => 'product',
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					's'              => $description,
					'fields'         => 'ids',
				);

				$search_query = new WP_Query( $args );
				$ids          = $search_query->posts;

				if ( function_exists( '_prime_post_caches' ) && ! empty( $ids ) ) {
					_prime_post_caches( $ids, true, true );
				}

				$resolved_products = array();
				foreach ( $ids as $product_id ) {
					$product = wc_get_product( $product_id );
					if ( ! $this->is_product_viewable_and_visible( $product, $sandbox ) ) {
						continue;
					}

					$matched_posts[] = array(
						'id'    => $product_id,
						'score' => null,
					);
					if ( $sandbox && $product ) {
						$resolved_products[ $product_id ] = $product;
					}
				}
			}
		} catch ( Throwable $e ) {
			TSBIFW_Logger::log( 'Search request encountered an exception: ' . $e->getMessage(), array( 'trace' => $e->getTraceAsString() ), true );
			return new WP_Error(
				'tsbifw_search_exception',
				esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
				array(
					'status'  => 500,
					'details' => ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? $e->getMessage() : '',
				)
			);
		}

		// Check if it is the admin sandbox request.
		if ( $sandbox ) {
			$nonce = $request->get_param( 'security' );
			if ( ! wp_verify_nonce( $nonce, 'tsbifw_admin_nonce' ) || ! current_user_can( 'manage_options' ) ) {
				return new WP_Error( 'tsbifw_forbidden', esc_html__( 'Forbidden.', 'searchips-search-by-image-for-woocommerce' ), array( 'status' => 403 ) );
			}

			// Pre-prime attachment image caches for sandbox results.
			$image_ids = array();
			foreach ( $matched_posts as $match ) {
				$product_id = $match['id'];
				$product    = isset( $resolved_products[ $product_id ] ) ? $resolved_products[ $product_id ] : wc_get_product( $product_id );
				if ( $product && $product->get_image_id() ) {
					$image_ids[] = $product->get_image_id();
				}
			}

			if ( function_exists( '_prime_post_caches' ) && ! empty( $image_ids ) ) {
				_prime_post_caches( $image_ids, false, true );
			}

			// Format product response lists for admin test search sandbox.
			$formatted_results = array();
			foreach ( $matched_posts as $match ) {
				$product_id = $match['id'];
				$product    = isset( $resolved_products[ $product_id ] ) ? $resolved_products[ $product_id ] : wc_get_product( $product_id );
				if ( ! $product ) {
					continue;
				}

				$image_id  = $product->get_image_id();
				$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : wc_placeholder_img_src();

				$score_percentage = null !== $match['score'] ? round( $match['score'] * 100 ) . '%' : null;

				// Add to Cart integration.
				$add_to_cart_url = esc_url( $product->add_to_cart_url() );

				$formatted_results[] = array(
					'id'              => $product_id,
					'title'           => wp_strip_all_tags( $product->get_name() ),
					'permalink'       => esc_url( $product->get_permalink() ),
					'image'           => esc_url( $image_url ),
					'price_html'      => $product->get_price_html(),
					'score'           => $score_percentage,
					'add_to_cart_url' => $add_to_cart_url,
					'is_in_stock'     => $product->is_in_stock(),
				);
			}

			TSBIFW_Logger::log(
				sprintf( 'Sandbox search completed. Found %d matching products.', count( $formatted_results ) ),
				array(
					'matches' => array_map(
						function( $item ) {
							return array(
								'id'    => $item['id'],
								'title' => $item['title'],
								'score' => $item['score'],
							);
						},
						$formatted_results
					),
				),
				true
			);

			return rest_ensure_response( $formatted_results );
		}

		// Otherwise, this is a frontend request. Perform redirect using transient and token.
		$product_ids = array_column( $matched_posts, 'id' );
		$token       = 'vs_' . wp_generate_password( 8, false );

		$cache_expiry = (int) get_option( 'tsbifw_search_cache_expiry', '600' );
		if ( $cache_expiry <= 0 ) {
			$cache_expiry = 600;
		}

		set_transient( 'tsbifw_vquery_' . $token, $product_ids, $cache_expiry );

		$search_term = '';
		if ( 'embeddings' === $strategy ) {
			$search_term = _x( 'image-search', 'default search term for visual search', 'searchips-search-by-image-for-woocommerce' );
		} else {
			$search_term = ! empty( $description ) ? $description : _x( 'image-search', 'default search term for visual search', 'searchips-search-by-image-for-woocommerce' );
		}

		$redirect_url = add_query_arg(
			array(
				's'             => $search_term,
				'tsbifw_vquery' => $token,
				'post_type'     => 'product',
			),
			home_url( '/' )
		);

		TSBIFW_Logger::log(
			sprintf( 'Frontend search processed. Matched %d products. Generated token: %s', count( $product_ids ), $token ),
			array(
				'redirect' => $redirect_url,
				'ids'      => $product_ids,
			),
			$sandbox
		);

		return rest_ensure_response( array( 'redirect_url' => $redirect_url ) );
	}

	/**
	 * Calculate cosine similarity scores for all published products using cursor batching.
	 *
	 * @param array $query_vector Query embedding vector.
	 * @param float $threshold    Minimum similarity threshold.
	 * @return array Map of product ID => similarity score.
	 */
	private function calculate_vector_scores( $query_vector, $threshold ) {
		if ( ! is_array( $query_vector ) || empty( $query_vector ) ) {
			return array();
		}

		$norm_query = 0.0;
		foreach ( $query_vector as $v ) {
			$norm_query += $v * $v;
		}
		$norm_query = sqrt( $norm_query );
		if ( $norm_query < 1e-10 ) {
			return array();
		}

		global $wpdb;
		$scores       = array();
		$batch_size   = 200;
		$last_post_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_value 
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s 
					  AND p.post_status = %s 
					  AND p.post_type = %s 
					  AND pm.post_id > %d
					ORDER BY pm.post_id ASC
					LIMIT %d",
					'_tsbifw_vectors',
					'publish',
					'product',
					$last_post_id,
					$batch_size
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$last_post_id = (int) $row['post_id'];
				$vector_list  = $this->safe_unserialize( $row['meta_value'] );
				if ( ! is_array( $vector_list ) ) {
					continue;
				}

				$max_score = 0.0;
				foreach ( $vector_list as $img_data ) {
					if ( isset( $img_data['vector'] ) && is_array( $img_data['vector'] ) ) {
						$score = $this->cosine_similarity_fast( $query_vector, $norm_query, $img_data['vector'] );
						if ( $score > $max_score ) {
							$max_score = $score;
						}
					}
				}

				if ( $max_score >= $threshold ) {
					$scores[ $last_post_id ] = $max_score;
				}
			}

			unset( $rows );
		} while ( true );

		return $scores;
	}

	/**
	 * Calculate Jaccard similarity scores for all published products using cursor batching.
	 *
	 * @param string $query_desc Query text description.
	 * @param float  $threshold  Minimum similarity threshold.
	 * @return array Map of product ID => similarity score.
	 */
	private function calculate_description_scores( $query_desc, $threshold ) {
		if ( ! is_string( $query_desc ) || '' === trim( $query_desc ) ) {
			return array();
		}

		global $wpdb;
		$scores       = array();
		$batch_size   = 200;
		$last_post_id = 0;

		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm.post_id, pm.meta_value 
					FROM {$wpdb->postmeta} pm
					INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
					WHERE pm.meta_key = %s 
					  AND p.post_status = %s 
					  AND p.post_type = %s 
					  AND pm.post_id > %d
					ORDER BY pm.post_id ASC
					LIMIT %d",
					'_tsbifw_descriptions',
					'publish',
					'product',
					$last_post_id,
					$batch_size
				),
				ARRAY_A
			);

			if ( empty( $rows ) ) {
				break;
			}

			foreach ( $rows as $row ) {
				$last_post_id = (int) $row['post_id'];
				$desc_list    = $this->safe_unserialize( $row['meta_value'] );
				if ( ! is_array( $desc_list ) ) {
					continue;
				}

				$max_score = 0.0;
				foreach ( $desc_list as $img_data ) {
					if ( isset( $img_data['description'] ) && is_string( $img_data['description'] ) ) {
						$score = $this->jaccard_similarity( $query_desc, $img_data['description'] );
						if ( $score > $max_score ) {
							$max_score = $score;
						}
					}
				}

				if ( $max_score >= $threshold ) {
					$scores[ $last_post_id ] = $max_score;
				}
			}

			unset( $rows );
		} while ( true );

		return $scores;
	}

	/**
	 * Compute fast Cosine Similarity using a pre-calculated query vector norm.
	 *
	 * @param array $vec1   Query vector.
	 * @param float $norm_a Euclidean norm of vec1.
	 * @param array $vec2   Candidate vector.
	 * @return float Cosine similarity score.
	 */
	private function cosine_similarity_fast( $vec1, $norm_a, $vec2 ) {
		if ( ! is_array( $vec1 ) || ! is_array( $vec2 ) ) {
			return 0.0;
		}

		$n = count( $vec1 );
		if ( $n !== count( $vec2 ) ) {
			return 0.0; // Vector dimensions mismatch.
		}

		$dot_product = 0.0;
		$norm_b      = 0.0;

		for ( $i = 0; $i < $n; $i++ ) {
			if ( ! isset( $vec2[ $i ] ) ) {
				continue;
			}
			$dot_product += $vec1[ $i ] * $vec2[ $i ];
			$norm_b      += $vec2[ $i ] * $vec2[ $i ];
		}

		if ( $norm_b < 1e-10 ) {
			return 0.0;
		}

		return $dot_product / ( $norm_a * sqrt( $norm_b ) );
	}

	/**
	 * Compute Jaccard Similarity between two description strings (fuzzy matching).
	 *
	 * @param string $str1 String 1.
	 * @param string $str2 String 2.
	 * @return float Jaccard Similarity score between 0.0 and 1.0.
	 */
	private function jaccard_similarity( $str1, $str2 ) {
		if ( ! is_string( $str1 ) || ! is_string( $str2 ) ) {
			return 0.0;
		}

		$tokens1 = $this->tokenize_description( $str1 );
		$tokens2 = $this->tokenize_description( $str2 );

		if ( empty( $tokens1 ) || empty( $tokens2 ) ) {
			return 0.0;
		}

		$intersection = array_intersect( $tokens1, $tokens2 );
		$union        = array_unique( array_merge( $tokens1, $tokens2 ) );

		return count( $intersection ) / count( $union );
	}

	/**
	 * Tokenize description text into keywords.
	 *
	 * @param string $str Text to tokenize.
	 * @return array Unique token strings.
	 */
	private function tokenize_description( $str ) {
		static $stop_words = array(
			'and', 'or', 'with', 'the', 'for', 'a', 'an', 'in', 'on', 'of',
			'to', 'at', 'by', 'this', 'that', 'is', 'are', 'was', 'were',
			'it', 'its', 'from', 'product', 'image',
		);

		$words  = explode( ' ', strtolower( $str ) );
		$tokens = array();
		foreach ( $words as $word ) {
			$word = trim( preg_replace( '/[^a-z0-9]/', '', $word ) );
			if ( strlen( $word ) > 2 && ! in_array( $word, $stop_words, true ) ) {
				$tokens[] = $word;
			}
		}
		return array_unique( $tokens );
	}

	private function is_product_viewable_and_visible( $product, $sandbox = false ) {
		if ( ! $product || ! ( $product instanceof WC_Product ) ) {
			return false;
		}

		if ( method_exists( $product, 'is_viewable' ) ) {
			if ( ! $product->is_viewable() ) {
				return false;
			}
		} elseif ( 'publish' !== $product->get_status() ) {
			return false;
		}

		if ( ! $sandbox && ! $product->is_visible() ) {
			return false;
		}

		return true;
	}

	/**
	 * Safely unserialize data without instantiating objects (CWE-502 mitigation).
	 *
	 * @param mixed $data Serialized string or data.
	 * @return mixed Unserialized data or original input.
	 */
	private function safe_unserialize( $data ) {
		if ( ! is_serialized( $data ) ) {
			return $data;
		}

		return @unserialize( $data, array( 'allowed_classes' => false ) );
	}
}
