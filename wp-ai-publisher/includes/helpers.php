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
			'ai_provider_preference'     => 'wordpress_ai_client_first',
			'fallback_to_openai_direct'  => true,
			'default_text_model'         => '',
			'default_image_model'        => '',
			'enable_logging'             => true,
			'log_retention_days'         => 30,
			'monthly_cost_limit'         => '',
			'daily_cost_limit'           => '',
			'github_updater_enabled'     => false,
		);
	}
}

if ( ! function_exists( 'wpai_publisher_get_settings' ) ) {
	/**
	 * Return merged plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_get_settings() {
		$settings = get_option( 'wpai_publisher_settings', array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		return wp_parse_args( $settings, wpai_publisher_default_settings() );
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
