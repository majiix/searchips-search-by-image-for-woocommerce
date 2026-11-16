<?php
/**
 * Lightweight standalone unit tests for TSBIFW_API multi-provider gateway support.
 *
 * Run via: php tests/run-tests.php
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

$GLOBALS['tsbifw_test_options']    = array();
$GLOBALS['tsbifw_test_transients'] = array();
$GLOBALS['tsbifw_test_http_queue'] = array();
$GLOBALS['tsbifw_test_http_calls'] = array();

class WP_Error {
	public $code;
	public $message;
	public $data;

	public function __construct( $code = '', $message = '', $data = '' ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}
	public function get_error_code() {
		return $this->code;
	}
	public function get_error_message() {
		return $this->message;
	}
	public function add_data( $data ) {
		$this->data = $data;
	}
}

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function apply_filters( $tag, $value ) {
	return $value;
}
function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['tsbifw_test_options'] ) ? $GLOBALS['tsbifw_test_options'][ $key ] : $default;
}
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['tsbifw_test_transients'] ) ? $GLOBALS['tsbifw_test_transients'][ $key ] : false;
}
function set_transient( $key, $val, $exp = 0 ) {
	$GLOBALS['tsbifw_test_transients'][ $key ] = $val;
	return true;
}
function esc_html__( $text, $domain = '' ) {
	return $text;
}
function __( $text, $domain = '' ) {
	return $text;
}
function get_home_url() {
	return 'https://example.test';
}
function wp_json_encode( $data ) {
	return json_encode( $data );
}
function wp_strip_all_tags( $text ) {
	return strip_tags( (string) $text );
}
function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}
function esc_url_raw( $url ) {
	return filter_var( trim( (string) $url ), FILTER_SANITIZE_URL ) ?: '';
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}
function wp_remote_post( $url, $args = array() ) {
	$GLOBALS['tsbifw_test_http_calls'][] = array(
		'method' => 'POST',
		'url'    => $url,
		'args'   => $args,
	);
	if ( empty( $GLOBALS['tsbifw_test_http_queue'] ) ) {
		return new WP_Error( 'http_request_failed', 'No mock response queued.' );
	}
	return array_shift( $GLOBALS['tsbifw_test_http_queue'] );
}
function wp_remote_get( $url, $args = array() ) {
	$GLOBALS['tsbifw_test_http_calls'][] = array(
		'method' => 'GET',
		'url'    => $url,
		'args'   => $args,
	);
	if ( empty( $GLOBALS['tsbifw_test_http_queue'] ) ) {
		return new WP_Error( 'http_request_failed', 'No mock response queued.' );
	}
	return array_shift( $GLOBALS['tsbifw_test_http_queue'] );
}
function wp_remote_retrieve_response_code( $res ) {
	return isset( $res['response']['code'] ) ? (int) $res['response']['code'] : 0;
}
function wp_remote_retrieve_body( $res ) {
	return isset( $res['body'] ) ? (string) $res['body'] : '';
}

class TSBIFW_Logger {
	public static function log( $message, $context = array(), $force = false ) {}
}

require_once dirname( __DIR__ ) . '/includes/class-tsbifw-api.php';
require_once dirname( __DIR__ ) . '/includes/class-tsbifw-admin.php';

$failures = 0;
$total    = 0;

function tsbifw_assert_true( $cond, $label ) {
	global $failures, $total;
	$total++;
	if ( ! $cond ) {
		$failures++;
		fwrite( STDERR, "[FAIL] {$label}\n" );
	} else {
		echo "[PASS] {$label}\n";
	}
}

function tsbifw_assert_same( $expected, $actual, $label ) {
	global $failures, $total;
	$total++;
	if ( $expected !== $actual ) {
		$failures++;
		fwrite( STDERR, "[FAIL] {$label}: expected " . var_export( $expected, true ) . ', got ' . var_export( $actual, true ) . "\n" );
	} else {
		echo "[PASS] {$label}\n";
	}
}

function tsbifw_reset_state( $options = array() ) {
	$GLOBALS['tsbifw_test_options']    = $options;
	$GLOBALS['tsbifw_test_transients'] = array();
	$GLOBALS['tsbifw_test_http_queue'] = array();
	$GLOBALS['tsbifw_test_http_calls'] = array();
}

$api = TSBIFW_API::instance();

// 1. Registry contains all 26 gateways.
$configs        = $api->get_gateway_configs();
$expected_slugs = array( 'openrouter', 'openai', 'gemini', 'jina', 'voyage', 'cohere', 'groq', 'avalai', 'gapgpt', 'nvidia', 'mistral', 'together', 'fireworks', 'twelvelabs', 'xai', 'dashscope', 'cloudflare', 'arvancloud', 'metisai', 'liara', 'heroai', 'talkbot', 'cupai', 'hooshang', 'hormouz', 'custom' );
tsbifw_assert_same( $expected_slugs, array_keys( $configs ), 'Gateway registry exposes all 26 gateways' );

// 2. Custom base URL validation (typical, edge, failure).
tsbifw_assert_same( 'https://api.metisai.ir/v1', TSBIFW_API::sanitize_custom_base_url( 'https://api.metisai.ir/v1' ), 'Custom URL accepts valid HTTPS endpoint' );
tsbifw_assert_same( 'https://api.metisai.ir/v1', TSBIFW_API::sanitize_custom_base_url( 'https://api.metisai.ir/v1/chat/completions/' ), 'Custom URL strips trailing /chat/completions/' );
tsbifw_assert_same( 'http://localhost:11434/v1', TSBIFW_API::sanitize_custom_base_url( 'http://localhost:11434/v1/' ), 'Custom URL allows HTTP on localhost for Ollama' );
tsbifw_assert_same( '', TSBIFW_API::sanitize_custom_base_url( 'http://example.com/v1' ), 'Custom URL rejects insecure remote HTTP' );
tsbifw_assert_same( '', TSBIFW_API::sanitize_custom_base_url( 'http://169.254.169.254/latest' ), 'Custom URL rejects cloud metadata IP' );
tsbifw_assert_same( '', TSBIFW_API::sanitize_custom_base_url( 'https://user:pass@example.com/v1' ), 'Custom URL rejects embedded credentials' );

// 3. Protocol payload & response extraction checks.
$sample_b64 = 'data:image/jpeg;base64,AAAA';

$gem_payload = $api->build_embeddings_payload( 'gemini_native', 'gemini-embedding-2', $sample_b64, 'query' );
tsbifw_assert_same( 'AAAA', $gem_payload['content']['parts'][0]['inlineData']['data'], 'Gemini native payload strips base64 data URI prefix' );
tsbifw_assert_same( 'RETRIEVAL_QUERY', $gem_payload['taskType'], 'Gemini native payload uses RETRIEVAL_QUERY for search' );
tsbifw_assert_same( array( 0.1, 0.2 ), $api->extract_embedding_vector( 'gemini_native', array( 'embedding' => array( 'values' => array( 0.1, 0.2 ) ) ) ), 'Gemini native extractor reads embedding.values' );

$jina_payload = $api->build_embeddings_payload( 'jina', 'jina-clip-v2', $sample_b64, 'document' );
tsbifw_assert_same( $sample_b64, $jina_payload['input'][0]['image'], 'Jina payload sends image data URL' );

$voyage_payload = $api->build_embeddings_payload( 'voyage', 'voyage-multimodal-3.5', $sample_b64, 'query' );
tsbifw_assert_same( 'query', $voyage_payload['input_type'], 'Voyage payload sets input_type=query for search' );
tsbifw_assert_same( 'image_base64', $voyage_payload['inputs'][0]['content'][0]['type'], 'Voyage payload uses image_base64 block' );

$cohere_payload = $api->build_embeddings_payload( 'cohere', 'embed-v4.0', $sample_b64, 'document' );
tsbifw_assert_same( 'search_document', $cohere_payload['input_type'], 'Cohere payload sets search_document for indexing' );
tsbifw_assert_same( array( 0.5, 0.6 ), $api->extract_embedding_vector( 'cohere', array( 'embeddings' => array( 'float' => array( array( 0.5, 0.6 ) ) ) ) ), 'Cohere extractor reads embeddings.float[0]' );
tsbifw_assert_same( null, $api->extract_embedding_vector( 'cohere', array( 'invalid' => true ) ), 'Extractor returns null on malformed JSON' );

// 4. AvalAI native Gemini embeddings flow.
tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'    => 'avalai',
		'tsbifw_api_key_avalai' => 'av-test-key',
		'tsbifw_embeddings_model' => 'google/gemini-embedding-2',
	)
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'embedding' => array( 'values' => array( 0.25, 0.75 ) ) ) ),
);
$av_vec = $api->get_embeddings( $sample_b64, 'document' );
tsbifw_assert_same( array( 0.25, 0.75 ), $av_vec, 'AvalAI get_embeddings returns vector from :embedContent' );
tsbifw_assert_same( 'https://api.avalai.ir/v1beta/models/gemini-embedding-2:embedContent', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'AvalAI strips google/ prefix and calls v1beta :embedContent' );

// 5. OpenAI Direct 2-Step Vision-to-Vector flow.
tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'      => 'openai',
		'tsbifw_api_key_openai'   => 'sk-test',
		'tsbifw_embeddings_model' => 'google/gemini-embedding-2',
		'tsbifw_vision_model'     => 'openai/gpt-4o-mini',
	)
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'red leather running sneaker' ) ) ) ) ),
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'data' => array( array( 'embedding' => array( 0.9, 0.1 ) ) ) ) ),
);
$oa_vec = $api->get_embeddings( $sample_b64, 'query' );
tsbifw_assert_same( array( 0.9, 0.1 ), $oa_vec, 'OpenAI Direct 2-step Vision-to-Vector returns embedding vector' );
tsbifw_assert_same( 'https://api.openai.com/v1/chat/completions', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'OpenAI Step 1 calls /v1/chat/completions' );
tsbifw_assert_same( 'https://api.openai.com/v1/embeddings', $GLOBALS['tsbifw_test_http_calls'][1]['url'], 'OpenAI Step 2 calls /v1/embeddings' );

// 6. Strategy compatibility failure cases.
tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'  => 'groq',
		'tsbifw_api_key_groq' => 'gsk-test',
	)
);
$groq_emb = $api->get_embeddings( $sample_b64, 'query' );
tsbifw_assert_true( is_wp_error( $groq_emb ) && 'tsbifw_unsupported_strategy' === $groq_emb->get_error_code(), 'Groq returns WP_Error when Strategy 1 embeddings requested' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'  => 'jina',
		'tsbifw_api_key_jina' => 'jina-test',
	)
);
$jina_vis = $api->get_vision_description( $sample_b64 );
tsbifw_assert_true( is_wp_error( $jina_vis ) && 'tsbifw_unsupported_strategy' === $jina_vis->get_error_code(), 'Jina AI returns WP_Error when Strategy 2 vision chat requested' );

// 7. Missing key & HTTP error failure cases.
tsbifw_reset_state( array( 'tsbifw_api_gateway' => 'voyage' ) );
$missing_key = $api->get_embeddings( $sample_b64 );
tsbifw_assert_true( is_wp_error( $missing_key ) && 'tsbifw_missing_api_key' === $missing_key->get_error_code(), 'Missing API key returns tsbifw_missing_api_key WP_Error' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'    => 'voyage',
		'tsbifw_api_key_voyage' => 'pa-bad',
	)
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 401 ),
	'body'     => json_encode( array( 'detail' => 'Invalid API key' ) ),
);
$http_err = $api->get_embeddings( $sample_b64 );
tsbifw_assert_true( is_wp_error( $http_err ) && strpos( $http_err->get_error_message(), 'Invalid API key' ) !== false, 'HTTP 401 extracts detail error message' );

// 8. Custom localhost Ollama works without explicit API key.
tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'     => 'custom',
		'tsbifw_custom_base_url' => 'http://localhost:11434/v1',
	)
);
tsbifw_assert_same( 'ollama', $api->get_api_key( 'custom' ), 'Custom localhost endpoint supplies default ollama token when key is blank' );

// 9. Persistent Clear & Re-index evaluation rules.
$base_s1 = array(
	'gateway'                 => 'openrouter',
	'strategy'                => 'embeddings',
	'embeddings_model'        => 'google/gemini-embedding-2',
	'vision_model'            => 'google/gemini-2.5-flash',
	'custom_base_url'         => '',
	'custom_embeddings_model' => '',
	'custom_vision_model'     => '',
);
tsbifw_assert_same( '', TSBIFW_Admin::evaluate_reindex_reason( $base_s1, array_merge( $base_s1, array( 'gateway' => 'gemini' ) ), 0 ), 'Reindex warning skipped when 0 products indexed' );
tsbifw_assert_same( '', TSBIFW_Admin::evaluate_reindex_reason( $base_s1, array_merge( $base_s1, array( 'strategy' => 'vision', 'gateway' => 'groq' ) ), 12 ), 'Strategy change skips manual reindex warning (handled by cron)' );

$base_s2 = array_merge( $base_s1, array( 'strategy' => 'vision', 'gateway' => 'openai', 'vision_model' => 'gpt-4o-mini' ) );
tsbifw_assert_same( '', TSBIFW_Admin::evaluate_reindex_reason( $base_s2, array_merge( $base_s2, array( 'gateway' => 'groq', 'vision_model' => 'llama-3.2' ) ), 20 ), 'Strategy 2 -> Strategy 2 changes never trigger reindex warning' );
tsbifw_assert_same( 'gateway', TSBIFW_Admin::evaluate_reindex_reason( $base_s1, array_merge( $base_s1, array( 'gateway' => 'jina' ) ), 15 ), 'Strategy 1 gateway change triggers gateway warning' );
tsbifw_assert_same( 'embeddings_model', TSBIFW_Admin::evaluate_reindex_reason( $base_s1, array_merge( $base_s1, array( 'embeddings_model' => 'openai/clip' ) ), 15 ), 'Strategy 1 embeddings model change triggers embeddings_model warning' );

$custom_s1 = array_merge( $base_s1, array( 'gateway' => 'custom', 'custom_base_url' => 'https://api.liara.ir/v1', 'custom_embeddings_model' => 'text-embedding-3-small', 'custom_vision_model' => 'gpt-4o-mini' ) );
tsbifw_assert_same( 'custom_base_url', TSBIFW_Admin::evaluate_reindex_reason( $custom_s1, array_merge( $custom_s1, array( 'custom_base_url' => 'https://api.metisai.ir/openai/v1' ) ), 10 ), 'Strategy 1 custom base URL change triggers custom_base_url warning' );
tsbifw_assert_same( 'embeddings_model', TSBIFW_Admin::evaluate_reindex_reason( $custom_s1, array_merge( $custom_s1, array( 'custom_embeddings_model' => 'text-embedding-3-large' ) ), 10 ), 'Strategy 1 custom embeddings override change triggers embeddings_model warning' );
tsbifw_assert_same( 'vision_model', TSBIFW_Admin::evaluate_reindex_reason( array_merge( $base_s1, array( 'gateway' => 'openai' ) ), array_merge( $base_s1, array( 'gateway' => 'openai', 'vision_model' => 'gpt-4o' ) ), 10 ), 'Strategy 1 vision model change triggers warning on 2-step gateway' );
tsbifw_assert_same( '', TSBIFW_Admin::evaluate_reindex_reason( array_merge( $base_s1, array( 'gateway' => 'gemini' ) ), array_merge( $base_s1, array( 'gateway' => 'gemini', 'vision_model' => 'gemini-2.5-pro' ) ), 10 ), 'Strategy 1 vision model change does not warn on native multimodal gateway' );

// 10. Gateway AJAX connection probe checks.
tsbifw_reset_state();
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'data' => array( 'label' => 'ok' ) ) ),
);
$or_conn = $api->test_connection( 'openrouter', 'sk-or-live-test' );
tsbifw_assert_true( is_array( $or_conn ) && 'openrouter' === $or_conn['gateway'], 'OpenRouter test_connection succeeds on 200' );
tsbifw_assert_same( 'https://openrouter.ai/api/v1/auth/key', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'OpenRouter test_connection probes /auth/key' );

tsbifw_reset_state();
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'data' => array( array( 'embedding' => array( 0.1 ) ) ) ) ),
);
$jina_conn = $api->test_connection( 'jina', 'jina_test_key' );
tsbifw_assert_true( is_array( $jina_conn ) && 'jina' === $jina_conn['gateway'], 'Jina test_connection succeeds via POST ping' );
tsbifw_assert_same( 'POST', $GLOBALS['tsbifw_test_http_calls'][0]['method'], 'Jina test_connection uses POST' );

tsbifw_reset_state();
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'data' => array() ) ),
);
$ollama_conn = $api->test_connection( 'custom', '', 'http://localhost:11434/v1' );
tsbifw_assert_true( is_array( $ollama_conn ) && 'custom' === $ollama_conn['gateway'], 'Custom localhost Ollama test_connection succeeds without explicit API key' );

$missing_conn = $api->test_connection( 'openai', '' );
tsbifw_assert_true( is_wp_error( $missing_conn ) && 'tsbifw_missing_api_key' === $missing_conn->get_error_code(), 'test_connection returns WP_Error when API key is blank' );

$bad_url_conn = $api->test_connection( 'custom', 'sk-test', 'http://169.254.169.254/latest' );
tsbifw_assert_true( is_wp_error( $bad_url_conn ) && 'tsbifw_missing_base_url' === $bad_url_conn->get_error_code(), 'test_connection rejects unsafe custom Base URL' );

tsbifw_reset_state();
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 401 ),
	'body'     => json_encode( array( 'error' => array( 'message' => 'Incorrect API key provided' ) ) ),
);
$unauth_conn = $api->test_connection( 'openai', 'sk-invalid' );
tsbifw_assert_true( is_wp_error( $unauth_conn ) && strpos( $unauth_conn->get_error_message(), 'Incorrect API key provided' ) !== false, 'test_connection extracts HTTP 401 error message' );

// 11. Expanded international & regional gateway protocols.
$nv_payload = $api->build_embeddings_payload( 'nvidia_nim', 'nvidia/llama-nemotron-embed-vl-1b-v2', $sample_b64, 'query' );
tsbifw_assert_same( 'query', $nv_payload['input_type'], 'NVIDIA Nemotron VL sets input_type=query on search' );
tsbifw_assert_same( array( '<img src="' . $sample_b64 . '" />' ), $nv_payload['input'], 'NVIDIA NIM wraps base64 image in img tag' );

$tl_payload = $api->build_embeddings_payload( 'twelvelabs', 'marengo3.5', $sample_b64, 'document' );
tsbifw_assert_same( 'AAAA', $tl_payload['multi_input']['media_sources'][0]['base_64_string'], 'TwelveLabs strips data URI prefix in base_64_string' );
tsbifw_assert_same( array( 0.5, 0.6 ), $api->extract_embedding_vector( 'twelvelabs', array( 'image_embedding' => array( 'segments' => array( array( 'embedding' => array( 0.5, 0.6 ) ) ) ) ) ), 'TwelveLabs extractor reads image_embedding.segments[0].embedding' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'        => 'arvancloud',
		'tsbifw_api_key_arvancloud' => 'apikey arv-secret-123',
		'tsbifw_vision_model'       => 'gpt-4o-mini',
	)
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'choices' => array( array( 'message' => array( 'content' => 'black leather jacket' ) ) ) ) ),
);
$arv_desc = $api->get_vision_description( $sample_b64 );
tsbifw_assert_same( 'black leather jacket', $arv_desc, 'ArvanCloud get_vision_description succeeds' );
tsbifw_assert_same( 'https://api.arvancloudai.ir/v1/chat/completions', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'ArvanCloud calls api.arvancloudai.ir/v1/chat/completions' );
tsbifw_assert_same( 'apikey arv-secret-123', $GLOBALS['tsbifw_test_http_calls'][0]['args']['headers']['Authorization'], 'ArvanCloud uses apikey Authorization prefix without duplicating' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'        => 'cloudflare',
		'tsbifw_api_key_cloudflare' => 'cf-token',
	)
);
$cf_missing_acct = $api->get_vision_description( $sample_b64 );
tsbifw_assert_true( is_wp_error( $cf_missing_acct ) && 'tsbifw_missing_account_id' === $cf_missing_acct->get_error_code(), 'Cloudflare returns tsbifw_missing_account_id when Account ID missing' );

tsbifw_assert_same( 'cfacct123', TSBIFW_API::sanitize_cloudflare_account_id( ' cfacct123/../ ' ), 'Cloudflare Account ID sanitizer strips invalid path characters' );
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'success' => true ) ),
);
$cf_conn = $api->test_connection( 'cloudflare', 'cf-token', null, 'cfacct123' );
tsbifw_assert_true( is_array( $cf_conn ) && 'cloudflare' === $cf_conn['gateway'], 'Cloudflare test_connection succeeds with Account ID' );
tsbifw_assert_same( 'https://api.cloudflare.com/client/v4/accounts/cfacct123/ai/models/search?per_page=1', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'Cloudflare test_connection hits account models/search' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway' => 'xai',
		'tsbifw_api_key_xai' => 'xai-key',
	)
);
$xai_emb = $api->get_embeddings( $sample_b64 );
tsbifw_assert_true( is_wp_error( $xai_emb ) && 'tsbifw_unsupported_strategy' === $xai_emb->get_error_code(), 'xAI rejects Strategy 1 embeddings cleanly' );

tsbifw_reset_state(
	array(
		'tsbifw_api_gateway'      => 'hormouz',
		'tsbifw_api_key_hormouz'  => 'hmz-key',
	)
);
$GLOBALS['tsbifw_test_http_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => json_encode( array( 'data' => array() ) ),
);
$hmz_conn = $api->test_connection( 'hormouz', 'hmz-key' );
tsbifw_assert_true( is_array( $hmz_conn ) && 'hormouz' === $hmz_conn['gateway'], 'Hormouz test_connection succeeds on 200' );
tsbifw_assert_same( 'https://api.hormouz.net/v1/models', $GLOBALS['tsbifw_test_http_calls'][0]['url'], 'Hormouz test_connection hits api.hormouz.net/v1/models' );

echo "\nTotal: {$total}, Failures: {$failures}\n";
exit( $failures > 0 ? 1 : 0 );
