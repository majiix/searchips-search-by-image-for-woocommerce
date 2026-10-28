<?php
/**
 * Searchips Search By Image Uninstall File.
 *
 * This file runs when the plugin is deleted via the WordPress Admin.
 * It deletes all plugin options, transients, and post metadata
 * if the user has explicitly enabled data deletion on uninstall.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Filter whether shared plugin data should be deleted upon uninstall.
 *
 * Allows standalone sibling forks or site administrators to protect
 * shared options and vector embeddings when Free is uninstalled.
 *
 * @param bool $allow_deletion Whether to proceed with data deletion. Default true.
 */
if ( ! apply_filters( 'tsbifw_allow_uninstall_data_deletion', true ) ) {
	return;
}

// Only delete data if the user explicitly enabled the uninstall data deletion option.
if ( 'yes' === get_option( 'tsbifw_delete_data_on_uninstall', 'no' ) ) {
	global $wpdb;

	// 1. Delete all plugin options.
	$tsbifw_options = array(
		'tsbifw_api_key',
		'tsbifw_api_gateway',
		'tsbifw_embeddings_model',
		'tsbifw_exclude_below_percent',
		'tsbifw_max_upload_size',
		'tsbifw_search_cache_expiry',
		'tsbifw_enable_auto_inject',
		'tsbifw_camera_left',
		'tsbifw_camera_right',
		'tsbifw_camera_bg_color',
		'tsbifw_camera_icon_size',
		'tsbifw_scanning_effect',
		'tsbifw_scanning_color',
		'tsbifw_index_featured',
		'tsbifw_index_gallery',
		'tsbifw_auto_index_on_save',
		'tsbifw_enable_media_column',
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

	// 2. Clear transients and object caches.
	delete_transient( 'tsbifw_clean_logs_lock' );
	delete_transient( 'tsbifw_cron_indexing_lock' );
	delete_transient( 'tsbifw_indexed_image_ids' );
	delete_transient( 'tsbifw_indexing_stats' );
	wp_cache_delete( 'tsbifw_indexing_stats', 'tsbifw_cache' );

	// Clear cached model transients and query tokens.
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tsbifw_models_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tsbifw_models_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tsbifw_vquery_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tsbifw_vquery_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_tsbifw_rl_%'" );
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_tsbifw_rl_%'" );

	// 3. Delete product post metadata.
	$tsbifw_meta_keys = array(
		'_tsbifw_vector',
		'_tsbifw_vectors',
		'_tsbifw_indexed_status',
		'_tsbifw_index_error',
	);

	foreach ( $tsbifw_meta_keys as $tsbifw_key ) {
		delete_post_meta_by_key( $tsbifw_key );
	}

	/**
	 * Fires when plugin data is deleted on uninstall.
	 */
	do_action( 'tsbifw_uninstall' );
}

// Always clean up scheduled cron jobs upon plugin deletion.
wp_clear_scheduled_hook( 'tsbifw_cron_indexing' );
wp_clear_scheduled_hook( 'tsbifw_clear_index_cron' );

// Clean up any pending Action Scheduler background indexing tasks.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'tsbifw_action_scheduler_index_product' );
}
