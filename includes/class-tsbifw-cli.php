<?php
/**
 * WP-CLI integration for Searchips Search By Image for WooCommerce.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TSBIFW_CLI' ) ) {

	/**
	 * Manage Searchips image indexing and vector caches via WP-CLI.
	 */
	class TSBIFW_CLI {

		/**
		 * Display current image indexing status and stats.
		 *
		 * ## EXAMPLES
		 *
		 *     wp searchips status
		 *
		 * @when after_wp_load
		 */
		public function status( $args, $assoc_args ) {
			if ( function_exists( 'tsbifw_get_indexing_stats' ) ) {
				$stats = tsbifw_get_indexing_stats();
			} else {
				WP_CLI::error( 'Searchips core plugin is not loaded.' );
			}

			$allowed_gateways = apply_filters( 'tsbifw_allowed_gateways', array( 'openrouter' ) );
			$gateway          = get_option( 'tsbifw_api_gateway', 'openrouter' );
			if ( ! in_array( $gateway, $allowed_gateways, true ) ) {
				$gateway = 'openrouter';
			}

			WP_CLI::line( WP_CLI::colorize( '%GSearchips Visual Search Status%n' ) );
			WP_CLI::line( '--------------------------------' );
			WP_CLI::line( sprintf( 'Active AI Gateway : %s', ucfirst( $gateway ) ) );
			WP_CLI::line( sprintf( 'Total Products    : %d', $stats['total'] ) );
			WP_CLI::line( sprintf( 'Indexed Products  : %d', $stats['indexed'] ) );
			WP_CLI::line( sprintf( 'Skipped (No Image): %d', $stats['skipped'] ) );
			WP_CLI::line( sprintf( 'Failed / Errors   : %d', $stats['errors'] ) );
			WP_CLI::line( sprintf( 'Completion Rate   : %s%%', $stats['percentage'] ) );
			WP_CLI::line( '--------------------------------' );
		}

		/**
		 * Index products using the active AI strategy.
		 *
		 * ## OPTIONS
		 *
		 * [--all]
		 * : Re-index all published products, including already indexed items.
		 *
		 * [--limit=<number>]
		 * : Maximum number of products to index in this run. Default is all pending.
		 *
		 * [--batch-size=<number>]
		 * : Number of products per query slice. Default is 50.
		 *
		 * ## EXAMPLES
		 *
		 *     wp searchips index
		 *     wp searchips index --all
		 *     wp searchips index --limit=100 --batch-size=25
		 *
		 * @when after_wp_load
		 */
		public function index( $args, $assoc_args ) {
			if ( ! function_exists( 'tsbifw_index_product' ) && ! has_action( 'tsbifw_index_product' ) ) {
				WP_CLI::error( 'Searchips Indexer module not found.' );
			}

			$reindex_all = isset( $assoc_args['all'] );
			$force       = isset( $assoc_args['force'] ) || $reindex_all;
			$limit       = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 0;
			$batch_size  = isset( $assoc_args['batch-size'] ) ? (int) $assoc_args['batch-size'] : 50;

			if ( $batch_size <= 0 ) {
				$batch_size = 50;
			}

			$query_args = array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => ( $limit > 0 ) ? $limit : -1,
				'fields'         => 'ids',
			);

			if ( ! $reindex_all ) {
				$query_args['meta_query'] = array(
					array(
						'key'     => '_tsbifw_indexed_status',
						'compare' => 'NOT EXISTS',
					),
				);
			}

			$query       = new WP_Query( $query_args );
			$product_ids = $query->posts;

			if ( empty( $product_ids ) ) {
				WP_CLI::success( 'No products found requiring indexing.' );
				return;
			}

			$total_to_process = count( $product_ids );
			WP_CLI::line( sprintf( 'Found %d product(s) to index...', $total_to_process ) );

			$progress = \WP_CLI\Utils\make_progress_bar( 'Indexing products', $total_to_process );

			$success = 0;
			$errors  = 0;

			foreach ( $product_ids as $id ) {
				if ( function_exists( 'tsbifw_index_product' ) ) {
					$res = tsbifw_index_product( (int) $id, $force );
				} else {
					$res = do_action( 'tsbifw_index_product', (int) $id, $force );
				}
				if ( is_wp_error( $res ) ) {
					$errors++;
				} else {
					$success++;
				}
				$progress->tick();
			}

			wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );

			$progress->finish();

			WP_CLI::success( sprintf( 'Indexing finished. Successfully processed: %d, Failed: %d.', $success, $errors ) );
		}

		/**
		 * Clear all indexed image vectors and caches.
		 *
		 * ## EXAMPLES
		 *
		 *     wp searchips clear
		 *
		 * @when after_wp_load
		 */
		public function clear( $args, $assoc_args ) {
			if ( ! class_exists( 'TSBIFW_Indexer' ) ) {
				WP_CLI::error( 'Searchips Indexer module not found.' );
			}

			WP_CLI::confirm( 'Are you sure you want to clear all product vector embeddings?' );

			TSBIFW_Indexer::instance()->clear_all_indexed_data();
			wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );

			WP_CLI::success( 'All Searchips indexed data has been successfully cleared.' );
		}
	}

	WP_CLI::add_command( 'searchips', 'TSBIFW_CLI' );
}
