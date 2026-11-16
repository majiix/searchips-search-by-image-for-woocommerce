<?php
/**
 * Multi-provider AI API client.
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
	 * Return declarative configuration for all supported AI gateways.
	 *
	 * @return array Map of gateway slug => config array.
	 */
	public function get_gateway_configs() {
		$configs = array(
			'openrouter' => array(
				'label'                    => 'OpenRouter',
				'api_key_option'           => 'tsbifw_api_key',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openrouter_multimodal',
				'default_embeddings_model' => 'google/gemini-embedding-2',
				'default_vision_model'     => 'google/gemini-2.5-flash',
			),
			'openai'     => array(
				'label'                    => 'OpenAI Direct',
				'api_key_option'           => 'tsbifw_api_key_openai',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'gemini'     => array(
				'label'                    => 'Google Gemini Direct',
				'api_key_option'           => 'tsbifw_api_key_gemini',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'gemini_native',
				'default_embeddings_model' => 'gemini-embedding-2',
				'default_vision_model'     => 'gemini-2.5-flash',
			),
			'jina'       => array(
				'label'                    => 'Jina AI',
				'api_key_option'           => 'tsbifw_api_key_jina',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => false,
				'embedding_protocol'       => 'jina',
				'default_embeddings_model' => 'jina-clip-v2',
				'default_vision_model'     => '',
			),
			'voyage'     => array(
				'label'                    => 'Voyage AI',
				'api_key_option'           => 'tsbifw_api_key_voyage',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => false,
				'embedding_protocol'       => 'voyage',
				'default_embeddings_model' => 'voyage-multimodal-3.5',
				'default_vision_model'     => '',
			),
			'cohere'     => array(
				'label'                    => 'Cohere',
				'api_key_option'           => 'tsbifw_api_key_cohere',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => false,
				'embedding_protocol'       => 'cohere',
				'default_embeddings_model' => 'embed-v4.0',
				'default_vision_model'     => '',
			),
			'groq'       => array(
				'label'                    => 'Groq',
				'api_key_option'           => 'tsbifw_api_key_groq',
				'supports_strategy_1'      => false,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'none',
				'default_embeddings_model' => '',
				'default_vision_model'     => 'meta-llama/llama-4-scout-17b-16e-instruct',
			),
			'avalai'     => array(
				'label'                    => 'AvalAI',
				'api_key_option'           => 'tsbifw_api_key_avalai',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'gemini_native',
				'default_embeddings_model' => 'gemini-embedding-2',
				'default_vision_model'     => 'gemini-2.5-flash',
			),
			'gapgpt'     => array(
				'label'                    => 'GapGPT',
				'api_key_option'           => 'tsbifw_api_key_gapgpt',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'nvidia'     => array(
				'label'                    => 'NVIDIA NIM',
				'api_key_option'           => 'tsbifw_api_key_nvidia',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'nvidia_nim',
				'default_embeddings_model' => 'nvidia/llama-nemotron-embed-vl-1b-v2',
				'default_vision_model'     => 'meta/llama-3.2-11b-vision-instruct',
			),
			'mistral'    => array(
				'label'                    => 'Mistral AI',
				'api_key_option'           => 'tsbifw_api_key_mistral',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'mistral-embed',
				'default_vision_model'     => 'pixtral-12b-2409',
			),
			'together'   => array(
				'label'                    => 'Together AI',
				'api_key_option'           => 'tsbifw_api_key_together',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'BAAI/bge-base-en-v1.5',
				'default_vision_model'     => 'meta-llama/Llama-3.2-11B-Vision-Instruct-Turbo',
			),
			'fireworks'  => array(
				'label'                    => 'Fireworks AI',
				'api_key_option'           => 'tsbifw_api_key_fireworks',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'nomic-ai/nomic-embed-text-v1.5',
				'default_vision_model'     => 'accounts/fireworks/models/llama-v3p2-11b-vision-instruct',
			),
			'twelvelabs' => array(
				'label'                    => 'TwelveLabs (Marengo)',
				'api_key_option'           => 'tsbifw_api_key_twelvelabs',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => false,
				'embedding_protocol'       => 'twelvelabs',
				'default_embeddings_model' => 'marengo3.5',
				'default_vision_model'     => '',
			),
			'xai'        => array(
				'label'                    => 'xAI (Grok)',
				'api_key_option'           => 'tsbifw_api_key_xai',
				'supports_strategy_1'      => false,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'none',
				'default_embeddings_model' => '',
				'default_vision_model'     => 'grok-2-vision-1212',
			),
			'dashscope'  => array(
				'label'                    => 'Alibaba Cloud (Qwen DashScope)',
				'api_key_option'           => 'tsbifw_api_key_dashscope',
				'supports_strategy_1'      => false,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'none',
				'default_embeddings_model' => '',
				'default_vision_model'     => 'qwen-vl-max',
			),
			'cloudflare' => array(
				'label'                    => 'Cloudflare Workers AI',
				'api_key_option'           => 'tsbifw_api_key_cloudflare',
				'supports_strategy_1'      => false,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'none',
				'default_embeddings_model' => '',
				'default_vision_model'     => '@cf/meta/llama-3.2-11b-vision-instruct',
			),
			'arvancloud' => array(
				'label'                    => 'ArvanCloud AI',
				'api_key_option'           => 'tsbifw_api_key_arvancloud',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-large',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'metisai'    => array(
				'label'                    => 'MetisAI',
				'api_key_option'           => 'tsbifw_api_key_metisai',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'liara'      => array(
				'label'                    => 'Liara AI',
				'api_key_option'           => 'tsbifw_api_key_liara',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'openai/text-embedding-3-small',
				'default_vision_model'     => 'openai/gpt-4o-mini',
			),
			'heroai'     => array(
				'label'                    => 'HeroAI',
				'api_key_option'           => 'tsbifw_api_key_heroai',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'talkbot'    => array(
				'label'                    => 'TalkBot',
				'api_key_option'           => 'tsbifw_api_key_talkbot',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'cupai'      => array(
				'label'                    => 'CupAI',
				'api_key_option'           => 'tsbifw_api_key_cupai',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'hooshang'   => array(
				'label'                    => 'Hooshang',
				'api_key_option'           => 'tsbifw_api_key_hooshang',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'hormouz'    => array(
				'label'                    => 'Hormouz',
				'api_key_option'           => 'tsbifw_api_key_hormouz',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
			'custom'     => array(
				'label'                    => 'Custom OpenAI-Compatible (Ollama / Custom)',
				'api_key_option'           => 'tsbifw_api_key_custom',
				'supports_strategy_1'      => true,
				'supports_strategy_2'      => true,
				'embedding_protocol'       => 'openai_vision_to_vector',
				'default_embeddings_model' => 'text-embedding-3-small',
				'default_vision_model'     => 'gpt-4o-mini',
			),
		);

		return apply_filters( 'tsbifw_gateway_configs', $configs );
	}

	/**
	 * Return gateway slug => label map.
	 *
	 * @return array
	 */
	public function get_gateway_labels() {
		$labels = array();
		foreach ( $this->get_gateway_configs() as $slug => $config ) {
			$labels[ $slug ] = $config['label'];
		}
		return apply_filters( 'tsbifw_api_gateways', $labels );
	}

	/**
	 * Get the currently active AI gateway.
	 *
	 * @return string Gateway identifier.
	 */
	public function get_active_gateway() {
		$default_allowed  = array_keys( $this->get_gateway_configs() );
		$allowed_gateways = apply_filters( 'tsbifw_allowed_gateways', $default_allowed );
		$gateway          = get_option( 'tsbifw_api_gateway', 'openrouter' );

		if ( ! in_array( $gateway, $allowed_gateways, true ) ) {
			$gateway = 'openrouter';
		}

		return $gateway;
	}

	/**
	 * Retrieve API Key for the active or specified gateway from settings.
	 *
	 * @param string|null $gateway Optional specific gateway identifier.
	 * @return string
	 */
	public function get_api_key( $gateway = null ) {
		if ( null === $gateway ) {
			$gateway = $this->get_active_gateway();
		}

		$configs    = $this->get_gateway_configs();
		$option_key = isset( $configs[ $gateway ]['api_key_option'] ) ? $configs[ $gateway ]['api_key_option'] : 'tsbifw_api_key';
		$api_key    = (string) get_option( $option_key, '' );

		if ( 'custom' === $gateway && '' === $api_key ) {
			$base_url = $this->get_custom_base_url();
			if ( '' !== $base_url && $this->is_loopback_host( $base_url ) ) {
				$api_key = 'ollama';
			}
		}

		return apply_filters( 'tsbifw_gateway_api_key', $api_key, $gateway );
	}

	/**
	 * Validate and normalize a custom OpenAI-compatible base URL.
	 *
	 * @param string $raw_url Raw URL string.
	 * @return string Sanitized base URL or empty string if invalid.
	 */
	public static function sanitize_custom_base_url( $raw_url ) {
		$url = trim( (string) $raw_url );
		if ( '' === $url ) {
			return '';
		}

		$url = preg_replace( '#/(chat/completions|embeddings|models)/?$#i', '', $url );
		$url = untrailingslashit( esc_url_raw( $url ) );
		if ( '' === $url ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( ! is_array( $parsed ) || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return '';
		}
		if ( ! empty( $parsed['user'] ) || ! empty( $parsed['pass'] ) ) {
			return '';
		}

		$scheme = strtolower( $parsed['scheme'] );
		$host   = strtolower( trim( $parsed['host'], '[]' ) );
		$local  = in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );

		if ( 'https' !== $scheme && ! ( 'http' === $scheme && $local ) ) {
			return '';
		}

		if ( ! $local && filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$is_public = filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
			if ( false === $is_public ) {
				return '';
			}
		}

		return $url;
	}

	/**
	 * Retrieve the validated custom base URL option.
	 *
	 * @return string
	 */
	public function get_custom_base_url() {
		return self::sanitize_custom_base_url( get_option( 'tsbifw_custom_base_url', '' ) );
	}

	/**
	 * Validate a Cloudflare Account ID (alphanumeric, hyphen, underscore only).
	 *
	 * @param string $raw_id Raw Account ID.
	 * @return string
	 */
	public static function sanitize_cloudflare_account_id( $raw_id ) {
		return preg_replace( '/[^a-zA-Z0-9_-]/', '', trim( (string) $raw_id ) );
	}

	/**
	 * Retrieve the validated Cloudflare Account ID option.
	 *
	 * @return string
	 */
	public function get_cloudflare_account_id() {
		return self::sanitize_cloudflare_account_id( get_option( 'tsbifw_cloudflare_account_id', '' ) );
	}

	private function is_loopback_host( $url ) {
		$host = strtolower( trim( (string) wp_parse_url( $url, PHP_URL_HOST ), '[]' ) );
		return in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true );
	}

	private function get_endpoint_url( $gateway, $action, $model = '' ) {
		$url        = '';
		$is_emb     = ( 'embeddings' === $action );
		$openai_map = array(
			'openrouter' => 'https://openrouter.ai/api/v1',
			'openai'     => 'https://api.openai.com/v1',
			'gapgpt'     => 'https://api.gapgpt.app/v1',
			'nvidia'     => 'https://integrate.api.nvidia.com/v1',
			'mistral'    => 'https://api.mistral.ai/v1',
			'together'   => 'https://api.together.xyz/v1',
			'fireworks'  => 'https://api.fireworks.ai/inference/v1',
			'xai'        => 'https://api.x.ai/v1',
			'dashscope'  => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1',
			'arvancloud' => 'https://api.arvancloudai.ir/v1',
			'metisai'    => 'https://api.metisai.ir/openai/v1',
			'liara'      => 'https://ai.liara.ir/v1',
			'heroai'     => 'https://api.heroai.ir/v1',
			'talkbot'    => 'https://api.talkbot.ir/v1',
			'cupai'      => 'https://api.cupai.ir/v1',
			'hooshang'   => 'https://hushang.cloud/v1',
			'hormouz'    => 'https://api.hormouz.net/v1',
		);

		if ( isset( $openai_map[ $gateway ] ) ) {
			$url = $openai_map[ $gateway ] . ( $is_emb ? '/embeddings' : '/chat/completions' );
		} elseif ( 'gemini' === $gateway ) {
			$url = $is_emb
				? 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':embedContent'
				: 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
		} elseif ( 'avalai' === $gateway ) {
			$url = $is_emb
				? 'https://api.avalai.ir/v1beta/models/' . rawurlencode( $model ) . ':embedContent'
				: 'https://api.avalai.ir/v1/chat/completions';
		} elseif ( 'jina' === $gateway ) {
			$url = 'https://api.jina.ai/v1/embeddings';
		} elseif ( 'voyage' === $gateway ) {
			$url = 'https://api.voyageai.com/v1/multimodalembeddings';
		} elseif ( 'cohere' === $gateway ) {
			$url = 'https://api.cohere.com/v2/embed';
		} elseif ( 'groq' === $gateway ) {
			$url = 'https://api.groq.com/openai/v1/chat/completions';
		} elseif ( 'twelvelabs' === $gateway ) {
			$url = 'https://api.twelvelabs.io/v1.3/embed-v2';
		} elseif ( 'cloudflare' === $gateway ) {
			$acct = $this->get_cloudflare_account_id();
			if ( '' !== $acct ) {
				$url = 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( $acct ) . '/ai/v1/chat/completions';
			}
		} elseif ( 'custom' === $gateway ) {
			$base = $this->get_custom_base_url();
			if ( '' !== $base ) {
				$url = $base . ( $is_emb ? '/embeddings' : '/chat/completions' );
			}
		}

		return apply_filters( 'tsbifw_api_url', $url, $gateway, $action );
	}

	private function get_request_headers( $gateway, $action, $api_key ) {
		$headers = array(
			'Content-Type' => 'application/json',
		);

		if ( 'gemini' === $gateway ) {
			$headers['x-goog-api-key'] = $api_key;
			if ( 'chat' === $action ) {
				$headers['Authorization'] = 'Bearer ' . $api_key;
			}
		} elseif ( 'avalai' === $gateway && 'embeddings' === $action ) {
			$headers['x-goog-api-key'] = $api_key;
			$headers['Authorization']  = 'Bearer ' . $api_key;
		} elseif ( 'twelvelabs' === $gateway ) {
			$headers['x-api-key'] = $api_key;
		} elseif ( 'arvancloud' === $gateway ) {
			$headers['Authorization'] = 'apikey ' . preg_replace( '/^apikey\s+/i', '', $api_key );
		} elseif ( 'openrouter' === $gateway ) {
			$headers['Authorization'] = 'Bearer ' . $api_key;
			$headers['HTTP-Referer']  = get_home_url();
			$headers['X-Title']       = 'Searchips WP';
		} else {
			$headers['Authorization'] = 'Bearer ' . $api_key;
		}

		return apply_filters( 'tsbifw_api_headers', $headers, $gateway, $action );
	}

	private function normalize_model( $model, $gateway, $action ) {
		$configs = $this->get_gateway_configs();
		$model   = trim( (string) $model );

		if ( 'custom' === $gateway ) {
			$opt_key      = ( 'embeddings' === $action ) ? 'tsbifw_custom_embeddings_model' : 'tsbifw_custom_vision_model';
			$custom_model = trim( (string) get_option( $opt_key, '' ) );
			if ( '' !== $custom_model ) {
				$model = $custom_model;
			}
		}

		$bare_gateways = array( 'openai', 'gemini', 'avalai', 'gapgpt', 'arvancloud', 'metisai', 'heroai', 'talkbot', 'cupai', 'hooshang', 'hormouz' );
		if ( in_array( $gateway, $bare_gateways, true ) && strpos( $model, '/' ) !== false ) {
			$parts = explode( '/', $model );
			$model = end( $parts );
		}

		$default_key = ( 'embeddings' === $action ) ? 'default_embeddings_model' : 'default_vision_model';
		$fallback    = isset( $configs[ $gateway ][ $default_key ] ) ? $configs[ $gateway ][ $default_key ] : '';

		if ( '' === $model || ! $this->is_model_compatible_with_gateway( $model, $gateway, $action ) ) {
			$model = $fallback;
		}

		return apply_filters( 'tsbifw_api_model', $model, $gateway, $action );
	}

	private function is_model_compatible_with_gateway( $model, $gateway, $action ) {
		$lower = strtolower( $model );
		if ( 'openrouter' === $gateway ) {
			return strpos( $lower, '/' ) !== false;
		}
		if ( 'embeddings' === $action ) {
			if ( 'jina' === $gateway ) {
				return strpos( $lower, 'jina' ) !== false;
			}
			if ( 'voyage' === $gateway ) {
				return strpos( $lower, 'voyage' ) !== false;
			}
			if ( 'cohere' === $gateway ) {
				return strpos( $lower, 'embed-' ) === 0;
			}
			if ( 'gemini' === $gateway || 'avalai' === $gateway ) {
				return strpos( $lower, 'gemini' ) !== false;
			}
			if ( 'nvidia' === $gateway ) {
				return strpos( $lower, 'nvidia/' ) === 0 || strpos( $lower, 'nvclip' ) !== false || strpos( $lower, 'nemotron' ) !== false;
			}
			if ( 'twelvelabs' === $gateway ) {
				return strpos( $lower, 'marengo' ) !== false;
			}
			if ( 'mistral' === $gateway ) {
				return strpos( $lower, 'mistral' ) !== false;
			}
			$openai_like = array( 'openai', 'gapgpt', 'arvancloud', 'metisai', 'liara', 'heroai', 'talkbot', 'cupai', 'hooshang', 'hormouz' );
			if ( in_array( $gateway, $openai_like, true ) ) {
				return strpos( $lower, 'jina' ) === false && strpos( $lower, 'voyage' ) === false && strpos( $lower, 'gemini' ) === false && strpos( $lower, 'embed-v' ) === false && strpos( $lower, 'marengo' ) === false;
			}
		} else {
			if ( 'groq' === $gateway ) {
				return strpos( $lower, 'llama' ) !== false || strpos( $lower, 'vision' ) !== false;
			}
			if ( 'xai' === $gateway ) {
				return strpos( $lower, 'grok' ) !== false;
			}
			if ( 'dashscope' === $gateway ) {
				return strpos( $lower, 'qwen' ) !== false;
			}
			if ( 'cloudflare' === $gateway ) {
				return strpos( $lower, '@cf/' ) === 0;
			}
		}

		return true;
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

		$metadata    = wp_get_attachment_metadata( $attachment_id );
		$target_path = $file_path;

		if ( is_array( $metadata ) && isset( $metadata['sizes'] ) ) {
			$sizes_to_try = array( 'medium_large', 'large' );
			foreach ( $sizes_to_try as $size_name ) {
				if ( isset( $metadata['sizes'][ $size_name ]['file'] ) ) {
					$size_file_path = dirname( $file_path ) . '/' . $metadata['sizes'][ $size_name ]['file'];
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
	 * Prepare a raw uploaded file for API transmission.
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

		$max_mb = (int) get_option( 'tsbifw_max_upload_size', 2 );
		if ( $max_mb <= 0 ) {
			$max_mb = 2;
		}
		if ( $size > ( $max_mb * 1024 * 1024 ) ) {
			return new WP_Error(
				'tsbifw_file_too_large',
				sprintf(
					// translators: 1: Current size in MB, 2: Max allowed size in MB.
					esc_html__( 'Uploaded image is too large (%1$.2f MB). Maximum allowed size is %2$d MB.', 'searchips-search-by-image-for-woocommerce' ),
					$size / ( 1024 * 1024 ),
					$max_mb
				)
			);
		}

		$dimensions = @getimagesize( $uploaded_file_path );
		if ( false === $dimensions ) {
			return new WP_Error( 'tsbifw_invalid_image', esc_html__( 'Unable to read image dimensions or invalid image file.', 'searchips-search-by-image-for-woocommerce' ) );
		}
		if ( $dimensions[0] > 6000 || $dimensions[1] > 6000 ) {
			return new WP_Error( 'tsbifw_image_dimensions_too_large', esc_html__( 'Image dimensions exceed the allowed limit (max 6000x6000px).', 'searchips-search-by-image-for-woocommerce' ) );
		}

		return $this->resize_and_encode_image( $uploaded_file_path );
	}

	private function resize_and_encode_image( $file_path ) {
		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$editor->resize( 512, 512, false );

		if ( ! function_exists( 'wp_tempnam' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$temp_file = function_exists( 'wp_tempnam' ) ? wp_tempnam( 'tsbifw_' ) : tempnam( get_temp_dir(), 'tsbifw_' );
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
	 * Fetch vector embeddings from the active AI Gateway.
	 *
	 * @param string $base64_image Base64 encoded image data URL.
	 * @param string $context      Retrieval context ('document' for indexing, 'query' for search).
	 * @return array|WP_Error Array of floats representing the embedding vector, or WP_Error.
	 */
	public function get_embeddings( $base64_image, $context = 'document' ) {
		$gateway = $this->get_active_gateway();
		$configs = $this->get_gateway_configs();
		$config  = isset( $configs[ $gateway ] ) ? $configs[ $gateway ] : $configs['openrouter'];

		if ( empty( $config['supports_strategy_1'] ) ) {
			return new WP_Error(
				'tsbifw_unsupported_strategy',
				sprintf(
					// translators: %s: Gateway display label.
					esc_html__( '%s does not provide an embeddings endpoint. Please switch Search Strategy to Strategy 2 (Vision-to-Text) in settings.', 'searchips-search-by-image-for-woocommerce' ),
					$config['label']
				)
			);
		}

		$api_key = $this->get_api_key( $gateway );
		if ( empty( $api_key ) ) {
			// translators: %s: Gateway label.
			return new WP_Error( 'tsbifw_missing_api_key', sprintf( esc_html__( '%s API Key is missing. Please configure it in WooCommerce settings.', 'searchips-search-by-image-for-woocommerce' ), $config['label'] ) );
		}

		if ( 'custom' === $gateway && '' === $this->get_custom_base_url() ) {
			return new WP_Error( 'tsbifw_missing_base_url', esc_html__( 'Custom OpenAI-Compatible Base URL is missing or invalid. Please configure a valid HTTPS (or localhost) URL in settings.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$model = $this->normalize_model( get_option( 'tsbifw_embeddings_model', $config['default_embeddings_model'] ), $gateway, 'embeddings' );

		if ( 'openai_vision_to_vector' === $config['embedding_protocol'] ) {
			return $this->get_vision_to_vector_embeddings( $gateway, $model, $base64_image, $api_key );
		}

		$body    = $this->build_embeddings_payload( $config['embedding_protocol'], $model, $base64_image, $context );
		$body    = apply_filters( 'tsbifw_embeddings_request_body', $body, $gateway, $model, $base64_image );
		$headers = $this->get_request_headers( $gateway, 'embeddings', $api_key );
		$api_url = $this->get_endpoint_url( $gateway, 'embeddings', $model );

		return $this->send_embeddings_request( $gateway, $config['embedding_protocol'], $model, $api_url, $headers, $body, strlen( $base64_image ) );
	}

	private function get_vision_to_vector_embeddings( $gateway, $embedding_model, $base64_image, $api_key ) {
		$description = $this->get_vision_description( $base64_image );
		if ( is_wp_error( $description ) ) {
			return $description;
		}

		$body = array(
			'model' => $embedding_model,
			'input' => $description,
		);
		$body    = apply_filters( 'tsbifw_embeddings_request_body', $body, $gateway, $embedding_model, $base64_image );
		$headers = $this->get_request_headers( $gateway, 'embeddings', $api_key );
		$api_url = $this->get_endpoint_url( $gateway, 'embeddings', $embedding_model );

		return $this->send_embeddings_request( $gateway, 'openai_text', $embedding_model, $api_url, $headers, $body, strlen( $description ) );
	}

	/**
	 * Build protocol-specific payload for image embeddings.
	 *
	 * @param string $protocol     Embedding protocol identifier.
	 * @param string $model        Normalized model ID.
	 * @param string $base64_image Base64 data URL.
	 * @param string $context      'document' or 'query'.
	 * @return array
	 */
	public function build_embeddings_payload( $protocol, $model, $base64_image, $context = 'document' ) {
		$is_query = ( 'query' === $context );

		if ( 'gemini_native' === $protocol ) {
			$raw_data = preg_replace( '#^data:image/[^;]+;base64,#i', '', $base64_image );
			return array(
				'content'  => array(
					'parts' => array(
						array(
							'inlineData' => array(
								'mimeType' => 'image/jpeg',
								'data'     => $raw_data,
							),
						),
					),
				),
				'taskType' => $is_query ? 'RETRIEVAL_QUERY' : 'RETRIEVAL_DOCUMENT',
			);
		}

		if ( 'jina' === $protocol ) {
			return array(
				'model'          => $model,
				'normalized'     => true,
				'embedding_type' => 'float',
				'input'          => array(
					array( 'image' => $base64_image ),
				),
			);
		}

		if ( 'voyage' === $protocol ) {
			return array(
				'model'      => $model,
				'input_type' => $is_query ? 'query' : 'document',
				'inputs'     => array(
					array(
						'content' => array(
							array(
								'type'         => 'image_base64',
								'image_base64' => $base64_image,
							),
						),
					),
				),
			);
		}

		if ( 'cohere' === $protocol ) {
			return array(
				'model'           => $model,
				'input_type'      => $is_query ? 'search_query' : 'search_document',
				'embedding_types' => array( 'float' ),
				'images'          => array( $base64_image ),
			);
		}

		if ( 'nvidia_nim' === $protocol ) {
			$payload = array(
				'model'           => $model,
				'input'           => array( '<img src="' . $base64_image . '" />' ),
				'encoding_format' => 'float',
			);
			$lower   = strtolower( $model );
			if ( false !== strpos( $lower, 'nemotron' ) || false !== strpos( $lower, '-vl' ) ) {
				$payload['input_type'] = $is_query ? 'query' : 'passage';
			}
			return $payload;
		}

		if ( 'twelvelabs' === $protocol ) {
			$raw_data = preg_replace( '#^data:image/[^;]+;base64,#i', '', $base64_image );
			return array(
				'input_type'  => 'multi_input',
				'model_name'  => $model,
				'multi_input' => array(
					'media_sources' => array(
						array(
							'media_type'     => 'image',
							'base_64_string' => $raw_data,
						),
					),
				),
			);
		}

		return array(
			'model' => $model,
			'input' => array(
				array(
					'content' => array(
						array(
							'type'      => 'image_url',
							'image_url' => array( 'url' => $base64_image ),
						),
					),
				),
			),
		);
	}

	/**
	 * Extract float embedding vector from decoded API JSON response.
	 *
	 * @param string $protocol Protocol identifier.
	 * @param mixed  $data     Decoded JSON array.
	 * @return array|null
	 */
	public function extract_embedding_vector( $protocol, $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}
		if ( 'gemini_native' === $protocol && isset( $data['embedding']['values'] ) && is_array( $data['embedding']['values'] ) ) {
			return $data['embedding']['values'];
		}
		if ( 'cohere' === $protocol && isset( $data['embeddings']['float'][0] ) && is_array( $data['embeddings']['float'][0] ) ) {
			return $data['embeddings']['float'][0];
		}
		if ( 'twelvelabs' === $protocol ) {
			if ( isset( $data['image_embedding']['segments'][0]['embedding'] ) && is_array( $data['image_embedding']['segments'][0]['embedding'] ) ) {
				return $data['image_embedding']['segments'][0]['embedding'];
			}
			if ( isset( $data['image_embedding']['segments'][0]['embeddings_float'] ) && is_array( $data['image_embedding']['segments'][0]['embeddings_float'] ) ) {
				return $data['image_embedding']['segments'][0]['embeddings_float'];
			}
		}
		if ( isset( $data['data'][0]['embedding'] ) && is_array( $data['data'][0]['embedding'] ) ) {
			return $data['data'][0]['embedding'];
		}
		return null;
	}

	private function send_embeddings_request( $gateway, $protocol, $model, $api_url, $headers, $body, $payload_bytes ) {
		TSBIFW_Logger::log(
			sprintf( 'Sending vector embeddings request to %s for model: %s', $gateway, $model ),
			array(
				'gateway'     => $gateway,
				'protocol'    => $protocol,
				'model'       => $model,
				'image_bytes' => $payload_bytes,
			)
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'body'        => is_array( $body ) ? wp_json_encode( $body ) : $body,
				'data_format' => 'body',
				'timeout'     => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( sprintf( '%s Embeddings request failed (WP_Error).', ucfirst( $gateway ) ), array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$parsed_custom = apply_filters( 'tsbifw_parse_embeddings_response', null, $response_body, $gateway, $response_code );
		if ( null !== $parsed_custom ) {
			return $parsed_custom;
		}

		if ( 200 !== $response_code ) {
			return $this->build_http_wp_error( $gateway, 'Embeddings', $response_code, $response_body );
		}

		$data   = json_decode( $response_body, true );
		$vector = $this->extract_embedding_vector( $protocol, $data );
		if ( empty( $vector ) ) {
			TSBIFW_Logger::log( 'Embeddings formatting mismatch in API response.', array( 'response' => $data ) );
			return new WP_Error( 'tsbifw_api_format_error', esc_html__( 'Failed to extract embedding vector from API response.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		TSBIFW_Logger::log( sprintf( 'Received embedding vector successfully. Dimensions: %d', count( $vector ) ) );
		return $vector;
	}

	/**
	 * Fetch image text description from AI Gateway for Strategy 2 or 2-step embeddings.
	 *
	 * @param string $base64_image   Base64 encoded image data URL.
	 * @param string $override_model Optional model override.
	 * @return string|WP_Error
	 */
	public function get_vision_description( $base64_image, $override_model = '' ) {
		$gateway = $this->get_active_gateway();
		$configs = $this->get_gateway_configs();
		$config  = isset( $configs[ $gateway ] ) ? $configs[ $gateway ] : $configs['openrouter'];

		if ( empty( $config['supports_strategy_2'] ) ) {
			return new WP_Error(
				'tsbifw_unsupported_strategy',
				sprintf(
					// translators: %s: Gateway display label.
					esc_html__( '%s is an embeddings-only provider and does not support Vision-to-Text chat. Please switch Search Strategy to Strategy 1 (Vector Embeddings).', 'searchips-search-by-image-for-woocommerce' ),
					$config['label']
				)
			);
		}

		$api_key = $this->get_api_key( $gateway );
		if ( empty( $api_key ) ) {
			// translators: %s: Gateway label.
			return new WP_Error( 'tsbifw_missing_api_key', sprintf( esc_html__( '%s API Key is missing. Please configure it in WooCommerce settings.', 'searchips-search-by-image-for-woocommerce' ), $config['label'] ) );
		}

		if ( 'custom' === $gateway && '' === $this->get_custom_base_url() ) {
			return new WP_Error( 'tsbifw_missing_base_url', esc_html__( 'Custom OpenAI-Compatible Base URL is missing or invalid. Please configure a valid HTTPS (or localhost) URL in settings.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		if ( 'cloudflare' === $gateway && '' === $this->get_cloudflare_account_id() ) {
			return new WP_Error( 'tsbifw_missing_account_id', esc_html__( 'Cloudflare Account ID is missing. Please configure it in WooCommerce settings.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$raw_model = '' !== $override_model ? $override_model : get_option( 'tsbifw_vision_model', $config['default_vision_model'] );
		$model     = $this->normalize_model( $raw_model, $gateway, 'chat' );

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
							'image_url' => array( 'url' => $base64_image ),
						),
					),
				),
			),
		);
		$body    = apply_filters( 'tsbifw_chat_request_body', $body, $gateway, $model, $base64_image );
		$headers = $this->get_request_headers( $gateway, 'chat', $api_key );
		$api_url = $this->get_endpoint_url( $gateway, 'chat', $model );

		TSBIFW_Logger::log(
			sprintf( 'Sending vision description request to %s for model: %s', $gateway, $model ),
			array(
				'gateway'     => $gateway,
				'model'       => $model,
				'image_bytes' => strlen( $base64_image ),
			)
		);

		$response = wp_remote_post(
			$api_url,
			array(
				'method'      => 'POST',
				'headers'     => $headers,
				'body'        => is_array( $body ) ? wp_json_encode( $body ) : $body,
				'data_format' => 'body',
				'timeout'     => 45,
			)
		);

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( sprintf( '%s Vision request failed (WP_Error).', ucfirst( $gateway ) ), array( 'error' => $response->get_error_message() ) );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		$parsed_custom = apply_filters( 'tsbifw_parse_chat_response', null, $response_body, $gateway, $response_code );
		if ( null !== $parsed_custom ) {
			return $parsed_custom;
		}

		if ( 200 !== $response_code ) {
			return $this->build_http_wp_error( $gateway, 'Vision', $response_code, $response_body );
		}

		$data = json_decode( $response_body, true );
		if ( ! isset( $data['choices'][0]['message']['content'] ) || ! is_string( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'tsbifw_api_format_error', esc_html__( 'Failed to extract text description from API response.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		return wp_strip_all_tags( trim( $data['choices'][0]['message']['content'] ) );
	}

	private function build_http_wp_error( $gateway, $context_label, $response_code, $response_body ) {
		$error_data   = json_decode( $response_body, true );
		$api_err_code = isset( $error_data['error']['code'] ) ? $error_data['error']['code'] : $response_code;
		$err_msg      = '';

		if ( isset( $error_data['error']['message'] ) && is_string( $error_data['error']['message'] ) ) {
			$err_msg = $error_data['error']['message'];
		} elseif ( isset( $error_data['error'] ) && is_string( $error_data['error'] ) ) {
			$err_msg = $error_data['error'];
		} elseif ( isset( $error_data['message'] ) && is_string( $error_data['message'] ) ) {
			$err_msg = $error_data['message'];
		} elseif ( isset( $error_data['detail'] ) && is_string( $error_data['detail'] ) ) {
			$err_msg = $error_data['detail'];
		} else {
			$err_msg = esc_html__( 'Unknown API error.', 'searchips-search-by-image-for-woocommerce' );
		}

		TSBIFW_Logger::log(
			sprintf( '%1$s %2$s HTTP Error: %3$d (API Code: %4$s)', ucfirst( $gateway ), $context_label, $response_code, $api_err_code ),
			array( 'response' => $error_data )
		);

		return new WP_Error(
			'tsbifw_api_error',
			sprintf(
				// translators: 1: Gateway, 2: API Error Code, 3: Error message.
				esc_html__( '%1$s API Error [Code %2$s]: %3$s', 'searchips-search-by-image-for-woocommerce' ),
				ucfirst( $gateway ),
				$api_err_code,
				$err_msg
			)
		);
	}

	/**
	 * Test connectivity and API credentials for a gateway.
	 *
	 * Uses a lightweight GET probe where supported and a minimal 1-token POST ping
	 * for embeddings-only providers without a models endpoint (Jina/Voyage).
	 *
	 * @param string      $gateway         Target gateway slug.
	 * @param string|null $api_key         Optional unsaved API key override.
	 * @param string|null $custom_base_url Optional unsaved custom base URL override.
	 * @return array|WP_Error Latency and status payload on success, or WP_Error on failure.
	 */
	public function test_connection( $gateway, $api_key = null, $custom_base_url = null, $account_id = null ) {
		$gateway = sanitize_key( (string) $gateway );
		$configs = $this->get_gateway_configs();
		if ( ! isset( $configs[ $gateway ] ) ) {
			return new WP_Error( 'tsbifw_invalid_gateway', esc_html__( 'Unknown AI provider gateway.', 'searchips-search-by-image-for-woocommerce' ) );
		}
		$config = $configs[ $gateway ];

		$base_url = '';
		if ( 'custom' === $gateway ) {
			$raw_base = ( null !== $custom_base_url ) ? (string) $custom_base_url : (string) get_option( 'tsbifw_custom_base_url', '' );
			$base_url = self::sanitize_custom_base_url( $raw_base );
			if ( '' === $base_url ) {
				return new WP_Error( 'tsbifw_missing_base_url', esc_html__( 'Custom OpenAI-Compatible Base URL is missing or invalid. Please enter a valid HTTPS (or localhost) URL.', 'searchips-search-by-image-for-woocommerce' ) );
			}
		}

		$cf_account_id = '';
		if ( 'cloudflare' === $gateway ) {
			$raw_acct      = ( null !== $account_id ) ? (string) $account_id : (string) get_option( 'tsbifw_cloudflare_account_id', '' );
			$cf_account_id = self::sanitize_cloudflare_account_id( $raw_acct );
			if ( '' === $cf_account_id ) {
				return new WP_Error( 'tsbifw_missing_account_id', esc_html__( 'Cloudflare Account ID is missing. Please enter your Account ID first.', 'searchips-search-by-image-for-woocommerce' ) );
			}
		}

		$resolved_key = ( null !== $api_key ) ? trim( (string) $api_key ) : trim( (string) $this->get_api_key( $gateway ) );
		if ( '' === $resolved_key && 'custom' === $gateway && $this->is_loopback_host( $base_url ) ) {
			$resolved_key = 'ollama';
		}
		if ( '' === $resolved_key ) {
			// translators: %s: Gateway display label.
			return new WP_Error( 'tsbifw_missing_api_key', sprintf( esc_html__( '%s API Key is missing. Please enter an API key first.', 'searchips-search-by-image-for-woocommerce' ), $config['label'] ) );
		}

		$probe = $this->build_connection_probe( $gateway, $resolved_key, $base_url, $cf_account_id );
		$start = microtime( true );
		$args  = array(
			'headers' => $probe['headers'],
			'timeout' => 15,
		);
		if ( 'POST' === $probe['method'] ) {
			$args['body']        = wp_json_encode( $probe['body'] );
			$args['data_format'] = 'body';
			$response            = wp_remote_post( $probe['url'], $args );
		} else {
			$response = wp_remote_get( $probe['url'], $args );
		}
		$latency_ms = max( 1, (int) round( ( microtime( true ) - $start ) * 1000 ) );

		if ( is_wp_error( $response ) ) {
			TSBIFW_Logger::log( sprintf( '%s connection test failed (WP_Error).', $config['label'] ), array( 'error' => $response->get_error_message() ) );
			return new WP_Error(
				'tsbifw_connection_failed',
				sprintf(
					// translators: %s: Gateway display label.
					esc_html__( 'Could not connect to %s. Check your network, Base URL, and SSL configuration.', 'searchips-search-by-image-for-woocommerce' ),
					$config['label']
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			return $this->build_http_wp_error( $gateway, 'Connection Test', $code, $body );
		}

		return array(
			'gateway'    => $gateway,
			'label'      => $config['label'],
			'latency_ms' => $latency_ms,
			'message'    => sprintf(
				// translators: 1: Gateway label, 2: Latency in milliseconds.
				esc_html__( 'Connected to %1$s (%2$d ms).', 'searchips-search-by-image-for-woocommerce' ),
				$config['label'],
				$latency_ms
			),
		);
	}

	private function build_connection_probe( $gateway, $api_key, $base_url, $cf_account_id = '' ) {
		if ( 'jina' === $gateway ) {
			return array(
				'method'  => 'POST',
				'url'     => 'https://api.jina.ai/v1/embeddings',
				'headers' => $this->get_request_headers( 'jina', 'embeddings', $api_key ),
				'body'    => array(
					'model' => 'jina-clip-v2',
					'input' => array( array( 'text' => 'ping' ) ),
				),
			);
		}

		if ( 'voyage' === $gateway ) {
			return array(
				'method'  => 'POST',
				'url'     => 'https://api.voyageai.com/v1/multimodalembeddings',
				'headers' => $this->get_request_headers( 'voyage', 'embeddings', $api_key ),
				'body'    => array(
					'model'  => 'voyage-multimodal-3.5',
					'inputs' => array( array( 'content' => array( array( 'type' => 'text', 'text' => 'ping' ) ) ) ),
				),
			);
		}

		$get_urls = array(
			'openrouter' => 'https://openrouter.ai/api/v1/auth/key',
			'openai'     => 'https://api.openai.com/v1/models',
			'gemini'     => 'https://generativelanguage.googleapis.com/v1beta/models',
			'cohere'     => 'https://api.cohere.com/v1/models',
			'groq'       => 'https://api.groq.com/openai/v1/models',
			'avalai'     => 'https://api.avalai.ir/v1/models',
			'gapgpt'     => 'https://api.gapgpt.app/v1/models',
			'nvidia'     => 'https://integrate.api.nvidia.com/v1/models',
			'mistral'    => 'https://api.mistral.ai/v1/models',
			'together'   => 'https://api.together.xyz/v1/models',
			'fireworks'  => 'https://api.fireworks.ai/inference/v1/models',
			'twelvelabs' => 'https://api.twelvelabs.io/v1.3/indexes?page=1&page_limit=1',
			'xai'        => 'https://api.x.ai/v1/models',
			'dashscope'  => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/models',
			'cloudflare' => 'https://api.cloudflare.com/client/v4/accounts/' . rawurlencode( $cf_account_id ) . '/ai/models/search?per_page=1',
			'arvancloud' => 'https://api.arvancloudai.ir/v1/models',
			'metisai'    => 'https://api.metisai.ir/openai/v1/models',
			'liara'      => 'https://ai.liara.ir/v1/models',
			'heroai'     => 'https://api.heroai.ir/v1/models',
			'talkbot'    => 'https://api.talkbot.ir/v1/models',
			'cupai'      => 'https://api.cupai.ir/v1/models',
			'hooshang'   => 'https://hushang.cloud/v1/models',
			'hormouz'    => 'https://api.hormouz.net/v1/models',
			'custom'     => $base_url . '/models',
		);

		$headers = $this->get_request_headers( $gateway, 'models', $api_key );
		unset( $headers['Content-Type'] );

		return array(
			'method'  => 'GET',
			'url'     => isset( $get_urls[ $gateway ] ) ? $get_urls[ $gateway ] : '',
			'headers' => $headers,
		);
	}

	/**
	 * Retrieve available models for a given modality and gateway.
	 *
	 * @param string      $modality 'embeddings' or '' (chat/vision).
	 * @param string|null $gateway  Gateway slug.
	 * @return array|WP_Error
	 */
	public function get_models( $modality = '', $gateway = null ) {
		if ( empty( $gateway ) ) {
			$gateway = $this->get_active_gateway();
		}

		$custom_models = apply_filters( 'tsbifw_get_models', null, $modality, $gateway );
		if ( null !== $custom_models ) {
			return $custom_models;
		}

		$curated = $this->get_curated_models( $gateway, $modality );

		if ( 'openrouter' === $gateway ) {
			$url = 'https://openrouter.ai/api/v1/models' . ( 'embeddings' === $modality ? '?output_modalities=embeddings' : '' );
			return $this->fetch_remote_models_cached( $gateway, $modality, $url, '', $curated );
		}

		if ( 'avalai' === $gateway ) {
			$api_key = $this->get_api_key( 'avalai' );
			if ( '' !== $api_key ) {
				return $this->fetch_remote_models_cached( $gateway, $modality, 'https://api.avalai.ir/v1/models', $api_key, $curated );
			}
			return $curated;
		}

		if ( 'groq' === $gateway && 'embeddings' !== $modality ) {
			$api_key = $this->get_api_key( 'groq' );
			if ( '' !== $api_key ) {
				return $this->fetch_remote_models_cached( $gateway, $modality, 'https://api.groq.com/openai/v1/models', $api_key, $curated );
			}
			return $curated;
		}

		if ( 'custom' === $gateway ) {
			$base = $this->get_custom_base_url();
			if ( '' !== $base ) {
				return $this->fetch_remote_models_cached( $gateway, $modality, $base . '/models', $this->get_api_key( 'custom' ), $curated );
			}
			return $curated;
		}

		return $curated;
	}

	private function fetch_remote_models_cached( $gateway, $modality, $url, $api_key, $fallback ) {
		$transient_key = 'tsbifw_models_' . md5( $modality . '_' . $gateway . '_' . $url . '_' . ( '' !== $api_key ? 'auth' : 'anon' ) );
		$cached        = get_transient( $transient_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$args = array( 'timeout' => 15 );
		if ( '' !== $api_key ) {
			$args['headers'] = array( 'Authorization' => 'Bearer ' . $api_key );
		}

		$response = wp_remote_get( $url, $args );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return $fallback;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! isset( $data['data'] ) || ! is_array( $data['data'] ) || empty( $data['data'] ) ) {
			return $fallback;
		}

		set_transient( $transient_key, $data['data'], HOUR_IN_SECONDS );
		return $data['data'];
	}

	private function get_curated_models( $gateway, $modality ) {
		$key = ( 'embeddings' === $modality ) ? 'embeddings' : 'chat';

		if ( in_array( $gateway, array( 'gapgpt', 'arvancloud', 'metisai', 'heroai', 'talkbot', 'cupai', 'hooshang', 'hormouz', 'custom' ), true ) ) {
			$gateway = 'openai';
		}

		$catalog = array(
			'openai'     => array(
				'embeddings' => array(
					array( 'id' => 'text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 Small' ),
					array( 'id' => 'text-embedding-3-large', 'name' => 'OpenAI: Text Embedding 3 Large' ),
				),
				'chat'       => array(
					array( 'id' => 'gpt-4o-mini', 'name' => 'OpenAI: GPT-4o Mini (Fast & Cost-Efficient)' ),
					array( 'id' => 'gpt-4o', 'name' => 'OpenAI: GPT-4o (High Accuracy Vision)' ),
					array( 'id' => 'gpt-4.1-mini', 'name' => 'OpenAI: GPT-4.1 Mini' ),
				),
			),
			'liara'      => array(
				'embeddings' => array(
					array( 'id' => 'openai/text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 Small' ),
					array( 'id' => 'openai/text-embedding-3-large', 'name' => 'OpenAI: Text Embedding 3 Large' ),
				),
				'chat'       => array(
					array( 'id' => 'openai/gpt-4o-mini', 'name' => 'OpenAI: GPT-4o Mini' ),
					array( 'id' => 'openai/gpt-4o', 'name' => 'OpenAI: GPT-4o' ),
					array( 'id' => 'google/gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash' ),
				),
			),
			'gemini'     => array(
				'embeddings' => array(
					array( 'id' => 'gemini-embedding-2', 'name' => 'Google: Gemini Embedding 2 (Native Multimodal)' ),
				),
				'chat'       => array(
					array( 'id' => 'gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash (Recommended)' ),
					array( 'id' => 'gemini-2.5-pro', 'name' => 'Google: Gemini 2.5 Pro (High Accuracy)' ),
					array( 'id' => 'gemini-1.5-flash', 'name' => 'Google: Gemini 1.5 Flash' ),
				),
			),
			'avalai'     => array(
				'embeddings' => array(
					array( 'id' => 'gemini-embedding-2', 'name' => 'Google: Gemini Embedding 2 (Native Multimodal)' ),
				),
				'chat'       => array(
					array( 'id' => 'gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash (Recommended)' ),
					array( 'id' => 'gpt-4o-mini', 'name' => 'OpenAI: GPT-4o Mini' ),
					array( 'id' => 'gpt-4o', 'name' => 'OpenAI: GPT-4o' ),
					array( 'id' => 'claude-sonnet-4-6', 'name' => 'Anthropic: Claude Sonnet 4.6' ),
				),
			),
			'jina'       => array(
				'embeddings' => array(
					array( 'id' => 'jina-clip-v2', 'name' => 'Jina AI: CLIP v2 (Multimodal Image & Text)' ),
					array( 'id' => 'jina-embeddings-v5-omni-small', 'name' => 'Jina AI: Embeddings v5 Omni Small' ),
					array( 'id' => 'jina-embeddings-v4', 'name' => 'Jina AI: Embeddings v4 (Multimodal)' ),
				),
				'chat'       => array(),
			),
			'voyage'     => array(
				'embeddings' => array(
					array( 'id' => 'voyage-multimodal-3.5', 'name' => 'Voyage AI: Multimodal 3.5 (Recommended)' ),
					array( 'id' => 'voyage-multimodal-3', 'name' => 'Voyage AI: Multimodal 3' ),
				),
				'chat'       => array(),
			),
			'cohere'     => array(
				'embeddings' => array(
					array( 'id' => 'embed-v4.0', 'name' => 'Cohere: Embed v4.0 (Multimodal)' ),
				),
				'chat'       => array(),
			),
			'groq'       => array(
				'embeddings' => array(),
				'chat'       => array(
					array( 'id' => 'meta-llama/llama-4-scout-17b-16e-instruct', 'name' => 'Meta: Llama 4 Scout 17B Vision (Recommended)' ),
					array( 'id' => 'meta-llama/llama-4-maverick-17b-128e-instruct', 'name' => 'Meta: Llama 4 Maverick 17B Vision' ),
				),
			),
			'nvidia'     => array(
				'embeddings' => array(
					array( 'id' => 'nvidia/llama-nemotron-embed-vl-1b-v2', 'name' => 'NVIDIA: Nemotron Embed VL 1B v2 (Native Multimodal)' ),
					array( 'id' => 'nvidia/nvclip', 'name' => 'NVIDIA: NVCLIP (Multimodal Image & Text)' ),
				),
				'chat'       => array(
					array( 'id' => 'meta/llama-3.2-11b-vision-instruct', 'name' => 'Meta: Llama 3.2 11B Vision Instruct' ),
					array( 'id' => 'meta/llama-3.2-90b-vision-instruct', 'name' => 'Meta: Llama 3.2 90B Vision Instruct' ),
				),
			),
			'mistral'    => array(
				'embeddings' => array(
					array( 'id' => 'mistral-embed', 'name' => 'Mistral: Embed' ),
				),
				'chat'       => array(
					array( 'id' => 'pixtral-12b-2409', 'name' => 'Mistral: Pixtral 12B (Vision)' ),
					array( 'id' => 'mistral-large-latest', 'name' => 'Mistral: Large Latest (Vision)' ),
				),
			),
			'together'   => array(
				'embeddings' => array(
					array( 'id' => 'BAAI/bge-base-en-v1.5', 'name' => 'BAAI: BGE Base En v1.5' ),
					array( 'id' => 'togethercomputer/m2-bert-80M-8k-retrieval', 'name' => 'Together: M2-BERT 80M Retrieval' ),
				),
				'chat'       => array(
					array( 'id' => 'meta-llama/Llama-3.2-11B-Vision-Instruct-Turbo', 'name' => 'Meta: Llama 3.2 11B Vision Turbo' ),
					array( 'id' => 'Qwen/Qwen2-VL-72B-Instruct', 'name' => 'Qwen: Qwen2-VL 72B Instruct' ),
				),
			),
			'fireworks'  => array(
				'embeddings' => array(
					array( 'id' => 'nomic-ai/nomic-embed-text-v1.5', 'name' => 'Nomic: Embed Text v1.5' ),
				),
				'chat'       => array(
					array( 'id' => 'accounts/fireworks/models/llama-v3p2-11b-vision-instruct', 'name' => 'Fireworks: Llama 3.2 11B Vision' ),
					array( 'id' => 'accounts/fireworks/models/qwen2p5-vl-32b-instruct', 'name' => 'Fireworks: Qwen2.5-VL 32B Instruct' ),
				),
			),
			'twelvelabs' => array(
				'embeddings' => array(
					array( 'id' => 'marengo3.5', 'name' => 'TwelveLabs: Marengo 3.5 (Native Multimodal)' ),
					array( 'id' => 'Marengo-retrieval-2.7', 'name' => 'TwelveLabs: Marengo Retrieval 2.7' ),
				),
				'chat'       => array(),
			),
			'xai'        => array(
				'embeddings' => array(),
				'chat'       => array(
					array( 'id' => 'grok-2-vision-1212', 'name' => 'xAI: Grok 2 Vision' ),
					array( 'id' => 'grok-4.7', 'name' => 'xAI: Grok 4.7 Multimodal' ),
				),
			),
			'dashscope'  => array(
				'embeddings' => array(),
				'chat'       => array(
					array( 'id' => 'qwen-vl-max', 'name' => 'Qwen: VL Max (Recommended)' ),
					array( 'id' => 'qwen-vl-plus', 'name' => 'Qwen: VL Plus' ),
					array( 'id' => 'qwen2.5-vl-72b-instruct', 'name' => 'Qwen: 2.5 VL 72B Instruct' ),
				),
			),
			'cloudflare' => array(
				'embeddings' => array(),
				'chat'       => array(
					array( 'id' => '@cf/meta/llama-3.2-11b-vision-instruct', 'name' => 'Cloudflare: Llama 3.2 11B Vision Instruct' ),
					array( 'id' => '@cf/llava-hf/llava-1.5-7b-hf', 'name' => 'Cloudflare: LLaVA 1.5 7B' ),
				),
			),
		);

		if ( isset( $catalog[ $gateway ][ $key ] ) ) {
			return $catalog[ $gateway ][ $key ];
		}

		if ( 'embeddings' === $key ) {
			return array(
				array( 'id' => 'google/gemini-embedding-2', 'name' => 'Google: Gemini Embedding 2 (Native Multimodal)' ),
			);
		}

		return array(
			array( 'id' => 'google/gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash' ),
			array( 'id' => 'google/gemini-2.5-pro', 'name' => 'Google: Gemini 2.5 Pro' ),
			array( 'id' => 'openai/gpt-4o-mini', 'name' => 'OpenAI: GPT-4o Mini' ),
			array( 'id' => 'openai/gpt-4o', 'name' => 'OpenAI: GPT-4o' ),
		);
	}

	/**
	 * Filter models for embeddings.
	 *
	 * @param array  $models  List of model arrays.
	 * @param string $gateway Optional gateway slug.
	 * @return array Filtered list.
	 */
	public function filter_embedding_models( $models, $gateway = '' ) {
		if ( ! is_array( $models ) ) {
			return array();
		}

		$filtered = array_values(
			array_filter(
				$models,
				function( $model ) use ( $gateway ) {
					if ( ! isset( $model['id'] ) ) {
						return false;
					}
					if ( 'avalai' === $gateway ) {
						$id = strtolower( $model['id'] );
						return strpos( $id, 'gemini-embedding-2' ) !== false;
					}
					return true;
				}
			)
		);

		if ( empty( $filtered ) && 'avalai' === $gateway ) {
			return $this->get_curated_models( 'avalai', 'embeddings' );
		}

		return $filtered;
	}

	/**
	 * Filter models for vision/chat description generation.
	 *
	 * @param array  $models  List of model arrays.
	 * @param string $gateway Optional gateway slug.
	 * @return array Filtered list.
	 */
	public function filter_vision_models( $models, $gateway = '' ) {
		if ( ! is_array( $models ) ) {
			return array();
		}

		$filtered = array();
		foreach ( $models as $model ) {
			if ( ! isset( $model['id'] ) ) {
				continue;
			}
			$id       = strtolower( $model['id'] );
			$modality = isset( $model['architecture']['modality'] ) ? strtolower( $model['architecture']['modality'] ) : '';

			if ( 'custom' === $gateway ) {
				$filtered[] = $model;
				continue;
			}

			if ( strpos( $modality, 'image' ) !== false || strpos( $id, 'vision' ) !== false || strpos( $id, 'gpt-4o' ) !== false || strpos( $id, 'gpt-4.1' ) !== false || strpos( $id, 'gpt-5' ) !== false || strpos( $id, 'gemini' ) !== false || strpos( $id, 'claude' ) !== false || strpos( $id, 'llama-4' ) !== false ) {
				if ( strpos( $id, 'embedding' ) === false ) {
					$filtered[] = $model;
				}
			}
		}

		return ! empty( $filtered ) ? $filtered : $this->get_curated_models( $gateway, '' );
	}
}
