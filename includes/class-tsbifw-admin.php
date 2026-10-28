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
		add_action( 'add_option_tsbifw_enable_cron_indexing', array( $this, 'check_cron_scheduling' ) );
		add_action( 'update_option_tsbifw_enable_cron_indexing', array( $this, 'check_cron_scheduling' ) );
		add_action( 'add_option_tsbifw_cron_interval', array( $this, 'check_cron_scheduling' ) );
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
		if ( 'yes' === get_option( 'tsbifw_enable_media_column', 'yes' ) ) {
			add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ) );
			add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
			add_action( 'restrict_manage_posts', array( $this, 'add_media_filter_dropdown' ) );
			add_action( 'pre_get_posts', array( $this, 'filter_media_by_indexed_status' ) );
		}

		// Register suggested privacy policy content.
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );

		// Unified feature UI renderers.
		add_action( 'tsbifw_settings_styling_effects', array( $this, 'render_styling_effects' ) );
		add_action( 'tsbifw_preview_stage_effects', array( $this, 'render_preview_stage_effects' ) );
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
		wp_enqueue_style( 'tsbifw-admin-css', TSBIFW_PLUGIN_URL . 'assets/css/admin.css', array( 'wp-color-picker' ), TSBIFW_VERSION );
		wp_enqueue_script( 'tsbifw-admin-js', TSBIFW_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery', 'tsbifw-cropperjs', 'wp-color-picker' ), TSBIFW_VERSION, true );

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
				'search_endpoint' => esc_url_raw( rest_url( 'tsbifw/v1/search' ) ),
				'gateway_labels'  => apply_filters( 'tsbifw_admin_gateway_labels', array( 'openrouter' => 'OpenRouter' ) ),
				'max_upload_size' => $max_mb * 1024 * 1024,
				'scanning_effect' => array_key_exists( get_option( 'tsbifw_scanning_effect', 'laser' ), apply_filters( 'tsbifw_scanning_effects', array( 'laser' => 'Laser' ) ) ) ? get_option( 'tsbifw_scanning_effect', 'laser' ) : 'laser',
				'scanning_color'  => get_option( 'tsbifw_scanning_color', '#6366f1' ),
				'strings'         => array(
					'scanning'             => esc_html__( 'Searching...', 'searchips-search-by-image-for-woocommerce' ),
					'no_results'           => esc_html__( 'No matching products found.', 'searchips-search-by-image-for-woocommerce' ),
					'error'                => esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
					'view_product'         => esc_html__( 'View Product', 'searchips-search-by-image-for-woocommerce' ),
					'add_to_cart'          => esc_html__( 'Add to Cart', 'searchips-search-by-image-for-woocommerce' ),
					'similarity_label'     => esc_html__( 'Match:', 'searchips-search-by-image-for-woocommerce' ),
					'view_in_store'        => esc_html__( 'View in Store Archive', 'searchips-search-by-image-for-woocommerce' ),
					'hidden_from_catalog'  => esc_html__( 'Hidden from Catalog', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'drag_drop_text'       => sprintf( esc_html__( 'Drag and drop an image here or click to browse (Max size: %dMB)', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
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
		register_setting( 'tsbifw_settings_group', 'tsbifw_embeddings_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
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
		register_setting( 'tsbifw_settings_group', 'tsbifw_auto_index_on_save', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_media_column', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no_default_yes' ),
		) );

		/**
		 * Action to allow extensions to register additional settings.
		 */
		do_action( 'tsbifw_register_settings' );
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

		$exclude_below_percent = (int) get_option( 'tsbifw_exclude_below_percent', 40 );

		$scanning_effect = get_option( 'tsbifw_scanning_effect', 'laser' );
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
			</h2>

			<div class="tsbifw-admin-columns">
				<div class="tsbifw-main-column">
					<div class="tsbifw-tab-content">
						<?php if ( 'general' === $active_tab ) : ?>
							<form method="post" action="options.php">
								<?php
								settings_fields( 'tsbifw_settings_group' );
								do_settings_sections( 'tsbifw_settings_group' );
								do_action( 'tsbifw_admin_after_settings_fields', 'general' );

								$default_gateways    = array(
									'openrouter' => esc_html__( 'OpenRouter', 'searchips-search-by-image-for-woocommerce' ),
								);
								$gateways            = apply_filters( 'tsbifw_api_gateways', $default_gateways );
								$active_gateway      = TSBIFW_API::instance()->get_active_gateway();
								if ( ! isset( $gateways[ $active_gateway ] ) ) {
									$active_gateway = 'openrouter';
								}
								$auto_index_on_save   = get_option( 'tsbifw_auto_index_on_save', 'no' );
								$enable_media_column  = get_option( 'tsbifw_enable_media_column', 'yes' );
								$embeddings_model     = get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' );
								$enable_auto_inject   = get_option( 'tsbifw_enable_auto_inject', 'yes' );
								$index_featured       = get_option( 'tsbifw_index_featured', 'yes' );
								$index_gallery        = get_option( 'tsbifw_index_gallery', 'no' );
								?>
								<table class="form-table">
									<tr>
										<th scope="row"><label for="tsbifw_api_gateway"><?php esc_html_e( 'AI Provider Gateway', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<?php if ( count( $gateways ) > 1 ) : ?>
												<select name="tsbifw_api_gateway" id="tsbifw_api_gateway">
													<?php foreach ( $gateways as $g_key => $g_label ) : ?>
														<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( $active_gateway, $g_key ); ?>>
															<?php echo esc_html( $g_label ); ?>
														</option>
													<?php endforeach; ?>
												</select>
											<?php else : ?>
												<span class="tsbifw-gateway-badge" style="display: inline-block; padding: 5px 12px; background: #e2e8f0; color: #1e293b; font-weight: 600; font-size: 13px; border-radius: 4px; border: 1px solid #cbd5e1;">
													<?php esc_html_e( 'OpenRouter API', 'searchips-search-by-image-for-woocommerce' ); ?>
												</span>
												<input type="hidden" name="tsbifw_api_gateway" id="tsbifw_api_gateway" value="<?php echo esc_attr( $active_gateway ); ?>" />
											<?php endif; ?>
											<p class="description"><?php esc_html_e( 'Visual search embeddings are powered via OpenRouter.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_api_key"><?php esc_html_e( 'OpenRouter API Key', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
												<input type="password" name="tsbifw_api_key" id="tsbifw_api_key" value="<?php echo esc_attr( $openrouter_key ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
												<button type="button" class="button-link tsbifw-toggle-pw" data-target="tsbifw_api_key" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description"><?php esc_html_e( 'Enter your OpenRouter API key to communicate with embeddings models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><?php esc_html_e( 'Search Engine', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<p style="margin: 0 0 6px 0; font-weight: 600; color: #1e293b;">
												<?php esc_html_e( 'Multimodal Vector Embeddings', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
											<p class="description">
												<?php esc_html_e( 'Visual search is powered by native multimodal vector embeddings. Product images are mapped into high-dimensional vector representations for instant, precise similarity matching.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>
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
											</fieldset>
											<p class="description">
												<?php esc_html_e( 'Select which images will be processed and indexed by the AI models.', 'searchips-search-by-image-for-woocommerce' ); ?>
											</p>

										</td>
									</tr>

									<tr>
										<th scope="row"><?php esc_html_e( 'Auto-Index on Save', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<label for="tsbifw_auto_index_on_save">
												<input type="checkbox" name="tsbifw_auto_index_on_save" id="tsbifw_auto_index_on_save" value="yes" <?php checked( $auto_index_on_save, 'yes' ); ?> />
												<?php esc_html_e( 'Automatically index new products or image updates on publish/save', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
											<p class="description"><?php esc_html_e( 'Immediately generates embeddings when a product is saved or updated.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

										</td>
									</tr>

									<tr>
										<th scope="row"><?php esc_html_e( 'Media Library Integration', 'searchips-search-by-image-for-woocommerce' ); ?></th>
										<td>
											<label for="tsbifw_enable_media_column">
												<input type="checkbox" name="tsbifw_enable_media_column" id="tsbifw_enable_media_column" value="yes" <?php checked( $enable_media_column, 'yes' ); ?> />
												<?php esc_html_e( 'Display Indexed Status column and filters in Media Library', 'searchips-search-by-image-for-woocommerce' ); ?>
											</label>
											<p class="description"><?php esc_html_e( 'Shows an "Indexed Status" badge and filter dropdown in the WordPress Media Library list view. Disable to reduce database queries on stores with massive media collections.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_embeddings_model"><?php esc_html_e( 'Embeddings Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-embeddings-model-skeleton"></div>
											<select name="tsbifw_embeddings_model" id="tsbifw_embeddings_model" style="display:none;" data-selected="<?php echo esc_attr( $embeddings_model ); ?>">
											</select>
											<p class="description" id="tsbifw-embeddings-model-desc"><?php esc_html_e( 'Select the embedding model ID for your chosen gateway.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_exclude_below_percent"><?php esc_html_e( 'Exclude Products Below Match Percentage', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="0" max="100" name="tsbifw_exclude_below_percent" id="tsbifw_exclude_below_percent" value="<?php echo esc_attr( $exclude_below_percent ); ?>" class="small-text" /> %
											<p class="description"><?php esc_html_e( 'Exclude products from search results if their similarity match falls below this percentage. Recommended: 35% - 50%.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

										</td>
									</tr>

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
												<?php
												$current_interval = get_option( 'tsbifw_cron_interval', 'tsbifw_every_5_minutes' );
												if ( 'every_minute' === $current_interval ) {
													$current_interval = 'tsbifw_every_minute';
												} elseif ( 'every_5_minutes' === $current_interval ) {
													$current_interval = 'tsbifw_every_5_minutes';
												} elseif ( 'every_15_minutes' === $current_interval ) {
													$current_interval = 'tsbifw_every_15_minutes';
												}
												?>
												<option value="tsbifw_every_minute" <?php selected( $current_interval, 'tsbifw_every_minute' ); ?>><?php esc_html_e( 'Every Minute', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_5_minutes" <?php selected( $current_interval, 'tsbifw_every_5_minutes' ); ?>><?php esc_html_e( 'Every 5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_15_minutes" <?php selected( $current_interval, 'tsbifw_every_15_minutes' ); ?>><?php esc_html_e( 'Every 15 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
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
											<span class="description" style="color: #7f1d1d;"><?php esc_html_e( 'If checked, all product vectors, search logs, and settings will be permanently deleted from the database when you delete this plugin.', 'searchips-search-by-image-for-woocommerce' ); ?></span>
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
								do_action( 'tsbifw_admin_after_settings_fields', 'styling' );
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

												<?php do_action( 'tsbifw_settings_styling_effects', $scanning_effect ); ?>
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
														<?php do_action( 'tsbifw_preview_stage_effects' ); ?>
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


								<div class="tsbifw-admin-search-box">
									<?php if ( empty( $api_key ) ) : ?>
										<p class="tsbifw-error-text inline-error"><?php esc_html_e( 'Please configure your OpenRouter API key on the General Settings tab before using the test search.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
									<?php else : ?>
										<div class="tsbifw-admin-search-controls" style="margin-bottom: 15px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; justify-content: flex-end;">
											<label for="tsbifw-admin-include-hidden" style="display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #475569; cursor: pointer;">
												<input type="checkbox" id="tsbifw-admin-include-hidden" value="1" />
												<span><?php esc_html_e( 'Include hidden catalog products (diagnostic)', 'searchips-search-by-image-for-woocommerce' ); ?></span>
											</label>
										</div>

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
													<?php do_action( 'tsbifw_preview_stage_effects' ); ?>
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
						<?php endif; ?>
					</div>
				</div>

				<div class="tsbifw-sidebar-column">
					<?php $this->render_sidebar_pro_upsell_card(); ?>
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
							</div>
						</div>

						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'Model Selection Help', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<p><strong><?php esc_html_e( 'Embeddings Model', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Used to convert product images into mathematical vectors. Gemini Embedding 2 is the recommended default.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
								<h4><?php esc_html_e( 'Search Engine Info', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<p><strong><?php esc_html_e( 'Multimodal Vector Embeddings', 'searchips-search-by-image-for-woocommerce' ); ?></strong><br />
								<?php esc_html_e( 'Encodes products and uploaded search photos into numerical vectors, calculating similarity using normalized dot-product algebra. Fast, accurate, and recommended.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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

		// Fallback models in case of network issues or empty responses.
		if ( empty( $embedding_models ) ) {
			$embedding_models = array(
				array( 'id' => 'google/gemini-embedding-2', 'name' => 'Google: Gemini Embedding 2' ),
			);
		}

		// Sort models alphabetically by name.
		$sort_models_by_name = function( $a, $b ) {
			$name_a = ! empty( $a['name'] ) ? $a['name'] : $a['id'];
			$name_b = ! empty( $b['name'] ) ? $b['name'] : $b['id'];
			return strcasecmp( $name_a, $name_b );
		};
		usort( $embedding_models, $sort_models_by_name );

		$response_data = apply_filters(
			'tsbifw_admin_available_models',
			array(
				'embedding_models' => $embedding_models,
			),
			$gateway,
			$api
		);

		wp_send_json_success( $response_data );
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

			$max_filter_ids = (int) apply_filters( 'tsbifw_max_media_filter_ids', 5000 );
			if ( $max_filter_ids > 0 && count( $indexed_ids ) > $max_filter_ids ) {
				$indexed_ids = array_slice( $indexed_ids, 0, $max_filter_ids );
			}

			if ( 'indexed' === $filter ) {
				if ( ! empty( $indexed_ids ) ) {
					$existing_in = (array) $query->get( 'post__in', array() );
					$merged_in   = ! empty( $existing_in ) ? array_values( array_intersect( $existing_in, $indexed_ids ) ) : $indexed_ids;
					$query->set( 'post__in', ! empty( $merged_in ) ? $merged_in : array( 0 ) );
				} else {
					$query->set( 'post__in', array( 0 ) ); // Force zero results
				}
			} elseif ( 'not_indexed' === $filter ) {
				if ( ! empty( $indexed_ids ) ) {
					$existing_not_in = (array) $query->get( 'post__not_in', array() );
					$merged_not_in   = ! empty( $existing_not_in ) ? array_values( array_unique( array_merge( $existing_not_in, $indexed_ids ) ) ) : $indexed_ids;
					$query->set( 'post__not_in', $merged_not_in );
				}
			}
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

		// Normalize legacy un-prefixed values to compliant tsbifw_ keys.
		$legacy_map = array(
			'every_minute'     => 'tsbifw_every_minute',
			'every_5_minutes'  => 'tsbifw_every_5_minutes',
			'every_15_minutes' => 'tsbifw_every_15_minutes',
		);
		if ( isset( $legacy_map[ $value ] ) ) {
			$value = $legacy_map[ $value ];
		}

		$valid = array(
			'tsbifw_every_minute',
			'tsbifw_every_5_minutes',
			'tsbifw_every_15_minutes',
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
	 * Render additional scanning effect selector cards in Styling tab.
	 *
	 * @param string $scanning_effect Current scanning effect key.
	 */
	public function render_styling_effects( $scanning_effect ) {
		$effects = array(
			'reticle'  => array(
				'title' => __( 'AI Vision Reticle', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Military-grade target locking brackets with crosshairs and tag metadata.', 'searchips-search-by-image-for-woocommerce' ),
				'icon'  => 'dashicons-visibility',
			),
			'radar'    => array(
				'title' => __( 'Sonar Radar Sweep', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Circular sonar sweep with expanding concentric rings and target dots.', 'searchips-search-by-image-for-woocommerce' ),
				'icon'  => 'dashicons-update',
			),
			'matrix'   => array(
				'title' => __( 'Digital Mesh Grid', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Tron-style 3D perspective grid wireframe with streaming coordinate dots.', 'searchips-search-by-image-for-woocommerce' ),
				'icon'  => 'dashicons-screenoptions',
			),
			'ripple'   => array(
				'title' => __( 'Concentric Ripple', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Smooth organic water-ripple wave pulse propagating from photo center.', 'searchips-search-by-image-for-woocommerce' ),
				'icon'  => 'dashicons-marker',
			),
			'hologram' => array(
				'title' => __( 'Luxury Hologram', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Iridescent chromatic prism light shimmer with diamond glint particles.', 'searchips-search-by-image-for-woocommerce' ),
				'icon'  => 'dashicons-star-filled',
			),
		);

		foreach ( $effects as $eff_key => $eff_data ) :
			$is_selected = ( $eff_key === $scanning_effect );
			?>
			<div class="tsbifw-effect-card <?php echo $is_selected ? 'active' : ''; ?>" data-effect="<?php echo esc_attr( $eff_key ); ?>">
				<input type="radio" name="tsbifw_scanning_effect" value="<?php echo esc_attr( $eff_key ); ?>" <?php checked( $scanning_effect, $eff_key ); ?> />
				<div class="tsbifw-effect-card-header">
					<span class="tsbifw-effect-icon dashicons <?php echo esc_attr( $eff_data['icon'] ); ?>"></span>
					<strong class="tsbifw-effect-title"><?php echo esc_html( $eff_data['title'] ); ?></strong>
				</div>
				<p class="tsbifw-effect-desc"><?php echo esc_html( $eff_data['desc'] ); ?></p>
			</div>
		<?php endforeach;
	}

	/**
	 * Render scanning animation effect layers for preview stages.
	 */
	public function render_preview_stage_effects() {
		?>
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
		<?php
	}

	/**
	 * Render the Pro Upgrade upsell card in the admin settings sidebar.
	 */
	public function render_sidebar_pro_upsell_card() {
		$buy_url = 'https://violo.ir/?p=707';
		?>
		<div class="tsbifw-sidebar-card tsbifw-pro-upsell-card">
			<div class="tsbifw-pro-upsell-header">
				<div class="tsbifw-pro-upsell-title">
					<svg class="tsbifw-pro-star-icon" width="18" height="18" viewBox="0 0 24 24" fill="#fbbf24" stroke="#f59e0b" stroke-width="1.5">
						<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
					</svg>
					<h4><?php esc_html_e( 'Upgrade to Pro', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
				</div>
				<span class="tsbifw-pro-badge-pill"><?php esc_html_e( 'PRO EDITION', 'searchips-search-by-image-for-woocommerce' ); ?></span>
			</div>
			<div class="tsbifw-pro-upsell-body">
				<p class="tsbifw-pro-upsell-intro">
					<?php esc_html_e( 'Unlock advanced enterprise tools to convert shoppers and maximize visual search performance:', 'searchips-search-by-image-for-woocommerce' ); ?>
				</p>
				<ul class="tsbifw-pro-features-list">
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M3 3v18h18"></path>
								<path d="m19 9-5 5-4-4-3 3"></path>
								<circle cx="19" cy="9" r="1.5" fill="currentColor"></circle>
								<circle cx="14" cy="14" r="1.5" fill="currentColor"></circle>
								<circle cx="10" cy="10" r="1.5" fill="currentColor"></circle>
								<circle cx="7" cy="13" r="1.5" fill="currentColor"></circle>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Visual Search Analytics:', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Track queries, CTR & zero-result demand', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<rect width="14" height="14" x="7" y="7" rx="2" ry="2"></rect>
								<path d="M4 17c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h11c1.1 0 2 .9 2 2"></path>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Variation Images Indexing:', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Match specific color & style variants', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
								<path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
								<path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"></path>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Smart Image Hashing (MD5):', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Skip unchanged images to slash API bills', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M17.5 19H9a7 7 0 1 1 6.71-9h1.79a4.5 4.5 0 1 1 0 9Z"></path>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Direct OpenAI & Gemini:', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Connect directly without middleware fees', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path>
								<circle cx="12" cy="13" r="3"></circle>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Mobile Camera Instant Capture:', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Let shoppers snap live photos on phones', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
					<li class="tsbifw-pro-feature-item">
						<span class="tsbifw-pro-feature-icon">
							<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
								<path d="m5 12 7-7 7 7"></path>
								<path d="M12 19V5"></path>
							</svg>
						</span>
						<div class="tsbifw-pro-feature-content">
							<strong><?php esc_html_e( 'Similarity Score Boost:', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
							<span><?php esc_html_e( 'Boost on-sale & featured products', 'searchips-search-by-image-for-woocommerce' ); ?></span>
						</div>
					</li>
				</ul>
				<a href="<?php echo esc_url( $buy_url ); ?>" target="_blank" rel="noopener noreferrer" class="tsbifw-pro-btn-primary">
					<?php esc_html_e( 'Upgrade to Pro', 'searchips-search-by-image-for-woocommerce' ); ?> &rarr;
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Register suggested privacy policy content for site owners.
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p><p>%s</p>',
			esc_html__( 'When visitors use the Search by Image feature, their uploaded image or photo is sent securely to OpenRouter API (https://openrouter.ai) to calculate a mathematical vector embedding for product similarity matching.', 'searchips-search-by-image-for-woocommerce' ),
			esc_html__( 'Uploaded search images are processed in transit and are not permanently retained or linked to personal account data by the search system.', 'searchips-search-by-image-for-woocommerce' )
		);

		wp_add_privacy_policy_content(
			__( 'Searchips Search By Image for WooCommerce', 'searchips-search-by-image-for-woocommerce' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}
}

