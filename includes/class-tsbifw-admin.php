<?php
/**
 * Admin settings page and batch indexer.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class TSBIFW_Admin {

	/**
	 * Singleton instance.
	 *
	 * @var TSBIFW_Admin|null
	 */
	private static $instance = null;

	/**
	 * Get class instance.
	 *
	 * @return TSBIFW_Admin
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
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_tsbifw_enable_cron_indexing', array( $this, 'check_cron_scheduling' ) );
		add_action( 'update_option_tsbifw_cron_interval', array( $this, 'check_cron_scheduling' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_tsbifw_batch_index', array( $this, 'ajax_batch_index' ) );
		add_action( 'wp_ajax_tsbifw_clear_index', array( $this, 'ajax_clear_index' ) );
		add_action( 'wp_ajax_tsbifw_fetch_openrouter_models', array( $this, 'ajax_fetch_openrouter_models' ) );
		add_action( 'wp_ajax_tsbifw_clear_logs', array( $this, 'ajax_clear_logs' ) );


		// Log option updates.
		add_action( 'updated_option', array( $this, 'log_option_update' ), 10, 3 );
		add_action( 'added_option', array( $this, 'log_option_added' ), 10, 2 );

		// Media Library column and filter hooks.
		add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		add_action( 'restrict_manage_posts', array( $this, 'add_media_filter_dropdown' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_media_by_indexed_status' ) );

		// Hook when search strategy changes.
		add_action( 'update_option_tsbifw_strategy', array( $this, 'on_strategy_change' ), 10, 2 );
	}

	/**
	 * Enqueue admin styles and scripts.
	 *
	 * @param string $hook Admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'woocommerce_page_tsbifw-settings' !== $hook ) {
			return;
		}

		wp_enqueue_script( 'tsbifw-cropperjs', TSBIFW_PLUGIN_URL . 'assets/js/cropper.min.js', array(), '2.1.1', true );
		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_style( 'tsbifw-admin-css', TSBIFW_PLUGIN_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), TSBIFW_VERSION . '.2' );
		wp_enqueue_script( 'tsbifw-admin-js', TSBIFW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tsbifw-cropperjs', 'wp-color-picker' ), TSBIFW_VERSION . '.2', true );

		$max_mb = (int) get_option( 'tsbifw_max_upload_size', 2 );
		if ( $max_mb <= 0 ) {
			$max_mb = 2;
		}

		wp_localize_script(
			'tsbifw-admin-js',
			'tsbifw_admin_params',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'tsbifw_admin_nonce' ),
				'wp_rest_nonce'   => wp_create_nonce( 'wp_rest' ),
				'confirm'         => esc_html__( 'Are you sure you want to clear all indexed vectors and descriptions? This cannot be undone.', 'searchips-search-by-image-for-woocommerce' ),
				'search_endpoint' => esc_url_raw( rest_url( 'tsbifw/v1/search' ) ),
				'track_endpoint'  => esc_url_raw( rest_url( 'tsbifw/v1/track-click' ) ),
				'max_upload_size' => $max_mb * 1024 * 1024,
				'is_pro'          => self::is_pro_active(),
				'scanning_effect' => array_key_exists( get_option( 'tsbifw_scanning_effect', 'laser' ), apply_filters( 'tsbifw_scanning_effects', array( 'laser' => 'Laser' ) ) ) ? get_option( 'tsbifw_scanning_effect', 'laser' ) : 'laser',
				'scanning_color'  => get_option( 'tsbifw_scanning_color', '#6366f1' ),
				'strings'         => array(
					'scanning'             => esc_html__( 'Searching...', 'searchips-search-by-image-for-woocommerce' ),
					'no_results'           => esc_html__( 'No matching products found.', 'searchips-search-by-image-for-woocommerce' ),
					'error'                => esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
					'view_product'         => esc_html__( 'View Product', 'searchips-search-by-image-for-woocommerce' ),
					'add_to_cart'          => esc_html__( 'Add to Cart', 'searchips-search-by-image-for-woocommerce' ),
					'similarity_label'     => esc_html__( 'Match:', 'searchips-search-by-image-for-woocommerce' ),
					'take_photo'           => esc_html__( 'Take Photo', 'searchips-search-by-image-for-woocommerce' ),
					'view_in_store'        => esc_html__( 'View in Store Archive', 'searchips-search-by-image-for-woocommerce' ),
					'hidden_from_catalog'  => esc_html__( 'Hidden from Catalog', 'searchips-search-by-image-for-woocommerce' ),
					'test_ctr_tracked'     => esc_html__( 'Click tracked in Analytics', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'drag_drop_text'       => sprintf( esc_html__( 'Drag and drop an image here or click to browse (Max size: %dMB)', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
					'strategy_warning'     => esc_html__( 'Attention: You have changed the Search Strategy. You should Clear / Reset the index and perform a complete re-indexing for matches to work correctly.', 'searchips-search-by-image-for-woocommerce' ),
					'search_btn_text'      => esc_html__( 'Start Search', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'file_too_large'       => sprintf( esc_html__( 'Selected file is too large. Maximum allowed size is %dMB.', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
				),
			)
		);
	}

	/**
	 * Add submenu page under WooCommerce menu.
	 */
	public function add_settings_page() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Search by Image', 'searchips-search-by-image-for-woocommerce' ),
			esc_html__( 'Search by Image', 'searchips-search-by-image-for-woocommerce' ),
			'manage_options',
			'tsbifw-settings',
			array( $this, 'render_settings_page' )
		);
	}

	/**
	 * Register settings fields.
	 */
	public function register_settings() {
		register_setting( 'tsbifw_settings_group', 'tsbifw_api_key', array(
			'sanitize_callback' => array( $this, 'sanitize_api_key' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_strategy', array(
			'sanitize_callback' => array( $this, 'sanitize_strategy' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_embeddings_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_vision_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_sync_to_tags', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_similarity_threshold', array(
			'sanitize_callback' => 'sanitize_text_field',
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_exclude_below_percent', array(
			'sanitize_callback' => array( $this, 'sanitize_exclude_below_percent' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_search_cache_expiry', array(
			'sanitize_callback' => array( $this, 'sanitize_search_cache_expiry' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_auto_inject', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no_default_yes' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_camera_left', array(
			'sanitize_callback' => array( $this, 'sanitize_css_position' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_camera_right', array(
			'sanitize_callback' => array( $this, 'sanitize_css_position' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_camera_bg_color', array(
			'sanitize_callback' => array( $this, 'sanitize_css_color' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_camera_icon_size', array(
			'sanitize_callback' => array( $this, 'sanitize_css_size' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_scanning_effect', array(
			'sanitize_callback' => array( $this, 'sanitize_scanning_effect' ),
		) );
		register_setting( 'tsbifw_styling_group', 'tsbifw_scanning_color', array(
			'sanitize_callback' => array( $this, 'sanitize_scanning_color' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_index_featured', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_index_gallery', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_index_variations', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_logs_group', 'tsbifw_enable_logging', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_logs_group', 'tsbifw_log_retention', array(
			'sanitize_callback' => array( $this, 'sanitize_log_retention' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_cron_indexing', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_cron_interval', array(
			'sanitize_callback' => array( $this, 'sanitize_cron_interval' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_cron_batch_size', array(
			'sanitize_callback' => array( $this, 'sanitize_cron_batch_size' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_delete_data_on_uninstall', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_max_upload_size', array(
			'sanitize_callback' => array( $this, 'sanitize_max_upload_size' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_api_gateway', array(
			'sanitize_callback' => array( $this, 'sanitize_api_gateway' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_api_key_openai', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_api_key' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_api_key_gemini', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_api_key' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_auto_index_on_save', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_skip_unchanged_images_hash', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_excluded_categories', array(
			'sanitize_callback' => array( $this, 'sanitize_excluded_categories' ),
			'autoload'          => false,
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_mobile_camera', array(
			'sanitize_callback' => array( $this, 'sanitize_enable_mobile_camera' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_similarity_boost', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_boost_featured', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_boost_on_sale', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_boost_percent', array(
			'sanitize_callback' => array( $this, 'sanitize_boost_percent' ),
		) );
		register_setting( 'tsbifw_analytics_group', 'tsbifw_enable_analytics', array(
			'sanitize_callback' => array( $this, 'sanitize_pro_yes_no' ),
		) );
		register_setting( 'tsbifw_analytics_group', 'tsbifw_analytics_retention', array(
			'sanitize_callback' => array( $this, 'sanitize_analytics_retention' ),
			'autoload'          => false,
		) );
	}

	/**
	 * Check if the Pro addon is installed and active with matching version.
	 *
	 * @return bool True if active and compatible.
	 */
	public static function is_pro_active() {
		static $cached_pro = null;
		if ( null !== $cached_pro ) {
			return $cached_pro;
		}

		if ( ! defined( 'TSBIFW_VERSION' ) || ! defined( 'TSBIFW_PRO_VERSION' ) ) {
			$cached_pro = false;
			return false;
		}

		if ( TSBIFW_PRO_VERSION !== TSBIFW_VERSION ) {
			$cached_pro = false;
			return false;
		}

		if ( ! class_exists( 'TSBIFW_Pro_Addon' ) ) {
			$cached_pro = false;
			return false;
		}

		$pro_basename = defined( 'TSBIFW_PRO_FILE' )
			? plugin_basename( TSBIFW_PRO_FILE )
			: 'searchips-search-by-image-for-woocommerce-pro-addon/searchips-search-by-image-for-woocommerce-pro-addon.php';

		$pro_file = defined( 'TSBIFW_PRO_FILE' )
			? TSBIFW_PRO_FILE
			: ( defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR . '/' . $pro_basename : '' );

		if ( empty( $pro_file ) || ! file_exists( $pro_file ) ) {
			$cached_pro = false;
			return false;
		}

		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$is_active_plugin = false;
		if ( function_exists( 'is_plugin_active' ) ) {
			$is_active_plugin = is_plugin_active( $pro_basename );
		} else {
			if ( ! function_exists( 'get_option' ) ) {
				$cached_pro = false;
				return false;
			}
			$active_plugins = (array) get_option( 'active_plugins', array() );
			$is_active_plugin = in_array( $pro_basename, $active_plugins, true );
			if ( ! $is_active_plugin && function_exists( 'is_multisite' ) && is_multisite() && function_exists( 'get_site_option' ) ) {
				$network_plugins  = (array) get_site_option( 'active_sitewide_plugins', array() );
				$is_active_plugin = isset( $network_plugins[ $pro_basename ] );
			}
		}

		if ( ! $is_active_plugin ) {
			$cached_pro = false;
			return false;
		}

		// Filter may only be used to temporarily disable Pro, never spoof it when not installed/active.
		$cached_pro = (bool) apply_filters( 'tsbifw_is_pro_active', true );
		return $cached_pro;
	}

	/**
	 * Render settings HTML dashboard.
	 */
	public function render_settings_page() {
		$this->check_cron_scheduling();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab     = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$api_key        = TSBIFW_API::instance()->get_api_key();
		$openrouter_key = get_option( 'tsbifw_api_key', '' );

		$exclude_below_percent = get_option( 'tsbifw_exclude_below_percent', '' );
		if ( '' === $exclude_below_percent ) {
			$similarity_threshold = get_option( 'tsbifw_similarity_threshold', '0.40' );
			$exclude_below_percent = round( (float) $similarity_threshold * 100 );
		}

		$is_pro          = self::is_pro_active();
		$scanning_effect = $is_pro ? get_option( 'tsbifw_scanning_effect', 'laser' ) : 'laser';
		$scanning_color  = get_option( 'tsbifw_scanning_color', '#6366f1' );
		?>
		<div class="wrap tsbifw-admin-wrap">
			<h1><?php esc_html_e( 'WooCommerce Search by Image Settings', 'searchips-search-by-image-for-woocommerce' ); ?></h1>
			<?php settings_errors(); ?>

			<h2 class="nav-tab-wrapper">
				<a href="?page=tsbifw-settings&tab=general" class="nav-tab <?php echo 'general' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'General Settings', 'searchips-search-by-image-for-woocommerce' ); ?>
				</a>
				<a href="?page=tsbifw-settings&tab=styling" class="nav-tab <?php echo 'styling' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Styling', 'searchips-search-by-image-for-woocommerce' ); ?>
				</a>
				<a href="?page=tsbifw-settings&tab=indexer" class="nav-tab <?php echo 'indexer' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Product Indexer', 'searchips-search-by-image-for-woocommerce' ); ?>
				</a>
				<a href="?page=tsbifw-settings&tab=test_search" class="nav-tab <?php echo 'test_search' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Test Search', 'searchips-search-by-image-for-woocommerce' ); ?>
				</a>
				<a href="?page=tsbifw-settings&tab=logs" class="nav-tab <?php echo 'logs' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'System Logs', 'searchips-search-by-image-for-woocommerce' ); ?>
				</a>
				<a href="?page=tsbifw-settings&tab=analytics" class="nav-tab <?php echo 'analytics' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Analytics', 'searchips-search-by-image-for-woocommerce' ); ?>
					<?php if ( ! $is_pro ) : ?>
						<span class="tsbifw-badge tsbifw-badge-pro" style="background:#4f46e5; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; margin-left:4px; vertical-align:middle;"><?php esc_html_e( 'Preview', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					<?php endif; ?>
				</a>
				<?php if ( ! $is_pro ) : ?>
					<a href="?page=tsbifw-settings&tab=pro" class="nav-tab <?php echo 'pro' === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php esc_html_e( 'Pro', 'searchips-search-by-image-for-woocommerce' ); ?>
						<span class="tsbifw-badge tsbifw-badge-pro" style="background:#4f46e5; color:#fff; font-size:10px; font-weight:700; padding:2px 6px; border-radius:4px; margin-left:4px; vertical-align:middle;"><?php esc_html_e( 'Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					</a>
				<?php endif; ?>
			</h2>

			<div class="tsbifw-admin-columns">
				<div class="tsbifw-main-column">
					<div class="tsbifw-tab-content">
						<?php if ( 'general' === $active_tab ) : ?>
							<form method="post" action="options.php">
								<?php
								settings_fields( 'tsbifw_settings_group' );
								do_settings_sections( 'tsbifw_settings_group' );

								$is_pro              = self::is_pro_active();
								$default_gateways    = array(
									'openrouter' => esc_html__( 'OpenRouter', 'searchips-search-by-image-for-woocommerce' ),
								);
								$gateways            = apply_filters( 'tsbifw_api_gateways', $default_gateways );
								$active_gateway      = TSBIFW_API::instance()->get_active_gateway();
								if ( ! isset( $gateways[ $active_gateway ] ) ) {
									$active_gateway = 'openrouter';
								}
								$openai_key                 = get_option( 'tsbifw_api_key_openai', '' );
								$gemini_key                 = get_option( 'tsbifw_api_key_gemini', '' );
								$auto_index_on_save         = get_option( 'tsbifw_auto_index_on_save', 'no' );
								$skip_unchanged_images_hash = get_option( 'tsbifw_skip_unchanged_images_hash', 'no' );
								$default_strategies         = array(
									'embeddings' => esc_html__( 'Strategy 1: Multimodal Vector Embeddings (Recommended)', 'searchips-search-by-image-for-woocommerce' ),
								);
								$strategies                 = apply_filters( 'tsbifw_search_strategies', $default_strategies );
								$strategy                   = get_option( 'tsbifw_strategy', 'embeddings' );
								if ( ! isset( $strategies[ $strategy ] ) ) {
									$strategy = 'embeddings';
								}
								$embeddings_model     = get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' );
								$vision_model         = get_option( 'tsbifw_vision_model', 'google/gemini-2.5-flash' );
								$sync_to_tags         = get_option( 'tsbifw_sync_to_tags', 'no' );
								$enable_auto_inject   = get_option( 'tsbifw_enable_auto_inject', 'yes' );
								$index_featured       = get_option( 'tsbifw_index_featured', 'yes' );
								$index_gallery        = get_option( 'tsbifw_index_gallery', 'no' );
								$index_variations     = get_option( 'tsbifw_index_variations', 'no' );
								$excluded_categories  = (array) get_option( 'tsbifw_excluded_categories', array() );
								$all_categories       = get_terms(
									array(
										'taxonomy'   => 'product_cat',
										'hide_empty' => false,
										'orderby'    => 'name',
										'order'      => 'ASC',
									)
								);
								if ( is_wp_error( $all_categories ) ) {
									$all_categories = array();
								}
								?>
								<table class="form-table">
									<tr>
										<th scope="row"><label for="tsbifw_api_gateway"><?php esc_html_e( 'AI Provider Gateway', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_api_gateway" id="tsbifw_api_gateway">
												<?php foreach ( $gateways as $g_key => $g_label ) : ?>
													<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( $active_gateway, $g_key ); ?>>
														<?php echo esc_html( $g_label ); ?>
													</option>
												<?php endforeach; ?>
											</select>
											<p class="description"><?php esc_html_e( 'Select the AI provider to handle image embeddings and descriptions.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
											<?php
											$this->render_pro_tip(
												__( 'Searchips Pro adds direct OpenAI and Google Gemini API gateways, allowing you to connect directly with your own private API keys without third-party platform fees.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-gateways'
											);
											?>
										</td>
									</tr>

									<tr class="tsbifw-gateway-field gateway-openrouter" style="<?php echo 'openrouter' === $active_gateway ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_api_key"><?php esc_html_e( 'OpenRouter API Key', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
												<input type="password" name="tsbifw_api_key" id="tsbifw_api_key" value="<?php echo esc_attr( $openrouter_key ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
												<button type="button" class="button-link tsbifw-toggle-pw" data-target="tsbifw_api_key" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description"><?php esc_html_e( 'Enter your OpenRouter API key to communicate with embeddings and vision models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<?php if ( isset( $gateways['openai'] ) ) : ?>
									<tr class="tsbifw-gateway-field gateway-openai" style="<?php echo 'openai' === $active_gateway ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_api_key_openai"><?php esc_html_e( 'OpenAI API Key', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
												<input type="password" name="tsbifw_api_key_openai" id="tsbifw_api_key_openai" value="<?php echo esc_attr( $openai_key ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
												<button type="button" class="button-link tsbifw-toggle-pw" data-target="tsbifw_api_key_openai" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description"><?php esc_html_e( 'Direct OpenAI API key for text-embedding-3 and GPT-4o vision models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>
									<?php endif; ?>

									<?php if ( isset( $gateways['gemini'] ) ) : ?>
									<tr class="tsbifw-gateway-field gateway-gemini" style="<?php echo 'gemini' === $active_gateway ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_api_key_gemini"><?php esc_html_e( 'Google Gemini API Key', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
												<input type="password" name="tsbifw_api_key_gemini" id="tsbifw_api_key_gemini" value="<?php echo esc_attr( $gemini_key ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
												<button type="button" class="button-link tsbifw-toggle-pw" data-target="tsbifw_api_key_gemini" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description"><?php esc_html_e( 'Direct Google AI Studio API key for Gemini multimodal embeddings and vision models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>
									<?php endif; ?>

									<tr>
										<th scope="row"><label for="tsbifw_strategy"><?php esc_html_e( 'Search Strategy', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_strategy" id="tsbifw_strategy">
												<?php foreach ( $strategies as $s_key => $s_label ) : ?>
													<option value="<?php echo esc_attr( $s_key ); ?>" <?php selected( $strategy, $s_key ); ?>><?php echo esc_html( $s_label ); ?></option>
												<?php endforeach; ?>
											</select>
											<div id="tsbifw-openai-strategy-notice" style="display:none; margin-top:8px; padding:8px 12px; background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; border-radius:4px; font-size:12px; color:#92400e; max-width:550px;">
												<span class="dashicons dashicons-info" style="vertical-align:text-bottom; margin-right:4px;"></span>
												<?php esc_html_e( 'Note: OpenAI direct API does not provide a multimodal image embedding endpoint. For OpenAI Direct, Strategy 2 (Vision-to-Text Description Search via GPT-4o) is recommended for visual image search.', 'searchips-search-by-image-for-woocommerce' ); ?>
												<button type="button" class="button button-small" id="tsbifw-switch-to-vision" style="margin-left:8px; font-size:11px;"><?php esc_html_e( 'Switch to Strategy 2', 'searchips-search-by-image-for-woocommerce' ); ?></button>
											</div>
											<p class="description">
												<?php esc_html_e( 'Select the underlying strategy for product indexing and searching.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
											<?php
											$this->render_pro_tip(
												__( 'Searchips Pro unlocks Strategy 2 (Vision-to-Text Description Search). It generates descriptive keywords and product tags from photos to power semantic store searches.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-gateways'
											);
											?>
										</td>
									</tr>

									<tr>
										<th scope="row"><?php esc_html_e( 'Images to Index', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<fieldset>
												<label for="tsbifw_index_featured">
													<input type="checkbox" name="tsbifw_index_featured" id="tsbifw_index_featured" value="yes" <?php checked( $index_featured, 'yes' ); ?> />
													<?php esc_html_e( 'Product Featured Image', 'searchips-search-by-image-for-woocommerce' ); ?>
												</label>
												<br />
												<label for="tsbifw_index_gallery">
													<input type="checkbox" name="tsbifw_index_gallery" id="tsbifw_index_gallery" value="yes" <?php checked( $index_gallery, 'yes' ); ?> />
													<?php esc_html_e( 'Product Gallery Images', 'searchips-search-by-image-for-woocommerce' ); ?>
												</label>
												<?php if ( $is_pro ) : ?>
												<br />
												<label for="tsbifw_index_variations">
													<input type="checkbox" name="tsbifw_index_variations" id="tsbifw_index_variations" value="yes" <?php checked( $index_variations, 'yes' ); ?> />
													<?php esc_html_e( 'Product Variation Images', 'searchips-search-by-image-for-woocommerce' ); ?>
												</label>
												<?php endif; ?>
											</fieldset>
											<p class="description">
												<?php esc_html_e( 'Select which images will be processed and indexed by the AI models.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
											<?php
											$this->render_pro_tip(
												__( 'Have variable products with different color or style images? Searchips Pro indexes all variation images and deep-links customers directly to matching variation options.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-variations'
											);
											?>
										</td>
									</tr>

									<tr>
										<th scope="row"><?php esc_html_e( 'Auto-Index on Save', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<label for="tsbifw_auto_index_on_save">
												<input type="checkbox" name="tsbifw_auto_index_on_save" id="tsbifw_auto_index_on_save" value="yes" <?php checked( $auto_index_on_save, 'yes' ); ?> />
												<?php esc_html_e( 'Automatically index new products or image updates on publish/save', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
											<p class="description"><?php esc_html_e( 'Immediately generates embeddings or descriptions when a product is saved or updated.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
											<?php
											$this->render_pro_tip(
												__( 'Searchips Pro adds Smart Image Hashing (MD5) to skip unchanged photos and slash API bills on product updates, plus Category Exclusion Filters to blacklist non-physical items.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-hashing'
											);
											?>
										</td>
									</tr>

									<?php if ( $is_pro ) : ?>
									<tr>
										<th scope="row"><?php esc_html_e( 'Skip Unchanged Images', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<label for="tsbifw_skip_unchanged_images_hash">
												<input type="checkbox" name="tsbifw_skip_unchanged_images_hash" id="tsbifw_skip_unchanged_images_hash" value="yes" <?php checked( $skip_unchanged_images_hash, 'yes' ); ?> />
												<?php esc_html_e( 'Skip AI re-indexing if product images have not changed (Image Hashing)', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
											<p class="description">
												<?php esc_html_e( 'Calculates an MD5 hash of product image attachments. Skips expensive remote AI API calls when saving products or running cron if image files are identical.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>

									<tr>
										<th scope="row">
											<label for="tsbifw_excluded_categories">
												<?php esc_html_e( 'Exclude Product Categories', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
										</th>
										<td>
											<select name="tsbifw_excluded_categories[]" id="tsbifw_excluded_categories" multiple="multiple" style="min-width: 320px; min-height: 120px;">
												<?php if ( empty( $all_categories ) ) : ?>
													<option value="" disabled><?php esc_html_e( 'No product categories found.', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<?php else : ?>
													<?php foreach ( $all_categories as $cat_term ) : ?>
														<option value="<?php echo esc_attr( $cat_term->term_id ); ?>" <?php echo in_array( (int) $cat_term->term_id, array_map( 'intval', $excluded_categories ), true ) ? 'selected' : ''; ?>>
															<?php echo esc_html( $cat_term->name ) . ' (' . (int) $cat_term->count . ')'; ?>
														</option>
													<?php endforeach; ?>
												<?php endif; ?>
											</select>
											<div style="margin-top: 6px;">
												<button type="button" class="button button-small" id="tsbifw-deselect-all-categories"><?php esc_html_e( 'Deselect All', 'searchips-search-by-image-for-woocommerce' ); ?></button>
											</div>
											<p class="description">
												<?php esc_html_e( 'Select product categories to exclude from visual indexing (hold Ctrl on Windows or Cmd on Mac to select or deselect multiple). Products in these categories will be skipped without consuming AI credits.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>
									<?php endif; ?>

									<tr class="tsbifw-strategy-field embeddings-field" style="<?php echo 'embeddings' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_embeddings_model"><?php esc_html_e( 'Embeddings Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-embeddings-model-skeleton"></div>
											<select name="tsbifw_embeddings_model" id="tsbifw_embeddings_model" style="display:none;" data-selected="<?php echo esc_attr( $embeddings_model ); ?>">
											</select>
											<p class="description" id="tsbifw-embeddings-model-desc"><?php esc_html_e( 'Select the embedding model ID for your chosen gateway.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field vision-field" style="<?php echo 'vision' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_vision_model"><?php esc_html_e( 'Vision Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-vision-model-skeleton"></div>
											<select name="tsbifw_vision_model" id="tsbifw_vision_model" style="display:none;" data-selected="<?php echo esc_attr( $vision_model ); ?>">
											</select>
											<p class="description" id="tsbifw-vision-model-desc"><?php esc_html_e( 'Select the vision model ID for your chosen gateway.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field vision-field" style="<?php echo 'vision' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_sync_to_tags"><?php esc_html_e( 'Sync Descriptions to Product Tags?', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_sync_to_tags" id="tsbifw_sync_to_tags">
												<option value="no" <?php selected( $sync_to_tags, 'no' ); ?>><?php esc_html_e( 'No (Store in Custom Postmeta Only)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="yes" <?php selected( $sync_to_tags, 'yes' ); ?>><?php esc_html_e( 'Yes (Append to WooCommerce Product Tags)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'If enabled, descriptors are attached to standard product tags, allowing seamless standard theme/search filtering.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_exclude_below_percent"><?php esc_html_e( 'Exclude Products Below Match Percentage', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="0" max="100" name="tsbifw_exclude_below_percent" id="tsbifw_exclude_below_percent" value="<?php echo esc_attr( $exclude_below_percent ); ?>" class="small-text" /> %
											<p class="description"><?php esc_html_e( 'Exclude products from search results if their similarity match falls below this percentage. Recommended: 35% - 50%.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
											<?php
											$this->render_pro_tip(
												__( 'Searchips Pro adds an algorithmic Similarity Score Boost (+1% to +30%) for on-sale and featured products, automatically prioritizing high-margin promotional inventory.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-boost'
											);
											?>
										</td>
									</tr>

									<?php if ( $is_pro ) : ?>
									<tr>
										<th scope="row">
											<label for="tsbifw_enable_similarity_boost">
												<?php esc_html_e( 'Similarity Score Boost', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
										</th>
										<td>
											<?php
											$enable_boost   = get_option( 'tsbifw_enable_similarity_boost', 'no' );
											$boost_featured = get_option( 'tsbifw_boost_featured', 'yes' );
											$boost_onsale   = get_option( 'tsbifw_boost_on_sale', 'yes' );
											$boost_percent  = (int) get_option( 'tsbifw_boost_percent', 10 );
											if ( $boost_percent < 1 || $boost_percent > 30 ) {
												$boost_percent = 10;
											}
											?>
											<label for="tsbifw_enable_similarity_boost">
												<input type="checkbox" name="tsbifw_enable_similarity_boost" id="tsbifw_enable_similarity_boost" value="yes" <?php checked( $enable_boost, 'yes' ); ?> />
												<?php esc_html_e( 'Enable similarity score bonus for prioritized inventory', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
											<div class="tsbifw-boost-options" style="margin-top: 10px; padding: 10px 14px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; max-width: 500px;">
												<fieldset style="margin-bottom: 8px;">
													<legend style="font-weight: 600; font-size: 13px; margin-bottom: 6px;"><?php esc_html_e( 'Boost Targets:', 'searchips-search-by-image-for-woocommerce' ); ?></legend>
													<label for="tsbifw_boost_featured" style="margin-right: 15px;">
														<input type="checkbox" name="tsbifw_boost_featured" id="tsbifw_boost_featured" value="yes" <?php checked( $boost_featured, 'yes' ); ?> />
														<?php esc_html_e( 'Featured Products', 'searchips-search-by-image-for-woocommerce' ); ?>
													</label>
													<label for="tsbifw_boost_on_sale">
														<input type="checkbox" name="tsbifw_boost_on_sale" id="tsbifw_boost_on_sale" value="yes" <?php checked( $boost_onsale, 'yes' ); ?> />
														<?php esc_html_e( 'On-Sale Products', 'searchips-search-by-image-for-woocommerce' ); ?>
													</label>
												</fieldset>
												<div style="display: flex; align-items: center; gap: 8px;">
													<label for="tsbifw_boost_percent" style="font-size: 13px; font-weight: 600;"><?php esc_html_e( 'Boost Amount:', 'searchips-search-by-image-for-woocommerce' ); ?></label>
													+<input type="number" min="1" max="30" name="tsbifw_boost_percent" id="tsbifw_boost_percent" value="<?php echo esc_attr( $boost_percent ); ?>" class="small-text" /> %
												</div>
											</div>
											<p class="description">
												<?php esc_html_e( 'Adds an algorithmic score bonus to qualifying products, lifting them above the similarity threshold and higher in search rankings. Maximum similarity score is capped at 100%.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>
									<?php endif; ?>

									<tr>
										<th scope="row"><label for="tsbifw_max_upload_size"><?php esc_html_e( 'Maximum Upload Size (MB)', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="1" max="100" name="tsbifw_max_upload_size" id="tsbifw_max_upload_size" value="<?php echo esc_attr( get_option( 'tsbifw_max_upload_size', '2' ) ); ?>" class="small-text" /> MB
											<p class="description"><?php esc_html_e( 'Set the maximum file size for uploaded images in the frontend search overlay to prevent server memory exhaustion. Default: 2MB.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_search_cache_expiry"><?php esc_html_e( 'Search Cache Expiry', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<?php $cache_expiry = get_option( 'tsbifw_search_cache_expiry', '300' ); ?>
											<select name="tsbifw_search_cache_expiry" id="tsbifw_search_cache_expiry">
												<option value="300" <?php selected( $cache_expiry, '300' ); ?>><?php esc_html_e( '5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="600" <?php selected( $cache_expiry, '600' ); ?>><?php esc_html_e( '10 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="1800" <?php selected( $cache_expiry, '1800' ); ?>><?php esc_html_e( '30 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="3600" <?php selected( $cache_expiry, '3600' ); ?>><?php esc_html_e( '1 Hour', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="7200" <?php selected( $cache_expiry, '7200' ); ?>><?php esc_html_e( '2 Hours', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="86400" <?php selected( $cache_expiry, '86400' ); ?>><?php esc_html_e( '24 Hours', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description">
												<?php esc_html_e( 'Choose how long the visual search queries and matching product IDs are stored temporarily on the server.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_enable_auto_inject"><?php esc_html_e( 'Auto-Inject Camera Icon?', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_enable_auto_inject" id="tsbifw_enable_auto_inject">
												<option value="yes" <?php selected( $enable_auto_inject, 'yes' ); ?>><?php esc_html_e( 'Yes (Inject into theme search forms automatically)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="no" <?php selected( $enable_auto_inject, 'no' ); ?>><?php esc_html_e( 'No (Render using [tsbifw_search_bar] shortcode manually)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Attempts to locate WooCommerce product search forms and insert a camera upload icon.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<?php if ( $is_pro ) : ?>
									<tr>
										<th scope="row">
											<label for="tsbifw_enable_mobile_camera">
												<?php esc_html_e( 'Mobile Camera Capture', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
										</th>
										<td>
											<?php $enable_mobile_camera = get_option( 'tsbifw_enable_mobile_camera', 'yes' ); ?>
											<select name="tsbifw_enable_mobile_camera" id="tsbifw_enable_mobile_camera">
												<option value="yes" <?php selected( $enable_mobile_camera, 'yes' ); ?>><?php esc_html_e( 'Yes (Allow instant camera photo capture on mobile)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="no" <?php selected( $enable_mobile_camera, 'no' ); ?>><?php esc_html_e( 'No (File gallery upload only)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description">
												<?php esc_html_e( 'Enables a "Take Photo" button in the visual search modal that launches the smartphone camera directly.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>
									<?php endif; ?>

									<tr>
										<th scope="row"><label for="tsbifw_enable_cron_indexing"><?php esc_html_e( 'Enable Background Cron Indexing?', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="checkbox" name="tsbifw_enable_cron_indexing" id="tsbifw_enable_cron_indexing" value="yes" <?php checked( get_option( 'tsbifw_enable_cron_indexing', 'no' ), 'yes' ); ?> />
											<p class="description"><?php esc_html_e( 'Automatically index new products and handle failed items in the background via WordPress Cron, without keeping the settings page open.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-cron-settings" style="<?php echo 'yes' === get_option( 'tsbifw_enable_cron_indexing', 'no' ) ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_cron_interval"><?php esc_html_e( 'Cron Run Interval', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_cron_interval" id="tsbifw_cron_interval">
												<?php $current_interval = get_option( 'tsbifw_cron_interval', 'tsbifw_every_5_minutes' ); ?>
												<option value="tsbifw_every_minute" <?php selected( in_array( $current_interval, array( 'tsbifw_every_minute', 'every_minute' ), true ) ); ?>><?php esc_html_e( 'Every Minute', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_5_minutes" <?php selected( in_array( $current_interval, array( 'tsbifw_every_5_minutes', 'every_5_minutes' ), true ) ); ?>><?php esc_html_e( 'Every 5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_15_minutes" <?php selected( in_array( $current_interval, array( 'tsbifw_every_15_minutes', 'every_15_minutes' ), true ) ); ?>><?php esc_html_e( 'Every 15 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="hourly" <?php selected( $current_interval, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="twice_daily" <?php selected( $current_interval, 'twice_daily' ); ?>><?php esc_html_e( 'Twice Daily', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="daily" <?php selected( $current_interval, 'daily' ); ?>><?php esc_html_e( 'Daily', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description">
												<?php esc_html_e( 'Choose how frequently the background cron task should execute product indexing runs.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>

									<tr class="tsbifw-cron-settings" style="<?php echo 'yes' === get_option( 'tsbifw_enable_cron_indexing', 'no' ) ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_cron_batch_size"><?php esc_html_e( 'Cron Batch Size', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="1" max="100" name="tsbifw_cron_batch_size" id="tsbifw_cron_batch_size" value="<?php echo esc_attr( get_option( 'tsbifw_cron_batch_size', '5' ) ); ?>" class="small-text" />
											<p class="description">
												<?php esc_html_e( 'Number of products to process in each background interval. Keep appropriate for your server resources.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
										</td>
									</tr>
								</table>

								<div class="tsbifw-danger-zone" style="margin-top: 30px; padding: 20px; border: 1px solid #fee2e2; background-color: #fef2f2; border-radius: 8px;">
									<h4 style="margin: 0 0 10px 0; color: #991b1b; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Danger Zone', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
									<label for="tsbifw_delete_data_on_uninstall" style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
										<input type="checkbox" name="tsbifw_delete_data_on_uninstall" id="tsbifw_delete_data_on_uninstall" value="yes" <?php checked( get_option( 'tsbifw_delete_data_on_uninstall', 'no' ), 'yes' ); ?> style="margin-top: 3px;" />
										<div>
											<span style="font-weight: 600; color: #991b1b; display: block; margin-bottom: 3px;"><?php esc_html_e( 'Delete all plugin data on uninstall', 'searchips-search-by-image-for-woocommerce' ); ?></span>
											<span class="description" style="color: #7f1d1d;"><?php esc_html_e( 'If checked, all product vectors, image descriptions, search logs, and settings will be permanently deleted from the database when you delete this plugin.', 'searchips-search-by-image-for-woocommerce' ); ?></span>
										</div>
									</label>
								</div>

								<?php submit_button(); ?>
							</form>
						<?php elseif ( 'styling' === $active_tab ) : ?>
							<?php
							$camera_left      = get_option( 'tsbifw_camera_left', 'auto' );
							$camera_right     = get_option( 'tsbifw_camera_right', '14px' );
							$camera_bg_color  = get_option( 'tsbifw_camera_bg_color', 'transparent' );
							$camera_icon_size = get_option( 'tsbifw_camera_icon_size', '20px' );
							?>
							<form method="post" action="options.php">
								<?php
								settings_fields( 'tsbifw_styling_group' );
								do_settings_sections( 'tsbifw_styling_group' );
								?>
								<table class="form-table">
									<tr>
										<th scope="row"><label for="tsbifw_camera_left"><?php esc_html_e( 'Camera Icon Left Position', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="text" name="tsbifw_camera_left" id="tsbifw_camera_left" value="<?php echo esc_attr( $camera_left ); ?>" class="small-text" />
											<p class="description"><?php esc_html_e( 'CSS left position offset for the camera icon (e.g. 14px or auto).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_camera_right"><?php esc_html_e( 'Camera Icon Right Position', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="text" name="tsbifw_camera_right" id="tsbifw_camera_right" value="<?php echo esc_attr( $camera_right ); ?>" class="small-text" />
											<p class="description"><?php esc_html_e( 'CSS right position offset for the camera icon (e.g. 14px or auto).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_camera_icon_size"><?php esc_html_e( 'Camera Icon Size', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="text" name="tsbifw_camera_icon_size" id="tsbifw_camera_icon_size" value="<?php echo esc_attr( $camera_icon_size ); ?>" class="small-text" />
											<p class="description"><?php esc_html_e( 'CSS size (width and height) for the camera icon (e.g. 20px, 24px, etc.).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_camera_bg_color"><?php esc_html_e( 'Camera Icon Background Color', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="text" name="tsbifw_camera_bg_color" id="tsbifw_camera_bg_color" value="<?php echo esc_attr( $camera_bg_color ); ?>" class="regular-text" />
											<p class="description"><?php esc_html_e( 'CSS background color value for the camera icon (e.g. transparent, #ffffff, or rgba(255,255,255,0.8)).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>
								</table>
								<?php
								$this->render_pro_tip(
									__( 'Searchips Pro adds native Mobile Camera Photo Capture. Mobile shoppers can tap "Take Photo" right in the search modal to snap live photos and search on the go.', 'searchips-search-by-image-for-woocommerce' ),
									'pro-feature-mobile-camera'
								);
								?>

								<div class="tsbifw-styling-effects-section" style="margin-top: 35px; border-top: 1px solid #e2e8f0; padding-top: 25px;">
									<h3 style="margin-bottom: 6px; font-size: 16px;"><?php esc_html_e( 'Search Loading & Scanning Animation', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
									<p class="description" style="margin-bottom: 22px; max-width: 800px;">
										<?php esc_html_e( 'Choose the visual scanning animation displayed to shoppers while visual AI analyzes an uploaded photo. Click any effect card below to preview the live animation.', 'searchips-search-by-image-for-woocommerce' ); ?>
									</p>

									<div class="tsbifw-effects-split-layout">
										<!-- Left Column: Effect Cards & Color Picker -->
										<div class="tsbifw-effects-selector-col">
											<div class="tsbifw-effect-cards-grid">
												<!-- 1. Laser Sweep (Free) -->
												<div class="tsbifw-effect-card <?php echo 'laser' === $scanning_effect ? 'active' : ''; ?>" data-effect="laser">
													<input type="radio" name="tsbifw_scanning_effect" value="laser" <?php checked( $scanning_effect, 'laser' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-image-filter"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'Laser Sweep', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( 'Classic glowing neon laser line sweeping continuously up and down across the preview.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>

												<?php if ( $is_pro ) : ?>
												<!-- 2. AI Vision Reticle (Pro) -->
												<div class="tsbifw-effect-card <?php echo 'reticle' === $scanning_effect ? 'active' : ''; ?>" data-effect="reticle">
													<input type="radio" name="tsbifw_scanning_effect" value="reticle" <?php checked( $scanning_effect, 'reticle' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-visibility"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'AI Vision Reticle', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( 'Futuristic computer vision corner brackets with targeting crosshairs and coordinate tracking.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>

												<!-- 3. Sonar Radar Sweep (Pro) -->
												<div class="tsbifw-effect-card <?php echo 'radar' === $scanning_effect ? 'active' : ''; ?>" data-effect="radar">
													<input type="radio" name="tsbifw_scanning_effect" value="radar" <?php checked( $scanning_effect, 'radar' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-marker"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'Sonar Radar Sweep', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( '360-degree rotating radar beam with concentric distance rings simulating spatial scanning.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>

												<!-- 4. Digital Mesh Grid (Pro) -->
												<div class="tsbifw-effect-card <?php echo 'matrix' === $scanning_effect ? 'active' : ''; ?>" data-effect="matrix">
													<input type="radio" name="tsbifw_scanning_effect" value="matrix" <?php checked( $scanning_effect, 'matrix' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-grid-view"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'Digital Mesh Grid', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( 'Luminous 3D perspective wireframe matrix with shimmering topology coordinate nodes.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>

												<!-- 5. Concentric Ripple (Pro) -->
												<div class="tsbifw-effect-card <?php echo 'ripple' === $scanning_effect ? 'active' : ''; ?>" data-effect="ripple">
													<input type="radio" name="tsbifw_scanning_effect" value="ripple" <?php checked( $scanning_effect, 'ripple' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-update"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'Concentric Ripple', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( 'Calm biometric ripple waves radiating outward smoothly from the image center.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>

												<!-- 6. Luxury Hologram Shimmer (Pro) -->
												<div class="tsbifw-effect-card <?php echo 'hologram' === $scanning_effect ? 'active' : ''; ?>" data-effect="hologram">
													<input type="radio" name="tsbifw_scanning_effect" value="hologram" <?php checked( $scanning_effect, 'hologram' ); ?> />
													<div class="tsbifw-effect-card-header">
														<span class="tsbifw-effect-icon dashicons dashicons-admin-appearance"></span>
														<strong class="tsbifw-effect-title"><?php esc_html_e( 'Luxury Hologram', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
													</div>
													<p class="tsbifw-effect-desc"><?php esc_html_e( 'Diagonal iridescent prism sheen with glassmorphism reflections for luxury brands.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
												</div>
												<?php endif; ?>
											</div>

											<div class="tsbifw-color-picker-box" style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px;">
												<label for="tsbifw_scanning_color" style="font-weight: 600; display: block; margin-bottom: 6px; font-size: 13px;">
													<?php esc_html_e( 'Scanner Glow Accent Color', 'searchips-search-by-image-for-woocommerce' ); ?>
												</label>
												<input type="text" name="tsbifw_scanning_color" id="tsbifw_scanning_color" value="<?php echo esc_attr( $scanning_color ); ?>" class="tsbifw-color-picker" data-default-color="#6366f1" />
												<p class="description" style="margin-top: 6px;">
													<?php esc_html_e( 'Custom accent color for lasers, reticles, radar sweeps, and glowing pulses (default: #6366f1).', 'searchips-search-by-image-for-woocommerce' ); ?>
												</p>
											</div>
											<?php
											$this->render_pro_tip(
												__( 'Searchips Pro unlocks 5 additional futuristic scanning animations: AI Vision Reticle, Sonar Radar Sweep, Digital Mesh Grid, Concentric Ripple, and Luxury Hologram Shimmer.', 'searchips-search-by-image-for-woocommerce' ),
												'pro-feature-effects'
											);
											?>
										</div>

										<!-- Right Column: Live Mockup Preview Stage -->
										<div class="tsbifw-effects-preview-col">
											<div class="tsbifw-mockup-wrapper">
												<div class="tsbifw-mockup-header">
													<div class="tsbifw-mockup-title"><?php esc_html_e( 'Live Scanner Preview', 'searchips-search-by-image-for-woocommerce' ); ?></div>
													<button type="button" class="button button-small" id="tsbifw-toggle-preview-anim" title="<?php esc_attr_e( 'Pause or play animation', 'searchips-search-by-image-for-woocommerce' ); ?>"><?php esc_html_e( 'Pause', 'searchips-search-by-image-for-woocommerce' ); ?></button>
												</div>

												<div class="tsbifw-mockup-stage" id="tsbifw-preview-stage" data-effect="<?php echo esc_attr( $scanning_effect ); ?>" style="--tsbifw-scan-color: <?php echo esc_attr( $scanning_color ); ?>;">
													<!-- Sample Product Image -->
													<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample Product" class="tsbifw-mockup-img" id="tsbifw-mockup-img" />

													<!-- Scanning animation layers container -->
													<div class="tsbifw-scan-container tsbifw-scan-active" id="tsbifw-preview-anim-container" data-effect="<?php echo esc_attr( $scanning_effect ); ?>">
														<!-- Laser layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-laser">
															<div class="tsbifw-scanner-bar"></div>
															<div class="tsbifw-scanning-overlay"></div>
														</div>
														<!-- Reticle layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-reticle">
															<div class="tsbifw-reticle-bracket tsbifw-reticle-tl"></div>
															<div class="tsbifw-reticle-bracket tsbifw-reticle-tr"></div>
															<div class="tsbifw-reticle-bracket tsbifw-reticle-bl"></div>
															<div class="tsbifw-reticle-bracket tsbifw-reticle-br"></div>
															<div class="tsbifw-reticle-crosshair"></div>
															<div class="tsbifw-reticle-tag">AI_TARGET: 0x94F2</div>
														</div>
														<!-- Radar layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-radar">
															<div class="tsbifw-radar-ring tsbifw-radar-ring-1"></div>
															<div class="tsbifw-radar-ring tsbifw-radar-ring-2"></div>
															<div class="tsbifw-radar-sweep"></div>
															<div class="tsbifw-radar-grid"></div>
														</div>
														<!-- Matrix layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-matrix">
															<div class="tsbifw-matrix-grid"></div>
														</div>
														<!-- Ripple layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-ripple">
															<div class="tsbifw-ripple-wave tsbifw-ripple-1"></div>
															<div class="tsbifw-ripple-wave tsbifw-ripple-2"></div>
															<div class="tsbifw-ripple-wave tsbifw-ripple-3"></div>
															<div class="tsbifw-ripple-core"></div>
														</div>
														<!-- Hologram layer -->
														<div class="tsbifw-effect-layer tsbifw-effect-hologram">
															<div class="tsbifw-hologram-prism"></div>
															<div class="tsbifw-hologram-shimmer"></div>
														</div>
													</div>

													<div class="tsbifw-mockup-status">
														<span class="tsbifw-pulse-dot"></span>
														<span id="tsbifw-preview-status-text"><?php esc_html_e( 'Scanning visual features...', 'searchips-search-by-image-for-woocommerce' ); ?></span>
													</div>
												</div>
											</div>
										</div>
									</div>
								</div>

								<?php submit_button(); ?>
							</form>
						<?php elseif ( 'indexer' === $active_tab ) : ?>
							<?php
							$stats = $this->get_indexing_stats();
							?>
							<div class="tsbifw-indexer-container">
								<h3><?php esc_html_e( 'Product Indexer Status', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'To enable image search, all WooCommerce products must have their featured images processed and indexed by the OpenRouter models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

								<div class="tsbifw-stats-grid">
									<div class="tsbifw-stat-card">
										<span class="tsbifw-stat-num" id="tsbifw-stat-total"><?php echo esc_html( $stats['total'] ); ?></span>
										<span class="tsbifw-stat-label"><?php esc_html_e( 'Total Products', 'searchips-search-by-image-for-woocommerce' ); ?></span>
									</div>
									<div class="tsbifw-stat-card">
										<span class="tsbifw-stat-num tsbifw-success-text" id="tsbifw-stat-indexed"><?php echo esc_html( $stats['indexed'] ); ?></span>
										<span class="tsbifw-stat-label"><?php esc_html_e( 'Successfully Indexed', 'searchips-search-by-image-for-woocommerce' ); ?></span>
									</div>
									<div class="tsbifw-stat-card">
										<span class="tsbifw-stat-num tsbifw-warning-text" id="tsbifw-stat-skipped"><?php echo esc_html( $stats['skipped'] ); ?></span>
										<span class="tsbifw-stat-label"><?php esc_html_e( 'Skipped (No Image)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
									</div>
									<div class="tsbifw-stat-card">
										<span class="tsbifw-stat-num tsbifw-error-text" id="tsbifw-stat-errors"><?php echo esc_html( $stats['errors'] ); ?></span>
										<span class="tsbifw-stat-label"><?php esc_html_e( 'Errors / Failed', 'searchips-search-by-image-for-woocommerce' ); ?></span>
									</div>
								</div>

								<div class="tsbifw-progress-wrapper" style="<?php echo ( $stats['processed'] > 0 ) ? '' : 'display:none;'; ?>">
									<div class="tsbifw-progress-bar">
										<div class="tsbifw-progress-fill" style="width: <?php echo esc_attr( $stats['percentage'] ); ?>%;"></div>
									</div>
									<div class="tsbifw-progress-text">
										<span id="tsbifw-progress-percent"><?php echo esc_html( $stats['percentage'] ); ?></span>% <?php esc_html_e( 'processed', 'searchips-search-by-image-for-woocommerce' ); ?>
										(<span id="tsbifw-processed-count"><?php echo esc_html( $stats['processed'] ); ?></span> / <span id="tsbifw-total-count"><?php echo esc_html( $stats['total'] ); ?></span>)
									</div>
								</div>

								<div class="tsbifw-indexer-actions">
									<button type="button" class="button button-primary button-large" id="tsbifw-start-indexing" <?php disabled( $stats['processed'] === $stats['total'] || empty( $api_key ) ); ?>>
										<?php esc_html_e( 'Start / Resume Indexing', 'searchips-search-by-image-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-secondary button-large" id="tsbifw-pause-indexing" style="display:none;">
										<?php esc_html_e( 'Pause Indexing', 'searchips-search-by-image-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-large tsbifw-stop-btn" id="tsbifw-stop-indexing" style="display:none; color:#b32d2e; border-color:#b32d2e;">
										<?php esc_html_e( 'Stop Indexing', 'searchips-search-by-image-for-woocommerce' ); ?>
									</button>
									<button type="button" class="button button-secondary button-large" id="tsbifw-reset-indexing" <?php disabled( 0 === $stats['processed'] ); ?>>
										<?php esc_html_e( 'Clear / Reset Index', 'searchips-search-by-image-for-woocommerce' ); ?>
									</button>

									<?php if ( empty( $api_key ) ) : ?>
										<p class="tsbifw-error-text inline-error"><?php esc_html_e( 'Please configure your OpenRouter API key on the General Settings tab before running the indexer.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
									<?php endif; ?>
								</div>
								<?php
								$this->render_pro_tip(
									__( 'Searchips Pro adds WooCommerce Products List bulk actions, WP-CLI mass indexing commands, and variable product variation images indexing for high-volume catalogs.', 'searchips-search-by-image-for-woocommerce' ),
									'pro-feature-variations'
								);
								?>

								<div class="tsbifw-log-output" style="display:none;">
									<div class="tsbifw-log-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:10px;">
										<h4 style="margin:0;"><?php esc_html_e( 'Indexing Output Logs', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
										<button type="button" class="button button-secondary" id="tsbifw-copy-logs"><?php esc_html_e( 'Copy Logs', 'searchips-search-by-image-for-woocommerce' ); ?></button>
									</div>
									<pre id="tsbifw-log-console"></pre>
								</div>
							</div>
						<?php elseif ( 'test_search' === $active_tab ) : ?>
							<div class="tsbifw-test-search-container">
								<h3><?php esc_html_e( 'Test Image Search similarity', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'Upload an image below to test search matching. The search will output matching WooCommerce products with their similarity scores.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
								<?php
								$this->render_pro_tip(
									__( 'Searchips Pro includes a full Visual Search Analytics dashboard to see real photos uploaded by customers, monitor Click-Through Rate (CTR), and track zero-result demand.', 'searchips-search-by-image-for-woocommerce' ),
									'pro-feature-analytics'
								);
								?>

								<div class="tsbifw-admin-search-box">
									<?php if ( empty( $api_key ) ) : ?>
										<p class="tsbifw-error-text inline-error"><?php esc_html_e( 'Please configure your OpenRouter API key on the General Settings tab before using the test search.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
									<?php else : ?>
										<div class="tsbifw-admin-search-controls" style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: space-between;">
											<div class="tsbifw-admin-camera-wrapper" style="display: flex; gap: 10px; align-items: center;">
												<button type="button" class="button button-secondary" id="tsbifw-admin-camera-btn" style="display: inline-flex; align-items: center; gap: 5px;">
													<span class="dashicons dashicons-camera" style="font-size: 17px; width: 17px; height: 17px; line-height: 17px;"></span>
													<span><?php esc_html_e( 'Take Photo', 'searchips-search-by-image-for-woocommerce' ); ?></span>
												</button>
												<span class="description" style="color: #64748b;"><?php esc_html_e( 'or drag and drop below', 'searchips-search-by-image-for-woocommerce' ); ?></span>
											</div>
											<label for="tsbifw-admin-include-hidden" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; cursor: pointer;">
												<input type="checkbox" id="tsbifw-admin-include-hidden" value="1" />
												<span><?php esc_html_e( 'Include hidden catalog products (diagnostic)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
											</label>
										</div>
										<input type="file" id="tsbifw-admin-camera-input" accept="image/*" capture="environment" style="display:none;" />

										<div class="tsbifw-drag-zone" id="tsbifw-admin-drag-zone">
											<svg class="tsbifw-drag-icon" viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
												<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
												<circle cx="8.5" cy="8.5" r="1.5"></circle>
												<polyline points="21 15 16 10 5 21"></polyline>
											</svg>
											<p><?php esc_html_e( 'Drag and drop an image here or click to browse', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</div>
										<input type="file" id="tsbifw-admin-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;" />

										<div class="tsbifw-preview-wrapper" id="tsbifw-admin-preview-wrapper" style="display:none;" data-effect="<?php echo esc_attr( $scanning_effect ); ?>">
											<div class="tsbifw-cropper-container" style="max-height: 320px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; border: 1px solid #ccd0d4; position: relative;">
												<cropper-canvas id="tsbifw-admin-cropper-canvas" style="height: 300px; display: none;">
													<cropper-image id="tsbifw-admin-preview-image" src="" rotatable scalable translatable></cropper-image>
													<cropper-shade></cropper-shade>
													<cropper-selection movable resizable initial-coverage="0.9" dynamic outlined>
														<cropper-grid role="grid" covered></cropper-grid>
														<cropper-crosshair centered></cropper-crosshair>
														<cropper-handle action="move" theme-color="rgba(255, 255, 255, 0.35)"></cropper-handle>
														<cropper-handle action="nw-resize" theme-color="#4f46e5"></cropper-handle>
														<cropper-handle action="ne-resize" theme-color="#4f46e5"></cropper-handle>
														<cropper-handle action="se-resize" theme-color="#4f46e5"></cropper-handle>
														<cropper-handle action="sw-resize" theme-color="#4f46e5"></cropper-handle>
													</cropper-selection>
												</cropper-canvas>

												<!-- Scanning animation layers container -->
												<div class="tsbifw-scan-container" data-effect="<?php echo esc_attr( $scanning_effect ); ?>" style="display:none; --tsbifw-scan-color: <?php echo esc_attr( $scanning_color ); ?>;">
													<!-- Laser layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-laser">
														<div class="tsbifw-scanner-bar"></div>
														<div class="tsbifw-scanning-overlay"></div>
													</div>
													<!-- Reticle layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-reticle">
														<div class="tsbifw-reticle-bracket tsbifw-reticle-tl"></div>
														<div class="tsbifw-reticle-bracket tsbifw-reticle-tr"></div>
														<div class="tsbifw-reticle-bracket tsbifw-reticle-bl"></div>
														<div class="tsbifw-reticle-bracket tsbifw-reticle-br"></div>
														<div class="tsbifw-reticle-crosshair"></div>
														<div class="tsbifw-reticle-tag">AI_TARGET: 0x94F2</div>
													</div>
													<!-- Radar layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-radar">
														<div class="tsbifw-radar-ring tsbifw-radar-ring-1"></div>
														<div class="tsbifw-radar-ring tsbifw-radar-ring-2"></div>
														<div class="tsbifw-radar-sweep"></div>
														<div class="tsbifw-radar-grid"></div>
													</div>
													<!-- Matrix layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-matrix">
														<div class="tsbifw-matrix-grid"></div>
													</div>
													<!-- Ripple layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-ripple">
														<div class="tsbifw-ripple-wave tsbifw-ripple-1"></div>
														<div class="tsbifw-ripple-wave tsbifw-ripple-2"></div>
														<div class="tsbifw-ripple-wave tsbifw-ripple-3"></div>
														<div class="tsbifw-ripple-core"></div>
													</div>
													<!-- Hologram layer -->
													<div class="tsbifw-effect-layer tsbifw-effect-hologram">
														<div class="tsbifw-hologram-prism"></div>
														<div class="tsbifw-hologram-shimmer"></div>
													</div>
												</div>
											</div>
											<div class="tsbifw-preview-actions" style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; align-items: center;">
												<button type="button" class="button button-primary" id="tsbifw-admin-search-btn"><?php esc_html_e( 'Start Search', 'searchips-search-by-image-for-woocommerce' ); ?></button>
												<button type="button" class="button button-secondary" id="tsbifw-admin-reselect-btn"><?php esc_html_e( 'Select Another', 'searchips-search-by-image-for-woocommerce' ); ?></button>
											</div>
										</div>

										<div class="tsbifw-search-status" id="tsbifw-admin-search-status" style="display:none;"></div>
										<div class="tsbifw-admin-results-toolbar" id="tsbifw-admin-results-toolbar" style="display:none; margin: 20px 0 12px; align-items: center; justify-content: space-between; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0;">
											<span class="tsbifw-results-count-badge" id="tsbifw-admin-results-count" style="font-weight: 600; color: #334155; font-size: 14px;"></span>
											<a href="#" target="_blank" class="button button-secondary" id="tsbifw-admin-view-in-store" style="display: inline-flex; align-items: center; gap: 5px;">
												<span class="dashicons dashicons-external" style="font-size: 16px; width: 16px; height: 16px; line-height: 16px;"></span>
												<span><?php esc_html_e( 'View in Store Archive', 'searchips-search-by-image-for-woocommerce' ); ?></span>
											</a>
										</div>
										<div class="tsbifw-results-grid" id="tsbifw-admin-results-grid" style="display:none;"></div>
									<?php endif; ?>
								</div>
							</div>
						<?php elseif ( 'logs' === $active_tab ) : ?>
							<div class="tsbifw-logs-container">
								<form method="post" action="options.php" style="margin-bottom: 25px;">
									<?php
									settings_fields( 'tsbifw_logs_group' );
									do_settings_sections( 'tsbifw_logs_group' );
									?>
									<table class="form-table">
										<tr>
											<th scope="row"><label for="tsbifw_enable_logging"><?php esc_html_e( 'Enable Logging?', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
											<td>
												<input type="checkbox" name="tsbifw_enable_logging" id="tsbifw_enable_logging" value="yes" <?php checked( get_option( 'tsbifw_enable_logging', 'yes' ), 'yes' ); ?> />
												<p class="description"><?php esc_html_e( 'If enabled, settings updates, API calls, requests, and search queries will be saved to the database.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
											</td>
										</tr>

										<tr>
											<th scope="row"><label for="tsbifw_log_retention"><?php esc_html_e( 'Log Retention Period', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
											<td>
												<?php $current_retention = get_option( 'tsbifw_log_retention', '7' ); ?>
												<select name="tsbifw_log_retention" id="tsbifw_log_retention">
													<option value="1" <?php selected( $current_retention, '1' ); ?>><?php esc_html_e( '1 Day', 'searchips-search-by-image-for-woocommerce' ); ?></option>
													<option value="3" <?php selected( $current_retention, '3' ); ?>><?php esc_html_e( '3 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
													<option value="7" <?php selected( $current_retention, '7' ); ?>><?php esc_html_e( '7 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
													<option value="14" <?php selected( $current_retention, '14' ); ?>><?php esc_html_e( '14 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
													<option value="30" <?php selected( $current_retention, '30' ); ?>><?php esc_html_e( '30 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
													<option value="0" <?php selected( $current_retention, '0' ); ?>><?php esc_html_e( 'Indefinitely (Keep all logs)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												</select>
												<p class="description">
													<?php esc_html_e( 'Choose how long logs should be kept in the database before being automatically cleared.', 'searchips-search-by-image-for-woocommerce' ); ?>
												</p>
											</td>
										</tr>
									</table>
									<?php submit_button( esc_html__( 'Save Log Settings', 'searchips-search-by-image-for-woocommerce' ) ); ?>
								</form>

								<hr style="margin: 25px 0; border: 0; border-top: 1px solid #ccd0d4;" />

								<div class="tsbifw-logs-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
									<h3 style="margin:0;"><?php esc_html_e( 'Recorded Logs', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
									<div>
										<button type="button" class="button button-secondary" id="tsbifw-admin-copy-logs"><?php esc_html_e( 'Copy Logs', 'searchips-search-by-image-for-woocommerce' ); ?></button>
										<button type="button" class="button tsbifw-stop-btn" id="tsbifw-admin-clear-logs" style="color:#b32d2e; border-color:#b32d2e;"><?php esc_html_e( 'Clear Logs', 'searchips-search-by-image-for-woocommerce' ); ?></button>
									</div>
								</div>

								<div class="tsbifw-log-list-wrapper" style="max-height: 600px; overflow-y: auto; background: #fff; border: 1px solid #ccd0d4; padding: 10px; border-radius: 4px;">
									<?php
									$logs = TSBIFW_Logger::get_logs();
									if ( empty( $logs ) ) {
										echo '<p class="description">' . esc_html__( 'No logs recorded yet.', 'searchips-search-by-image-for-woocommerce' ) . '</p>';
									} else {
										// Reverse logs to show newest first.
										$logs = array_reverse( $logs );
										?>
										<table class="wp-list-table widefat fixed striped tsbifw-logs-table">
											<thead>
												<tr>
													<th class="column-timestamp" style="width: 20%; padding: 8px; font-weight: bold;"><?php esc_html_e( 'Timestamp', 'searchips-search-by-image-for-woocommerce' ); ?></th>
													<th class="column-message" style="width: 50%; padding: 8px; font-weight: bold;"><?php esc_html_e( 'Message', 'searchips-search-by-image-for-woocommerce' ); ?></th>
													<th class="column-context" style="width: 30%; padding: 8px; font-weight: bold;"><?php esc_html_e( 'Context / Metadata', 'searchips-search-by-image-for-woocommerce' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach ( $logs as $log ) : ?>
													<tr>
														<td style="padding: 8px; vertical-align: top;"><strong><?php echo esc_html( $log['timestamp'] ); ?></strong></td>
														<td style="padding: 8px; vertical-align: top; white-space: pre-wrap;"><?php echo esc_html( $log['message'] ); ?></td>
														<td style="padding: 8px; vertical-align: top;">
															<?php if ( ! empty( $log['context'] ) ) : ?>
																<pre style="margin:0; padding:5px; background:#f6f7f7; font-size:11px; max-height:100px; overflow:auto; border: 1px solid #ddd; border-radius: 3px; font-family: monospace;"><?php echo esc_html( wp_json_encode( $log['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre>
															<?php else : ?>
																<span class="description">-</span>
															<?php endif; ?>
														</td>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
										<?php
									}
									?>
								</div>
							</div>
						<?php elseif ( 'analytics' === $active_tab ) : ?>
							<div class="tsbifw-tab-pane tsbifw-analytics-pane">
								<?php
								if ( $is_pro ) {
									do_action( 'tsbifw_render_analytics_tab' );
								} else {
									$this->render_analytics_preview_tab();
								}
								?>
							</div>
						<?php elseif ( 'pro' === $active_tab ) : ?>
							<div class="tsbifw-tab-pane tsbifw-pro-pane">
								<?php $this->render_pro_preview_tab(); ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="tsbifw-sidebar-column">
					<?php $this->render_pro_card( $active_tab ); ?>
					<?php if ( 'general' === $active_tab ) : ?>
						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'API Key Setup Guide', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<h5 style="margin: 0 0 6px 0; font-size: 13px; color: #0f172a;"><?php esc_html_e( 'OpenRouter', 'searchips-search-by-image-for-woocommerce' ); ?></h5>
								<ol style="margin: 0 0 14px 0; padding-left: 18px; list-style-type: decimal;">
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;">
										<?php
										// translators: %s is the link to the external website.
										$msg = sprintf( esc_html__( 'Visit the %s website and sign in.', 'searchips-search-by-image-for-woocommerce' ), '<a href="https://openrouter.ai/" target="_blank" rel="noopener noreferrer">OpenRouter</a>' );
										echo wp_kses_post( $msg );
										?>
									</li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Navigate to Keys and click "Create Key".', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Copy the key into the OpenRouter API Key input.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
								</ol>

								<h5 style="margin: 0 0 6px 0; font-size: 13px; color: #0f172a;"><?php esc_html_e( 'OpenAI (Direct)', 'searchips-search-by-image-for-woocommerce' ); ?></h5>
								<ol style="margin: 0 0 14px 0; padding-left: 18px; list-style-type: decimal;">
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;">
										<?php
										// translators: %s is the link to the external website.
										$msg = sprintf( esc_html__( 'Visit the %s page.', 'searchips-search-by-image-for-woocommerce' ), '<a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener noreferrer">OpenAI API Keys</a>' );
										echo wp_kses_post( $msg );
										?>
									</li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Click "Create new secret key" and label your key.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Copy the secret key into the OpenAI API Key input.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
								</ol>

								<h5 style="margin: 0 0 6px 0; font-size: 13px; color: #0f172a;"><?php esc_html_e( 'Google Gemini (Direct)', 'searchips-search-by-image-for-woocommerce' ); ?></h5>
								<ol style="margin: 0 0 4px 0; padding-left: 18px; list-style-type: decimal;">
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;">
										<?php
										// translators: %s is the link to the external website.
										$msg = sprintf( esc_html__( 'Visit %s and log in with your Google account.', 'searchips-search-by-image-for-woocommerce' ), '<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noopener noreferrer">Google AI Studio</a>' );
										echo wp_kses_post( $msg );
										?>
									</li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Click "Create API key" and select your Google Cloud project.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 4px;"><?php esc_html_e( 'Copy the API key into the Google Gemini API Key input.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
								</ol>
							</div>
						</div>

						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'Model Selection Help', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<p><strong><?php esc_html_e( 'Embeddings Model', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Used by Strategy 1 to convert product images into mathematical vectors. Gemini Embedding 2 is the recommended default.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

								<p><strong><?php esc_html_e( 'Vision Model', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Used by Strategy 2 to write textual descriptions from photos. Gemini 2.5 Flash is recommended for its speed and accuracy.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
							</div>
						</div>
					<?php endif; ?>

					<div class="tsbifw-sidebar-card">
						<div class="tsbifw-sidebar-card-header">
							<h4><?php esc_html_e( 'Shortcode Usage', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
						</div>
						<div class="tsbifw-sidebar-card-body">
							<p><?php esc_html_e( 'To display the image search bar anywhere on your store front-end (e.g. pages, posts, or widgets), insert the following shortcode:', 'searchips-search-by-image-for-woocommerce' ); ?></p>
							<code style="display: block; padding: 8px; background: #f1f5f9; border-radius: 4px; font-family: monospace; font-size: 12px; font-weight: bold; color: #0f172a; text-align: center; border: 1px solid #cbd5e1; margin-bottom: 8px;">[tsbifw_search_bar]</code>
							<p style="font-size: 11px !important; color: #64748b !important; margin: 0;"><?php esc_html_e( 'This shortcode outputs a complete, responsive product search form equipped with the camera upload icon.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
						</div>
					</div>

					<?php if ( 'general' === $active_tab ) : ?>
						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'Similarity Options Guide', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<h5><?php esc_html_e( 'Match Percentage Threshold', 'searchips-search-by-image-for-woocommerce' ); ?></h5>
								<p><?php esc_html_e( 'This controls the strictness of the similarity matching algorithm:', 'searchips-search-by-image-for-woocommerce' ); ?></p>
								<ul style="margin: 0; padding-left: 20px; list-style-type: disc;">
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;"><strong><?php esc_html_e( 'Higher (e.g., 55% - 70%)', 'searchips-search-by-image-for-woocommerce' ); ?></strong>: <?php esc_html_e( 'Very strict matches. Only shows products that look almost identical to the query image.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;"><strong><?php esc_html_e( 'Lower (e.g., 30% - 40%)', 'searchips-search-by-image-for-woocommerce' ); ?></strong>: <?php esc_html_e( 'Loose matching. Returns products with similar color schemes, shapes, or silhouettes.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
								</ul>
							</div>
						</div>

						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'Search Strategies', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<p><strong><?php esc_html_e( 'Strategy 1: Vector Embeddings', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Encodes products and uploaded search photos into numerical vectors, calculating similarity using normalized dot-product algebra. Fast, accurate, and recommended.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

								<p><strong><?php esc_html_e( 'Strategy 2: Vision-to-Text', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Generates high-quality textual descriptions of images on-the-fly and processes them as keyword tags to query standard store search pages.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
							</div>
						</div>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Compile product indexing statistics.
	 *
	 * @return array Map containing total, indexed, skipped, errors, processed counts and percentage.
	 */
	public function get_indexing_stats() {
		global $wpdb;

		$cache_key = 'tsbifw_indexing_stats';
		$stats     = get_transient( $cache_key );
		if ( false !== $stats ) {
			return $stats;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = %s",
				'product',
				'publish'
			)
		);

		// Single aggregated query grouping counts by status instead of 3 separate table scans.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$status_rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm.meta_value AS status_val, COUNT(pm.post_id) AS cnt
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_status = %s
				GROUP BY pm.meta_value",
				'_tsbifw_indexed_status',
				'publish'
			),
			OBJECT_K
		);

		$indexed = isset( $status_rows['indexed'] ) ? (int) $status_rows['indexed']->cnt : 0;
		$skipped = isset( $status_rows['skipped'] ) ? (int) $status_rows['skipped']->cnt : 0;
		$errors  = isset( $status_rows['error'] ) ? (int) $status_rows['error']->cnt : 0;

		$processed  = $indexed + $skipped + $errors;
		$percentage = ( $total > 0 ) ? round( ( $processed / $total ) * 100 ) : 0;

		$stats = array(
			'total'      => $total,
			'indexed'    => $indexed,
			'skipped'    => $skipped,
			'errors'     => $errors,
			'processed'  => $processed,
			'percentage' => $percentage,
		);

		set_transient( $cache_key, $stats, 60 );

		return $stats;
	}

	/**
	 * AJAX endpoint to batch process indexing.
	 */
	public function ajax_batch_index() {
		check_ajax_referer( 'tsbifw_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		// Invalidate indexing stats cache.
		wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );
		delete_transient( 'tsbifw_indexing_stats' );

		$stats       = $this->get_indexing_stats();
		$batch_limit = 5;

		// Find products that are not yet indexed.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_limit,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				array(
					'key'     => '_tsbifw_indexed_status',
					'compare' => 'NOT EXISTS',
				),
			),
		);

		$products = new WP_Query( $query_args );
		$product_ids = $products->posts;

		if ( empty( $product_ids ) ) {
			$stats = $this->get_indexing_stats();
			wp_send_json_success(
				array(
					'completed' => true,
					'logs'      => array( esc_html__( 'All products indexed successfully.', 'searchips-search-by-image-for-woocommerce' ) ),
					'stats'     => $stats,
				)
			);
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $product_ids, true, false );
		}

		$indexer = TSBIFW_Indexer::instance();
		$logs    = array();

		foreach ( $product_ids as $id ) {
			$title  = get_the_title( $id );
			$result = $indexer->index_product( $id, false, true );

			if ( is_wp_error( $result ) ) {
				$logs[] = sprintf(
					// translators: 1: Product title, 2: Error message
					esc_html__( 'Failed to index "%1$s": %2$s', 'searchips-search-by-image-for-woocommerce' ),
					$title,
					$result->get_error_message()
				);
			} else {
				$status = get_post_meta( $id, '_tsbifw_indexed_status', true );
				if ( 'skipped' === $status ) {
					$logs[] = sprintf(
						// translators: %s: Product title
						esc_html__( 'Skipped "%s" (No featured image found)', 'searchips-search-by-image-for-woocommerce' ),
						$title
					);
				} else {
					$logs[] = sprintf(
						// translators: %s: Product title
						esc_html__( 'Successfully indexed "%s"', 'searchips-search-by-image-for-woocommerce' ),
						$title
					);
				}
			}
		}

		$indexer->clear_cache();
		$stats = $this->get_indexing_stats();

		$is_completed = ( $stats['processed'] >= $stats['total'] );

		wp_send_json_success(
			array(
				'completed' => $is_completed,
				'logs'      => $logs,
				'stats'     => $stats,
			)
		);
	}

	/**
	 * AJAX endpoint to clear indexing data.
	 */
	public function ajax_clear_index() {
		check_ajax_referer( 'tsbifw_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		TSBIFW_Indexer::instance()->clear_all_indexed_data();

		// Invalidate indexing stats cache.
		wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );

		$stats = $this->get_indexing_stats();
		wp_send_json_success(
			array(
				'stats' => $stats,
				'msg'   => esc_html__( 'All indexed metadata has been successfully cleared.', 'searchips-search-by-image-for-woocommerce' ),
			)
		);
	}

	/**
	 * AJAX endpoint to fetch OpenRouter models.
	 */
	public function ajax_fetch_openrouter_models() {
		check_ajax_referer( 'tsbifw_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$gateway = isset( $_POST['gateway'] ) ? sanitize_text_field( wp_unslash( $_POST['gateway'] ) ) : '';
		$api = TSBIFW_API::instance();
		if ( empty( $gateway ) ) {
			$gateway = $api->get_active_gateway();
		}

		// Fetch embeddings models (using output_modalities=embeddings).
		$embeddings_data = $api->get_models( 'embeddings', $gateway );
		if ( is_wp_error( $embeddings_data ) ) {
			$embedding_models = array();
		} else {
			$embedding_models = $api->filter_embedding_models( $embeddings_data );
		}

		// Fetch text generation models and filter for vision support.
		$generation_data = $api->get_models( '', $gateway );
		if ( is_wp_error( $generation_data ) ) {
			$vision_models = array();
		} else {
			$vision_models = $api->filter_vision_models( $generation_data );
		}

		// Fallback models in case of network issues or empty responses.
		if ( empty( $embedding_models ) ) {
			$embedding_models = array(
				array( 'id' => 'google/gemini-embedding-2', 'name' => 'Google: Gemini Embedding 2' ),
				array( 'id' => 'openai/text-embedding-3-small', 'name' => 'OpenAI: Text Embedding 3 (Small)' ),
				array( 'id' => 'openai/text-embedding-3-large', 'name' => 'OpenAI: Text Embedding 3 (Large)' ),
			);
		}

		if ( empty( $vision_models ) ) {
			$vision_models = array(
				array( 'id' => 'google/gemini-2.5-flash', 'name' => 'Google: Gemini 2.5 Flash' ),
				array( 'id' => 'google/gemini-2.5-pro', 'name' => 'Google: Gemini 2.5 Pro' ),
				array( 'id' => 'openai/gpt-4o-mini', 'name' => 'OpenAI: GPT-4o Mini' ),
				array( 'id' => 'openai/gpt-4o', 'name' => 'OpenAI: GPT-4o' ),
				array( 'id' => 'anthropic/claude-3-haiku', 'name' => 'Anthropic: Claude 3 Haiku' ),
			);
		}

		// Sort models alphabetically by name.
		$sort_models_by_name = function( $a, $b ) {
			$name_a = ! empty( $a['name'] ) ? $a['name'] : $a['id'];
			$name_b = ! empty( $b['name'] ) ? $b['name'] : $b['id'];
			return strcasecmp( $name_a, $name_b );
		};
		usort( $embedding_models, $sort_models_by_name );
		usort( $vision_models, $sort_models_by_name );

		wp_send_json_success(
			array(
				'embedding_models' => $embedding_models,
				'vision_models'    => $vision_models,
			)
		);
	}

	/**
	 * Keep the hourly/custom background indexing cron aligned with the enable option.
	 */
	public function check_cron_scheduling() {
		$enable_cron = get_option( 'tsbifw_enable_cron_indexing', 'no' );
		$interval    = get_option( 'tsbifw_cron_interval', 'tsbifw_every_5_minutes' );
		$timestamp   = wp_next_scheduled( 'tsbifw_cron_indexing' );

		if ( 'yes' === $enable_cron ) {
			if ( $timestamp ) {
				$current_schedule = wp_get_schedule( 'tsbifw_cron_indexing' );
				if ( $current_schedule !== $interval ) {
					wp_unschedule_event( $timestamp, 'tsbifw_cron_indexing' );
					wp_schedule_event( time(), $interval, 'tsbifw_cron_indexing' );
					// translators: 1: Old schedule interval, 2: New schedule interval
					$msg = sprintf( esc_html__( 'Rescheduled background indexing cron event from %1$s to %2$s.', 'searchips-search-by-image-for-woocommerce' ), $current_schedule, $interval );
					TSBIFW_Logger::log( $msg );
				}
			} else {
				wp_schedule_event( time(), $interval, 'tsbifw_cron_indexing' );
				// translators: %s is the cron run interval display name.
				$msg = sprintf( esc_html__( 'Scheduled %s background indexing cron event.', 'searchips-search-by-image-for-woocommerce' ), $interval );
				TSBIFW_Logger::log( $msg );
			}
		} else {
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, 'tsbifw_cron_indexing' );
				TSBIFW_Logger::log( 'Unscheduled background indexing cron event.' );
			}
		}
	}

	/**
	 * Log settings option updates.
	 *
	 * @param string $option    Option name.
	 * @param mixed  $old_value Old option value.
	 * @param mixed  $value     New option value.
	 */
	public function log_option_update( $option, $old_value, $value ) {
		if ( strpos( $option, 'tsbifw_' ) === 0 && 'tsbifw_logs' !== $option ) {
			$logged_old = $old_value;
			$logged_new = $value;
			if ( false !== strpos( $option, 'api_key' ) ) {
				$logged_old = empty( $old_value ) ? '' : '***' . substr( (string) $old_value, -4 );
				$logged_new = empty( $value ) ? '' : '***' . substr( (string) $value, -4 );
			}
			// translators: %s is the option name key.
			$msg = sprintf( esc_html__( 'Setting updated: %s changed.', 'searchips-search-by-image-for-woocommerce' ), $option );
			TSBIFW_Logger::log(
				$msg,
				array(
					'old' => $logged_old,
					'new' => $logged_new,
				)
			);
		}
	}

	/**
	 * Log settings option additions.
	 *
	 * @param string $option Option name.
	 * @param mixed  $value  Option value.
	 */
	public function log_option_added( $option, $value ) {
		if ( strpos( $option, 'tsbifw_' ) === 0 && 'tsbifw_logs' !== $option ) {
			$logged_val = $value;
			if ( false !== strpos( $option, 'api_key' ) ) {
				$logged_val = empty( $value ) ? '' : '***' . substr( (string) $value, -4 );
			}
			// translators: %s is the option name key.
			$msg = sprintf( esc_html__( 'Setting added: %s set.', 'searchips-search-by-image-for-woocommerce' ), $option );
			TSBIFW_Logger::log(
				$msg,
				array(
					'value' => $logged_val,
				)
			);
		}
	}

	/**
	 * AJAX endpoint to clear plugin logs.
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'tsbifw_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		TSBIFW_Logger::clear_all_logs();

		wp_send_json_success(
			array(
				'msg' => esc_html__( 'All system logs have been successfully cleared.', 'searchips-search-by-image-for-woocommerce' ),
			)
		);
	}

	/**
	 * Add "Indexed Status" column to Media Library.
	 *
	 * @param array $columns Media columns.
	 * @return array
	 */
	public function add_media_columns( $columns ) {
		$columns['tsbifw_indexed'] = esc_html__( 'Indexed Status', 'searchips-search-by-image-for-woocommerce' );
		return $columns;
	}

	/**
	 * Render custom column content.
	 *
	 * @param string $column_name Column name.
	 * @param int    $post_id     Attachment ID.
	 */
	public function render_media_column( $column_name, $post_id ) {
		if ( 'tsbifw_indexed' === $column_name ) {
			if ( ! wp_attachment_is_image( $post_id ) ) {
				echo '<span style="color:#94a3b8;">-</span>';
				return;
			}

			$indexer = TSBIFW_Indexer::instance();
			$is_indexed = $indexer->is_image_indexed( $post_id );

			if ( $is_indexed ) {
				echo '<span class="tsbifw-indexed-badge indexed" style="color:#0f5132; background-color:#d1e7dd; border:1px solid #badbcc; padding:4px 8px; border-radius:12px; font-weight:600; font-size:11px; display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-yes" style="font-size:16px; width:16px; height:16px; margin:0;"></span>' . esc_html__( 'Indexed', 'searchips-search-by-image-for-woocommerce' ) . '</span>';
			} else {
				echo '<span class="tsbifw-indexed-badge not-indexed" style="color:#664d03; background-color:#fff3cd; border:1px solid #ffecb5; padding:4px 8px; border-radius:12px; font-weight:600; font-size:11px; display:inline-flex; align-items:center; gap:4px;"><span class="dashicons dashicons-no" style="font-size:16px; width:16px; height:16px; margin:0;"></span>' . esc_html__( 'Not Indexed', 'searchips-search-by-image-for-woocommerce' ) . '</span>';
			}
		}
	}

	/**
	 * Add custom filter dropdown to the Media Library list table.
	 */
	public function add_media_filter_dropdown() {
		$scr = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $scr && 'upload' === $scr->base ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$selected = isset( $_GET['tsbifw_indexed_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['tsbifw_indexed_filter'] ) ) : '';
			?>
			<select name="tsbifw_indexed_filter" id="tsbifw_indexed_filter">
				<option value=""><?php esc_html_e( 'All Indexed Statuses', 'searchips-search-by-image-for-woocommerce' ); ?></option>
				<option value="indexed" <?php selected( $selected, 'indexed' ); ?>><?php esc_html_e( 'Indexed', 'searchips-search-by-image-for-woocommerce' ); ?></option>
				<option value="not_indexed" <?php selected( $selected, 'not_indexed' ); ?>><?php esc_html_e( 'Not Indexed', 'searchips-search-by-image-for-woocommerce' ); ?></option>
			</select>
			<?php
		}
	}

	/**
	 * Filter query parameters according to selected indexed status filter.
	 *
	 * @param WP_Query $query Query object.
	 */
	public function filter_media_by_indexed_status( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() ) {
			return;
		}

		$scr = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $scr || 'upload' !== $scr->base ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['tsbifw_indexed_filter'] ) && ! empty( $_GET['tsbifw_indexed_filter'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filter      = sanitize_text_field( wp_unslash( $_GET['tsbifw_indexed_filter'] ) );
			$indexer     = TSBIFW_Indexer::instance();
			$indexed_ids = $indexer->get_indexed_image_ids();

			if ( 'indexed' === $filter ) {
				if ( ! empty( $indexed_ids ) ) {
					$query->set( 'post__in', $indexed_ids );
				} else {
					$query->set( 'post__in', array( 0 ) ); // Force zero results
				}
			} elseif ( 'not_indexed' === $filter ) {
				if ( ! empty( $indexed_ids ) ) {
					$query->set( 'post__not_in', $indexed_ids );
				}
			}
		}
	}

	/**
	 * Automatically schedule a single background cron event to clear all indexes when strategy changes.
	 *
	 * @param string $old_value Old strategy value.
	 * @param string $value     New strategy value.
	 */
	public function on_strategy_change( $old_value, $value ) {
		if ( $old_value !== $value ) {
			if ( ! wp_next_scheduled( 'tsbifw_clear_index_cron' ) ) {
				wp_schedule_single_event( time(), 'tsbifw_clear_index_cron' );
			}

			// translators: 1: Old strategy, 2: New strategy
			$msg = sprintf( esc_html__( 'Search strategy changed from %1$s to %2$s. Scheduled background cron job to clear all existing index data.', 'searchips-search-by-image-for-woocommerce' ), $old_value, $value );
			TSBIFW_Logger::log( $msg );
		}
	}

	/**
	 * Sanitize API Key.
	 *
	 * @param string $value Input value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );
		if ( empty( $value ) ) {
			add_settings_error(
				'tsbifw_api_key',
				'tsbifw_api_key_empty',
				esc_html__( 'OpenRouter API Key is required for image search and indexing to function.', 'searchips-search-by-image-for-woocommerce' ),
				'error'
			);
		}
		return $value;
	}

	/**
	 * Sanitize Exclude Below Percent.
	 *
	 * @param mixed $value Input value.
	 * @return int|string
	 */
	public function sanitize_exclude_below_percent( $value ) {
		if ( '' === $value || null === $value ) {
			return '';
		}
		$num = (int) $value;
		if ( $num < 0 || $num > 100 ) {
			add_settings_error(
				'tsbifw_exclude_below_percent',
				'tsbifw_exclude_below_percent_range',
				esc_html__( 'Match percentage threshold must be between 0 and 100.', 'searchips-search-by-image-for-woocommerce' ),
				'error'
			);
			return max( 0, min( 100, $num ) );
		}
		return $num;
	}

	/**
	 * Sanitize Search Cache Expiry.
	 *
	 * @param mixed $value Input cache expiry in seconds.
	 * @return int Sanitized cache duration in seconds.
	 */
	public function sanitize_search_cache_expiry( $value ) {
		$value   = absint( $value );
		$allowed = array( 300, 600, 1800, 3600, 7200, 86400 );
		return in_array( $value, $allowed, true ) ? $value : 300;
	}

	/**
	 * Sanitize log retention period.
	 *
	 * @param mixed $value Input days.
	 * @return int
	 */
	public function sanitize_log_retention( $value ) {
		$value = absint( $value );
		$valid = array( 0, 1, 3, 7, 14, 30 );
		return in_array( $value, $valid, true ) ? $value : 7;
	}

	/**
	 * Sanitize Cron Batch Size.
	 *
	 * @param mixed $value Input value.
	 * @return int
	 */
	public function sanitize_cron_batch_size( $value ) {
		$num = (int) $value;

		if ( $num <= 0 ) {
			add_settings_error(
				'tsbifw_cron_batch_size',
				'tsbifw_cron_batch_size_invalid',
				esc_html__( 'Cron batch size must be a positive number.', 'searchips-search-by-image-for-woocommerce' ),
				'error'
			);
			return 5; // Fallback default.
		}

		return $num;
	}

	/**
	 * Sanitize API Gateway.
	 *
	 * @param string $value Gateway key.
	 * @return string
	 */
	public function sanitize_api_gateway( $value ) {
		$allowed = apply_filters( 'tsbifw_allowed_gateways', array( 'openrouter' ) );
		$val     = sanitize_key( $value );
		return in_array( $val, $allowed, true ) ? $val : 'openrouter';
	}

	/**
	 * Renders upgrade card to purchase/activate Pro version.
	 *
	 * @param string $active_tab Current active settings tab.
	 */
	private function render_pro_card( $active_tab = 'general' ) {
		if ( self::is_pro_active() ) {
			return;
		}
		?>
		<div class="tsbifw-sidebar-card tsbifw-upgrade-card" style="border: 1px solid #c7d2fe !important; border-radius: 10px !important; overflow: hidden !important; background: #ffffff !important; box-shadow: 0 4px 16px rgba(79, 70, 229, 0.08) !important;">
			<div class="tsbifw-sidebar-card-header" style="background: linear-gradient(135deg, #4338ca 0%, #312e81 100%) !important; padding: 14px 16px !important; display: flex !important; align-items: center !important; justify-content: space-between !important;">
				<div style="display: flex; align-items: center; gap: 8px;">
					<span class="dashicons dashicons-star-filled" style="color: #facc15 !important; font-size: 18px !important; width: 18px !important; height: 18px !important; line-height: 18px !important;"></span>
					<strong style="color: #ffffff !important; font-size: 14px !important; font-weight: 700 !important; margin: 0 !important;"><?php esc_html_e( 'Upgrade to Pro', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
				</div>
				<span class="tsbifw-upgrade-badge" style="background: rgba(255, 255, 255, 0.18) !important; color: #ffffff !important; font-size: 10px !important; font-weight: 700 !important; text-transform: uppercase !important; padding: 3px 8px !important; border-radius: 12px !important; letter-spacing: 0.5px !important; border: 1px solid rgba(255, 255, 255, 0.3) !important; white-space: nowrap !important;"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
			</div>
			<div class="tsbifw-sidebar-card-body" style="padding: 18px 16px !important;">
				<p class="tsbifw-upgrade-intro" style="font-size: 13px !important; line-height: 1.5 !important; color: #334155 !important; margin: 0 0 14px 0 !important; font-weight: 500 !important;">
					<?php esc_html_e( 'Unlock advanced enterprise tools to convert shoppers and maximize visual search performance:', 'searchips-search-by-image-for-woocommerce' ); ?>
				</p>
				<ul class="tsbifw-upgrade-features" style="list-style: none !important; list-style-type: none !important; margin: 0 0 18px 0 !important; padding: 0 !important; padding-left: 0 !important; display: flex !important; flex-direction: column !important; gap: 10px !important;">
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-chart-area" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Visual Search Analytics', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Track queries, CTR & zero-result demand', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-images-alt2" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Variation Images Indexing', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Match specific color & style variants', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-database" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Smart Image Hashing (MD5)', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Skip unchanged images to slash API bills', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-cloud" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Direct OpenAI & Gemini', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Connect directly without middleware fees', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-camera" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Mobile Camera Instant Capture', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Let shoppers snap live photos on phones', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-arrow-up-alt" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( 'Similarity Score Boost', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Boost on-sale & featured products', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
					<li style="list-style: none !important; list-style-type: none !important; margin: 0 !important; padding: 0 !important; display: flex !important; align-items: flex-start !important; gap: 10px !important;">
						<span class="tsbifw-feat-icon" style="display: inline-flex !important; align-items: center !important; justify-content: center !important; width: 22px !important; height: 22px !important; border-radius: 6px !important; background: #eef2ff !important; color: #4f46e5 !important; flex-shrink: 0 !important; margin-top: 1px !important;">
							<span class="dashicons dashicons-admin-customizer" style="font-size: 14px !important; width: 14px !important; height: 14px !important; line-height: 14px !important; color: #4f46e5 !important;"></span>
						</span>
						<div style="font-size: 12px !important; line-height: 1.45 !important; color: #475569 !important;">
							<strong style="color: #0f172a !important; font-weight: 600 !important;"><?php esc_html_e( '5 Futuristic Scanner FX', 'searchips-search-by-image-for-woocommerce' ); ?>:</strong>
							<?php esc_html_e( 'Reticle, radar, mesh, ripple & hologram', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</li>
				</ul>
				<div class="tsbifw-upgrade-actions" style="display: flex !important; flex-direction: column !important; gap: 10px !important; margin-top: 16px !important; padding-top: 16px !important; border-top: 1px solid #f1f5f9 !important;">
					<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="tsbifw-btn-upgrade" style="display: flex !important; align-items: center !important; justify-content: center !important; gap: 8px !important; width: 100% !important; box-sizing: border-box !important; padding: 11px 16px !important; background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%) !important; color: #ffffff !important; border: 1px solid #4338ca !important; border-radius: 8px !important; font-size: 13.5px !important; font-weight: 700 !important; text-decoration: none !important; text-shadow: none !important; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25) !important; cursor: pointer !important;">
						<span><?php esc_html_e( 'Get Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						<span style="font-size: 16px; font-weight: 700;">&rarr;</span>
					</a>
					<?php if ( 'pro' !== $active_tab ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsbifw-settings&tab=pro' ) ); ?>" class="tsbifw-btn-preview" style="display: flex !important; align-items: center !important; justify-content: center !important; gap: 7px !important; width: 100% !important; box-sizing: border-box !important; padding: 10px 16px !important; background: #ffffff !important; color: #4338ca !important; border: 1.5px solid #c7d2fe !important; border-radius: 8px !important; font-size: 13px !important; font-weight: 600 !important; text-decoration: none !important; text-shadow: none !important; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important; cursor: pointer !important;">
							<span class="dashicons dashicons-visibility" style="font-size: 16px !important; width: 16px !important; height: 16px !important; line-height: 16px !important; color: #6366f1 !important;"></span>
							<span><?php esc_html_e( 'Explore Interactive Preview', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</a>
					<?php endif; ?>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders an informational Pro Tip callout box for features with Pro upgrades.
	 *
	 * @param string $message Text explaining the Pro capability.
	 * @param string $anchor Optional anchor ID on the Pro preview tab.
	 */
	public function render_pro_tip( $message, $anchor = '' ) {
		if ( self::is_pro_active() ) {
			return;
		}
		$pro_url = admin_url( 'admin.php?page=tsbifw-settings&tab=pro' );
		if ( ! empty( $anchor ) ) {
			$pro_url .= '#' . sanitize_key( $anchor );
		}
		?>
		<div class="tsbifw-pro-tip-box">
			<div class="tsbifw-pro-tip-badge">
				<span class="dashicons dashicons-lightbulb"></span>
				<span><?php esc_html_e( 'Pro Tip', 'searchips-search-by-image-for-woocommerce' ); ?></span>
			</div>
			<div class="tsbifw-pro-tip-content">
				<span class="tsbifw-pro-tip-text"><?php echo esc_html( $message ); ?></span>
				<a href="<?php echo esc_url( $pro_url ); ?>" class="tsbifw-pro-tip-link">
					<?php esc_html_e( 'Explore in Pro Preview', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Sanitize CSS position property (e.g. left, right).
	 *
	 * @param string $value CSS position.
	 * @return string Sanitized value.
	 */
	public function sanitize_css_position( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( in_array( strtolower( $value ), array( 'auto', 'inherit', 'initial', 'unset' ), true ) ) {
			return strtolower( $value );
		}
		if ( preg_match( '/^[+-]?[0-9]+(?:\.[0-9]+)?(?:px|%|em|rem|ex|ch|vh|vw|vmin|vmax)?$/i', $value ) ) {
			return $value;
		}
		return 'auto';
	}

	/**
	 * Sanitize CSS size property (e.g. size, width, height).
	 *
	 * @param string $value CSS size.
	 * @return string Sanitized value.
	 */
	public function sanitize_css_size( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( preg_match( '/^[0-9]+(?:\.[0-9]+)?(?:px|%|em|rem)?$/i', $value ) ) {
			return $value;
		}
		return '20px';
	}

	/**
	 * Sanitize CSS color value.
	 *
	 * @param string $value CSS color.
	 * @return string Sanitized value.
	 */
	public function sanitize_css_color( $value ) {
		$value = trim( sanitize_text_field( $value ) );
		if ( '' === $value ) {
			return 'transparent';
		}
		$hex = sanitize_hex_color( $value );
		if ( ! empty( $hex ) ) {
			return $hex;
		}
		// Strict RGB/RGBA check: numeric comma-separated values only, no CSS delimiters (; or }).
		if ( preg_match( '/^rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
			return $value;
		}
		// Strict HSL/HSLA check:
		if ( preg_match( '/^hsla?\(\s*\d{1,3}(?:deg)?\s*,\s*\d{1,3}%\s*,\s*\d{1,3}%(?:\s*,\s*(?:0|1|0?\.\d+))?\s*\)$/i', $value ) ) {
			return $value;
		}
		// Named colors: letters only (3 to 20 characters, e.g. transparent, red, white).
		if ( preg_match( '/^[a-z]{3,20}$/i', $value ) ) {
			return strtolower( $value );
		}
		return 'transparent';
	}

	/**
	 * Sanitize search strategy.
	 *
	 * @param string $value Strategy.
	 * @return string Sanitized strategy.
	 */
	/**
	 * Sanitize search strategy option.
	 *
	 * @param string $value Strategy.
	 * @return string Sanitized strategy.
	 */
	public function sanitize_strategy( $value ) {
		$value      = sanitize_text_field( $value );
		$strategies = apply_filters( 'tsbifw_search_strategies', array( 'embeddings' => esc_html__( 'Strategy 1: Multimodal Vector Embeddings (Recommended)', 'searchips-search-by-image-for-woocommerce' ) ) );
		return isset( $strategies[ $value ] ) ? $value : 'embeddings';
	}

	/**
	 * Sanitize model ID.
	 *
	 * @param string $value Model ID.
	 * @return string Sanitized model ID.
	 */
	public function sanitize_model_id( $value ) {
		$value = sanitize_text_field( $value );
		return preg_match( '/^[a-zA-Z0-9_\-\.\/:]+$/', $value ) ? $value : '';
	}

	/**
	 * Sanitize yes/no options.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized value.
	 */
	public function sanitize_yes_no( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'no';
	}

	/**
	 * Sanitize Pro-only yes/no option.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized value ('yes' or 'no').
	 */
	public function sanitize_pro_yes_no( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'no';
	}

	/**
	 * Sanitize excluded product categories option.
	 *
	 * @param mixed $value Option value.
	 * @return array Array of sanitized category term IDs.
	 */
	public function sanitize_excluded_categories( $value ) {
		if ( empty( $value ) || ! is_array( $value ) ) {
			return array();
		}
		return array_values( array_filter( array_map( 'absint', $value ) ) );
	}

	/**
	 * Sanitize enable mobile camera option.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized value ('yes' or 'no').
	 */
	public function sanitize_enable_mobile_camera( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'yes';
	}


	/**
	 * Sanitize similarity boost percent option.
	 *
	 * @param mixed $value Option value.
	 * @return int Sanitized percentage (1 to 30).
	 */
	public function sanitize_boost_percent( $value ) {
		$val = (int) $value;
		if ( $val < 1 ) {
			return 1;
		}
		if ( $val > 30 ) {
			return 30;
		}
		return $val;
	}

	/**
	 * Sanitize yes/no options with yes default.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized value.
	 */
	public function sanitize_yes_no_default_yes( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'yes', 'no' ), true ) ? $value : 'yes';
	}

	/**
	 * Sanitize cron interval option.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized value.
	 */
	public function sanitize_cron_interval( $value ) {
		$value = sanitize_text_field( $value );
		$valid = array(
			'tsbifw_every_minute',
			'tsbifw_every_5_minutes',
			'tsbifw_every_15_minutes',
			'every_minute',
			'every_5_minutes',
			'every_15_minutes',
			'hourly',
			'twice_daily',
			'daily',
		);
		return in_array( $value, $valid, true ) ? $value : 'tsbifw_every_5_minutes';
	}

	/**
	 * Sanitize visual scanning loading effect option.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized effect key.
	 */
	public function sanitize_scanning_effect( $value ) {
		$value   = sanitize_key( $value );
		$allowed = apply_filters( 'tsbifw_scanning_effects', array( 'laser' => esc_html__( 'Laser Line (Default)', 'searchips-search-by-image-for-woocommerce' ) ) );
		return isset( $allowed[ $value ] ) ? $value : 'laser';
	}

	/**
	 * Sanitize scanning accent color.
	 *
	 * @param string $value Option value.
	 * @return string Sanitized hex color.
	 */
	public function sanitize_scanning_color( $value ) {
		$color = sanitize_hex_color( $value );
		return ! empty( $color ) ? $color : '#6366f1';
	}

	/**
	 * Sanitize Max Upload Size.
	 *
	 * @param mixed $value Input value.
	 * @return int
	 */
	public function sanitize_max_upload_size( $value ) {
		$num = (int) $value;
		if ( $num <= 0 ) {
			add_settings_error(
				'tsbifw_max_upload_size',
				'tsbifw_max_upload_size_invalid',
				esc_html__( 'Maximum upload size must be a positive number.', 'searchips-search-by-image-for-woocommerce' ),
				'error'
			);
			return 2; // Fallback default.
		}
		return $num;
	}

	/**
	 * Sanitize direct Pro API key settings.
	 *
	 * @param string $value API key value.
	 * @return string
	 */
	public function sanitize_pro_api_key( $value ) {
		return sanitize_text_field( $value );
	}

	/**
	 * Sanitize enable analytics setting.
	 *
	 * @param mixed $value Input value.
	 * @return string 'yes' or 'no'.
	 */
	public function sanitize_enable_analytics( $value ) {
		$value = sanitize_text_field( $value );
		return ( 'yes' === $value ) ? 'yes' : 'no';
	}

	/**
	 * Sanitize analytics retention days.
	 *
	 * @param mixed $value Input value.
	 * @return int Days (between 7 and 365).
	 */
	public function sanitize_analytics_retention( $value ) {
		$days = (int) $value;
		if ( $days < 7 ) {
			$days = 7;
		} elseif ( $days > 365 ) {
			$days = 365;
		}
		return $days;
	}

	/**
	 * Renders an educational preview demonstration of the Visual Search Analytics dashboard for free users.
	 *
	 * Strictly compliant with WordPress.org Guideline 5: clearly demarcated as a sample
	 * demonstration showcase of Pro Addon analytics without locked forms or deceptive controls.
	 */
	public function render_analytics_preview_tab() {
		?>
		<div class="tsbifw-analytics-dashboard tsbifw-analytics-preview-mode">
			<!-- Preview Notice Banner -->
			<div style="background: #ffffff; border: 1px solid #c7d2fe; border-left: 4px solid #4f46e5; border-radius: 8px; padding: 18px 20px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(79, 70, 229, 0.05); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 16px;">
				<div style="max-width: 750px;">
					<div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
						<span class="dashicons dashicons-chart-area" style="color: #4f46e5; font-size: 20px; width: 20px; height: 20px;"></span>
						<strong style="font-size: 14.5px; color: #0f172a;"><?php esc_html_e( 'Visual Search Analytics (Pro Preview Demonstration)', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
						<span style="background: #eef2ff; color: #4338ca; border: 1px solid #c7d2fe; font-size: 10px; font-weight: 700; text-transform: uppercase; padding: 2px 6px; border-radius: 4px;"><?php esc_html_e( 'Sample Data', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					</div>
					<p style="font-size: 13px; color: #475569; margin: 0; line-height: 1.5;">
						<?php esc_html_e( 'This screen demonstrates how the Searchips Pro Addon tracks shopper visual searches, Click-Through Rates (CTR), and unfulfilled demand. Below is a sample preview of the dashboard with demonstration metrics.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
				</div>
				<div>
					<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); border-color: #4338ca; font-weight: 700; font-size: 13px; padding: 6px 16px;">
						<?php esc_html_e( 'Upgrade to Pro to Enable Live Tracking', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
					</a>
				</div>
			</div>

			<!-- Mockup Settings Bar (Demo Controls) -->
			<div style="margin-bottom: 20px; background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 15px 20px;">
				<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
					<div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
						<label style="font-weight: 600; color: #64748b; display: inline-flex; align-items: center; gap: 6px; cursor: not-allowed;" title="<?php esc_attr_e( 'Active in Searchips Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?>">
							<input type="checkbox" checked disabled style="cursor: not-allowed;" />
							<?php esc_html_e( 'Enable Visual Search Analytics', 'searchips-search-by-image-for-woocommerce' ); ?>
						</label>
						<div style="display: inline-flex; align-items: center; gap: 8px;">
							<label style="font-size: 13px; color: #64748b; font-weight: 500;"><?php esc_html_e( 'Data Retention:', 'searchips-search-by-image-for-woocommerce' ); ?></label>
							<select disabled style="font-size: 13px; cursor: not-allowed;">
								<option><?php esc_html_e( '30 Days (Default)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
							</select>
						</div>
						<span style="font-size: 11px; color: #94a3b8; font-style: italic;">
							(<?php esc_html_e( 'Configuration available with Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?>)
						</span>
					</div>
					<div>
						<button type="button" class="button" disabled style="margin: 0; opacity: 0.6; cursor: not-allowed;"><?php esc_html_e( 'Save Analytics Settings', 'searchips-search-by-image-for-woocommerce' ); ?></button>
					</div>
				</div>
			</div>

			<!-- Sample KPI Summary Cards -->
			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Total Visual Searches', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					<div style="font-size: 26px; font-weight: 800; color: #0f172a; margin-top: 6px;">1,428</div>
					<span style="font-size: 11.5px; color: #10b981; font-weight: 600;">&uarr; +18.4% <?php esc_html_e( 'this month', 'searchips-search-by-image-for-woocommerce' ); ?></span>
				</div>
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Overall CTR', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					<div style="font-size: 26px; font-weight: 800; color: #4f46e5; margin-top: 6px;">34.8%</div>
					<span style="font-size: 11.5px; color: #64748b;"><?php esc_html_e( 'Shoppers clicked to view products', 'searchips-search-by-image-for-woocommerce' ); ?></span>
				</div>
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Matched Searches', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					<div style="font-size: 26px; font-weight: 800; color: #10b981; margin-top: 6px;">1,288</div>
					<span style="font-size: 11.5px; color: #10b981; font-weight: 600;">90.2% <?php esc_html_e( 'catalog match rate', 'searchips-search-by-image-for-woocommerce' ); ?></span>
				</div>
				<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 18px; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
					<span style="font-size: 11.5px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px;"><?php esc_html_e( 'Unfulfilled Demand (0 Results)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					<div style="font-size: 26px; font-weight: 800; color: #ef4444; margin-top: 6px;">140</div>
					<span style="font-size: 11.5px; color: #ef4444; font-weight: 600;"><?php esc_html_e( 'Missed inventory opportunities', 'searchips-search-by-image-for-woocommerce' ); ?></span>
				</div>
			</div>

			<!-- Mockup Filter Buttons Bar -->
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
				<div style="display: flex; gap: 8px;">
					<button type="button" class="button button-primary" style="pointer-events: none;">
						<?php esc_html_e( 'All Searches (1,428)', 'searchips-search-by-image-for-woocommerce' ); ?>
					</button>
					<button type="button" class="button button-secondary" style="pointer-events: none; opacity: 0.85;">
						<?php esc_html_e( 'Unfulfilled Demand Only', 'searchips-search-by-image-for-woocommerce' ); ?>
						<span class="count" style="background: #ef4444; color: #fff; border-radius: 10px; padding: 0 6px; font-size: 11px; margin-left: 4px;">140</span>
					</button>
				</div>
				<div style="display: flex; gap: 8px; align-items: center;">
					<button type="button" class="button button-secondary" disabled style="display: inline-flex; align-items: center; gap: 5px; opacity: 0.6; cursor: not-allowed;">
						<span class="dashicons dashicons-update" style="font-size: 16px; width: 16px; height: 16px;"></span>
						<span><?php esc_html_e( 'Reload (Preview)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					</button>
					<button type="button" class="button button-secondary" disabled style="opacity: 0.6; cursor: not-allowed;"><?php esc_html_e( 'Prune > 30 Days', 'searchips-search-by-image-for-woocommerce' ); ?></button>
					<button type="button" class="button" disabled style="color: #b32d2e; border-color: #b32d2e; opacity: 0.6; cursor: not-allowed;"><?php esc_html_e( 'Clear All Data', 'searchips-search-by-image-for-woocommerce' ); ?></button>
				</div>
			</div>

			<!-- Sample Queries Table -->
			<div style="background: #fff; border: 1px solid #ccd0d4; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 4px rgba(0,0,0,0.04); margin-bottom: 20px;">
				<table class="wp-list-table widefat fixed striped" style="border: none;">
					<thead>
						<tr>
							<th style="width: 75px; font-weight: 700;"><?php esc_html_e( 'Query Image', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="width: 140px; font-weight: 700;"><?php esc_html_e( 'Timestamp', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="width: 130px; font-weight: 700;"><?php esc_html_e( 'Strategy', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="width: 140px; font-weight: 700;"><?php esc_html_e( 'Results', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="font-weight: 700;"><?php esc_html_e( 'Shopper Action / Clicked Product (CTR)', 'searchips-search-by-image-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<!-- Sample Row 1 -->
						<tr>
							<td style="vertical-align: middle; padding: 10px;">
								<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; display: block;" />
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #334155;">
								<strong><?php esc_html_e( '12 minutes ago', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<span style="font-size: 11px; color: #94a3b8;">Today, 12:48 PM</span>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #eef2ff; color: #4338ca; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">Embeddings</span>
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #10b981; font-weight: 600;">
								&#10004; 12 <?php esc_html_e( 'Found', 'searchips-search-by-image-for-woocommerce' ); ?>
								<div style="font-size: 11px; color: #64748b; font-weight: normal;"><?php esc_html_e( 'Top: 98.2% Match', 'searchips-search-by-image-for-woocommerce' ); ?></div>
							</td>
							<td style="vertical-align: middle;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										&#10004; <?php esc_html_e( 'CTR Converted', 'searchips-search-by-image-for-woocommerce' ); ?>
									</span>
									<span style="font-size: 12.5px; color: #0f172a; font-weight: 600;">Nike Air Max Sport Edition (SKU: NK-90)</span>
								</div>
							</td>
						</tr>

						<!-- Sample Row 2 -->
						<tr>
							<td style="vertical-align: middle; padding: 10px;">
								<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; display: block;" />
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #334155;">
								<strong><?php esc_html_e( '45 minutes ago', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<span style="font-size: 11px; color: #94a3b8;">Today, 12:15 PM</span>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #f0fdf4; color: #15803d; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">Vision AI</span>
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #10b981; font-weight: 600;">
								&#10004; 8 <?php esc_html_e( 'Found', 'searchips-search-by-image-for-woocommerce' ); ?>
								<div style="font-size: 11px; color: #64748b; font-weight: normal;"><?php esc_html_e( 'Top: 94.7% Match', 'searchips-search-by-image-for-woocommerce' ); ?></div>
							</td>
							<td style="vertical-align: middle;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										&#10004; <?php esc_html_e( 'CTR Converted', 'searchips-search-by-image-for-woocommerce' ); ?>
									</span>
									<span style="font-size: 12.5px; color: #0f172a; font-weight: 600;">Casual Denim Jacket - Medium / Dark Blue</span>
								</div>
							</td>
						</tr>

						<!-- Sample Row 3: Zero-Result Unfulfilled Demand -->
						<tr style="background: #fffbfa;">
							<td style="vertical-align: middle; padding: 10px;">
								<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1.5px solid #fca5a5; display: block;" />
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #334155;">
								<strong><?php esc_html_e( '2 hours ago', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<span style="font-size: 11px; color: #94a3b8;">Today, 11:00 AM</span>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #eef2ff; color: #4338ca; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">Embeddings</span>
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #ef4444; font-weight: 700;">
								&#10008; 0 <?php esc_html_e( 'Found', 'searchips-search-by-image-for-woocommerce' ); ?>
								<div style="font-size: 11px; color: #ef4444; font-weight: 600;"><?php esc_html_e( 'Demand Opportunity', 'searchips-search-by-image-for-woocommerce' ); ?></div>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; padding: 3px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 600;">
									<?php esc_html_e( 'Unfulfilled Search: Shopper looked for a product not in your current catalog.', 'searchips-search-by-image-for-woocommerce' ); ?>
								</span>
							</td>
						</tr>

						<!-- Sample Row 4 -->
						<tr>
							<td style="vertical-align: middle; padding: 10px;">
								<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; display: block;" />
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #334155;">
								<strong><?php esc_html_e( '3 hours ago', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<span style="font-size: 11px; color: #94a3b8;">Today, 09:42 AM</span>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #fdf4ff; color: #a21caf; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">OpenAI GPT-4o</span>
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #10b981; font-weight: 600;">
								&#10004; 15 <?php esc_html_e( 'Found', 'searchips-search-by-image-for-woocommerce' ); ?>
								<div style="font-size: 11px; color: #64748b; font-weight: normal;"><?php esc_html_e( 'Top: 96.1% Match', 'searchips-search-by-image-for-woocommerce' ); ?></div>
							</td>
							<td style="vertical-align: middle;">
								<div style="display: flex; align-items: center; gap: 8px;">
									<span style="background: #ecfdf5; color: #047857; border: 1px solid #a7f3d0; padding: 3px 8px; border-radius: 4px; font-size: 11px; font-weight: 700;">
										&#10004; <?php esc_html_e( 'CTR Converted', 'searchips-search-by-image-for-woocommerce' ); ?>
									</span>
									<span style="font-size: 12.5px; color: #0f172a; font-weight: 600;">Noise Cancelling Wireless Headphones</span>
								</div>
							</td>
						</tr>

						<!-- Sample Row 5 -->
						<tr>
							<td style="vertical-align: middle; padding: 10px;">
								<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Sample" style="width: 48px; height: 48px; object-fit: cover; border-radius: 6px; border: 1px solid #cbd5e1; display: block;" />
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #334155;">
								<strong><?php esc_html_e( 'Yesterday', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<span style="font-size: 11px; color: #94a3b8;">Yesterday, 18:32 PM</span>
							</td>
							<td style="vertical-align: middle;">
								<span style="background: #eef2ff; color: #4338ca; padding: 2px 7px; border-radius: 4px; font-size: 11px; font-weight: 600; display: inline-block;">Embeddings</span>
							</td>
							<td style="vertical-align: middle; font-size: 12.5px; color: #10b981; font-weight: 600;">
								&#10004; 6 <?php esc_html_e( 'Found', 'searchips-search-by-image-for-woocommerce' ); ?>
								<div style="font-size: 11px; color: #64748b; font-weight: normal;"><?php esc_html_e( 'Top: 91.5% Match', 'searchips-search-by-image-for-woocommerce' ); ?></div>
							</td>
							<td style="vertical-align: middle;">
								<span style="color: #94a3b8; font-size: 12px; font-style: italic;">
									<?php esc_html_e( 'Shopper browsed results without clicking through.', 'searchips-search-by-image-for-woocommerce' ); ?>
								</span>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Mockup Pagination -->
			<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px; padding: 10px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 6px;">
				<span style="font-size: 12.5px; color: #64748b;">
					<?php esc_html_e( 'Showing 5 of 1,428 sample records (Page 1 of 72)', 'searchips-search-by-image-for-woocommerce' ); ?>
				</span>
				<div style="display: flex; gap: 4px;">
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">&laquo; <?php esc_html_e( 'First', 'searchips-search-by-image-for-woocommerce' ); ?></button>
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">&lsaquo; <?php esc_html_e( 'Prev', 'searchips-search-by-image-for-woocommerce' ); ?></button>
					<button type="button" class="button button-primary" style="pointer-events: none;">1</button>
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">2</button>
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">3</button>
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">&rsaquo; <?php esc_html_e( 'Next', 'searchips-search-by-image-for-woocommerce' ); ?></button>
					<button type="button" class="button" disabled style="opacity: 0.5; cursor: not-allowed;">&raquo; <?php esc_html_e( 'Last', 'searchips-search-by-image-for-woocommerce' ); ?></button>
				</div>
			</div>

			<!-- Upgrade Call to Action Banner -->
			<div style="background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%); color: #fff; padding: 28px; border-radius: 10px; box-shadow: 0 4px 14px rgba(49, 46, 129, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
				<div style="max-width: 650px;">
					<h3 style="color: #fff; font-size: 18px; font-weight: 700; margin: 0 0 8px 0;">
						<?php esc_html_e( 'Ready to Track Real Shopper Visual Searches?', 'searchips-search-by-image-for-woocommerce' ); ?>
					</h3>
					<p style="color: #c7d2fe; font-size: 13.5px; line-height: 1.5; margin: 0;">
						<?php esc_html_e( 'The Searchips Pro Addon connects seamlessly to this interface, automatically recording query photos, tracking conversion CTR beacons, and identifying missed catalog demand in real time.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
				</div>
				<div>
					<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #ffffff; color: #4338ca; border: none; font-size: 14px; font-weight: 700; padding: 10px 24px; height: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.2);">
						<?php esc_html_e( 'Get Searchips Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
					</a>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the dedicated Pro Feature Preview Tab for Free users.
	 *
	 * Compliant with WordPress.org Plugin Directory Guidelines. Provides an
	 * educational preview of features available in the Searchips Pro Addon.
	 */
	public function render_pro_preview_tab() {
		?>
		<div class="tsbifw-pro-preview-wrap">
			<!-- Hero Header -->
			<div class="tsbifw-pro-hero" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #fff; padding: 40px 30px; border-radius: 12px; margin-bottom: 30px; box-shadow: 0 10px 25px -5px rgba(79, 70, 229, 0.25);">
				<div style="max-width: 800px;">
					<span style="display: inline-block; background: rgba(255,255,255,0.2); backdrop-filter: blur(4px); font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; padding: 4px 10px; border-radius: 20px; margin-bottom: 12px;">
						<?php esc_html_e( 'Searchips Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?>
					</span>
					<h2 style="font-size: 28px; font-weight: 800; color: #fff; margin: 0 0 12px 0; line-height: 1.25;">
						<?php esc_html_e( 'Supercharge Your Visual Search with Pro', 'searchips-search-by-image-for-woocommerce' ); ?>
					</h2>
					<p style="font-size: 15px; line-height: 1.6; color: #e0e7ff; margin: 0 0 20px 0;">
						<?php esc_html_e( 'Unlock enterprise-grade features designed for fast-growing stores: live analytics with Click-Through Rate tracking, variable product variations indexing, direct OpenAI & Gemini API connections, smart MD5 caching to slash API bills, and algorithmic similarity boosting.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="display: flex; gap: 12px; flex-wrap: wrap;">
						<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #fff; color: #4f46e5; border: none; font-size: 14px; font-weight: 700; padding: 8px 24px; height: auto; box-shadow: 0 4px 12px rgba(0,0,0,0.15);">
							<?php esc_html_e( 'Upgrade to Searchips Pro', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
						</a>
					</div>
				</div>
			</div>

			<!-- Feature Showcase Grid -->
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 18px 0;">
				<?php esc_html_e( 'Explore Pro Features & Settings Preview', 'searchips-search-by-image-for-woocommerce' ); ?>
			</h3>

			<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px; margin-bottom: 35px;">
				<!-- Card 1: Visual Search Analytics Mockup -->
				<div id="pro-feature-analytics" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-chart-area" style="font-size: 24px; width: 24px; height: 24px; color: #4f46e5;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Visual Search Analytics & CTR', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Monitor shopper behavior in real-time. View exact photo thumbnails searched, conversion Click-Through Rate, and uncover zero-result queries to optimize catalog stock.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<!-- Mini Interactive Preview Mockup -->
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px;">
						<div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
							<span><strong><?php esc_html_e( 'Total Queries:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> 1,420</span>
							<span style="color: #10b981;"><strong><?php esc_html_e( 'CTR:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> 34.8%</span>
						</div>
						<div style="background: #e2e8f0; height: 6px; border-radius: 3px; overflow: hidden;">
							<div style="background: #4f46e5; width: 34.8%; height: 100%;"></div>
						</div>
						<span style="display: block; margin-top: 8px; font-size: 11px; color: #94a3b8;"><?php esc_html_e( '140 Zero-Result Unfulfilled Searches flagged for inventory restocking.', 'searchips-search-by-image-for-woocommerce' ); ?></span>
					</div>
				</div>

				<!-- Card 2: Variations Indexing -->
				<div id="pro-feature-variations" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-images-alt2" style="font-size: 24px; width: 24px; height: 24px; color: #0284c7;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Variable Product Variations Indexing', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Indexes individual WooCommerce variable product thumbnails. When customers upload a photo of a blue shirt, the visual search links straight to the blue variation swatch.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px;">
						<div style="display: inline-flex; align-items: center; gap: 6px; color: #0369a1; font-weight: 600;">
							<span class="dashicons dashicons-yes"></span>
							<?php esc_html_e( 'Swaps thumbnail on archive loops to the exact matching variation.', 'searchips-search-by-image-for-woocommerce' ); ?>
						</div>
					</div>
				</div>

				<!-- Card 3: Direct Gateways -->
				<div id="pro-feature-gateways" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-rest-api" style="font-size: 24px; width: 24px; height: 24px; color: #10b981;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Direct OpenAI & Gemini Gateways', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Connect directly to your own OpenAI or Google AI Studio accounts. Zero intermediary markup, lower latency, and full support for GPT-4o vision and native multimodal embeddings.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; color: #334155;">
						<code>OpenAI Direct (text-embedding-3 / GPT-4o)</code><br />
						<code>Google AI Studio (Gemini Embedding 2 / 2.5 Flash)</code>
					</div>
				</div>

				<!-- Card 4: Smart Image Hashing -->
				<div id="pro-feature-hashing" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-shield" style="font-size: 24px; width: 24px; height: 24px; color: #d97706;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Smart MD5 Image Hashing', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Calculates file checksum hashes of product attachments. When products are saved or batch-indexed, unchanged images are skipped, slashing API costs by up to 80%.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; color: #b45309;">
						<span class="dashicons dashicons-saved" style="vertical-align: text-bottom;"></span>
						<?php esc_html_e( 'Reduces server CPU, prevents timeouts, and conserves API quotas.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</div>
				</div>

				<!-- Card 5: Category Exclusions -->
				<div id="pro-feature-categories" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-category" style="font-size: 24px; width: 24px; height: 24px; color: #8b5cf6;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Category Exclusion Rules', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Exclude non-physical categories like digital downloads, gift cards, software licenses, or warranties from visual search indexing automatically.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; color: #475569;">
						<?php esc_html_e( 'Multi-select categories to keep visual search results focused on tangible products.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</div>
				</div>

				<!-- Card 6: Similarity Boost -->
				<div id="pro-feature-boost" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-awards" style="font-size: 24px; width: 24px; height: 24px; color: #ec4899;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Algorithmic Similarity Boost', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Give featured and on-sale items a +1% to +30% boost in visual similarity scores to prioritize inventory with promotions or higher margins.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; color: #be185d;">
						<?php esc_html_e( 'Configurable +10% default bonus for Featured and Sale products.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</div>
				</div>

				<!-- Card 7: Native Mobile Camera Photo Capture -->
				<div id="pro-feature-mobile-camera" style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 22px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
					<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
						<span class="dashicons dashicons-camera" style="font-size: 24px; width: 24px; height: 24px; color: #10b981;"></span>
						<h4 style="margin: 0; font-size: 16px; color: #0f172a;"><?php esc_html_e( 'Mobile Camera Instant Capture', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
					</div>
					<p style="font-size: 13px; color: #64748b; line-height: 1.5; margin: 0 0 14px 0;">
						<?php esc_html_e( 'Mobile shoppers can tap "Take Photo" inside the search modal to launch their native smartphone camera directly and search live photos.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</p>
					<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 12px; font-size: 12px; color: #047857;">
						<span class="dashicons dashicons-smartphone" style="vertical-align: text-bottom;"></span>
						<?php esc_html_e( 'HTML5 native environment capture with autofocus and zero user permission hurdles.', 'searchips-search-by-image-for-woocommerce' ); ?>
					</div>
				</div>

				</div>

			<!-- 5 Futuristic Scanner FXs Live Interactive Showcase -->
			<div id="pro-feature-effects" class="tsbifw-pro-scanner-showcase">
				<div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 14px; margin-bottom: 22px; border-bottom: 1px solid #f1f5f9; padding-bottom: 18px;">
					<div>
						<div style="display: flex; align-items: center; gap: 10px; margin-bottom: 6px;">
							<span class="dashicons dashicons-admin-customizer" style="font-size: 26px; width: 26px; height: 26px; color: #4f46e5;"></span>
							<h3 style="margin: 0; font-size: 20px; font-weight: 700; color: #0f172a;"><?php esc_html_e( '5 Futuristic Scanner FXs (Live Previews)', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Feature', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<p style="font-size: 13.5px; color: #64748b; margin: 0; line-height: 1.5; max-width: 820px;">
							<?php esc_html_e( 'Delight shoppers with cinema-grade visual scanning animations that elevate your store above competitors. All 5 Pro effects run in pure GPU-accelerated CSS and dynamically inherit your custom brand glow color. Preview each animation live below:', 'searchips-search-by-image-for-woocommerce' ); ?>
						</p>
					</div>
					<div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
						<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: linear-gradient(135deg, #4f46e5 0%, #4338ca 100%); border-color: #4338ca; font-weight: 700; font-size: 13px; padding: 6px 18px;">
							<?php esc_html_e( 'Get Pro Addon to Unlock All 5', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
						</a>
					</div>
				</div>

				<!-- Live Preview Cards Grid (5 Pro FXs + 1 Free Baseline) -->
				<div class="tsbifw-fx-gallery-grid">
					<!-- 1. AI Vision Reticle (Pro) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-visibility" style="color: #6366f1;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( '1. AI Vision Reticle', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="reticle" style="--tsbifw-scan-color: #6366f1;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Reticle Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="reticle">
								<div class="tsbifw-effect-layer tsbifw-effect-reticle">
									<div class="tsbifw-reticle-bracket tsbifw-reticle-tl"></div>
									<div class="tsbifw-reticle-bracket tsbifw-reticle-tr"></div>
									<div class="tsbifw-reticle-bracket tsbifw-reticle-bl"></div>
									<div class="tsbifw-reticle-bracket tsbifw-reticle-br"></div>
									<div class="tsbifw-reticle-crosshair"></div>
									<div class="tsbifw-reticle-tag">AI_TARGET: 0x94F2</div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #6366f1; box-shadow: 0 0 8px #6366f1;"></span>
								<span><?php esc_html_e( 'AI_TARGET: 0x94F2 (Locking Features)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( 'Precision computer vision corner brackets with 360-degree rotating crosshairs, pulsing acquisition locks, and live coordinate tracking tags.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit">
								<strong><?php esc_html_e( 'Best For:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Streetwear, High-Tech, Electronics, Footwear & Apparel.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Target Crosshairs', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Coordinate Telemetry', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>

					<!-- 2. Sonar Radar Sweep (Pro) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-marker" style="color: #10b981;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( '2. Sonar Radar Sweep', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="radar" style="--tsbifw-scan-color: #10b981;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Radar Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="radar">
								<div class="tsbifw-effect-layer tsbifw-effect-radar">
									<div class="tsbifw-radar-ring tsbifw-radar-ring-1"></div>
									<div class="tsbifw-radar-ring tsbifw-radar-ring-2"></div>
									<div class="tsbifw-radar-sweep"></div>
									<div class="tsbifw-radar-grid"></div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #10b981; box-shadow: 0 0 8px #10b981;"></span>
								<span><?php esc_html_e( 'RADAR: 360° Spatial Echo Scanning', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( '360-degree rotating conical radar beam with concentric distance rings simulating military-grade spatial echo and sonar catalog probing.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit">
								<strong><?php esc_html_e( 'Best For:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Outdoor, Sports, Tactical, Camping & Industrial gear.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Conic Sweep Beam', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Concentric Rings', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>

					<!-- 3. Digital Mesh Grid (Pro) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-grid-view" style="color: #06b6d4;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( '3. Digital Mesh Grid', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="matrix" style="--tsbifw-scan-color: #06b6d4;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Mesh Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="matrix">
								<div class="tsbifw-effect-layer tsbifw-effect-matrix">
									<div class="tsbifw-matrix-grid"></div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #06b6d4; box-shadow: 0 0 8px #06b6d4;"></span>
								<span><?php esc_html_e( 'MESH: Volumetric 3D Topology Active', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( 'Luminous wireframe matrix with animated perspective flow simulating 3D surface depth mapping and geometric object reconstruction.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit">
								<strong><?php esc_html_e( 'Best For:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Cyber, Gaming, Modern Gadgets & 3D Products.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Perspective Matrix', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( '3D Depth Mapping', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>

					<!-- 4. Concentric Ripple (Pro) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-update" style="color: #ec4899;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( '4. Concentric Ripple', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="ripple" style="--tsbifw-scan-color: #ec4899;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Ripple Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="ripple">
								<div class="tsbifw-effect-layer tsbifw-effect-ripple">
									<div class="tsbifw-ripple-wave tsbifw-ripple-1"></div>
									<div class="tsbifw-ripple-wave tsbifw-ripple-2"></div>
									<div class="tsbifw-ripple-wave tsbifw-ripple-3"></div>
									<div class="tsbifw-ripple-core"></div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #ec4899; box-shadow: 0 0 8px #ec4899;"></span>
								<span><?php esc_html_e( 'RIPPLE: Harmonic 432Hz Pulse Wave', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( 'Calm biometric sonar ripples radiating outward smoothly from the image focal center with glowing harmonic resonance and organic pacing.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit">
								<strong><?php esc_html_e( 'Best For:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Wellness, Beauty, Cosmetics, Organic & Home Decor.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Harmonic Resonator', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Organic Flow', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>

					<!-- 5. Luxury Hologram Shimmer (Pro) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-admin-appearance" style="color: #8b5cf6;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( '5. Luxury Hologram', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-pro"><?php esc_html_e( 'Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="hologram" style="--tsbifw-scan-color: #8b5cf6;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Hologram Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="hologram">
								<div class="tsbifw-effect-layer tsbifw-effect-hologram">
									<div class="tsbifw-hologram-prism"></div>
									<div class="tsbifw-hologram-shimmer"></div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #8b5cf6; box-shadow: 0 0 8px #8b5cf6;"></span>
								<span><?php esc_html_e( 'HOLOGRAM: Iridescent Prism Sheen', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( 'Diagonal iridescent prism sheen with glassmorphism reflections and multidimensional light refractions engineered for high-end boutique storefronts.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit">
								<strong><?php esc_html_e( 'Best For:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'Jewelry, Watches, Luxury Handbags & High Fashion.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Prism Glassmorphism', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Iridescent Shimmer', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>

					<!-- 6. Classic Laser Sweep (Free Baseline) -->
					<div class="tsbifw-fx-preview-card">
						<div class="tsbifw-fx-card-header">
							<div class="tsbifw-fx-card-title-wrap">
								<span class="dashicons dashicons-image-filter" style="color: #64748b;"></span>
								<h4 class="tsbifw-fx-card-title"><?php esc_html_e( 'Classic Laser Sweep', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<span class="tsbifw-fx-badge-free"><?php esc_html_e( 'Free Included', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
						<div class="tsbifw-fx-stage-wrap tsbifw-mockup-stage" data-effect="laser" style="--tsbifw-scan-color: #6366f1;">
							<img src="<?php echo esc_url( TSBIFW_PLUGIN_URL . 'assets/images/preview-sample.svg' ); ?>" alt="Laser Preview" class="tsbifw-mockup-img" />
							<div class="tsbifw-scan-container tsbifw-scan-active" data-effect="laser">
								<div class="tsbifw-effect-layer tsbifw-effect-laser">
									<div class="tsbifw-scanner-bar"></div>
									<div class="tsbifw-scanning-overlay"></div>
								</div>
							</div>
							<div class="tsbifw-mockup-status" style="bottom: 8px; padding: 4px 10px; font-size: 10px;">
								<span class="tsbifw-pulse-dot" style="background: #6366f1; box-shadow: 0 0 8px #6366f1;"></span>
								<span><?php esc_html_e( 'LASER: Linear Vertical Sweep', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
						<div class="tsbifw-fx-card-body">
							<p class="tsbifw-fx-desc">
								<?php esc_html_e( 'Standard vertical neon laser line sweeping continuously up and down across the preview image. Included for all users in the Free version.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</p>
							<div class="tsbifw-fx-fit" style="border-left-color: #94a3b8;">
								<strong><?php esc_html_e( 'Baseline:', 'searchips-search-by-image-for-woocommerce' ); ?></strong> <?php esc_html_e( 'General retail and all standard WooCommerce catalogs.', 'searchips-search-by-image-for-woocommerce' ); ?>
							</div>
							<div class="tsbifw-fx-tags">
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Linear Beam', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<span class="tsbifw-fx-tag"><?php esc_html_e( 'Standard Default', 'searchips-search-by-image-for-woocommerce' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>

			<!-- Comparison Table -->
			<h3 style="font-size: 18px; font-weight: 700; color: #0f172a; margin: 0 0 18px 0;">
				<?php esc_html_e( 'Feature Comparison: Free vs Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?>
			</h3>

			<div style="background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; overflow: hidden; margin-bottom: 30px; box-shadow: 0 1px 3px rgba(0,0,0,0.04);">
				<table class="widefat" style="border: none; border-collapse: collapse;">
					<thead>
						<tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
							<th style="padding: 14px 20px; font-size: 13px; font-weight: 700; color: #334155;"><?php esc_html_e( 'Feature / Capability', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="padding: 14px 20px; font-size: 13px; font-weight: 700; color: #334155; width: 180px; text-align: center;"><?php esc_html_e( 'Free Version', 'searchips-search-by-image-for-woocommerce' ); ?></th>
							<th style="padding: 14px 20px; font-size: 13px; font-weight: 700; color: #4f46e5; width: 220px; text-align: center; background: #eef2ff;"><?php esc_html_e( 'Searchips Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Strategy 1: Vector Embeddings Search', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981;">&#10004; <?php esc_html_e( 'Included', 'searchips-search-by-image-for-woocommerce' ); ?></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Included', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Strategy 2: Vision-to-Text Description Search', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Included (GPT-4o & Gemini Flash)', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Featured & Gallery Image Indexing', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981;">&#10004; <?php esc_html_e( 'Included', 'searchips-search-by-image-for-woocommerce' ); ?></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Included', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Product Variation Images Indexing', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Full Variable Support', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Supported AI Gateways', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #475569;"><?php esc_html_e( 'OpenRouter', 'searchips-search-by-image-for-woocommerce' ); ?></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;"><?php esc_html_e( 'OpenRouter, OpenAI Direct, Gemini Direct', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Visual Search Analytics & CTR Tracker', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Full Dashboard & Retention Control', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Smart MD5 Image Hashing', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Automatic Cost Saving', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Category Exclusion Rules', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Multi-Category Filter', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Algorithmic Similarity Boost', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( '+1% to +30% Score Boost', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'Visual Scanning Animations', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #475569;"><?php esc_html_e( 'Laser line', 'searchips-search-by-image-for-woocommerce' ); ?></td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;"><?php esc_html_e( '6 Animations (Radar, Mesh, Reticle, etc.)', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr style="border-bottom: 1px solid #f1f5f9;">
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'WC Products Bulk Action Indexing', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <?php esc_html_e( 'Action Scheduler Async Background', 'searchips-search-by-image-for-woocommerce' ); ?></td>
						</tr>
						<tr>
							<td style="padding: 12px 20px;"><strong><?php esc_html_e( 'WP-CLI CLI Integration', 'searchips-search-by-image-for-woocommerce' ); ?></strong></td>
							<td style="padding: 12px 20px; text-align: center; color: #94a3b8;">-</td>
							<td style="padding: 12px 20px; text-align: center; color: #10b981; font-weight: 600; background: #f8fafc;">&#10004; <code>wp searchips index</code></td>
						</tr>
					</tbody>
				</table>
			</div>

			<!-- Call To Action Footer -->
			<div style="text-align: center; padding: 25px 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px;">
				<h4 style="margin: 0 0 8px 0; font-size: 18px; color: #0f172a; font-weight: 700;">
					<?php esc_html_e( 'Ready to unlock Searchips Pro?', 'searchips-search-by-image-for-woocommerce' ); ?>
				</h4>
				<p style="margin: 0 0 16px 0; color: #64748b; font-size: 13px;">
					<?php esc_html_e( 'Install and activate the Searchips Pro Addon plugin on this site to seamlessly activate all capabilities.', 'searchips-search-by-image-for-woocommerce' ); ?>
				</p>
				<a href="https://violo.ir/?p=707" target="_blank" rel="noopener noreferrer" class="button button-primary" style="background: #4f46e5; border-color: #4338ca; font-size: 14px; font-weight: 700; padding: 8px 26px; height: auto;">
					<?php esc_html_e( 'Get Searchips Pro Addon', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}
}

