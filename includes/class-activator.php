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

		if ( version_compare( $wp_version, '7.0', '<' ) ) {
			deactivate_plugins( WPAIP_PLUGIN_BASENAME );
			wp_die( esc_html__( 'WP AI Publisher requires WordPress 7.0 or higher.', 'wp-ai-publisher' ) );
		}

		$db = new DB();
		$db->create_tables();
		$db->set_schema_version( WPAIP_DB_SCHEMA_VERSION );

		update_option( 'wpai_publisher_version', WPAIP_VERSION, false );
		if ( class_exists( __NAMESPACE__ . '\\Article_Types' ) ) {
			Article_Types::maybe_create_defaults();
		}

		if ( false === get_option( 'wpai_publisher_settings', false ) ) {
			add_option( 'wpai_publisher_settings', wpai_publisher_default_settings(), '', false );
		}
	}
}
