<?php
/**
 * Settings registration and sanitization.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings.
 */
class Settings {
	/**
	 * Option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'wpai_publisher_settings';

	/**
	 * Register WordPress settings.
	 *
	 * @return void
	 */
	public function register() {
		register_setting(
			'wpai_publisher_settings_group',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => wpai_publisher_default_settings(),
			)
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ) {
		$defaults = wpai_publisher_default_settings();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		$output['ai_provider_preference'] = 'wordpress_ai_client_only';
		$output['default_text_model']     = isset( $input['default_text_model'] ) ? sanitize_text_field( $input['default_text_model'] ) : $defaults['default_text_model'];
		$output['enable_logging']         = ! empty( $input['enable_logging'] );
		$output['log_retention_days']     = isset( $input['log_retention_days'] ) ? max( 1, min( 365, absint( $input['log_retention_days'] ) ) ) : $defaults['log_retention_days'];
		$output['daily_cost_limit']       = $this->sanitize_cost_limit( $input['daily_cost_limit'] ?? '' );
		$output['monthly_cost_limit']     = $this->sanitize_cost_limit( $input['monthly_cost_limit'] ?? '' );
		$output['github_updater_enabled'] = false;

		return $output;
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Non hai i permessi per accedere a questa pagina.', 'wp-ai-publisher' ) );
		}

		$settings    = wpai_publisher_get_settings();
		$ai_adapter  = new AI_Provider_Adapter();
		$ai_status   = $ai_adapter->get_status();
		$ai_models   = $ai_adapter->get_available_models( 'text' );
		include WPAIP_PLUGIN_DIR . 'admin/views/settings.php';
	}

	/**
	 * Sanitize numeric cost limit while allowing an empty value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private function sanitize_cost_limit( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );

		if ( '' === $value ) {
			return '';
		}

		$value = preg_replace( '/[^0-9.]/', '', $value );
		if ( null === $value || '' === $value ) {
			return '';
		}

		return (string) max( 0, (float) $value );
	}
}
