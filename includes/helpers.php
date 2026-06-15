<?php
/**
 * Shared helper functions for WP AI Publisher.
 *
 * @package WPAIPublisher
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wpai_publisher_default_site_context' ) ) {
	/**
	 * Return default editorial site context settings.
	 *
	 * @return array<string,string>
	 */
	function wpai_publisher_default_site_context() {
		return array(
			'site_profile_name'                    => '',
			'site_description'                     => '',
			'content_niche'                        => '',
			'default_audience'                     => '',
			'default_tone'                         => 'chiaro_didattico_e_operativo',
			'default_language'                     => 'it',
			'default_editor'                       => 'classic',
			'default_post_status_after_generation' => 'draft',
			'allowed_categories'                   => '',
			'preferred_tags'                       => '',
			'excluded_topics'                      => '',
			'internal_link_strategy'               => 'semantic_targets',
			'seo_plugin_preference'                => 'aioseo',
			'writing_rules'                        => '',
			'forbidden_claims'                     => '',
			'brand_terms'                          => '',
			'content_format_preference'            => 'tutorial_html_classic',
		);
	}
}

if ( ! function_exists( 'wpai_publisher_default_settings' ) ) {
	/**
	 * Return default plugin settings.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_default_settings() {
		return array(
			'ai_provider_preference'        => 'wordpress_ai_client_only',
			'default_text_model'            => '',
			'enable_logging'                => true,
			'log_retention_days'            => 30,
			'monthly_cost_limit'            => '',
			'daily_cost_limit'              => '',
			'safe_ai_ability_names'         => '',
			'allow_unverified_ai_abilities' => false,
			'auto_create_draft_from_idea'   => true,
			'workflow_mode'                 => 'simple',
			'site_context'                  => wpai_publisher_default_site_context(),
		);
	}
}


if ( ! function_exists( 'wpai_publisher_normalize_default_tone' ) ) {
	/**
	 * Normalize legacy/corrupted default tone values to sanitize_key-compatible choices.
	 *
	 * @param string $value Raw default tone key.
	 * @return string
	 */
	function wpai_publisher_normalize_default_tone( $value ) {
		$value = sanitize_key( (string) $value );
		$legacy_map = array(
			'chiarodidatticoeoperativo'   => 'chiaro_didattico_e_operativo',
			'chiaro_didattico_operativo' => 'chiaro_didattico_e_operativo',
		);

		return $legacy_map[ $value ] ?? $value;
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

		$defaults                                  = wpai_publisher_default_settings();
		$settings['site_context']                  = wpai_publisher_normalize_site_context( $settings['site_context'] ?? array() );
		$settings['safe_ai_ability_names']         = sanitize_textarea_field( (string) ( $settings['safe_ai_ability_names'] ?? '' ) );
		$settings['allow_unverified_ai_abilities'] = ! empty( $settings['allow_unverified_ai_abilities'] );
		$settings['auto_create_draft_from_idea']   = ! empty( $settings['auto_create_draft_from_idea'] );
		$settings['workflow_mode']                 = in_array( sanitize_key( (string) ( $settings['workflow_mode'] ?? 'simple' ) ), array( 'simple', 'advanced' ), true ) ? sanitize_key( (string) $settings['workflow_mode'] ) : 'simple';

		$allowed = array_keys( $defaults );
		return array_intersect_key( $settings, array_flip( $allowed ) );
	}
}

if ( ! function_exists( 'wpai_publisher_normalize_site_context' ) ) {
	/**
	 * Normalize the editorial site context and enforce supported select values.
	 *
	 * @param mixed $context Raw site context.
	 * @return array<string,string>
	 */
	function wpai_publisher_normalize_site_context( $context ) {
		$context  = is_array( $context ) ? $context : array();
		$defaults = wpai_publisher_default_site_context();
		$context  = wp_parse_args( $context, $defaults );

		$text_fields = array( 'site_profile_name', 'content_niche', 'default_audience' );
		foreach ( $text_fields as $field ) {
			$context[ $field ] = sanitize_text_field( (string) ( $context[ $field ] ?? $defaults[ $field ] ) );
		}

		$textarea_fields = array( 'site_description', 'allowed_categories', 'preferred_tags', 'excluded_topics', 'writing_rules', 'forbidden_claims', 'brand_terms' );
		foreach ( $textarea_fields as $field ) {
			$context[ $field ] = sanitize_textarea_field( (string) ( $context[ $field ] ?? $defaults[ $field ] ) );
		}

		$allowed_values = wpai_publisher_site_context_allowed_values();
		foreach ( $allowed_values as $field => $values ) {
			$value = sanitize_key( (string) ( $context[ $field ] ?? $defaults[ $field ] ) );
			if ( 'default_tone' === $field ) {
				$value = wpai_publisher_normalize_default_tone( $value );
			}
			if ( ! in_array( $value, $values, true ) ) {
				$value = sanitize_key( (string) $defaults[ $field ] );
			}
			$context[ $field ] = $value;
		}

		$context['default_editor']                       = 'classic';
		$context['default_post_status_after_generation'] = in_array( $context['default_post_status_after_generation'], array( 'draft', 'pending', 'publish' ), true ) ? $context['default_post_status_after_generation'] : 'draft';

		return array_intersect_key( $context, $defaults );
	}
}

if ( ! function_exists( 'wpai_publisher_site_context_allowed_values' ) ) {
	/**
	 * Return allowed select values for site context.
	 *
	 * @return array<string,array<int,string>>
	 */
	function wpai_publisher_site_context_allowed_values() {
		return array(
			'default_tone'                         => array( 'chiaro_didattico_e_operativo', 'professionale_tecnico', 'divulgativo_semplice', 'commerciale_informativo', 'editoriale_narrativo', 'personalizzato' ),
			'default_language'                     => array( 'it', 'en', 'fr', 'es', 'de' ),
			'default_editor'                       => array( 'classic' ),
			'default_post_status_after_generation' => array( 'draft', 'pending', 'publish' ),
			'internal_link_strategy'               => array( 'semantic_targets', 'future_existing_content', 'disabled' ),
			'seo_plugin_preference'                => array( 'aioseo', 'none', 'other_future' ),
			'content_format_preference'            => array( 'tutorial_html_classic', 'informational_article', 'product_sheet', 'local_guide', 'affiliate_content', 'other_future' ),
		);
	}
}

if ( ! function_exists( 'wpai_publisher_get_site_context' ) ) {
	/**
	 * Return normalized editorial site context.
	 *
	 * @return array<string,string>
	 */
	function wpai_publisher_get_site_context() {
		$settings = wpai_publisher_get_settings();
		return wpai_publisher_normalize_site_context( $settings['site_context'] ?? array() );
	}
}

if ( ! function_exists( 'wpai_publisher_is_site_context_configured' ) ) {
	/**
	 * Determine whether the minimum editorial context has been configured.
	 *
	 * @param mixed $context Optional context.
	 * @return bool
	 */
	function wpai_publisher_is_site_context_configured( $context = null ) {
		$context = null === $context ? wpai_publisher_get_site_context() : wpai_publisher_normalize_site_context( $context );
		return '' !== trim( (string) $context['site_description'] ) || '' !== trim( (string) $context['content_niche'] );
	}
}

if ( ! function_exists( 'wpai_publisher_split_context_list' ) ) {
	/**
	 * Split comma/newline-separated setting values.
	 *
	 * @param string $value Raw list.
	 * @return array<int,string>
	 */
	function wpai_publisher_split_context_list( $value ) {
		$parts = preg_split( '/[\r\n,]+/', (string) $value );
		if ( ! is_array( $parts ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'trim', $parts ) ) ) );
	}
}

if ( ! function_exists( 'wpai_publisher_site_context_label' ) ) {
	/**
	 * Return display label for site context values.
	 *
	 * @param string $field Field key.
	 * @param string $value Stored value.
	 * @return string
	 */
	function wpai_publisher_site_context_label( $field, $value ) {
		$labels = array(
			'default_tone' => array(
				'chiaro_didattico_e_operativo' => __( 'chiaro, didattico e operativo', 'wp-ai-publisher' ),
				'professionale_tecnico'      => __( 'professionale e tecnico', 'wp-ai-publisher' ),
				'divulgativo_semplice'       => __( 'divulgativo e semplice', 'wp-ai-publisher' ),
				'commerciale_informativo'    => __( 'commerciale ma informativo', 'wp-ai-publisher' ),
				'editoriale_narrativo'       => __( 'editoriale e narrativo', 'wp-ai-publisher' ),
				'personalizzato'             => __( 'personalizzato', 'wp-ai-publisher' ),
			),
			'default_language' => array(
				'it' => __( 'Italiano', 'wp-ai-publisher' ),
				'en' => __( 'Inglese', 'wp-ai-publisher' ),
				'fr' => __( 'Francese', 'wp-ai-publisher' ),
				'es' => __( 'Spagnolo', 'wp-ai-publisher' ),
				'de' => __( 'Tedesco', 'wp-ai-publisher' ),
			),
			'default_editor' => array(
				'classic' => __( 'Editor Classico', 'wp-ai-publisher' ),
			),
			'default_post_status_after_generation' => array(
				'draft'   => __( 'Bozza', 'wp-ai-publisher' ),
				'pending' => __( 'In attesa di revisione', 'wp-ai-publisher' ),
				'publish' => __( 'Pubblicato', 'wp-ai-publisher' ),
			),
			'internal_link_strategy' => array(
				'semantic_targets'        => __( 'Target semantici, non URL', 'wp-ai-publisher' ),
				'future_existing_content' => __( 'Cerca contenuti esistenti, fase futura', 'wp-ai-publisher' ),
				'disabled'                => __( 'Disabilita suggerimenti link', 'wp-ai-publisher' ),
			),
			'seo_plugin_preference' => array(
				'aioseo'       => __( 'AIOSEO', 'wp-ai-publisher' ),
				'none'         => __( 'Nessuno', 'wp-ai-publisher' ),
				'other_future' => __( 'Altro, futuro', 'wp-ai-publisher' ),
			),
			'content_format_preference' => array(
				'tutorial_html_classic' => __( 'Tutorial HTML per Editor Classico', 'wp-ai-publisher' ),
				'informational_article' => __( 'Articolo informativo', 'wp-ai-publisher' ),
				'product_sheet'         => __( 'Scheda prodotto', 'wp-ai-publisher' ),
				'local_guide'           => __( 'Guida locale', 'wp-ai-publisher' ),
				'affiliate_content'     => __( 'Contenuto affiliato', 'wp-ai-publisher' ),
				'other_future'          => __( 'Altro, futuro', 'wp-ai-publisher' ),
			),
		);

		return $labels[ $field ][ $value ] ?? $value;
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
