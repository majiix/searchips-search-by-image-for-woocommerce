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
				'confirm'         => esc_html__( 'Are you sure you want to clear all indexed vectors and descriptions? This cannot be undone.', 'searchips-search-by-image-for-woocommerce' ),
				'search_endpoint' => esc_url_raw( rest_url( 'tsbifw/v1/search' ) ),
				'max_upload_size' => $max_mb * 1024 * 1024,
				'strings'         => array(
					'scanning'         => esc_html__( 'Searching...', 'searchips-search-by-image-for-woocommerce' ),
					'no_results'       => esc_html__( 'No matching products found.', 'searchips-search-by-image-for-woocommerce' ),
					'error'            => esc_html__( 'Search failed. Please try again.', 'searchips-search-by-image-for-woocommerce' ),
					'view_product'     => esc_html__( 'View Product', 'searchips-search-by-image-for-woocommerce' ),
					'add_to_cart'      => esc_html__( 'Add to Cart', 'searchips-search-by-image-for-woocommerce' ),
					'similarity_label' => esc_html__( 'Match:', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'drag_drop_text'   => sprintf( esc_html__( 'Drag and drop an image here or click to browse (Max size: %dMB)', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
					'strategy_warning' => esc_html__( 'Attention: You have changed the Search Strategy. You should Clear / Reset the index and perform a complete re-indexing for matches to work correctly.', 'searchips-search-by-image-for-woocommerce' ),
					'search_btn_text'  => esc_html__( 'Start Search', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'file_too_large'   => sprintf( esc_html__( 'Selected file is too large. Maximum allowed size is %dMB.', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
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
		register_setting( 'tsbifw_settings_group', 'tsbifw_results_limit', array(
			'sanitize_callback' => array( $this, 'sanitize_results_limit' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_search_cache_expiry', array(
			'sanitize_callback' => 'absint',
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
		register_setting( 'tsbifw_settings_group', 'tsbifw_index_featured', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_index_gallery', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_logging', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_log_retention', array(
			'sanitize_callback' => 'absint',
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_enable_cron_indexing', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_cron_interval', array(
			'sanitize_callback' => array( $this, 'sanitize_cron_interval' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_cron_batch_size', array(
			'sanitize_callback' => array( $this, 'sanitize_cron_batch_size' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_delete_data_on_uninstall', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_max_upload_size', array(
			'sanitize_callback' => array( $this, 'sanitize_max_upload_size' ),
		) );
	}

	/**
	 * Render settings HTML dashboard.
	 */
	public function render_settings_page() {
		$this->check_cron_scheduling();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$api_key    = get_option( 'tsbifw_api_key', '' );

		$exclude_below_percent = get_option( 'tsbifw_exclude_below_percent', '' );
		if ( '' === $exclude_below_percent ) {
			$similarity_threshold = get_option( 'tsbifw_similarity_threshold', '0.40' );
			$exclude_below_percent = round( (float) $similarity_threshold * 100 );
		}
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
					<?php esc_html_e( 'Logs', 'searchips-search-by-image-for-woocommerce' ); ?>
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

								$strategy             = get_option( 'tsbifw_strategy', 'embeddings' );
								$embeddings_model     = get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' );
								$vision_model         = get_option( 'tsbifw_vision_model', 'google/gemini-2.5-flash' );
								$sync_to_tags         = get_option( 'tsbifw_sync_to_tags', 'no' );
								$results_limit        = get_option( 'tsbifw_results_limit', '12' );
								$enable_auto_inject   = get_option( 'tsbifw_enable_auto_inject', 'yes' );
								$index_featured       = get_option( 'tsbifw_index_featured', 'yes' );
								$index_gallery        = get_option( 'tsbifw_index_gallery', 'no' );
								?>
								<table class="form-table">
									<tr>
										<th scope="row"><label for="tsbifw_api_key"><?php esc_html_e( 'OpenRouter API Key', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
												<input type="password" name="tsbifw_api_key" id="tsbifw_api_key" value="<?php echo esc_attr( $api_key ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
												<button type="button" id="tsbifw-toggle-api-key" class="button-link" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
													<span class="dashicons dashicons-visibility"></span>
												</button>
											</div>
											<p class="description"><?php esc_html_e( 'Enter your OpenRouter API key to communicate with embeddings and vision models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_strategy"><?php esc_html_e( 'Search Strategy', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_strategy" id="tsbifw_strategy">
												<option value="embeddings" <?php selected( $strategy, 'embeddings' ); ?>><?php esc_html_e( 'Strategy 1: Multimodal Vector Embeddings (Recommended)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="vision" <?php selected( $strategy, 'vision' ); ?>><?php esc_html_e( 'Strategy 2: Vision-to-Text Description Search', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Select the underlying strategy for product indexing and searching.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
											<p class="description"><?php esc_html_e( 'Select which images will be processed and indexed by the OpenRouter models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field embeddings-field" style="<?php echo 'embeddings' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_embeddings_model"><?php esc_html_e( 'Embeddings Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-embeddings-model-skeleton"></div>
											<select name="tsbifw_embeddings_model" id="tsbifw_embeddings_model" style="display:none;" data-selected="<?php echo esc_attr( $embeddings_model ); ?>">
											</select>
											<p class="description"><?php esc_html_e( 'Select the multimodal embedding model ID from OpenRouter.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field vision-field" style="<?php echo 'vision' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_vision_model"><?php esc_html_e( 'Vision Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-vision-model-skeleton"></div>
											<select name="tsbifw_vision_model" id="tsbifw_vision_model" style="display:none;" data-selected="<?php echo esc_attr( $vision_model ); ?>">
											</select>
											<p class="description"><?php esc_html_e( 'Select the vision chat completion model ID from OpenRouter.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
										<th scope="row"><label for="tsbifw_results_limit"><?php esc_html_e( 'Search Results Limit', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="1" max="100" name="tsbifw_results_limit" id="tsbifw_results_limit" value="<?php echo esc_attr( $results_limit ); ?>" class="small-text" />
											<p class="description"><?php esc_html_e( 'Maximum number of products to show in frontend search results.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_search_cache_expiry"><?php esc_html_e( 'Search Cache Expiry', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<?php $cache_expiry = get_option( 'tsbifw_search_cache_expiry', '600' ); ?>
											<select name="tsbifw_search_cache_expiry" id="tsbifw_search_cache_expiry">
												<option value="300" <?php selected( $cache_expiry, '300' ); ?>><?php esc_html_e( '5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="600" <?php selected( $cache_expiry, '600' ); ?>><?php esc_html_e( '10 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="1800" <?php selected( $cache_expiry, '1800' ); ?>><?php esc_html_e( '30 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="3600" <?php selected( $cache_expiry, '3600' ); ?>><?php esc_html_e( '1 Hour', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="7200" <?php selected( $cache_expiry, '7200' ); ?>><?php esc_html_e( '2 Hours', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="86400" <?php selected( $cache_expiry, '86400' ); ?>><?php esc_html_e( '24 Hours', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Choose how long the visual search queries and matching product IDs are stored temporarily on the server.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
										<th scope="row"><label for="tsbifw_enable_logging"><?php esc_html_e( 'Enable Logging?', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="checkbox" name="tsbifw_enable_logging" id="tsbifw_enable_logging" value="yes" <?php checked( get_option( 'tsbifw_enable_logging', 'yes' ), 'yes' ); ?> />
											<p class="description"><?php esc_html_e( 'If enabled, settings updates, API calls, requests, and search queries will be saved to the database.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr>
										<th scope="row"><label for="tsbifw_log_retention"><?php esc_html_e( 'Log Retention Period', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_log_retention" id="tsbifw_log_retention">
												<option value="1" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '1' ); ?>><?php esc_html_e( '1 Day', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="3" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '3' ); ?>><?php esc_html_e( '3 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="7" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '7' ); ?>><?php esc_html_e( '7 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="14" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '14' ); ?>><?php esc_html_e( '14 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="30" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '30' ); ?>><?php esc_html_e( '30 Days', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="0" <?php selected( get_option( 'tsbifw_log_retention', '7' ), '0' ); ?>><?php esc_html_e( 'Indefinitely (Keep all logs)', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Choose how long logs should be kept in the database before being automatically cleared.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
												<?php $current_interval = get_option( 'tsbifw_cron_interval', 'hourly' ); ?>
												<option value="tsbifw_every_minute" <?php selected( in_array( $current_interval, array( 'tsbifw_every_minute', 'every_minute' ), true ) ); ?>><?php esc_html_e( 'Every Minute', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_5_minutes" <?php selected( in_array( $current_interval, array( 'tsbifw_every_5_minutes', 'every_5_minutes' ), true ) ); ?>><?php esc_html_e( 'Every 5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="tsbifw_every_15_minutes" <?php selected( in_array( $current_interval, array( 'tsbifw_every_15_minutes', 'every_15_minutes' ), true ) ); ?>><?php esc_html_e( 'Every 15 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="hourly" <?php selected( $current_interval, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="twice_daily" <?php selected( $current_interval, 'twice_daily' ); ?>><?php esc_html_e( 'Twice Daily', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="daily" <?php selected( $current_interval, 'daily' ); ?>><?php esc_html_e( 'Daily', 'searchips-search-by-image-for-woocommerce' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Choose how frequently the background cron task should execute product indexing runs.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-cron-settings" style="<?php echo 'yes' === get_option( 'tsbifw_enable_cron_indexing', 'no' ) ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_cron_batch_size"><?php esc_html_e( 'Cron Batch Size', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<input type="number" min="1" max="100" name="tsbifw_cron_batch_size" id="tsbifw_cron_batch_size" value="<?php echo esc_attr( get_option( 'tsbifw_cron_batch_size', '5' ) ); ?>" class="small-text" />
											<p class="description"><?php esc_html_e( 'Number of products to process in each background interval (e.g. 2 products per minute). Keep this low to prevent CPU load.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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
								<?php submit_button(); ?>
							</form>
						<?php elseif ( 'indexer' === $active_tab ) : ?>
							<div class="tsbifw-indexer-container">
								<h3><?php esc_html_e( 'Product Indexer Status', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
								<p><?php esc_html_e( 'To enable image search, all WooCommerce products must have their featured images processed and indexed by the OpenRouter models.', 'searchips-search-by-image-for-woocommerce' ); ?></p>

								<?php
								$stats = $this->get_indexing_stats();
								?>
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
										<div class="tsbifw-drag-zone" id="tsbifw-admin-drag-zone">
											<svg class="tsbifw-drag-icon" viewBox="0 0 24 24" width="48" height="48" stroke="currentColor" stroke-width="2" fill="none" stroke-linecap="round" stroke-linejoin="round">
												<rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
												<circle cx="8.5" cy="8.5" r="1.5"></circle>
												<polyline points="21 15 16 10 5 21"></polyline>
											</svg>
											<p><?php esc_html_e( 'Drag and drop an image here or click to browse', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</div>
										<input type="file" id="tsbifw-admin-file-input" accept="image/jpeg,image/png,image/webp" style="display:none;" />

										<div class="tsbifw-preview-wrapper" id="tsbifw-admin-preview-wrapper" style="display:none;">
											<div class="tsbifw-cropper-container" style="max-height: 320px; overflow: hidden; border-radius: 8px; margin-bottom: 15px; border: 1px solid #ccd0d4;">
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
											</div>
											<div class="tsbifw-scanner-bar"></div>
											<div class="tsbifw-scanning-overlay"></div>
											<div class="tsbifw-preview-actions" style="margin-top: 15px; display: flex; gap: 10px; justify-content: center; align-items: center;">
												<button type="button" class="button button-primary" id="tsbifw-admin-search-btn"><?php esc_html_e( 'Start Search', 'searchips-search-by-image-for-woocommerce' ); ?></button>
												<button type="button" class="button button-secondary" id="tsbifw-admin-reselect-btn"><?php esc_html_e( 'Select Another', 'searchips-search-by-image-for-woocommerce' ); ?></button>
											</div>
										</div>

										<div class="tsbifw-search-status" id="tsbifw-admin-search-status" style="display:none;"></div>
										<div class="tsbifw-results-grid" id="tsbifw-admin-results-grid" style="display:none;"></div>
									<?php endif; ?>
								</div>
							</div>
						<?php elseif ( 'logs' === $active_tab ) : ?>
							<div class="tsbifw-logs-container">
								<div class="tsbifw-logs-header" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
									<h3 style="margin:0;"><?php esc_html_e( 'System Logs', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
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
					<?php if ( 'general' === $active_tab ) : ?>
						<div class="tsbifw-sidebar-card">
							<div class="tsbifw-sidebar-card-header">
								<h4><?php esc_html_e( 'API Key Setup Guide', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
							</div>
							<div class="tsbifw-sidebar-card-body">
								<p><?php esc_html_e( 'Follow these steps to obtain your OpenRouter API Key:', 'searchips-search-by-image-for-woocommerce' ); ?></p>
								<ol style="margin: 0 0 12px 0; padding-left: 20px; list-style-type: decimal;">
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;">
										<?php
										// translators: %s is the link to the external website.
										$msg = sprintf( esc_html__( 'Visit the %s website.', 'searchips-search-by-image-for-woocommerce' ), '<a href="https://openrouter.ai/" target="_blank">OpenRouter</a>' );
										echo wp_kses_post( $msg );
										?>
									</li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;"><?php esc_html_e( 'Register or log in to your account.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;"><?php esc_html_e( 'Navigate to Keys section and click "Create Key".', 'searchips-search-by-image-for-woocommerce' ); ?></li>
									<li style="font-size: 12px; line-height: 1.5; color: #475569; margin-bottom: 6px;"><?php esc_html_e( 'Copy the generated key and paste it into the API Key setting input.', 'searchips-search-by-image-for-woocommerce' ); ?></li>
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
	private function get_indexing_stats() {
		global $wpdb;

		$cache_key = 'tsbifw_indexing_stats';
		$stats     = wp_cache_get( $cache_key, 'tsbifw_cache' );
		if ( false !== $stats ) {
			return $stats;
		}

		$stats = get_transient( $cache_key );
		if ( false !== $stats ) {
			wp_cache_set( $cache_key, $stats, 'tsbifw_cache', 60 );
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

		wp_cache_set( $cache_key, $stats, 'tsbifw_cache', 60 );
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

		// Find products that are not indexed or returned error.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 5, // Small batch to prevent php timeouts.
			'fields'         => 'ids',
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'meta_query'     => array(
				'relation' => 'OR',
				array(
					'key'     => '_tsbifw_indexed_status',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_tsbifw_indexed_status',
					'value'   => 'error',
					'compare' => '=',
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
			$result = $indexer->index_product( $id );

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

		$stats = $this->get_indexing_stats();
		wp_send_json_success(
			array(
				'completed' => false,
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

		$api = TSBIFW_API::instance();

		// Fetch embeddings models (using output_modalities=embeddings).
		$embeddings_data = $api->get_models( 'embeddings' );
		if ( is_wp_error( $embeddings_data ) ) {
			$embedding_models = array();
		} else {
			$embedding_models = $api->filter_embedding_models( $embeddings_data );
		}

		// Fetch text generation models and filter for vision support.
		$generation_data = $api->get_models();
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
		$interval    = get_option( 'tsbifw_cron_interval', 'hourly' );
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
			if ( 'tsbifw_api_key' === $option ) {
				$logged_old = empty( $old_value ) ? '' : '***' . substr( $old_value, -4 );
				$logged_new = empty( $value ) ? '' : '***' . substr( $value, -4 );
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
			if ( 'tsbifw_api_key' === $option ) {
				$logged_val = empty( $value ) ? '' : '***' . substr( $value, -4 );
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
			wp_schedule_single_event( time(), 'tsbifw_clear_index_cron' );

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
	 * Sanitize Results Limit.
	 *
	 * @param mixed $value Input value.
	 * @return int
	 */
	public function sanitize_results_limit( $value ) {
		$num = (int) $value;
		if ( $num <= 0 ) {
			add_settings_error(
				'tsbifw_results_limit',
				'tsbifw_results_limit_invalid',
				esc_html__( 'Search results limit must be a positive number.', 'searchips-search-by-image-for-woocommerce' ),
				'error'
			);
			return 12; // Fallback default.
		}
		return $num;
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
		if ( preg_match( '/^(?:rgb|rgba|hsl|hsla)\([^)]*\)$/i', $value ) ) {
			return $value;
		}
		if ( preg_match( '/^[a-z]+$/i', $value ) ) {
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
	public function sanitize_strategy( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, array( 'embeddings', 'vision' ), true ) ? $value : 'embeddings';
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
		return in_array( $value, $valid, true ) ? $value : 'hourly';
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
}
