<?php
/**
 * Indexing and Caching manager.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class TSBIFW_Indexer {

	/**
	 * Singleton instance.
	 *
	 * @var TSBIFW_Indexer|null
	 */
	private static $instance = null;

	/**
	 * Track indexed product IDs in the current request to avoid duplicate indexing runs.
	 *
	 * @var array
	 */
	private $indexed_products = array();

	/**
	 * Track indexed image attachment IDs map for O(1) hash lookups.
	 *
	 * @var array|null
	 */
	private $indexed_image_map = null;

	/**
	 * Get class instance.
	 *
	 * @return TSBIFW_Indexer
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
		add_action( 'before_delete_post', array( $this, 'on_product_delete' ), 10, 1 );

		// Metadata hooks to catch product image / gallery updates.
		add_action( 'added_post_meta', array( $this, 'on_meta_update' ), 10, 4 );
		add_action( 'updated_post_meta', array( $this, 'on_meta_update' ), 10, 4 );
		add_action( 'deleted_post_meta', array( $this, 'on_meta_delete' ), 10, 4 );

		// Cron background indexing hook.
		add_action( 'tsbifw_cron_indexing', array( $this, 'run_cron_indexing' ) );
		add_action( 'tsbifw_clear_index_cron', array( $this, 'clear_all_indexed_data' ) );

		// Product save hook for auto-indexing.
		add_action( 'save_post_product', array( $this, 'on_product_save' ), 20, 2 );

		// Custom schedules filter.
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Collect active target image attachments for a given product.
	 *
	 * @param WC_Product $product Product instance.
	 * @return array List of image descriptor arrays.
	 */
	public function get_product_target_images( $product ) {
		$index_featured = ( get_option( 'tsbifw_index_featured', 'yes' ) === 'yes' );
		$index_gallery  = ( get_option( 'tsbifw_index_gallery', 'no' ) === 'yes' );

		$featured_id = $product->get_image_id();
		$gallery_ids = $product->get_gallery_image_ids();

		$target_images = array();
		$tracked_ids   = array();

		if ( $index_featured && $featured_id ) {
			$target_images[] = array(
				'id'           => $featured_id,
				'type'         => 'featured',
				'variation_id' => 0,
			);
			$tracked_ids[]   = (int) $featured_id;
		}
		if ( $index_gallery && ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $gallery_id ) {
				$gid = (int) $gallery_id;
				if ( ! in_array( $gid, $tracked_ids, true ) ) {
					$target_images[] = array(
						'id'           => $gid,
						'type'         => 'gallery',
						'variation_id' => 0,
					);
					$tracked_ids[]   = $gid;
				}
			}
		}

		/**
		 * Filter active product target images before indexing.
		 *
		 * @param array      $target_images List of target image descriptors.
		 * @param WC_Product $product       Product instance.
		 */
		return apply_filters( 'tsbifw_product_target_images', $target_images, $product );
	}

	/**
	 * Indexes a single product using the active strategy.
	 *
	 * @param int  $product_id       Product ID.
	 * @param bool $force            Whether to force re-indexing even if image hash is unchanged.
	 * @param bool $skip_cache_clear Whether to defer cache invalidation (useful in batch loops).
	 * @return bool|WP_Error True if success, WP_Error if failure.
	 */
	public function index_product( $product_id, $force = false, $skip_cache_clear = false ) {
		if ( in_array( $product_id, $this->indexed_products, true ) ) {
			return true;
		}
		$this->indexed_products[] = $product_id;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'tsbifw_invalid_product', esc_html__( 'Invalid product ID.', 'searchips-search-by-image-for-woocommerce' ), array( 'status' => 400 ) );
		}

		$target_images = $this->get_product_target_images( $product );

		if ( empty( $target_images ) ) {
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
			delete_post_meta( $product_id, '_tsbifw_vectors' );
			delete_post_meta( $product_id, '_tsbifw_descriptions' );
			delete_post_meta( $product_id, '_tsbifw_images_hash' );
			if ( ! $skip_cache_clear ) {
				$this->clear_cache();
			}
			return true;
		}

		$should_skip = apply_filters( 'tsbifw_skip_product_indexing', false, $product_id, $target_images, $force );
		if ( $should_skip ) {
			if ( ! $skip_cache_clear ) {
				$this->clear_cache();
			}
			return true;
		}

		$strategy   = get_option( 'tsbifw_strategy', 'embeddings' );
		$strategies = apply_filters( 'tsbifw_search_strategies', array( 'embeddings' => esc_html__( 'Strategy 1: Image Embeddings', 'searchips-search-by-image-for-woocommerce' ) ) );
		if ( ! isset( $strategies[ $strategy ] ) ) {
			$strategy = 'embeddings';
		}
		$api = TSBIFW_API::instance();

		$vectors             = array();
		$descriptions        = array();
		$all_keywords        = array();
		$cached_vectors      = array();
		$cached_descriptions = array();

		foreach ( $target_images as $image ) {
			$img_id = (int) $image['id'];
			$var_id = isset( $image['variation_id'] ) ? (int) $image['variation_id'] : 0;

			if ( 'embeddings' === $strategy ) {
				if ( isset( $cached_vectors[ $img_id ] ) ) {
					$vector = $cached_vectors[ $img_id ];
				} else {
					$base64 = $api->prepare_image( $img_id );
					if ( is_wp_error( $base64 ) ) {
						if ( 'tsbifw_empty_image' === $base64->get_error_code() || 'tsbifw_file_not_found' === $base64->get_error_code() ) {
							TSBIFW_Logger::log( sprintf( 'Skipped image ID %d for product ID %d. Reason: %s', $img_id, $product_id, $base64->get_error_message() ) );
							continue;
						}
						update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
						update_post_meta( $product_id, '_tsbifw_index_error', $base64->get_error_message() );
						delete_post_meta( $product_id, '_tsbifw_images_hash' );
						return $base64;
					}
					$vector = $api->get_embeddings( $base64 );
					if ( is_wp_error( $vector ) ) {
						update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
						update_post_meta( $product_id, '_tsbifw_index_error', $vector->get_error_message() );
						delete_post_meta( $product_id, '_tsbifw_images_hash' );
						return $vector;
					}
					$cached_vectors[ $img_id ] = $vector;
				}

				$vectors[] = array(
					'id'           => $img_id,
					'type'         => $image['type'],
					'variation_id' => $var_id,
					'vector'       => $vector,
				);
			} else {
				if ( isset( $cached_descriptions[ $img_id ] ) ) {
					$description = $cached_descriptions[ $img_id ];
				} else {
					$base64 = $api->prepare_image( $img_id );
					if ( is_wp_error( $base64 ) ) {
						if ( 'tsbifw_empty_image' === $base64->get_error_code() || 'tsbifw_file_not_found' === $base64->get_error_code() ) {
							TSBIFW_Logger::log( sprintf( 'Skipped image ID %d for product ID %d. Reason: %s', $img_id, $product_id, $base64->get_error_message() ) );
							continue;
						}
						update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
						update_post_meta( $product_id, '_tsbifw_index_error', $base64->get_error_message() );
						delete_post_meta( $product_id, '_tsbifw_images_hash' );
						return $base64;
					}
					$description = $api->get_description( $base64 );
					if ( is_wp_error( $description ) ) {
						update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
						update_post_meta( $product_id, '_tsbifw_index_error', $description->get_error_message() );
						delete_post_meta( $product_id, '_tsbifw_images_hash' );
						return $description;
					}
					$cached_descriptions[ $img_id ] = $description;
					$all_keywords[]                 = $description;
				}

				$descriptions[] = array(
					'id'           => $img_id,
					'type'         => $image['type'],
					'variation_id' => $var_id,
					'description'  => $description,
				);
			}
		}

		if ( 'embeddings' === $strategy ) {
			if ( empty( $vectors ) ) {
				update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
				delete_post_meta( $product_id, '_tsbifw_vectors' );
				delete_post_meta( $product_id, '_tsbifw_descriptions' );
				delete_post_meta( $product_id, '_tsbifw_images_hash' );
				delete_post_meta( $product_id, '_tsbifw_index_error' );
				if ( ! $skip_cache_clear ) {
					$this->clear_cache();
				}
				return true;
			}
			update_post_meta( $product_id, '_tsbifw_vectors', $vectors );
			delete_post_meta( $product_id, '_tsbifw_descriptions' );
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'indexed' );
			delete_post_meta( $product_id, '_tsbifw_index_error' );
			if ( ! $skip_cache_clear ) {
				$this->clear_cache();
			}
		} else {
			if ( empty( $descriptions ) ) {
				update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
				delete_post_meta( $product_id, '_tsbifw_descriptions' );
				delete_post_meta( $product_id, '_tsbifw_vectors' );
				delete_post_meta( $product_id, '_tsbifw_images_hash' );
				delete_post_meta( $product_id, '_tsbifw_index_error' );
				if ( ! $skip_cache_clear ) {
					$this->clear_cache();
				}
				return true;
			}
			update_post_meta( $product_id, '_tsbifw_descriptions', $descriptions );
			delete_post_meta( $product_id, '_tsbifw_vectors' );
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'indexed' );
			delete_post_meta( $product_id, '_tsbifw_index_error' );
			if ( ! $skip_cache_clear ) {
				$this->clear_cache();
			}

			$sync_tags = get_option( 'tsbifw_sync_to_tags', 'no' );
			if ( 'yes' === $sync_tags && ! empty( $all_keywords ) ) {
				$merged_tags = array();
				foreach ( $all_keywords as $keywords_str ) {
					$tags = array_map( 'trim', explode( ',', $keywords_str ) );
					$merged_tags = array_merge( $merged_tags, $tags );
				}
				$merged_tags = array_filter( array_unique( $merged_tags ) );
				if ( ! empty( $merged_tags ) ) {
					wp_set_object_terms( $product_id, $merged_tags, 'product_tag', true );
				}
			}
		}

		do_action( 'tsbifw_after_product_indexed', $product_id, $target_images, $strategy );

		return true;
	}

	public function on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' !== $meta_key && '_product_image_gallery' !== $meta_key ) {
			return;
		}

		$post_type         = get_post_type( $object_id );
		$target_product_id = $object_id;

		if ( 'product_variation' === $post_type ) {
			$parent_id = wp_get_post_parent_id( $object_id );
			if ( ! $parent_id || 'product' !== get_post_type( $parent_id ) ) {
				return;
			}
			$target_product_id = $parent_id;
		} elseif ( 'product' !== $post_type ) {
			return;
		}

		$index_featured = ( get_option( 'tsbifw_index_featured', 'yes' ) === 'yes' );
		$index_gallery  = ( get_option( 'tsbifw_index_gallery', 'no' ) === 'yes' );

		$should_queue = false;
		if ( '_thumbnail_id' === $meta_key && $index_featured ) {
			$should_queue = true;
		} elseif ( '_product_image_gallery' === $meta_key && $index_gallery ) {
			$should_queue = true;
		}

		$should_queue = apply_filters( 'tsbifw_should_queue_meta_update', $should_queue, $meta_key, $object_id, $target_product_id );

		if ( $should_queue ) {
			$product = wc_get_product( $target_product_id );
			if ( ! $product ) {
				return;
			}

			$target_images = $this->get_product_target_images( $product );
			if ( apply_filters( 'tsbifw_skip_product_indexing', false, $target_product_id, $target_images, false ) ) {
				return;
			}

			// translators: 1: Metadata key changed, 2: Product ID
			$msg = sprintf( esc_html__( 'Product image metadata changed (%1$s) for product ID %2$d. Queued for background indexing.', 'searchips-search-by-image-for-woocommerce' ), $meta_key, $target_product_id );
			TSBIFW_Logger::log( $msg );
			delete_post_meta( $target_product_id, '_tsbifw_indexed_status' );
			delete_post_meta( $target_product_id, '_tsbifw_vectors' );
			delete_post_meta( $target_product_id, '_tsbifw_descriptions' );
			delete_post_meta( $target_product_id, '_tsbifw_images_hash' );
			delete_post_meta( $target_product_id, '_tsbifw_index_error' );
			$this->clear_cache();

			$allow_instant = ( 'yes' === get_option( 'tsbifw_auto_index_on_save', 'no' ) );
			if ( apply_filters( 'tsbifw_allow_instant_auto_index', $allow_instant, $target_product_id ) ) {
				$this->index_product( $target_product_id, false );
			}
		}
	}

	public function on_meta_delete( $meta_ids, $object_id, $meta_key, $meta_value ) {
		if ( '_thumbnail_id' !== $meta_key && '_product_image_gallery' !== $meta_key ) {
			return;
		}

		$post_type         = get_post_type( $object_id );
		$target_product_id = $object_id;

		if ( 'product_variation' === $post_type ) {
			$parent_id = wp_get_post_parent_id( $object_id );
			if ( ! $parent_id || 'product' !== get_post_type( $parent_id ) ) {
				return;
			}
			$target_product_id = $parent_id;
		} elseif ( 'product' !== $post_type ) {
			return;
		}

		$index_featured = ( get_option( 'tsbifw_index_featured', 'yes' ) === 'yes' );
		$index_gallery  = ( get_option( 'tsbifw_index_gallery', 'no' ) === 'yes' );

		$should_queue = false;
		if ( '_thumbnail_id' === $meta_key && $index_featured ) {
			$should_queue = true;
		} elseif ( '_product_image_gallery' === $meta_key && $index_gallery ) {
			$should_queue = true;
		}

		$should_queue = apply_filters( 'tsbifw_should_queue_meta_update', $should_queue, $meta_key, $object_id, $target_product_id );

		if ( $should_queue ) {
			$product = wc_get_product( $target_product_id );
			if ( ! $product ) {
				return;
			}

			$target_images = $this->get_product_target_images( $product );
			if ( apply_filters( 'tsbifw_skip_product_indexing', false, $target_product_id, $target_images, false ) ) {
				return;
			}

			// translators: 1: Metadata key deleted, 2: Product ID
			$msg = sprintf( esc_html__( 'Product image metadata deleted (%1$s) for product ID %2$d. Queued for background indexing.', 'searchips-search-by-image-for-woocommerce' ), $meta_key, $target_product_id );
			TSBIFW_Logger::log( $msg );
			delete_post_meta( $target_product_id, '_tsbifw_indexed_status' );
			delete_post_meta( $target_product_id, '_tsbifw_vectors' );
			delete_post_meta( $target_product_id, '_tsbifw_descriptions' );
			delete_post_meta( $target_product_id, '_tsbifw_images_hash' );
			delete_post_meta( $target_product_id, '_tsbifw_index_error' );
			$this->clear_cache();
		}
	}

	/**
	 * Handles product save/update to trigger immediate indexing when enabled.
	 *
	 * @param int     $post_id Product post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_product_save( $post_id, $post ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! $post || 'publish' !== $post->post_status ) {
			return;
		}
		if ( 'yes' !== get_option( 'tsbifw_auto_index_on_save', 'no' ) ) {
			return;
		}

		$this->index_product( $post_id, false );
	}

	/**
	 * Run background indexing task via WP Cron.
	 */
	public function run_cron_indexing() {
		if ( 'yes' !== get_option( 'tsbifw_enable_cron_indexing', 'no' ) ) {
			return;
		}

		$api_key = TSBIFW_API::instance()->get_api_key();
		if ( empty( $api_key ) ) {
			return; // Bypassed since API key for active gateway is missing.
		}

		if ( false !== get_transient( 'tsbifw_cron_indexing_lock' ) ) {
			return;
		}
		set_transient( 'tsbifw_cron_indexing_lock', true, 10 * MINUTE_IN_SECONDS );

		TSBIFW_Logger::log( 'Cron Indexing: Starting background cron job...' );

		$batch_size = (int) get_option( 'tsbifw_cron_batch_size', 5 );
		if ( $batch_size <= 0 ) {
			$batch_size = 5;
		}



		// Query up to configured batch size unindexed products to prevent background execution timeouts.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
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
			TSBIFW_Logger::log( 'Cron Indexing: No products in queue to index.' );
			delete_transient( 'tsbifw_cron_indexing_lock' );
			return;
		}

		if ( function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( $product_ids, true, false );
		}

		TSBIFW_Logger::log( sprintf( 'Cron Indexing: Found %d products to index.', count( $product_ids ) ), array( 'ids' => $product_ids ) );

		$indexed_count = 0;
		$failed_count  = 0;

		foreach ( $product_ids as $id ) {
			$result = $this->index_product( $id, false, true );
			if ( is_wp_error( $result ) ) {
				$failed_count++;
				TSBIFW_Logger::log( sprintf( 'Cron Indexing: Failed for product ID %d. Error: %s', $id, $result->get_error_message() ) );
			} else {
				$indexed_count++;
				TSBIFW_Logger::log( sprintf( 'Cron Indexing: Succeeded for product ID %d.', $id ) );
			}
		}

		$this->clear_cache();

		TSBIFW_Logger::log( sprintf( 'Cron Indexing: Finished batch. Succeeded: %d, Failed: %d.', $indexed_count, $failed_count ) );
		delete_transient( 'tsbifw_cron_indexing_lock' );
	}

	/**
	 * Add custom intervals for WP Cron scheduling.
	 *
	 * @param array $schedules Cron schedules.
	 * @return array
	 */
	public function add_custom_cron_schedules( $schedules ) {
		$schedules['tsbifw_every_minute'] = array(
			'interval' => 60,
			'display'  => esc_html__( 'Every Minute', 'searchips-search-by-image-for-woocommerce' ),
		);
		$schedules['tsbifw_every_5_minutes'] = array(
			'interval' => 300,
			'display'  => esc_html__( 'Every 5 Minutes', 'searchips-search-by-image-for-woocommerce' ),
		);
		$schedules['tsbifw_every_15_minutes'] = array(
			'interval' => 900,
			'display'  => esc_html__( 'Every 15 Minutes', 'searchips-search-by-image-for-woocommerce' ),
		);

		// Legacy aliases for backwards compatibility.
		$schedules['every_minute']     = $schedules['tsbifw_every_minute'];
		$schedules['every_5_minutes']  = $schedules['tsbifw_every_5_minutes'];
		$schedules['every_15_minutes'] = $schedules['tsbifw_every_15_minutes'];

		return $schedules;
	}

	/**
	 * Callback when a product is deleted.
	 *
	 * @param int $post_id Post ID.
	 */
	public function on_product_delete( $post_id ) {
		if ( 'product' === get_post_type( $post_id ) ) {
			delete_post_meta( $post_id, '_tsbifw_vectors' );
			delete_post_meta( $post_id, '_tsbifw_descriptions' );
			delete_post_meta( $post_id, '_tsbifw_indexed_status' );
			delete_post_meta( $post_id, '_tsbifw_images_hash' );
			delete_post_meta( $post_id, '_tsbifw_index_error' );
			$this->clear_cache();
		}
	}

	/**
	 * Clear all indexed data from the database.
	 */
	public function clear_all_indexed_data() {
		delete_metadata( 'post', 0, '_tsbifw_vector', '', true );
		delete_metadata( 'post', 0, '_tsbifw_description', '', true );
		delete_metadata( 'post', 0, '_tsbifw_vectors', '', true );
		delete_metadata( 'post', 0, '_tsbifw_descriptions', '', true );
		delete_metadata( 'post', 0, '_tsbifw_indexed_status', '', true );
		delete_metadata( 'post', 0, '_tsbifw_images_hash', '', true );
		delete_metadata( 'post', 0, '_tsbifw_index_error', '', true );

		$this->clear_cache();

		TSBIFW_Logger::log( esc_html__( 'All product embeddings, descriptions, and indexing metadata cleared successfully.', 'searchips-search-by-image-for-woocommerce' ) );
	}

	/**
	 * Invalidate indexer caches and transients.
	 */
	public function clear_cache() {
		$this->indexed_image_map = null;
		delete_transient( 'tsbifw_indexed_image_ids' );
		delete_transient( 'tsbifw_cron_indexing_lock' );
		wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );
		delete_transient( 'tsbifw_indexing_stats' );
		wp_cache_delete( 'tsbifw_catalog_vectors', 'tsbifw_cache' );
		delete_transient( 'tsbifw_catalog_vectors' );
		wp_cache_delete( 'tsbifw_catalog_descriptions', 'tsbifw_cache' );
		delete_transient( 'tsbifw_catalog_descriptions' );
	}

	/**
	 * Retrieve all indexed image attachment IDs across all products efficiently.
	 *
	 * Queries attachment IDs directly from indexed products without loading heavy vectors into memory.
	 *
	 * @return array Array of attachment IDs.
	 */
	public function get_indexed_image_ids() {
		if ( null !== $this->indexed_image_map ) {
			return array_keys( $this->indexed_image_map );
		}

		$cached = get_transient( 'tsbifw_indexed_image_ids' );
		if ( is_array( $cached ) ) {
			$this->indexed_image_map = array_fill_keys( $cached, true );
			return $cached;
		}

		$this->indexed_image_map = array();
		$image_ids               = array();
		global $wpdb;

		$index_featured = ( get_option( 'tsbifw_index_featured', 'yes' ) === 'yes' );
		$index_gallery  = ( get_option( 'tsbifw_index_gallery', 'no' ) === 'yes' );

		$meta_keys = array();
		if ( $index_featured ) {
			$meta_keys[] = '_thumbnail_id';
		}
		if ( $index_gallery ) {
			$meta_keys[] = '_product_image_gallery';
		}

		$meta_keys = apply_filters( 'tsbifw_indexed_meta_keys', $meta_keys );

		if ( ! empty( $meta_keys ) ) {
			$placeholders = implode( ', ', array_fill( 0, count( $meta_keys ), '%s' ) );
			$prepare_args = array_merge(
				array(
					'_tsbifw_indexed_status',
					'indexed',
					'publish',
					'product',
				),
				$meta_keys
			);

			// Query attachment IDs directly from configured image metadata of indexed published products.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT pm_img.meta_value 
					FROM {$wpdb->postmeta} pm_status
					INNER JOIN {$wpdb->posts} p ON p.ID = pm_status.post_id
					INNER JOIN {$wpdb->postmeta} pm_img ON pm_img.post_id = pm_status.post_id
					WHERE pm_status.meta_key = %s 
					  AND pm_status.meta_value = %s 
					  AND p.post_status = %s 
					  AND p.post_type = %s 
					  AND pm_img.meta_key IN ($placeholders)",
					$prepare_args
				),
				ARRAY_A
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

			if ( ! empty( $rows ) ) {
				foreach ( $rows as $row ) {
					$val = trim( $row['meta_value'] );
					if ( '' === $val ) {
						continue;
					}
					if ( is_numeric( $val ) ) {
						$id = (int) $val;
						if ( $id > 0 ) {
							$this->indexed_image_map[ $id ] = true;
							$image_ids[]                    = $id;
						}
					} else {
						$ids = explode( ',', $val );
						foreach ( $ids as $id_str ) {
							$id = (int) trim( $id_str );
							if ( $id > 0 ) {
								$this->indexed_image_map[ $id ] = true;
								$image_ids[]                    = $id;
							}
						}
					}
				}
			}
		}

		$image_ids = apply_filters( 'tsbifw_indexed_image_ids', $image_ids );
		$image_ids = array_values( array_unique( $image_ids ) );
		foreach ( $image_ids as $id ) {
			$this->indexed_image_map[ (int) $id ] = true;
		}
		set_transient( 'tsbifw_indexed_image_ids', $image_ids, 12 * HOUR_IN_SECONDS );

		return $image_ids;
	}

	/**
	 * Check if an image attachment is indexed using O(1) hash map lookup.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if indexed, false otherwise.
	 */
	public function is_image_indexed( $attachment_id ) {
		if ( null === $this->indexed_image_map ) {
			$this->get_indexed_image_ids();
		}
		return isset( $this->indexed_image_map[ (int) $attachment_id ] );
	}
}
