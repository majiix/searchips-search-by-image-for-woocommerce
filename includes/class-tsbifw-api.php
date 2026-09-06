<?php
/**
 * API client class for OpenRouter.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class TSBIFW_API {

	/**
	 * Singleton instance.
	 *
	 * @var TSBIFW_API|null
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return TSBIFW_API
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Retrieve OpenRouter API Key from settings.
	 *
	 * @return string
	 */
	public function get_api_key() {
		return get_option( 'tsbifw_api_key', '' );
	}

	/**
	 * Prepare image for API transmission by resizing it and converting to base64.
	 *
	 * @param int $attachment_id Attachment ID of the image.
	 * @return string|WP_Error Base64 data URL or WP_Error.
	 */
	public function prepare_image( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'tsbifw_file_not_found', esc_html__( 'Image file path not found or does not exist on disk.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$target_path = $file_path;

		if ( is_array( $metadata ) && isset( $metadata['sizes'] ) ) {
			// Prefer medium_large (768px) or large (1024px) for resizing if they exist, to save memory.
			$sizes_to_try = array( 'medium_large', 'large' );
			foreach ( $sizes_to_try as $size_name ) {
				if ( isset( $metadata['sizes'][ $size_name ]['file'] ) ) {
					$dir = dirname( $file_path );
					$size_file_path = $dir . '/' . $metadata['sizes'][ $size_name ]['file'];
					if ( file_exists( $size_file_path ) ) {
						$target_path = $size_file_path;
						break;
					}
				}
			}
		}

		$size = filesize( $target_path );
		if ( false === $size || 0 === $size ) {
			return new WP_Error( 'tsbifw_empty_image', esc_html__( 'Image file is empty (0 bytes).', 'searchips-search-by-image-for-woocommerce' ) );
		}

		return $this->resize_and_encode_image( $target_path );
	}

	/**
	 * Helper method to prepare a raw uploaded file for API transmission.
	 *
	 * @param string $uploaded_file_path Path to uploaded file.
	 * @return string|WP_Error Base64 data URL or WP_Error.
	 */
	public function prepare_raw_file( $uploaded_file_path ) {
		if ( ! file_exists( $uploaded_file_path ) ) {
			return new WP_Error( 'tsbifw_file_not_found', esc_html__( 'Uploaded file not found on disk.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$size = filesize( $uploaded_file_path );
		if ( false === $size || 0 === $size ) {
			return new WP_Error( 'tsbifw_empty_image', esc_html__( 'Uploaded file is empty (0 bytes).', 'searchips-search-by-image-for-woocommerce' ) );
		}

		// Retrieve max upload size setting (default 2MB)
		$max_mb = (int) get_option( 'tsbifw_max_upload_size', 2 );
		if ( $max_mb <= 0 ) {
			$max_mb = 2;
		}
		$max_bytes = $max_mb * 1024 * 1024;

		if ( $size > $max_bytes ) {
			return new WP_Error(
				'tsbifw_file_too_large',
				sprintf(
					// translators: 1: Current size in MB, 2: Max allowed size in MB
					esc_html__( 'Uploaded image is too large (%1$.2f MB). Maximum allowed size is %2$d MB.', 'searchips-search-by-image-for-woocommerce' ),
					$size / ( 1024 * 1024 ),
					$max_mb
				)
			);
		}

		// Verify image dimensions before loading into GD/Imagick to prevent memory exhaustion / decompression bomb.
		$dimensions = @getimagesize( $uploaded_file_path );
		if ( false === $dimensions ) {
			return new WP_Error( 'tsbifw_invalid_image', esc_html__( 'Unable to read image dimensions or invalid image file.', 'searchips-search-by-image-for-woocommerce' ) );
		}
		if ( $dimensions[0] > 6000 || $dimensions[1] > 6000 ) {
			return new WP_Error( 'tsbifw_image_dimensions_too_large', esc_html__( 'Image dimensions exceed the allowed limit (max 6000x6000px).', 'searchips-search-by-image-for-woocommerce' ) );
		}

		return $this->resize_and_encode_image( $uploaded_file_path );
	}

	/**
	 * Resize an image file to max 512x512 JPEG and return base64 data URL.
	 *
	 * @param string $file_path Path to image file on disk.
	 * @return string|WP_Error Base64 data URL or WP_Error.
	 */
	private function resize_and_encode_image( $file_path ) {
		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$editor->resize( 512, 512, false );

		$temp_file = wp_tempnam( 'tsbifw_' );
		if ( ! $temp_file ) {
			return new WP_Error( 'tsbifw_temp_file_failed', esc_html__( 'Could not create temporary file for image processing.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$saved = $editor->save( $temp_file, 'image/jpeg' );
		if ( is_wp_error( $saved ) ) {
			@wp_delete_file( $temp_file );
			return $saved;
		}

		$resized_path = $saved['path'];
		$content      = file_get_contents( $resized_path );
		@wp_delete_file( $resized_path );
		if ( $resized_path !== $temp_file ) {
			@wp_delete_file( $temp_file );
		}

		if ( false === $content ) {
			return new WP_Error( 'tsbifw_read_failed', esc_html__( 'Failed to read image content.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		return 'data:image/jpeg;base64,' . base64_encode( $content );
	}

	/**
	 * Fetch vector embeddings from OpenRouter.
	 *
	 * @param string $base64_image Base64 encoded image data URL.
	 * @return array|WP_Error Array of floats representing the embedding vector, or WP_Error.
	 */
	public function get_embeddings( $base64_image ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'tsbifw_missing_api_key', esc_html__( 'OpenRouter API Key is missing. Please configure it in WooCommerce settings.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$model = get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' );
		if ( empty( $model ) ) {
			$model = 'google/gemini-embedding-2';
		}

		$body = array(
			'model' => $model,
			'input' => array(
				array(
					'content' => array(
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url' => $base64_image,
							),
						),
					),
				),
			),
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => get_home_url(),
			'X-Title'       => 'Searchips WP',
		);

		TSBIFW_Logger::log(
			sprintf( 'Sending vector embeddings request to OpenRouter for model: %s', $model ),
			array(
				'model'       => $model,
				'image_bytes' => strlen( $base64_image ),
			)
		);

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/embeddings',
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
				'timeout'     => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( 'OpenRouter Embeddings request failed (WP_Error).', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$api_err_code = isset( $error_data['error']['code'] ) ? $error_data['error']['code'] : $response_code;
			$err_msg    = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : esc_html__( 'Unknown API error.', 'searchips-search-by-image-for-woocommerce' );
			// translators: 1: HTTP Response Code, 2: API Error Code
			$log_msg = sprintf( 'OpenRouter Embeddings HTTP Error: %1$d (API Code: %2$s)', $response_code, $api_err_code );
			TSBIFW_Logger::log( $log_msg, array( 'response' => $error_data ) );
			return new WP_Error(
				'tsbifw_api_error',
				sprintf(
					// translators: 1: API Error Code, 2: Error message
					esc_html__( 'OpenRouter API Error [Code %1$s]: %2$s', 'searchips-search-by-image-for-woocommerce' ),
					$api_err_code,
					$err_msg
				)
			);
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['data'][0]['embedding'] ) || ! is_array( $data['data'][0]['embedding'] ) ) {
			TSBIFW_Logger::log( 'Embeddings formatting mismatch in OpenRouter response.', array( 'response' => $data ) );
			return new WP_Error( 'tsbifw_api_format_error', esc_html__( 'Failed to extract embedding vector from API response.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		TSBIFW_Logger::log( sprintf( 'Received embedding vector successfully. Dimensions: %d', count( $data['data'][0]['embedding'] ) ) );

		return $data['data'][0]['embedding'];
	}

	/**
	 * Fetch image text description from OpenRouter.
	 *
	 * @param string $base64_image Base64 encoded image data URL.
	 * @return string|WP_Error Descriptive search phrase, or WP_Error.
	 */
	public function get_description( $base64_image ) {
		$api_key = $this->get_api_key();
		if ( empty( $api_key ) ) {
			return new WP_Error( 'tsbifw_missing_api_key', esc_html__( 'OpenRouter API Key is missing. Please configure it in WooCommerce settings.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$model = get_option( 'tsbifw_vision_model', 'google/gemini-2.5-flash' );
		if ( empty( $model ) ) {
			$model = 'google/gemini-2.5-flash';
		}

		$body = array(
			'model'    => $model,
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => 'Describe this product image in a detailed, descriptive search phrase. Include keywords about its type, color, material, style, and distinguishing visual features. Output only the description phrase or comma-separated list of keywords, without any extra text or intro.',
						),
						array(
							'type'      => 'image_url',
							'image_url' => array(
								'url' => $base64_image,
							),
						),
					),
				),
			),
		);

		$headers = array(
			'Authorization' => 'Bearer ' . $api_key,
			'Content-Type'  => 'application/json',
			'HTTP-Referer'  => get_home_url(),
			'X-Title'       => 'Searchips WP',
		);

		TSBIFW_Logger::log(
			sprintf( 'Sending vision description request to OpenRouter for model: %s', $model ),
			array(
				'model'       => $model,
				'image_bytes' => strlen( $base64_image ),
			)
		);

		$response = wp_remote_post(
			'https://openrouter.ai/api/v1/chat/completions',
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'body'        => wp_json_encode( $body ),
				'data_format' => 'body',
				'timeout'     => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( 'OpenRouter Vision request failed (WP_Error).', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$api_err_code = isset( $error_data['error']['code'] ) ? $error_data['error']['code'] : $response_code;
			$err_msg    = isset( $error_data['error']['message'] ) ? $error_data['error']['message'] : esc_html__( 'Unknown API error.', 'searchips-search-by-image-for-woocommerce' );
			// translators: 1: HTTP Response Code, 2: API Error Code
			$log_msg = sprintf( 'OpenRouter Vision HTTP Error: %1$d (API Code: %2$s)', $response_code, $api_err_code );
			TSBIFW_Logger::log( $log_msg, array( 'response' => $error_data ) );
			return new WP_Error(
				'tsbifw_api_error',
				sprintf(
					// translators: 1: API Error Code, 2: Error message
					esc_html__( 'OpenRouter API Error [Code %1$s]: %2$s', 'searchips-search-by-image-for-woocommerce' ),
					$api_err_code,
					$err_msg
				)
			);
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			TSBIFW_Logger::log( 'Vision formatting mismatch in OpenRouter response.', array( 'response' => $data ) );
			return new WP_Error( 'tsbifw_api_format_error', esc_html__( 'Failed to extract text description from API response.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$description = wp_strip_all_tags( trim( $data['choices'][0]['message']['content'] ) );
		TSBIFW_Logger::log( 'Received description successfully.', array( 'description' => $description ) );

		return $description;
	}

	public function get_models( $modality = '' ) {
		$transient_key = 'tsbifw_models_' . md5( $modality );
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$url = 'https://openrouter.ai/api/v1/models';
		if ( 'embeddings' === $modality ) {
			$url .= '?output_modalities=embeddings';
		}

		TSBIFW_Logger::log( sprintf( 'Fetching models list from OpenRouter. URL: %s', $url ) );

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 15,
			)
		);

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( 'Failed to retrieve models from OpenRouter.', array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			TSBIFW_Logger::log( sprintf( 'Failed to retrieve models. HTTP Status Code: %d', $response_code ), array( 'body' => $response_body ) );
			return new WP_Error( 'tsbifw_api_error', esc_html__( 'Failed to retrieve models from OpenRouter.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) ) {
			TSBIFW_Logger::log( 'Invalid models list structure from OpenRouter.' );
			return new WP_Error( 'tsbifw_api_format_error', esc_html__( 'Invalid models list format from OpenRouter.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		// Cache for 1 hour.
		set_transient( $transient_key, $data['data'], HOUR_IN_SECONDS );

		TSBIFW_Logger::log( sprintf( 'Successfully retrieved and cached %d models.', count( $data['data'] ) ) );

		return $data['data'];
	}

	/**
	 * Filter models for embeddings.
	 *
	 * @param array $models List of model arrays.
	 * @return array Filtered list.
	 */
	public function filter_embedding_models( $models ) {
		if ( ! is_array( $models ) ) {
			return array();
		}
		return array_values(
			array_filter(
				$models,
				function( $model ) {
					return isset( $model['id'] );
				}
			)
		);
	}

	/**
	 * Filter models for vision.
	 *
	 * @param array $models List of model arrays.
	 * @return array Filtered list.
	 */
	public function filter_vision_models( $models ) {
		$filtered = array();
		if ( ! is_array( $models ) ) {
			return $filtered;
		}
		foreach ( $models as $model ) {
			if ( ! isset( $model['id'] ) ) {
				continue;
			}
			$id       = strtolower( $model['id'] );
			$modality = isset( $model['architecture']['modality'] ) ? strtolower( $model['architecture']['modality'] ) : '';
			if ( strpos( $modality, 'image' ) !== false || strpos( $id, 'vision' ) !== false || strpos( $id, 'gpt-4o' ) !== false || strpos( $id, 'gemini-flash' ) !== false || strpos( $id, 'gemini-pro' ) !== false ) {
				$filtered[] = $model;
			}
		}
		return $filtered;
	}
}
