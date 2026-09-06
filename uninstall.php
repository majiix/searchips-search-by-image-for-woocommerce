<?php
/**
 * Searchips Search By Image Uninstall File.
 *
 * This file runs when the plugin is deleted via the WordPress Admin.
 * It deletes all plugin options, transients, post metadata, and logs if configured.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Only delete data if the user explicitly enabled the uninstall data deletion option.
if ( 'yes' === get_option( 'tsbifw_delete_data_on_uninstall', 'no' ) ) {
	global $wpdb;

	// 1. Delete all custom options.
	$tsbifw_options = array(
		'tsbifw_api_key',
		'tsbifw_strategy',
		'tsbifw_embeddings_model',
		'tsbifw_vision_model',
		'tsbifw_sync_to_tags',
		'tsbifw_similarity_threshold',
		'tsbifw_exclude_below_percent',
		'tsbifw_results_limit',
		'tsbifw_max_upload_size',
		'tsbifw_search_cache_expiry',
		'tsbifw_enable_auto_inject',
		'tsbifw_camera_left',
		'tsbifw_camera_right',
		'tsbifw_camera_bg_color',
		'tsbifw_camera_icon_size',
		'tsbifw_index_featured',
		'tsbifw_index_gallery',
		'tsbifw_enable_logging',
		'tsbifw_log_retention',
		'tsbifw_enable_cron_indexing',
		'tsbifw_cron_interval',
		'tsbifw_cron_batch_size',
		'tsbifw_delete_data_on_uninstall',
		'tsbifw_logs',
	);

	foreach ( $tsbifw_options as $tsbifw_option ) {
		delete_option( $tsbifw_option );
	}

	// 2. Clear transients.
	delete_transient( 'tsbifw_clean_logs_lock' );
	delete_transient( 'tsbifw_indexed_image_ids' );
	delete_transient( 'tsbifw_indexing_stats' );
	wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );

	// Clear any cached models transients.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tsbifw_models_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tsbifw_models_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tsbifw_vquery_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tsbifw_vquery_%'" );

	// 3. Delete product post metadata.
	$tsbifw_meta_keys = array(
		'_tsbifw_vector',
		'_tsbifw_description',
		'_tsbifw_vectors',
		'_tsbifw_descriptions',
		'_tsbifw_indexed_status',
		'_tsbifw_index_error',
	);

	foreach ( $tsbifw_meta_keys as $tsbifw_key ) {
		delete_post_meta_by_key( $tsbifw_key );
	}

	// 4. Clean up scheduled cron jobs.
	wp_clear_scheduled_hook( 'tsbifw_cron_indexing' );
	wp_clear_scheduled_hook( 'tsbifw_clear_index_cron' );
}
