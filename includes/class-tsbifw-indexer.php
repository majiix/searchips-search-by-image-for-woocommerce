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
	 * Track indexed image attachment IDs.
	 *
	 * @var array|null
	 */
	private $indexed_image_ids = null;

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

		// Custom schedules filter.
		add_filter( 'cron_schedules', array( $this, 'add_custom_cron_schedules' ) );
	}

	/**
	 * Indexes a single product using the active strategy.
	 *
	 * @param int $product_id Product ID.
	 * @return bool|WP_Error True if success, WP_Error if failure.
	 */
	public function index_product( $product_id ) {
		if ( in_array( $product_id, $this->indexed_products, true ) ) {
			return true;
		}
		$this->indexed_products[] = $product_id;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'tsbifw_invalid_product', esc_html__( 'Invalid product ID.', 'searchips-search-by-image-for-woocommerce' ) );
		}

		$index_featured = ( get_option( 'tsbifw_index_featured', 'yes' ) === 'yes' );
		$index_gallery  = ( get_option( 'tsbifw_index_gallery', 'no' ) === 'yes' );

		$featured_id = $product->get_image_id();
		$gallery_ids = $product->get_gallery_image_ids();

		$target_images = array();
		if ( $index_featured && $featured_id ) {
			$target_images[] = array(
				'id'   => $featured_id,
				'type' => 'featured',
			);
		}
		if ( $index_gallery && ! empty( $gallery_ids ) ) {
			foreach ( $gallery_ids as $gallery_id ) {
				$target_images[] = array(
					'id'   => $gallery_id,
					'type' => 'gallery',
				);
			}
		}

		if ( empty( $target_images ) ) {
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
			delete_post_meta( $product_id, '_tsbifw_vectors' );
			delete_post_meta( $product_id, '_tsbifw_descriptions' );
			$this->clear_cache();
			return true;
		}

		$strategy = get_option( 'tsbifw_strategy', 'embeddings' );
		$api      = TSBIFW_API::instance();

		$vectors      = array();
		$descriptions = array();
		$all_keywords = array();

		foreach ( $target_images as $image ) {
			$base64 = $api->prepare_image( $image['id'] );
			if ( is_wp_error( $base64 ) ) {
				if ( 'tsbifw_empty_image' === $base64->get_error_code() || 'tsbifw_file_not_found' === $base64->get_error_code() ) {
					TSBIFW_Logger::log( sprintf( 'Skipped image ID %d for product ID %d. Reason: %s', $image['id'], $product_id, $base64->get_error_message() ) );
					continue; // Skip this problematic image.
				}
				update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
				update_post_meta( $product_id, '_tsbifw_index_error', $base64->get_error_message() );
				return $base64;
			}

			if ( 'embeddings' === $strategy ) {
				$vector = $api->get_embeddings( $base64 );
				if ( is_wp_error( $vector ) ) {
					update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
					update_post_meta( $product_id, '_tsbifw_index_error', $vector->get_error_message() );
					return $vector;
				}
				$vectors[] = array(
					'id'     => $image['id'],
					'type'   => $image['type'],
					'vector' => $vector,
				);
			} else {
				$description = $api->get_description( $base64 );
				if ( is_wp_error( $description ) ) {
					update_post_meta( $product_id, '_tsbifw_indexed_status', 'error' );
					update_post_meta( $product_id, '_tsbifw_index_error', $description->get_error_message() );
					return $description;
				}
				$descriptions[] = array(
					'id'          => $image['id'],
					'type'        => $image['type'],
					'description' => $description,
				);
				$all_keywords[] = $description;
			}
		}

		if ( 'embeddings' === $strategy ) {
			if ( empty( $vectors ) ) {
				update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
				delete_post_meta( $product_id, '_tsbifw_vectors' );
				delete_post_meta( $product_id, '_tsbifw_index_error' );
				$this->clear_cache();
				return true;
			}
			update_post_meta( $product_id, '_tsbifw_vectors', $vectors );
			delete_post_meta( $product_id, '_tsbifw_descriptions' );
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'indexed' );
			delete_post_meta( $product_id, '_tsbifw_index_error' );
			$this->clear_cache();
		} else {
			if ( empty( $descriptions ) ) {
				update_post_meta( $product_id, '_tsbifw_indexed_status', 'skipped' );
				delete_post_meta( $product_id, '_tsbifw_descriptions' );
				delete_post_meta( $product_id, '_tsbifw_index_error' );
				return true;
			}
			update_post_meta( $product_id, '_tsbifw_descriptions', $descriptions );
			delete_post_meta( $product_id, '_tsbifw_vectors' );
			update_post_meta( $product_id, '_tsbifw_indexed_status', 'indexed' );
			delete_post_meta( $product_id, '_tsbifw_index_error' );

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

		return true;
	}

	public function on_meta_update( $meta_id, $object_id, $meta_key, $meta_value ) {
		if ( 'product' !== get_post_type( $object_id ) ) {
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

		if ( $should_queue ) {
			// translators: 1: Metadata key changed, 2: Product ID
			$msg = sprintf( esc_html__( 'Product image metadata changed (%1$s) for product ID %2$d. Queued for background indexing.', 'searchips-search-by-image-for-woocommerce' ), $meta_key, $object_id );
			TSBIFW_Logger::log( $msg );
			delete_post_meta( $object_id, '_tsbifw_indexed_status' );
			delete_post_meta( $object_id, '_tsbifw_vectors' );
			delete_post_meta( $object_id, '_tsbifw_descriptions' );
			delete_post_meta( $object_id, '_tsbifw_index_error' );
			$this->clear_cache();
		}
	}

	public function on_meta_delete( $meta_ids, $object_id, $meta_key, $meta_value ) {
		if ( 'product' !== get_post_type( $object_id ) ) {
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

		if ( $should_queue ) {
			// translators: 1: Metadata key deleted, 2: Product ID
			$msg = sprintf( esc_html__( 'Product image metadata deleted (%1$s) for product ID %2$d. Queued for background indexing.', 'searchips-search-by-image-for-woocommerce' ), $meta_key, $object_id );
			TSBIFW_Logger::log( $msg );
			delete_post_meta( $object_id, '_tsbifw_indexed_status' );
			delete_post_meta( $object_id, '_tsbifw_vectors' );
			delete_post_meta( $object_id, '_tsbifw_descriptions' );
			delete_post_meta( $object_id, '_tsbifw_index_error' );
			$this->clear_cache();
		}
	}

	/**
	 * Run background indexing task via WP Cron.
	 */
	public function run_cron_indexing() {
		if ( 'yes' !== get_option( 'tsbifw_enable_cron_indexing', 'no' ) ) {
			return;
		}

		$api_key = get_option( 'tsbifw_api_key', '' );
		if ( empty( $api_key ) ) {
			return; // Bypassed since OpenRouter API key is missing.
		}

		TSBIFW_Logger::log( 'Cron Indexing: Starting background cron job...' );

		$batch_size = (int) get_option( 'tsbifw_cron_batch_size', 5 );
		if ( $batch_size <= 0 ) {
			$batch_size = 5;
		}

		// Query up to configured batch size products to prevent background execution timeouts.
		$query_args = array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => $batch_size,
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
			TSBIFW_Logger::log( 'Cron Indexing: No products in queue to index.' );
			return;
		}

		TSBIFW_Logger::log( sprintf( 'Cron Indexing: Found %d products to index.', count( $product_ids ) ), array( 'ids' => $product_ids ) );

		$indexed_count = 0;
		$failed_count  = 0;

		foreach ( $product_ids as $id ) {
			$result = $this->index_product( $id );
			if ( is_wp_error( $result ) ) {
				$failed_count++;
				TSBIFW_Logger::log( sprintf( 'Cron Indexing: Failed for product ID %d. Error: %s', $id, $result->get_error_message() ) );
			} else {
				$indexed_count++;
				TSBIFW_Logger::log( sprintf( 'Cron Indexing: Succeeded for product ID %d.', $id ) );
			}
		}

		TSBIFW_Logger::log( sprintf( 'Cron Indexing: Finished batch. Succeeded: %d, Failed: %d.', $indexed_count, $failed_count ) );
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
			delete_post_meta( $post_id, '_tsbifw_index_error' );
			$this->clear_cache();
		}
	}

	/**
	 * Clear cached indexing states.
	 */
	public function clear_cache() {
		$this->indexed_image_ids = null;
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
		delete_metadata( 'post', 0, '_tsbifw_index_error', '', true );

		$this->clear_cache();

		TSBIFW_Logger::log( esc_html__( 'All product embeddings, descriptions, and indexing metadata cleared successfully.', 'searchips-search-by-image-for-woocommerce' ) );
	}

	/**
	 * Retrieve all indexed image attachment IDs across all products efficiently.
	 *
	 * Queries attachment IDs directly from indexed products without loading heavy vectors into memory.
	 *
	 * @return array Array of attachment IDs.
	 */
	public function get_indexed_image_ids() {
		if ( null !== $this->indexed_image_ids ) {
			return $this->indexed_image_ids;
		}

		$this->indexed_image_ids = array();
		global $wpdb;

		// Query attachment IDs directly from featured image and gallery of indexed published products.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
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
				  AND pm_img.meta_key IN ('_thumbnail_id', '_product_image_gallery')",
				'_tsbifw_indexed_status',
				'indexed',
				'publish',
				'product'
			),
			ARRAY_A
		);

		if ( ! empty( $rows ) ) {
			foreach ( $rows as $row ) {
				$val = trim( $row['meta_value'] );
				if ( '' === $val ) {
					continue;
				}
				if ( is_numeric( $val ) ) {
					$this->indexed_image_ids[] = (int) $val;
				} else {
					$ids = explode( ',', $val );
					foreach ( $ids as $id ) {
						$id = (int) trim( $id );
						if ( $id > 0 ) {
							$this->indexed_image_ids[] = $id;
						}
					}
				}
			}
		}

		$this->indexed_image_ids = array_values( array_unique( $this->indexed_image_ids ) );
		return $this->indexed_image_ids;
	}

	/**
	 * Check if an image attachment is indexed.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool True if indexed, false otherwise.
	 */
	public function is_image_indexed( $attachment_id ) {
		$ids = $this->get_indexed_image_ids();
		return in_array( (int) $attachment_id, $ids, true );
	}
}
