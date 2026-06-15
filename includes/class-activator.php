<?php
/**
 * Activation routines.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin activation.
 */
final class Activator {
	/**
	 * Activate plugin.
	 *
	 * @return void
	 */
	public static function activate() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( WPAIP_PLUGIN_BASENAME );
			wp_die( esc_html__( 'WP AI Publisher requires PHP 8.1 or higher.', 'wp-ai-publisher' ) );
		}

		$min_wp_version = defined( 'WPAIP_MIN_WP_VERSION' ) ? (string) WPAIP_MIN_WP_VERSION : '6.5';
		if ( version_compare( $wp_version, $min_wp_version, '<' ) ) {
			deactivate_plugins( WPAIP_PLUGIN_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: minimum required WordPress version. */
						__( 'WP AI Publisher requires WordPress %s or higher.', 'wp-ai-publisher' ),
						$min_wp_version
					)
				)
			);
		}

		$db = new DB();
		$db->create_tables();
		$db->set_schema_version( WPAIP_DB_SCHEMA_VERSION );

		if ( function_exists( 'wpai_publisher_grant_capabilities' ) ) {
			wpai_publisher_grant_capabilities();
		}

		update_option( 'wpai_publisher_version', WPAIP_VERSION, false );

		if ( false === get_option( 'wpai_publisher_settings', false ) ) {
			add_option( 'wpai_publisher_settings', wpai_publisher_default_settings(), '', false );
		}
	}
}
