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

		// Allow users with the dedicated plugin capability (but not manage_options)
		// to save the settings form posted to options.php.
		add_filter( 'option_page_capability_wpai_publisher_settings_group', 'wpai_publisher_capability' );
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

		$output['ai_provider_preference']        = 'wordpress_ai_client_only';
		$output['default_text_model']            = isset( $input['default_text_model'] ) ? sanitize_text_field( $input['default_text_model'] ) : $defaults['default_text_model'];
		$output['enable_logging']                = ! empty( $input['enable_logging'] );
		$output['log_retention_days']            = isset( $input['log_retention_days'] ) ? max( 1, min( 365, absint( $input['log_retention_days'] ) ) ) : $defaults['log_retention_days'];
		$output['daily_cost_limit']              = $this->sanitize_cost_limit( $input['daily_cost_limit'] ?? '' );
		$output['monthly_cost_limit']            = $this->sanitize_cost_limit( $input['monthly_cost_limit'] ?? '' );
		$output['safe_ai_ability_names']         = isset( $input['safe_ai_ability_names'] ) ? sanitize_textarea_field( $input['safe_ai_ability_names'] ) : $defaults['safe_ai_ability_names'];
		$output['allow_unverified_ai_abilities'] = ! empty( $input['allow_unverified_ai_abilities'] );
		$output['auto_create_draft_from_idea']   = ! empty( $input['auto_create_draft_from_idea'] );
		$output['delete_data_on_uninstall']      = ! empty( $input['delete_data_on_uninstall'] );
		$workflow_mode                           = sanitize_key( (string) ( $input['workflow_mode'] ?? $defaults['workflow_mode'] ) );
		$output['workflow_mode']                 = in_array( $workflow_mode, array( 'simple', 'advanced' ), true ) ? $workflow_mode : 'simple';
		$output['site_context']                  = $this->sanitize_site_context( $input['site_context'] ?? array() );

		return $output;
	}

	/**
	 * Sanitize editorial site context settings.
	 *
	 * @param mixed $input Raw site context.
	 * @return array<string,string>
	 */
	private function sanitize_site_context( $input ) {
		$defaults = wpai_publisher_default_site_context();
		$current  = wpai_publisher_get_site_context();
		$input    = is_array( $input ) ? $input : array();
		$output   = array();

		foreach ( array( 'site_profile_name' ) as $field ) {
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_text_field( $input[ $field ] ) : ( $current[ $field ] ?? $defaults[ $field ] );
		}

		foreach ( array( 'site_description', 'content_niche', 'default_audience', 'allowed_categories', 'preferred_tags', 'excluded_topics', 'writing_rules', 'forbidden_claims', 'brand_terms' ) as $field ) {
			$fallback = array_key_exists( $field, $current ) ? $current[ $field ] : $defaults[ $field ];
			$output[ $field ] = isset( $input[ $field ] ) ? sanitize_textarea_field( $input[ $field ] ) : $fallback;
		}

		if ( array_key_exists( 'allowed_category_ids', $input ) || ! empty( $input['__allowed_category_ids_present'] ) ) {
			$output['allowed_category_ids'] = wpai_publisher_sanitize_category_ids( $input['allowed_category_ids'] ?? array() );
		} else {
			$output['allowed_category_ids'] = array_key_exists( 'allowed_category_ids', $current ) ? (array) $current['allowed_category_ids'] : $defaults['allowed_category_ids'];
		}

		$allowed_values = wpai_publisher_site_context_allowed_values();
		foreach ( $allowed_values as $field => $allowed ) {
			$fallback = array_key_exists( $field, $current ) ? $current[ $field ] : $defaults[ $field ];
			$value    = isset( $input[ $field ] ) ? sanitize_key( $input[ $field ] ) : sanitize_key( $fallback );
			if ( 'default_tone' === $field && function_exists( 'wpai_publisher_normalize_default_tone' ) ) {
				$value = wpai_publisher_normalize_default_tone( $value );
			}
			if ( ! in_array( $value, $allowed, true ) ) {
				$value = sanitize_key( $defaults[ $field ] );
			}
			$output[ $field ] = $value;
		}

		// Current phase is Classic Editor only. Gutenberg remains a future inactive target.
		$output['default_editor'] = 'classic';

		if ( ! in_array( $output['default_post_status_after_generation'], array( 'draft', 'pending', 'publish' ), true ) ) {
			$output['default_post_status_after_generation'] = 'draft';
		}

		return wpai_publisher_normalize_site_context( $output );
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
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
