<?php
/**
 * Plugin Name: Searchips Search By Image for WooCommerce
 * Description: Enable customers to search WooCommerce products using images powered by machine learning.
 * Version:     1.5.0
 * Author:      micromax
 * Text Domain: searchips-search-by-image-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 11.1
 * Requires Plugins: woocommerce
 * License:     GPL-2.0+
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'TSBIFW_VERSION', '1.5.0' );
define( 'TSBIFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSBIFW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Declare compatibility with WooCommerce features (HPOS and Cart/Checkout Blocks).
 */
function tsbifw_declare_woocommerce_feature_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'tsbifw_declare_woocommerce_feature_compatibility' );

/**
 * Initialize the plugin.
 */
function tsbifw_init() {
	// Check if WooCommerce is active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'tsbifw_woocommerce_missing_notice' );
		return;
	}

	// Include required files.
	require_once TSBIFW_PLUGIN_DIR . 'includes/class-tsbifw-logger.php';
	require_once TSBIFW_PLUGIN_DIR . 'includes/class-tsbifw-api.php';
	require_once TSBIFW_PLUGIN_DIR . 'includes/class-tsbifw-indexer.php';
	require_once TSBIFW_PLUGIN_DIR . 'includes/class-tsbifw-admin.php';
	require_once TSBIFW_PLUGIN_DIR . 'includes/class-tsbifw-search.php';

	// Instantiate core modules.
	TSBIFW_API::instance();
	TSBIFW_Indexer::instance();
	if ( is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
		TSBIFW_Admin::instance();
	}
	TSBIFW_Search::instance();

	// Public action listener for indexer execution.
	add_action( 'tsbifw_index_product', 'tsbifw_index_product', 10, 2 );

	/**
	 * Fires after Searchips core plugin has completely loaded.
	 *
	 * Add-on plugins can hook here to initialize extensions and declare dependencies.
	 */
	do_action( 'tsbifw_loaded' );
}
add_action( 'plugins_loaded', 'tsbifw_init' );

/**
 * Public procedural helper to index a product image for visual search.
 *
 * @param int  $product_id Product ID.
 * @param bool $force      Whether to force re-indexing.
 * @return bool|WP_Error
 */
function tsbifw_index_product( $product_id, $force = false ) {
	if ( class_exists( 'TSBIFW_Indexer' ) ) {
		return TSBIFW_Indexer::instance()->index_product( (int) $product_id, (bool) $force );
	}
	return false;
}

/**
 * Public procedural helper to retrieve product indexing statistics.
 *
 * @return array
 */
function tsbifw_get_indexing_stats() {
	if ( class_exists( 'TSBIFW_Admin' ) ) {
		return TSBIFW_Admin::instance()->get_indexing_stats();
	}
	return array(
		'total'      => 0,
		'indexed'    => 0,
		'skipped'    => 0,
		'errors'     => 0,
		'processed'  => 0,
		'percentage' => 0,
	);
}

/**
 * Public procedural helper to retrieve the active AI provider gateway.
 *
 * @return string Gateway key identifier (e.g. 'openrouter', 'openai', 'gemini').
 */
function tsbifw_get_active_gateway() {
	if ( class_exists( 'TSBIFW_API' ) ) {
		return TSBIFW_API::instance()->get_active_gateway();
	}
	return 'openrouter';
}

/**
 * Public procedural helper to retrieve the configured API key for a gateway.
 *
 * @param string|null $gateway Optional gateway key identifier. Defaults to active gateway.
 * @return string
 */
function tsbifw_get_api_key( $gateway = null ) {
	if ( class_exists( 'TSBIFW_API' ) ) {
		return TSBIFW_API::instance()->get_api_key( $gateway );
	}
	return '';
}

/**
 * Public procedural helper to resize and base64-encode an attachment image for AI transmission.
 *
 * @param int $attachment_id Media attachment ID.
 * @return string|WP_Error Base64 data URL string or WP_Error on failure.
 */
function tsbifw_prepare_image( $attachment_id ) {
	if ( class_exists( 'TSBIFW_API' ) ) {
		return TSBIFW_API::instance()->prepare_image( (int) $attachment_id );
	}
	return new WP_Error( 'tsbifw_missing_core_api', esc_html__( 'Core API module is not loaded.', 'searchips-search-by-image-for-woocommerce' ) );
}


/**
 * Clean up scheduled cron hooks upon plugin deactivation.
 */
function tsbifw_deactivate() {
	wp_clear_scheduled_hook( 'tsbifw_cron_indexing' );
	wp_clear_scheduled_hook( 'tsbifw_clear_index_cron' );
}
register_deactivation_hook( __FILE__, 'tsbifw_deactivate' );

/**
 * Display notice if WooCommerce is not active.
 */
function tsbifw_woocommerce_missing_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'Searchips Search By Image for WooCommerce requires WooCommerce to be installed and active.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
	</div>
	<?php
}
