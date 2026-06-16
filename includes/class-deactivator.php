<?php
/**
 * Deactivation routines.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin deactivation.
 */
final class Deactivator {
	/**
	 * Deactivate plugin without deleting data.
	 *
	 * @return void
	 */
	public static function deactivate() {
		foreach ( array( 'wpai_publisher_future_cron', 'wpai_publisher_run_scheduled_ideas', 'wpai_publisher_process_jobs' ) as $hook ) {
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( $hook );
				continue;
			}
			$timestamp = wp_next_scheduled( $hook );
			while ( false !== $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
				$timestamp = wp_next_scheduled( $hook );
			}
		}
	}
}
