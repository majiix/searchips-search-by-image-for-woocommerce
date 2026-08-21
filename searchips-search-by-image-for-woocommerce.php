<?php
/**
 * Plugin Name: Searchips Search By Image for WooCommerce
 * Description: Enable customers to search WooCommerce products using images powered by machine learning.
 * Version:     1.2.0
 * Author:      micromax
 * Text Domain: searchips-search-by-image-for-woocommerce
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 11.0
 * Requires Plugins: woocommerce
 * License:     GPL-2.0+
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// Define plugin constants.
define( 'TSBIFW_VERSION', '1.2.0' );
define( 'TSBIFW_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TSBIFW_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TSBIFW_FILE', __FILE__ );

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
	TSBIFW_Admin::instance();
	TSBIFW_Search::instance();
}
add_action( 'plugins_loaded', 'tsbifw_init' );

/**
 * Display notice if WooCommerce is not active.
 */
function tsbifw_woocommerce_missing_notice() {
	?>
	<div class="error">
		<p><?php esc_html_e( 'Searchips Search By Image for WooCommerce requires WooCommerce to be installed and active.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
	</div>
	<?php
}
