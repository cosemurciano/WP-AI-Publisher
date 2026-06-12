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
		$timestamp = wp_next_scheduled( 'wpai_publisher_future_cron' );

		while ( false !== $timestamp ) {
			wp_unschedule_event( $timestamp, 'wpai_publisher_future_cron' );
			$timestamp = wp_next_scheduled( 'wpai_publisher_future_cron' );
		}
	}
}
