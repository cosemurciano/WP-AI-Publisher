<?php
/**
 * Shared helper functions for WP AI Publisher.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpai_publisher_default_settings' ) ) {
	/**
	 * Return default plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_default_settings() {
		return array(
			'ai_provider_preference'  => 'wordpress_ai_client_only',
			'default_text_model'      => '',
			'enable_logging'          => true,
			'log_retention_days'      => 30,
			'monthly_cost_limit'      => '',
			'daily_cost_limit'        => '',
			'github_updater_enabled'  => false,
		);
	}
}

if ( ! function_exists( 'wpai_publisher_normalize_settings' ) ) {
	/**
	 * Normalize settings and remove obsolete direct-provider keys.
	 *
	 * @param mixed $settings Raw settings.
	 * @return array<string,mixed>
	 */
	function wpai_publisher_normalize_settings( $settings ) {
		$settings = is_array( $settings ) ? $settings : array();
		$settings = wp_parse_args( $settings, wpai_publisher_default_settings() );

		// Migrazione leggera: da 0.3.5 il plugin usa solo il sistema AI di WordPress.
		$settings['ai_provider_preference'] = 'wordpress_ai_client_only';
		unset( $settings['fallback_to_openai_direct'], $settings['default_image_model'] );

		$allowed = array_keys( wpai_publisher_default_settings() );
		return array_intersect_key( $settings, array_flip( $allowed ) );
	}
}

if ( ! function_exists( 'wpai_publisher_get_settings' ) ) {
	/**
	 * Return merged plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_get_settings() {
		$raw_settings = get_option( 'wpai_publisher_settings', array() );
		$settings     = wpai_publisher_normalize_settings( $raw_settings );

		if ( is_array( $raw_settings ) && $settings !== $raw_settings ) {
			update_option( 'wpai_publisher_settings', $settings, false );
		}

		return $settings;
	}
}

if ( ! function_exists( 'wpai_publisher_badge_class' ) ) {
	/**
	 * Build a CSS badge modifier class from a status key.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	function wpai_publisher_badge_class( $status ) {
		$status = sanitize_key( $status );

		return 'wpai-badge wpai-badge--' . $status;
	}
}
