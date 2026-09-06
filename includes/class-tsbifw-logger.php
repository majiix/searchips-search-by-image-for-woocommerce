<?php
/**
 * Database Logging Helper for Searchips Search By Image.
 *
 * @package SearchipsSearchByImageForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class TSBIFW_Logger {

	/**
	 * Log a message to the database and WooCommerce logger.
	 *
	 * @param string $message Log message.
	 * @param array  $context Optional context metadata.
	 * @param bool   $db_log  Whether to persist to in-database option (defaults to true; set false on high-concurrency requests).
	 */
	public static function log( $message, $context = array(), $db_log = true ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->info( $message, array( 'source' => 'tsbifw', 'context' => $context ) );
		}

		if ( 'yes' !== get_option( 'tsbifw_enable_logging', 'yes' ) ) {
			return;
		}

		// Skip database option write if db_log is disabled and not in WP_DEBUG mode.
		if ( ! $db_log && ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) {
			return;
		}

		self::clean_expired_logs();

		$logs = get_option( 'tsbifw_logs', array() );
		if ( ! is_array( $logs ) ) {
			$logs = array();
		}

		$logs[] = array(
			'timestamp' => current_time( 'mysql' ),
			'message'   => $message,
			'context'   => $context,
		);

		// Keep logs size reasonable in database (max 500 items).
		if ( count( $logs ) > 500 ) {
			$logs = array_slice( $logs, -500 );
		}

		update_option( 'tsbifw_logs', $logs, false );
		if ( function_exists( 'wp_set_option_autoload' ) ) {
			wp_set_option_autoload( 'tsbifw_logs', 'no' );
		}
	}

	/**
	 * Remove expired logs based on retention days option.
	 */
	public static function clean_expired_logs() {
		if ( false !== get_transient( 'tsbifw_clean_logs_lock' ) ) {
			return;
		}

		// Set transient lock for 12 hours.
		set_transient( 'tsbifw_clean_logs_lock', 'yes', 12 * HOUR_IN_SECONDS );

		$retention_days = (int) get_option( 'tsbifw_log_retention', 7 );
		if ( 0 === $retention_days ) {
			return;
		}

		$logs = get_option( 'tsbifw_logs', array() );
		if ( empty( $logs ) || ! is_array( $logs ) ) {
			return;
		}

		$cutoff       = strtotime( "-{$retention_days} days" );
		$updated_logs = array_values(
			array_filter(
				$logs,
				function( $log ) use ( $cutoff ) {
					return isset( $log['timestamp'] ) && strtotime( $log['timestamp'] ) >= $cutoff;
				}
			)
		);

		if ( count( $updated_logs ) !== count( $logs ) ) {
			update_option( 'tsbifw_logs', $updated_logs, false );
		}
	}

	/**
	 * Get logs from the database.
	 *
	 * @return array
	 */
	public static function get_logs() {
		$logs = get_option( 'tsbifw_logs', array() );
		return is_array( $logs ) ? $logs : array();
	}

	/**
	 * Delete all logs from the database.
	 */
	public static function clear_all_logs() {
		delete_option( 'tsbifw_logs' );
	}
}
