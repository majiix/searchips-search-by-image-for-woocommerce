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
	 * Snapshot of indexing-relevant settings taken before options.php saves.
	 *
	 * @var array|null
	 */
	private $pre_save_snapshot = null;

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
		add_action( 'wp_ajax_tsbifw_test_connection', array( $this, 'ajax_test_connection' ) );
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

		// Clear cached indexes when search strategy changes.
		add_action( 'update_option_tsbifw_strategy', array( $this, 'on_strategy_change' ), 10, 2 );
		add_action( 'admin_init', array( $this, 'capture_pre_save_snapshot' ), 20 );

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

		$api             = TSBIFW_API::instance();
		$gateway_configs = $api->get_gateway_configs();
		$gateway_caps    = array();
		foreach ( $gateway_configs as $slug => $cfg ) {
			$gateway_caps[ $slug ] = array(
				'supports_strategy_1' => ! empty( $cfg['supports_strategy_1'] ),
				'supports_strategy_2' => ! empty( $cfg['supports_strategy_2'] ),
				'embedding_protocol'  => isset( $cfg['embedding_protocol'] ) ? $cfg['embedding_protocol'] : '',
				'api_key_option'      => isset( $cfg['api_key_option'] ) ? $cfg['api_key_option'] : '',
			);
		}

		wp_localize_script(
			'tsbifw-admin-js',
			'tsbifw_admin_params',
			array(
				'ajax_url'        => admin_url( 'admin-ajax.php' ),
				'nonce'           => wp_create_nonce( 'tsbifw_admin_nonce' ),
				'wp_rest_nonce'   => wp_create_nonce( 'wp_rest' ),
				'search_endpoint' => esc_url_raw( rest_url( 'tsbifw/v1/search' ) ),
				'gateway_labels'  => apply_filters( 'tsbifw_admin_gateway_labels', $api->get_gateway_labels() ),
				'gateway_caps'    => $gateway_caps,
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
					'strategy_warning'     => esc_html__( 'Attention: You have changed the Search Strategy. You should Clear / Reset the index and perform a complete re-indexing for matches to work correctly.', 'searchips-search-by-image-for-woocommerce' ),
					'search_btn_text'      => esc_html__( 'Start Search', 'searchips-search-by-image-for-woocommerce' ),
					// translators: %d: Max upload size in MB
					'file_too_large'       => sprintf( esc_html__( 'Selected file is too large. Maximum allowed size is %dMB.', 'searchips-search-by-image-for-woocommerce' ), $max_mb ),
					'testing_conn'         => esc_html__( 'Testing...', 'searchips-search-by-image-for-woocommerce' ),
					'test_conn_label'      => esc_html__( 'Test Connection', 'searchips-search-by-image-for-woocommerce' ),
					'conn_network_err'     => esc_html__( 'Network error occurred while testing connection.', 'searchips-search-by-image-for-woocommerce' ),
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
		$gateway_configs = TSBIFW_API::instance()->get_gateway_configs();
		foreach ( $gateway_configs as $slug => $cfg ) {
			$opt_key = $cfg['api_key_option'];
			register_setting(
				'tsbifw_settings_group',
				$opt_key,
				array(
					'sanitize_callback' => ( 'openrouter' === $slug ) ? array( $this, 'sanitize_api_key' ) : 'sanitize_text_field',
				)
			);
		}
		register_setting( 'tsbifw_settings_group', 'tsbifw_custom_base_url', array(
			'sanitize_callback' => array( 'TSBIFW_API', 'sanitize_custom_base_url' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_custom_embeddings_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_custom_vision_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_strategy', array(
			'sanitize_callback' => array( $this, 'sanitize_strategy' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_vision_model', array(
			'sanitize_callback' => array( $this, 'sanitize_model_id' ),
		) );
		register_setting( 'tsbifw_settings_group', 'tsbifw_sync_to_tags', array(
			'sanitize_callback' => array( $this, 'sanitize_yes_no' ),
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
		$active_tab           = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'general';
		$api                  = TSBIFW_API::instance();
		$active_gateway       = $api->get_active_gateway();
		$gateway_labels       = $api->get_gateway_labels();
		$active_gateway_label = isset( $gateway_labels[ $active_gateway ] ) ? $gateway_labels[ $active_gateway ] : ucfirst( $active_gateway );
		$api_key              = $api->get_api_key( $active_gateway );
		$api_key_ready        = ! empty( $api_key ) && ( 'custom' !== $active_gateway || '' !== $api->get_custom_base_url() );

		$exclude_below_percent = (int) get_option( 'tsbifw_exclude_below_percent', 40 );

		$scanning_effect = get_option( 'tsbifw_scanning_effect', 'laser' );
		$scanning_color  = get_option( 'tsbifw_scanning_color', '#6366f1' );
		?>
		<div class="wrap tsbifw-admin-wrap">
			<h1><?php esc_html_e( 'WooCommerce Search by Image Settings', 'searchips-search-by-image-for-woocommerce' ); ?></h1>
			<?php settings_errors(); ?>
			<?php $this->render_reindex_required_notice( $active_tab ); ?>

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
				<a href="?page=tsbifw-settings&tab=help" class="nav-tab <?php echo 'help' === $active_tab ? 'nav-tab-active' : ''; ?>">
					<?php esc_html_e( 'Help & Setup Guide', 'searchips-search-by-image-for-woocommerce' ); ?>
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

								$api                  = TSBIFW_API::instance();
								$gateway_configs      = $api->get_gateway_configs();
								$gateways             = apply_filters( 'tsbifw_api_gateways', $api->get_gateway_labels() );
								$active_gateway       = $api->get_active_gateway();
								if ( ! isset( $gateways[ $active_gateway ] ) ) {
									$active_gateway = 'openrouter';
								}
								$active_config        = isset( $gateway_configs[ $active_gateway ] ) ? $gateway_configs[ $active_gateway ] : $gateway_configs['openrouter'];
								$default_strategies   = array(
									'embeddings' => esc_html__( 'Strategy 1: Multimodal Vector Embeddings (Recommended)', 'searchips-search-by-image-for-woocommerce' ),
								);
								$strategies           = apply_filters( 'tsbifw_search_strategies', $default_strategies );
								$strategy             = get_option( 'tsbifw_strategy', 'embeddings' );
								if ( ! isset( $strategies[ $strategy ] ) ) {
									$strategy = 'embeddings';
								}
								$auto_index_on_save   = get_option( 'tsbifw_auto_index_on_save', 'no' );
								$enable_media_column  = get_option( 'tsbifw_enable_media_column', 'yes' );
								$embeddings_model     = get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' );
								$vision_model         = get_option( 'tsbifw_vision_model', 'google/gemini-2.5-flash' );
								$sync_to_tags         = get_option( 'tsbifw_sync_to_tags', 'no' );
								$enable_auto_inject   = get_option( 'tsbifw_enable_auto_inject', 'yes' );
								$index_featured       = get_option( 'tsbifw_index_featured', 'yes' );
								$show_vision_for_v2v  = ( 'embeddings' === $strategy && isset( $active_config['embedding_protocol'] ) && 'openai_vision_to_vector' === $active_config['embedding_protocol'] );
								?>
								<table class="form-table">
									<tr>
										<th scope="row"><label for="tsbifw_api_gateway"><?php esc_html_e( 'AI Provider Gateway', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<?php $this->render_gateway_select( $gateway_configs, $gateways, $active_gateway ); ?>
											<p class="description"><?php esc_html_e( 'Select the AI provider to power image search and indexing.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<?php $this->render_gateway_credential_rows( $gateway_configs, $active_gateway ); ?>

									<tr>
										<th scope="row"><label for="tsbifw_strategy"><?php esc_html_e( 'Search Strategy', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<select name="tsbifw_strategy" id="tsbifw_strategy">
												<?php foreach ( $strategies as $s_key => $s_label ) : ?>
													<option value="<?php echo esc_attr( $s_key ); ?>" <?php selected( $strategy, $s_key ); ?>><?php echo esc_html( $s_label ); ?></option>
												<?php endforeach; ?>
											</select>
											<?php $this->render_strategy_guidance_notices( $strategy, $active_gateway, $gateway_configs ); ?>
											<p class="description">
												<?php esc_html_e( 'Select the underlying strategy for product indexing and searching.', 'searchips-search-by-image-for-woocommerce' ); ?>
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

									<tr class="tsbifw-strategy-field embeddings-field" style="<?php echo 'embeddings' === $strategy ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_embeddings_model"><?php esc_html_e( 'Embeddings Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-embeddings-model-skeleton"></div>
											<select name="tsbifw_embeddings_model" id="tsbifw_embeddings_model" style="display:none;" data-selected="<?php echo esc_attr( $embeddings_model ); ?>">
											</select>
											<p class="description" id="tsbifw-embeddings-model-desc"><?php esc_html_e( 'Select the embedding model ID for your chosen gateway.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field tsbifw-strategy-field-vision vision-field" style="<?php echo ( 'vision' === $strategy || $show_vision_for_v2v ) ? '' : 'display:none;'; ?>">
										<th scope="row"><label for="tsbifw_vision_model"><?php esc_html_e( 'Vision Model ID', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<div class="tsbifw-skeleton-loader" id="tsbifw-vision-model-skeleton"></div>
											<select name="tsbifw_vision_model" id="tsbifw_vision_model" style="display:none;" data-selected="<?php echo esc_attr( $vision_model ); ?>">
											</select>
											<p class="description" id="tsbifw-vision-model-desc"><?php esc_html_e( 'Select the vision model ID for your chosen gateway.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
										</td>
									</tr>

									<tr class="tsbifw-strategy-field tsbifw-strategy-field-vision vision-only-row" style="<?php echo 'vision' === $strategy ? '' : 'display:none;'; ?>">
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
										<th scope="row"><label for="tsbifw_search_cache_expiry"><?php esc_html_e( 'Search Cache Expiry', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
										<td>
											<?php
											$cache_expiry = (string) get_option( 'tsbifw_search_cache_expiry', '300' );
											if ( ! in_array( $cache_expiry, array( '300', '600' ), true ) ) {
												$cache_expiry = '300';
											}
											?>
											<select name="tsbifw_search_cache_expiry" id="tsbifw_search_cache_expiry">
												<option value="300" <?php selected( $cache_expiry, '300' ); ?>><?php esc_html_e( '5 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
												<option value="600" <?php selected( $cache_expiry, '600' ); ?>><?php esc_html_e( '10 Minutes', 'searchips-search-by-image-for-woocommerce' ); ?></option>
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
												if ( in_array( $current_interval, array( 'every_minute', 'tsbifw_every_minute', 'every_5_minutes' ), true ) ) {
													$current_interval = 'tsbifw_every_5_minutes';
												} elseif ( 'every_15_minutes' === $current_interval ) {
													$current_interval = 'tsbifw_every_15_minutes';
												}
												?>
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
								<p>
									<?php
									// translators: %s: Active AI gateway label.
									echo esc_html( sprintf( __( 'To enable image search, all WooCommerce products must have their featured images processed and indexed by %s.', 'searchips-search-by-image-for-woocommerce' ), $active_gateway_label ) );
									?>
								</p>

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
									<button type="button" class="button button-primary button-large" id="tsbifw-start-indexing" <?php disabled( $stats['processed'] === $stats['total'] || ! $api_key_ready ); ?>>
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

									<?php if ( ! $api_key_ready ) : ?>
										<p class="tsbifw-error-text inline-error">
											<?php
											// translators: %s: Active AI gateway label.
											echo esc_html( sprintf( __( 'Please configure your %s credentials on the General Settings tab before running the indexer.', 'searchips-search-by-image-for-woocommerce' ), $active_gateway_label ) );
											?>
										</p>
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
									<?php if ( ! $api_key_ready ) : ?>
										<p class="tsbifw-error-text inline-error">
											<?php
											// translators: %s: Active AI gateway label.
											echo esc_html( sprintf( __( 'Please configure your %s credentials on the General Settings tab before using the test search.', 'searchips-search-by-image-for-woocommerce' ), $active_gateway_label ) );
											?>
										</p>
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
						<?php elseif ( 'help' === $active_tab ) : ?>
							<?php $this->render_help_tab_content( $active_gateway ); ?>
						<?php endif; ?>
					</div>
				</div>

				<div class="tsbifw-sidebar-column">
					<?php $this->render_sidebar_pro_upsell_card(); ?>

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
								<?php esc_html_e( 'Generates textual descriptions of images on-the-fly and matches keywords using Jaccard similarity. Ideal for Vision-only providers like Groq or OpenAI.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
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

		$gateway = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		$api     = TSBIFW_API::instance();
		if ( empty( $gateway ) ) {
			$gateway = $api->get_active_gateway();
		}

		$embeddings_data  = $api->get_models( 'embeddings', $gateway );
		$embedding_models = is_wp_error( $embeddings_data ) ? array() : $api->filter_embedding_models( $embeddings_data, $gateway );

		$vision_data   = $api->get_models( '', $gateway );
		$vision_models = is_wp_error( $vision_data ) ? array() : $api->filter_vision_models( $vision_data, $gateway );

		$sort_models_by_name = function( $a, $b ) {
			$name_a = ! empty( $a['name'] ) ? $a['name'] : $a['id'];
			$name_b = ! empty( $b['name'] ) ? $b['name'] : $b['id'];
			return strcasecmp( $name_a, $name_b );
		};
		usort( $embedding_models, $sort_models_by_name );
		usort( $vision_models, $sort_models_by_name );

		$response_data = apply_filters(
			'tsbifw_admin_available_models',
			array(
				'embedding_models' => $embedding_models,
				'vision_models'    => $vision_models,
			),
			$gateway,
			$api
		);

		wp_send_json_success( $response_data );
	}

	/**
	 * AJAX endpoint to test connectivity and credentials for the selected AI provider.
	 */
	public function ajax_test_connection() {
		check_ajax_referer( 'tsbifw_admin_nonce', 'security' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( esc_html__( 'Unauthorized access.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$gateway = isset( $_POST['gateway'] ) ? sanitize_key( wp_unslash( $_POST['gateway'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$api_key = isset( $_POST['api_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) ) : null;
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$custom_base_url = isset( $_POST['custom_base_url'] ) ? sanitize_text_field( wp_unslash( $_POST['custom_base_url'] ) ) : null;

		$result = TSBIFW_API::instance()->test_connection( $gateway, $api_key, $custom_base_url );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	/**
	 * Keep the hourly/custom background indexing cron aligned with the enable option.
	 */
	public function check_cron_scheduling() {
		$enable_cron = get_option( 'tsbifw_enable_cron_indexing', 'no' );
		$interval    = $this->sanitize_cron_interval( get_option( 'tsbifw_cron_interval', 'tsbifw_every_5_minutes' ) );
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
	 * Automatically schedule a background cron event to clear all indexes when strategy changes.
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
	 * Capture current indexing-relevant settings before options.php processes a save.
	 */
	public function capture_pre_save_snapshot() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$option_page = isset( $_POST['option_page'] ) ? sanitize_key( wp_unslash( $_POST['option_page'] ) ) : '';
		if ( 'tsbifw_settings_group' !== $option_page || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$this->pre_save_snapshot = self::get_reindex_config_snapshot();
		add_filter( 'wp_redirect', array( $this, 'on_settings_save_redirect' ), 10, 1 );
	}

	/**
	 * Evaluate whether saved General Settings invalidate the current index before redirecting.
	 *
	 * @param string $location Redirect URL.
	 * @return string
	 */
	public function on_settings_save_redirect( $location ) {
		if ( null === $this->pre_save_snapshot ) {
			return $location;
		}

		$before                  = $this->pre_save_snapshot;
		$this->pre_save_snapshot = null;
		$after                   = self::get_reindex_config_snapshot();

		wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );
		delete_transient( 'tsbifw_indexing_stats' );
		$stats         = $this->get_indexing_stats();
		$indexed_count = isset( $stats['indexed'] ) ? (int) $stats['indexed'] : 0;

		$reason = self::evaluate_reindex_reason( $before, $after, $indexed_count );
		if ( '' !== $reason ) {
			update_option(
				'tsbifw_reindex_required',
				array(
					'reason'     => $reason,
					'updated_at' => time(),
				),
				false
			);
		}

		return $location;
	}

	/**
	 * Return a snapshot of settings that affect vector compatibility.
	 *
	 * @return array
	 */
	public static function get_reindex_config_snapshot() {
		return array(
			'gateway'                 => TSBIFW_API::instance()->get_active_gateway(),
			'strategy'                => get_option( 'tsbifw_strategy', 'embeddings' ),
			'embeddings_model'        => get_option( 'tsbifw_embeddings_model', 'google/gemini-embedding-2' ),
			'vision_model'            => get_option( 'tsbifw_vision_model', 'google/gemini-2.5-flash' ),
			'custom_base_url'         => get_option( 'tsbifw_custom_base_url', '' ),
			'custom_embeddings_model' => get_option( 'tsbifw_custom_embeddings_model', '' ),
			'custom_vision_model'     => get_option( 'tsbifw_custom_vision_model', '' ),
		);
	}

	/**
	 * Determine whether changing from $before to $after requires manual Clear & Re-index.
	 *
	 * Strategy switches use on_strategy_change() background cron, and Strategy 2 stays compatible
	 * without re-indexing, so neither returns a manual warning reason.
	 *
	 * @param array $before        Snapshot prior to save.
	 * @param array $after         Snapshot after save.
	 * @param int   $indexed_count Count of currently indexed products.
	 * @return string Reason key ('gateway', 'custom_base_url', 'embeddings_model', 'vision_model') or ''.
	 */
	public static function evaluate_reindex_reason( $before, $after, $indexed_count ) {
		if ( (int) $indexed_count <= 0 ) {
			return '';
		}

		$before_strategy = isset( $before['strategy'] ) ? $before['strategy'] : 'embeddings';
		$after_strategy  = isset( $after['strategy'] ) ? $after['strategy'] : 'embeddings';

		if ( $before_strategy !== $after_strategy || 'vision' === $after_strategy ) {
			return '';
		}

		$before_gw = isset( $before['gateway'] ) ? $before['gateway'] : 'openrouter';
		$after_gw  = isset( $after['gateway'] ) ? $after['gateway'] : 'openrouter';
		if ( $before_gw !== $after_gw ) {
			return 'gateway';
		}

		if ( 'custom' === $after_gw ) {
			$before_url = isset( $before['custom_base_url'] ) ? trim( (string) $before['custom_base_url'] ) : '';
			$after_url  = isset( $after['custom_base_url'] ) ? trim( (string) $after['custom_base_url'] ) : '';
			if ( $before_url !== $after_url ) {
				return 'custom_base_url';
			}
		}

		$before_emb = self::resolve_snapshot_model( $before, $before_gw, 'embeddings' );
		$after_emb  = self::resolve_snapshot_model( $after, $after_gw, 'embeddings' );
		if ( $before_emb !== $after_emb ) {
			return 'embeddings_model';
		}

		$configs  = TSBIFW_API::instance()->get_gateway_configs();
		$protocol = isset( $configs[ $after_gw ]['embedding_protocol'] ) ? $configs[ $after_gw ]['embedding_protocol'] : '';
		if ( 'openai_vision_to_vector' === $protocol ) {
			$before_vis = self::resolve_snapshot_model( $before, $before_gw, 'vision' );
			$after_vis  = self::resolve_snapshot_model( $after, $after_gw, 'vision' );
			if ( $before_vis !== $after_vis ) {
				return 'vision_model';
			}
		}

		return '';
	}

	private static function resolve_snapshot_model( $snapshot, $gateway, $kind ) {
		$field        = ( 'embeddings' === $kind ) ? 'embeddings_model' : 'vision_model';
		$custom_field = ( 'embeddings' === $kind ) ? 'custom_embeddings_model' : 'custom_vision_model';

		if ( 'custom' === $gateway && ! empty( $snapshot[ $custom_field ] ) ) {
			return trim( (string) $snapshot[ $custom_field ] );
		}

		return isset( $snapshot[ $field ] ) ? trim( (string) $snapshot[ $field ] ) : '';
	}

	/**
	 * Render a non-dismissible notice across settings tabs when the index must be cleared and rebuilt.
	 *
	 * @param string $active_tab Current active settings tab slug.
	 */
	private function render_reindex_required_notice( $active_tab ) {
		$flag = get_option( 'tsbifw_reindex_required', false );
		if ( empty( $flag ) ) {
			return;
		}

		$stats = $this->get_indexing_stats();
		if ( empty( $stats['indexed'] ) ) {
			delete_option( 'tsbifw_reindex_required' );
			return;
		}

		$reason   = is_array( $flag ) && isset( $flag['reason'] ) ? $flag['reason'] : 'embeddings_model';
		$messages = array(
			'gateway'          => __( 'AI Provider changed: Your existing product image index was created with another provider and will not match queries accurately. Go to the Product Indexer tab, click "Clear / Reset Index", and re-index your products.', 'searchips-search-by-image-for-woocommerce' ),
			'custom_base_url'  => __( 'Custom Endpoint URL changed: Your existing product image index was generated against a different endpoint. Go to the Product Indexer tab, click "Clear / Reset Index", and re-index your products.', 'searchips-search-by-image-for-woocommerce' ),
			'embeddings_model' => __( 'Embeddings Model changed: Your existing product index uses vectors from another model (mismatched vectors return 0% match or inaccurate results). Please click "Clear / Reset Index" and re-index your catalog.', 'searchips-search-by-image-for-woocommerce' ),
			'vision_model'     => __( 'Step 1 Vision Model changed: Because this provider uses a 2-step Vision-to-Vector pipeline, changing the vision model changes generated vectors. Please click "Clear / Reset Index" and re-index your catalog.', 'searchips-search-by-image-for-woocommerce' ),
		);
		$message = isset( $messages[ $reason ] ) ? $messages[ $reason ] : $messages['embeddings_model'];
		?>
		<div id="tsbifw-reindex-required-notice" class="notice notice-warning tsbifw-persistent-notice" style="padding: 12px 15px; margin: 12px 0;">
			<p style="margin: 0 0 6px 0;">
				<strong><?php esc_html_e( 'Action Required: Re-index Product Catalog', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
			</p>
			<p style="margin: 0;"><?php echo esc_html( $message ); ?></p>
			<?php if ( 'indexer' !== $active_tab ) : ?>
				<p style="margin: 10px 0 0 0;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=tsbifw-settings&tab=indexer' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Go to Product Indexer', 'searchips-search-by-image-for-woocommerce' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

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
	 * Sanitize API Key.
	 *
	 * @param string $value Input value.
	 * @return string
	 */
	public function sanitize_api_key( $value ) {
		$value = sanitize_text_field( $value );
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$submitted_gw = isset( $_POST['tsbifw_api_gateway'] ) ? sanitize_key( wp_unslash( $_POST['tsbifw_api_gateway'] ) ) : TSBIFW_API::instance()->get_active_gateway();
		if ( empty( $value ) && 'openrouter' === $submitted_gw ) {
			add_settings_error(
				'tsbifw_api_key',
				'tsbifw_api_key_empty',
				esc_html__( 'OpenRouter API Key is required when OpenRouter is selected.', 'searchips-search-by-image-for-woocommerce' ),
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
		$allowed = array( 300, 600 );
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
		$allowed = apply_filters( 'tsbifw_allowed_gateways', array_keys( TSBIFW_API::instance()->get_gateway_configs() ) );
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
			'every_5_minutes'  => 'tsbifw_every_5_minutes',
			'every_15_minutes' => 'tsbifw_every_15_minutes',
		);
		if ( isset( $legacy_map[ $value ] ) ) {
			$value = $legacy_map[ $value ];
		}

		$valid = array(
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
		$buy_url  = 'https://violo.ir/?p=707';
		$features = array(
			array(
				'title' => __( 'Visual Search Analytics:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Track image queries, click-through rate (CTR), & zero-result demand', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<path d="M3 3v18h18"></path><path d="m19 9-5 5-4-4-3 3"></path><circle cx="19" cy="9" r="1.5" fill="currentColor"></circle><circle cx="14" cy="14" r="1.5" fill="currentColor"></circle><circle cx="10" cy="10" r="1.5" fill="currentColor"></circle><circle cx="7" cy="13" r="1.5" fill="currentColor"></circle>',
			),
			array(
				'title' => __( 'Product Gallery Images:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Index every product gallery photo for accurate multi-angle matching', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<rect x="3" y="5" width="14" height="14" rx="2"></rect><path d="M21 9v10a2 2 0 0 1-2 2H9"></path><circle cx="8.5" cy="10.5" r="1.5"></circle><path d="m17 15-3.5-3.5L7 18"></path>',
			),
			array(
				'title' => __( 'Variation Images Indexing:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Match exact shopper photos to specific color & style variants', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<rect width="14" height="14" x="7" y="7" rx="2" ry="2"></rect><path d="M4 17c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h11c1.1 0 2 .9 2 2"></path>',
			),
			array(
				'title' => __( 'Smart Image Hashing (MD5):', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Skip unchanged product images on save to slash AI API costs', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<ellipse cx="12" cy="5" rx="9" ry="3"></ellipse><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path><path d="M3 12c0 1.66 4 3 9 3s9-1.34 9-3"></path>',
			),
			array(
				'title' => __( 'Excluded Categories Filter:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Exclude digital, service, or non-physical categories from indexing', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon>',
			),
			array(
				'title' => __( 'Mobile Camera Instant Capture:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Let shoppers snap live product photos right from their phones', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"></path><circle cx="12" cy="13" r="3"></circle>',
			),
			array(
				'title' => __( 'Similarity Score Boost:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Promote featured & on-sale products higher in visual results', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<path d="m5 12 7-7 7 7"></path><path d="M12 19V5"></path>',
			),
			array(
				'title' => __( '1-Minute Background Cron:', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Auto-index new & updated products every minute without delays', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline>',
			),
			array(
				'title' => __( 'Extended Search Cache (Up to 24h):', 'searchips-search-by-image-for-woocommerce' ),
				'desc'  => __( 'Cache results for 30m, 1h, 2h, or 24h to speed up repeat queries', 'searchips-search-by-image-for-woocommerce' ),
				'svg'   => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>',
			),
		);
		$allowed_svg = array(
			'path'     => array( 'd' => true ),
			'rect'     => array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'ry' => true ),
			'circle'   => array( 'cx' => true, 'cy' => true, 'r' => true, 'fill' => true ),
			'polygon'  => array( 'points' => true ),
			'polyline' => array( 'points' => true ),
			'ellipse'  => array( 'cx' => true, 'cy' => true, 'rx' => true, 'ry' => true ),
		);
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
					<?php esc_html_e( 'Unlock higher accuracy, lower API bills, and deeper store insights with Pro-exclusive features:', 'searchips-search-by-image-for-woocommerce' ); ?>
				</p>
				<ul class="tsbifw-pro-features-list">
					<?php foreach ( $features as $feature ) : ?>
						<li class="tsbifw-pro-feature-item">
							<span class="tsbifw-pro-feature-icon">
								<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
									<?php echo wp_kses( $feature['svg'], $allowed_svg ); ?>
								</svg>
							</span>
							<div class="tsbifw-pro-feature-content">
								<strong><?php echo esc_html( $feature['title'] ); ?></strong>
								<span><?php echo esc_html( $feature['desc'] ); ?></span>
							</div>
						</li>
					<?php endforeach; ?>
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

		$api     = TSBIFW_API::instance();
		$gateway = $api->get_active_gateway();
		$labels  = $api->get_gateway_labels();
		$gw_name = isset( $labels[ $gateway ] ) ? $labels[ $gateway ] : ucfirst( $gateway );

		$content = sprintf(
			'<p>%s</p><p>%s</p>',
			sprintf(
				// translators: %s: Active AI provider name.
				esc_html__( 'When visitors use the Search by Image feature, their uploaded image or photo is sent securely to the configured AI provider (%s) to process product similarity matching.', 'searchips-search-by-image-for-woocommerce' ),
				esc_html( $gw_name )
			),
			esc_html__( 'Uploaded search images are processed in transit and are not permanently retained or linked to personal account data by the search system.', 'searchips-search-by-image-for-woocommerce' )
		);

		wp_add_privacy_policy_content(
			__( 'Searchips Search By Image for WooCommerce', 'searchips-search-by-image-for-woocommerce' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	private function render_gateway_select( $gateway_configs, $gateways, $active_gateway ) {
		?>
		<div class="tsbifw-gateway-select-row" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
			<select name="tsbifw_api_gateway" id="tsbifw_api_gateway">
				<?php foreach ( $gateways as $g_key => $g_label ) : ?>
					<option value="<?php echo esc_attr( $g_key ); ?>" <?php selected( $active_gateway, $g_key ); ?>>
						<?php echo esc_html( $g_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="button" class="button button-secondary button-small" id="tsbifw-test-connection" style="display: inline-flex; align-items: center; gap: 4px; height: 30px;">
				<span class="dashicons dashicons-admin-plugins" style="font-size: 15px; width: 15px; height: 15px; line-height: 15px;"></span>
				<span><?php esc_html_e( 'Test Connection', 'searchips-search-by-image-for-woocommerce' ); ?></span>
			</button>
			<span id="tsbifw-test-connection-status" aria-live="polite" style="font-size: 12px; font-weight: 600;"></span>
		</div>
		<?php
	}

	private function render_gateway_credential_rows( $gateway_configs, $active_gateway ) {
		$descriptions = array(
			'openrouter' => __( 'Enter your OpenRouter API key.', 'searchips-search-by-image-for-woocommerce' ),
			'openai'     => __( 'Enter your private OpenAI API Key.', 'searchips-search-by-image-for-woocommerce' ),
			'gemini'     => __( 'Enter your Google AI Studio Gemini API Key.', 'searchips-search-by-image-for-woocommerce' ),
			'jina'       => __( 'Enter your Jina AI API key (includes 10M free tokens on signup).', 'searchips-search-by-image-for-woocommerce' ),
			'voyage'     => __( 'Enter your Voyage AI API key (includes 200M free tokens for new accounts).', 'searchips-search-by-image-for-woocommerce' ),
			'cohere'     => __( 'Enter your Cohere API key (supports multimodal Embed v4.0).', 'searchips-search-by-image-for-woocommerce' ),
			'groq'       => __( 'Enter your Groq API key (fast Llama Vision inference for Strategy 2).', 'searchips-search-by-image-for-woocommerce' ),
			'avalai'     => __( 'Enter your AvalAI API key (supports Gemini Embedding 2 & Vision chat).', 'searchips-search-by-image-for-woocommerce' ),
			'gapgpt'     => __( 'Enter your GapGPT API key.', 'searchips-search-by-image-for-woocommerce' ),
			'custom'     => __( 'Enter the API key for your custom endpoint (leave blank or use "ollama" for local Ollama).', 'searchips-search-by-image-for-woocommerce' ),
		);

		foreach ( $gateway_configs as $slug => $cfg ) :
			$opt_key = $cfg['api_key_option'];
			$val     = get_option( $opt_key, '' );
			$visible = ( $slug === $active_gateway );
			if ( 'custom' === $slug ) :
				$custom_base   = get_option( 'tsbifw_custom_base_url', '' );
				$custom_emb    = get_option( 'tsbifw_custom_embeddings_model', '' );
				$custom_vision = get_option( 'tsbifw_custom_vision_model', '' );
				?>
				<tr class="tsbifw-gateway-field gateway-custom" style="<?php echo $visible ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="tsbifw_custom_base_url"><?php esc_html_e( 'Custom Base URL', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
					<td>
						<input type="url" name="tsbifw_custom_base_url" id="tsbifw_custom_base_url" value="<?php echo esc_attr( $custom_base ); ?>" class="regular-text" placeholder="https://api.metisai.ir/v1" />
						<div style="margin-top:6px; display:flex; flex-wrap:wrap; gap:6px;">
							<button type="button" class="button button-small tsbifw-custom-preset" data-url="https://ai.liara.ir/api/v1" data-note="<?php esc_attr_e( 'Replace with your exact Base URL from the Liara AI console.', 'searchips-search-by-image-for-woocommerce' ); ?>"><?php esc_html_e( 'Liara AI', 'searchips-search-by-image-for-woocommerce' ); ?></button>
							<button type="button" class="button button-small tsbifw-custom-preset" data-url="https://api.metisai.ir/v1"><?php esc_html_e( 'MetisAI', 'searchips-search-by-image-for-woocommerce' ); ?></button>
							<button type="button" class="button button-small tsbifw-custom-preset" data-url="https://api.together.xyz/v1"><?php esc_html_e( 'Together AI', 'searchips-search-by-image-for-woocommerce' ); ?></button>
							<button type="button" class="button button-small tsbifw-custom-preset" data-url="http://localhost:11434/v1"><?php esc_html_e( 'Ollama (Local)', 'searchips-search-by-image-for-woocommerce' ); ?></button>
						</div>
						<p class="description" id="tsbifw-custom-url-note"><?php esc_html_e( 'OpenAI-compatible /v1 base URL (HTTPS required for remote servers; HTTP allowed for localhost).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
					</td>
				</tr>
				<tr class="tsbifw-gateway-field gateway-custom" style="<?php echo $visible ? '' : 'display:none;'; ?>">
					<th scope="row"><label for="tsbifw_custom_embeddings_model"><?php esc_html_e( 'Custom Model Overrides (Optional)', 'searchips-search-by-image-for-woocommerce' ); ?></label></th>
					<td>
						<input type="text" name="tsbifw_custom_embeddings_model" id="tsbifw_custom_embeddings_model" value="<?php echo esc_attr( $custom_emb ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Embeddings model (e.g. openai/text-embedding-3-small)', 'searchips-search-by-image-for-woocommerce' ); ?>" style="margin-bottom:6px; display:block;" />
						<input type="text" name="tsbifw_custom_vision_model" id="tsbifw_custom_vision_model" value="<?php echo esc_attr( $custom_vision ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Vision model (e.g. openai/gpt-4o-mini or llava)', 'searchips-search-by-image-for-woocommerce' ); ?>" />
						<p class="description"><?php esc_html_e( 'If set, overrides the dropdown model IDs above when using a custom OpenAI-compatible provider.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
					</td>
				</tr>
			<?php endif; ?>
			<tr class="tsbifw-gateway-field gateway-<?php echo esc_attr( $slug ); ?>" style="<?php echo $visible ? '' : 'display:none;'; ?>">
				<th scope="row">
					<label for="<?php echo esc_attr( $opt_key ); ?>">
						<?php
						// translators: %s: Gateway display label.
						echo esc_html( sprintf( __( '%s API Key', 'searchips-search-by-image-for-woocommerce' ), $cfg['label'] ) );
						?>
					</label>
				</th>
				<td>
					<div class="tsbifw-password-wrapper" style="position: relative; display: inline-block; max-width: 25em; width: 100%;">
						<input type="password" name="<?php echo esc_attr( $opt_key ); ?>" id="<?php echo esc_attr( $opt_key ); ?>" value="<?php echo esc_attr( $val ); ?>" class="regular-text" style="width: 100%; padding-right: 35px;" />
						<button type="button" class="button-link tsbifw-toggle-pw" data-target="<?php echo esc_attr( $opt_key ); ?>" style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; padding: 0; cursor: pointer; color: #72777c; outline: none; box-shadow: none; display: flex; align-items: center; justify-content: center;">
							<span class="dashicons dashicons-visibility"></span>
						</button>
					</div>
					<p class="description"><?php echo esc_html( isset( $descriptions[ $slug ] ) ? $descriptions[ $slug ] : '' ); ?></p>
				</td>
			</tr>
			<?php
		endforeach;
	}

	private function render_strategy_guidance_notices( $strategy, $gateway, $gateway_configs ) {
		$cfg             = isset( $gateway_configs[ $gateway ] ) ? $gateway_configs[ $gateway ] : array();
		$supports_s1     = ! empty( $cfg['supports_strategy_1'] );
		$supports_s2     = ! empty( $cfg['supports_strategy_2'] );
		$is_v2v          = ( isset( $cfg['embedding_protocol'] ) && 'openai_vision_to_vector' === $cfg['embedding_protocol'] );
		$show_s1_blocked = ( 'embeddings' === $strategy && ! $supports_s1 );
		$show_s2_blocked = ( 'vision' === $strategy && ! $supports_s2 );
		$show_v2v_info   = ( 'embeddings' === $strategy && $is_v2v );
		?>
		<div id="tsbifw-s1-unsupported-notice" style="<?php echo $show_s1_blocked ? '' : 'display:none;'; ?> margin-top:8px; padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #dc2626; border-radius:4px; font-size:12px; color:#991b1b; max-width:550px;">
			<span class="dashicons dashicons-warning" style="vertical-align:text-bottom; margin-right:4px;"></span>
			<?php esc_html_e( 'This provider does not offer an embeddings endpoint. Please switch to Strategy 2 (Vision-to-Text).', 'searchips-search-by-image-for-woocommerce' ); ?>
			<button type="button" class="button button-small tsbifw-quick-switch-strategy" data-strategy="vision" style="margin-left:8px; font-size:11px;"><?php esc_html_e( 'Switch to Strategy 2', 'searchips-search-by-image-for-woocommerce' ); ?></button>
		</div>
		<div id="tsbifw-s2-unsupported-notice" style="<?php echo $show_s2_blocked ? '' : 'display:none;'; ?> margin-top:8px; padding:8px 12px; background:#fef2f2; border:1px solid #fecaca; border-left:4px solid #dc2626; border-radius:4px; font-size:12px; color:#991b1b; max-width:550px;">
			<span class="dashicons dashicons-warning" style="vertical-align:text-bottom; margin-right:4px;"></span>
			<?php esc_html_e( 'This provider is multimodal embeddings-only and does not support Vision-to-Text chat. Please switch to Strategy 1.', 'searchips-search-by-image-for-woocommerce' ); ?>
			<button type="button" class="button button-small tsbifw-quick-switch-strategy" data-strategy="embeddings" style="margin-left:8px; font-size:11px;"><?php esc_html_e( 'Switch to Strategy 1', 'searchips-search-by-image-for-woocommerce' ); ?></button>
		</div>
		<div id="tsbifw-v2v-strategy-notice" style="<?php echo $show_v2v_info ? '' : 'display:none;'; ?> margin-top:8px; padding:8px 12px; background:#fffbeb; border:1px solid #fef3c7; border-left:4px solid #f59e0b; border-radius:4px; font-size:12px; color:#92400e; max-width:550px;">
			<span class="dashicons dashicons-info" style="vertical-align:text-bottom; margin-right:4px;"></span>
			<?php esc_html_e( 'Note: This gateway has no native image-embedding endpoint, so Strategy 1 runs a 2-step Vision-to-Vector pipeline (Vision model + Text Embedding model). Or switch to Strategy 2 for single-call Vision-to-Text.', 'searchips-search-by-image-for-woocommerce' ); ?>
			<button type="button" class="button button-small tsbifw-quick-switch-strategy" data-strategy="vision" style="margin-left:8px; font-size:11px;"><?php esc_html_e( 'Switch to Strategy 2', 'searchips-search-by-image-for-woocommerce' ); ?></button>
		</div>
		<?php
	}

	private function render_help_tab_content( $active_gateway ) {
		$guides = array(
			'openrouter' => array(
				'title'   => 'OpenRouter',
				'badge'   => __( 'Strategy 1 & 2', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://openrouter.ai/keys',
				'link'    => 'openrouter.ai/keys',
				'steps'   => array(
					__( 'Visit openrouter.ai and sign in or create an account.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Open Keys and click "Create Key", then copy the secret key.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "OpenRouter" in General Settings and paste your key.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "google/gemini-embedding-2" (Strategy 1) or "google/gemini-2.5-flash" (Strategy 2).', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'openai'     => array(
				'title'   => 'OpenAI Direct',
				'badge'   => __( 'Strategy 1 (2-Step) & Strategy 2', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://platform.openai.com/api-keys',
				'link'    => 'platform.openai.com/api-keys',
				'steps'   => array(
					__( 'Sign in to platform.openai.com and add billing credit if needed.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Navigate to API Keys, click "Create new secret key", and copy it.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "OpenAI Direct" and paste your key into the OpenAI API Key field.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Use Strategy 2 with "gpt-4o-mini" for single-call matching, or Strategy 1 for 2-step Vision-to-Vector ("gpt-4o-mini" + "text-embedding-3-small").', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'gemini'     => array(
				'title'   => 'Google Gemini Direct',
				'badge'   => __( 'Strategy 1 & 2 (Free Tier Available)', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://aistudio.google.com/app/apikey',
				'link'    => 'aistudio.google.com/app/apikey',
				'steps'   => array(
					__( 'Visit Google AI Studio and sign in with your Google account.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Click "Create API key" and select or create a Google Cloud project.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "Google Gemini Direct" and paste your API key.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "gemini-embedding-2" (Strategy 1) or "gemini-2.5-flash" (Strategy 2).', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'jina'       => array(
				'title'   => 'Jina AI',
				'badge'   => __( 'Strategy 1 Only (10M Free Tokens)', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://jina.ai/embeddings/',
				'link'    => 'jina.ai/embeddings',
				'steps'   => array(
					__( 'Open jina.ai/embeddings and create a free account (no credit card required for 10M free tokens).', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Copy your API key from the Jina AI dashboard.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "Jina AI" in General Settings with Strategy 1 (Multimodal Vector Embeddings).', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "jina-clip-v2" or "jina-embeddings-v5-omni-small".', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'voyage'     => array(
				'title'   => 'Voyage AI',
				'badge'   => __( 'Strategy 1 Only (200M Free Tokens)', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://dash.voyageai.com/',
				'link'    => 'dash.voyageai.com',
				'steps'   => array(
					__( 'Sign up at dash.voyageai.com to receive 200M free tokens.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Generate an API key from your Voyage AI console.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "Voyage AI" in General Settings with Strategy 1.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "voyage-multimodal-3.5".', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'cohere'     => array(
				'title'   => 'Cohere',
				'badge'   => __( 'Strategy 1 Only', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://dashboard.cohere.com/api-keys',
				'link'    => 'dashboard.cohere.com/api-keys',
				'steps'   => array(
					__( 'Log in at dashboard.cohere.com and open API Keys.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Copy a Trial key (1,000 calls/month) or Production key.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "Cohere" in General Settings with Strategy 1.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "embed-v4.0".', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'groq'       => array(
				'title'   => 'Groq',
				'badge'   => __( 'Strategy 2 Only (Free Tier Available)', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://console.groq.com/keys',
				'link'    => 'console.groq.com/keys',
				'steps'   => array(
					__( 'Sign in at console.groq.com and open API Keys.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Click "Create API Key" and paste it into the Groq API Key field.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Set Search Strategy to "Strategy 2: Vision-to-Text Description Search" (Groq does not offer an embeddings endpoint).', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended model: "meta-llama/llama-4-scout-17b-16e-instruct".', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'avalai'     => array(
				'title'   => 'AvalAI',
				'badge'   => __( 'Strategy 1 & 2', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://avalai.ir/',
				'link'    => 'avalai.ir',
				'steps'   => array(
					__( 'Sign in to your AvalAI panel and top up your credit if needed.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Create an API key from your dashboard and paste it into the AvalAI API Key field.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'For Strategy 1, choose "gemini-embedding-2" (uses native multimodal :embedContent).', 'searchips-search-by-image-for-woocommerce' ),
					__( 'For Strategy 2, choose "gemini-2.5-flash", "gpt-4o-mini", or any vision model returned in the dropdown.', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'gapgpt'     => array(
				'title'   => 'GapGPT',
				'badge'   => __( 'Strategy 1 (2-Step) & Strategy 2', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://gapgpt.app/',
				'link'    => 'gapgpt.app',
				'steps'   => array(
					__( 'Sign in to gapgpt.app and create an API key in your developer panel.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Select "GapGPT" in General Settings and paste your API key.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Recommended: Strategy 2 with "gpt-4o-mini" or Strategy 1 (2-step Vision-to-Vector with "gpt-4o-mini" + "text-embedding-3-small").', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
			'custom'     => array(
				'title'   => 'Custom OpenAI-Compatible (Liara AI / MetisAI / Together AI / Ollama)',
				'badge'   => __( 'Strategy 1 (2-Step) & Strategy 2', 'searchips-search-by-image-for-woocommerce' ),
				'url'     => 'https://liara.ir/',
				'link'    => 'liara.ir / metisai.ir',
				'steps'   => array(
					__( 'Select "Custom OpenAI-Compatible" in General Settings.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'Click a preset button (Liara AI, MetisAI, Together AI, or Ollama) or enter your provider /v1 Base URL (HTTPS required for remote servers; HTTP allowed for localhost).', 'searchips-search-by-image-for-woocommerce' ),
					__( 'For Liara AI, copy the exact Base URL and API Key from your Liara AI service console.', 'searchips-search-by-image-for-woocommerce' ),
					__( 'For local Ollama, use http://localhost:11434/v1 (API key can be left blank) and enter your pulled vision/embedding model names in Custom Model Overrides.', 'searchips-search-by-image-for-woocommerce' ),
				),
			),
		);
		?>
		<div class="tsbifw-help-container">
			<h3><?php esc_html_e( 'AI Provider Setup Guide', 'searchips-search-by-image-for-woocommerce' ); ?></h3>
			<p class="description" style="margin-bottom: 20px; font-size: 13px;">
				<?php esc_html_e( 'Follow the setup instructions below for your chosen AI provider, then save your credentials under General Settings and index your products in the Product Indexer tab.', 'searchips-search-by-image-for-woocommerce' ); ?>
			</p>

			<div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
				<h4 style="margin: 0 0 12px 0; font-size: 14px; color: #0f172a;"><?php esc_html_e( 'Model Selection Help', 'searchips-search-by-image-for-woocommerce' ); ?></h4>
				<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 16px;">
					<div>
						<strong style="display: block; margin-bottom: 4px; color: #0f172a; font-size: 13px;"><?php esc_html_e( 'Embeddings Model', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
						<p style="margin: 0; color: #334155; font-size: 12.5px; line-height: 1.55;"><?php esc_html_e( 'Converts product images into numerical vectors (e.g. Gemini Embedding 2, Jina CLIP v2, Voyage Multimodal 3.5, Cohere Embed v4).', 'searchips-search-by-image-for-woocommerce' ); ?></p>
					</div>
					<div>
						<strong style="display: block; margin-bottom: 4px; color: #0f172a; font-size: 13px;"><?php esc_html_e( 'Vision Model', 'searchips-search-by-image-for-woocommerce' ); ?></strong>
						<p style="margin: 0; color: #334155; font-size: 12.5px; line-height: 1.55;"><?php esc_html_e( 'Used by Strategy 2 (and Step 1 of Vision-to-Vector gateways like OpenAI/GapGPT) to generate detailed visual keywords.', 'searchips-search-by-image-for-woocommerce' ); ?></p>
					</div>
				</div>
			</div>

			<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 16px;">
				<?php foreach ( $guides as $slug => $guide ) : ?>
					<?php $is_active = ( $slug === $active_gateway ); ?>
					<div style="background: #f8fafc; border: 1px solid <?php echo $is_active ? '#4f46e5' : '#e2e8f0'; ?>; border-radius: 8px; padding: 16px;">
						<div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 8px;">
							<h4 style="margin: 0; font-size: 14px; color: #0f172a;">
								<?php echo esc_html( $guide['title'] ); ?>
								<?php if ( $is_active ) : ?>
									<span style="display: inline-block; margin-left: 6px; padding: 2px 7px; font-size: 10px; font-weight: 700; background: #4f46e5; color: #fff; border-radius: 10px; vertical-align: middle;"><?php esc_html_e( 'ACTIVE', 'searchips-search-by-image-for-woocommerce' ); ?></span>
								<?php endif; ?>
							</h4>
						</div>
						<div style="margin-bottom: 10px; display: flex; flex-wrap: wrap; gap: 8px; align-items: center;">
							<span style="display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 600; background: #e0e7ff; color: #3730a3; border-radius: 4px;"><?php echo esc_html( $guide['badge'] ); ?></span>
							<a href="<?php echo esc_url( $guide['url'] ); ?>" target="_blank" rel="noopener noreferrer" style="font-size: 12px;"><?php echo esc_html( $guide['link'] ); ?> &rarr;</a>
						</div>
						<ol style="margin: 0; padding-left: 18px; color: #334155; font-size: 12.5px; line-height: 1.55;">
							<?php foreach ( $guide['steps'] as $step ) : ?>
								<li style="margin-bottom: 4px;"><?php echo esc_html( $step ); ?></li>
							<?php endforeach; ?>
						</ol>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}
}

