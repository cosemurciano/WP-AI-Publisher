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
			'allowed_category_ids'                 => array(),
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
			'enable_logging'                => true,
			'log_retention_days'            => 30,
			'safe_ai_ability_names'         => '',
			'allow_unverified_ai_abilities' => false,
			'auto_create_draft_from_idea'   => true,
			'delete_data_on_uninstall'      => false,
			'ai_model'                      => '',
			'ai_http_timeout'               => 180,
			'ai_max_output_tokens'          => 4000,
			'ai_temperature'                => '',
			'generate_featured_image'       => false,
			'generate_inline_images'        => false,
			'max_inline_images'             => 3,
			'use_openai_file_search'        => false,
			'openai_vector_store_ids'       => '',
			'openai_responses_model'        => '',
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

		// Legacy keys non più usate (provider fisso al sistema AI di WordPress, modelli/limiti costo legacy).
		unset( $settings['fallback_to_openai_direct'], $settings['default_image_model'], $settings['ai_provider_preference'], $settings['default_text_model'], $settings['monthly_cost_limit'], $settings['daily_cost_limit'] );

		$defaults                                  = wpai_publisher_default_settings();
		$settings['site_context']                  = wpai_publisher_normalize_site_context( $settings['site_context'] ?? array() );
		$settings['safe_ai_ability_names']         = sanitize_textarea_field( (string) ( $settings['safe_ai_ability_names'] ?? '' ) );
		$settings['allow_unverified_ai_abilities'] = ! empty( $settings['allow_unverified_ai_abilities'] );
		$settings['auto_create_draft_from_idea']   = ! empty( $settings['auto_create_draft_from_idea'] );
		$settings['delete_data_on_uninstall']      = ! empty( $settings['delete_data_on_uninstall'] );
		$settings['ai_model']                      = sanitize_text_field( (string) ( $settings['ai_model'] ?? '' ) );
		$settings['ai_http_timeout']               = max( 15, min( 600, (int) ( $settings['ai_http_timeout'] ?? 180 ) ) );
		$settings['ai_max_output_tokens']          = max( 0, min( 32000, (int) ( $settings['ai_max_output_tokens'] ?? 4000 ) ) );
		$raw_temperature                           = $settings['ai_temperature'] ?? '';
		$settings['ai_temperature']                = ( '' === $raw_temperature || null === $raw_temperature ) ? '' : (string) max( 0, min( 2, (float) $raw_temperature ) );
		$settings['generate_featured_image']       = ! empty( $settings['generate_featured_image'] );
		$settings['generate_inline_images']        = ! empty( $settings['generate_inline_images'] );
		$settings['max_inline_images']             = max( 0, min( 10, (int) ( $settings['max_inline_images'] ?? 3 ) ) );
		$settings['use_openai_file_search']        = ! empty( $settings['use_openai_file_search'] );
		$settings['openai_vector_store_ids']       = sanitize_textarea_field( (string) ( $settings['openai_vector_store_ids'] ?? '' ) );
		$settings['openai_responses_model']        = sanitize_text_field( (string) ( $settings['openai_responses_model'] ?? '' ) );

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

		$text_fields = array( 'site_profile_name' );
		foreach ( $text_fields as $field ) {
			$context[ $field ] = sanitize_text_field( (string) ( $context[ $field ] ?? $defaults[ $field ] ) );
		}

		$textarea_fields = array( 'site_description', 'content_niche', 'default_audience', 'allowed_categories', 'preferred_tags', 'excluded_topics', 'writing_rules', 'forbidden_claims', 'brand_terms' );
		foreach ( $textarea_fields as $field ) {
			$context[ $field ] = sanitize_textarea_field( (string) ( $context[ $field ] ?? $defaults[ $field ] ) );
		}

		$context['allowed_category_ids'] = wpai_publisher_sanitize_category_ids( $context['allowed_category_ids'] ?? array() );

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

if ( ! function_exists( 'wpai_publisher_sanitize_category_ids' ) ) {
	/**
	 * Sanitize category IDs and keep only existing WordPress categories.
	 *
	 * @param mixed $ids Raw IDs.
	 * @return array<int,int>
	 */
	function wpai_publisher_sanitize_category_ids( $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
		if ( empty( $ids ) || ! function_exists( 'get_terms' ) ) {
			return $ids;
		}

		$existing = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) );
		if ( is_wp_error( $existing ) ) {
			return array();
		}

		return array_values( array_intersect( $ids, array_map( 'absint', (array) $existing ) ) );
	}
}

if ( ! function_exists( 'wpai_publisher_resolve_allowed_category_ids' ) ) {
	/**
	 * Resolve final allowed categories by intersecting global and article-type boundaries.
	 *
	 * @param array<string,mixed> $article_type Article type data.
	 * @param array<string,mixed>|null $site_context Site context.
	 * @return array{ids:array<int,int>,has_restriction:bool,conflict:bool,message:string}
	 */
	function wpai_publisher_resolve_allowed_category_ids( $article_type = array(), $site_context = null ) {
		$site_context = null === $site_context ? wpai_publisher_get_site_context() : wpai_publisher_normalize_site_context( $site_context );
		$global_ids   = wpai_publisher_sanitize_category_ids( $site_context['allowed_category_ids'] ?? array() );
		$type_ids     = wpai_publisher_sanitize_category_ids( $article_type['allowed_category_ids'] ?? array() );

		if ( ! empty( $global_ids ) && ! empty( $type_ids ) ) {
			$ids = array_values( array_intersect( $global_ids, $type_ids ) );
			return array(
				'ids'             => $ids,
				'has_restriction' => true,
				'conflict'        => empty( $ids ),
				'message'         => empty( $ids ) ? __( 'Le categorie consentite nelle impostazioni globali e nella tipologia articolo non coincidono. Modifica le impostazioni o la tipologia articolo.', 'wp-ai-publisher' ) : '',
			);
		}

		$ids = ! empty( $type_ids ) ? $type_ids : $global_ids;
		return array( 'ids' => $ids, 'has_restriction' => ! empty( $ids ), 'conflict' => false, 'message' => '' );
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

if ( ! function_exists( 'wpai_publisher_get_raw_settings' ) ) {
	/**
	 * Return raw plugin settings exactly as stored.
	 *
	 * This diagnostic helper is intentionally read-only: it does not sanitize,
	 * normalize, or persist settings.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_get_raw_settings() {
		$settings = get_option( 'wpai_publisher_settings', array() );
		return is_array( $settings ) ? $settings : array();
	}
}

if ( ! function_exists( 'wpai_publisher_get_raw_site_context' ) ) {
	/**
	 * Return raw editorial site context exactly as stored.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_get_raw_site_context() {
		$settings = wpai_publisher_get_raw_settings();
		$context  = $settings['site_context'] ?? array();
		return is_array( $context ) ? $context : array();
	}
}

if ( ! function_exists( 'wpai_publisher_get_settings' ) ) {
	/**
	 * Return merged plugin settings.
	 *
	 * Getter functions must stay read-only. Settings are persisted only by the
	 * explicit WordPress settings save flow.
	 *
	 * @return array<string,mixed>
	 */
	function wpai_publisher_get_settings() {
		return wpai_publisher_normalize_settings( wpai_publisher_get_raw_settings() );
	}
}

if ( ! function_exists( 'wpai_publisher_capability' ) ) {
	/**
	 * Return the capability required to manage WP AI Publisher.
	 *
	 * Defaults to a dedicated capability so administrators can delegate the
	 * editorial workflow to other roles (e.g. editor) without granting full
	 * `manage_options` access. Filterable for sites that prefer a core capability.
	 *
	 * @return string
	 */
	function wpai_publisher_capability() {
		$capability = apply_filters( 'wpai_publisher_capability', 'manage_wp_ai_publisher' );

		return is_string( $capability ) && '' !== $capability ? $capability : 'manage_wp_ai_publisher';
	}
}

if ( ! function_exists( 'wpai_publisher_grant_capabilities' ) ) {
	/**
	 * Grant the plugin capability to administrators.
	 *
	 * Runs on activation and on upgrade so the dedicated capability never locks
	 * out existing administrators. Other roles can be granted the capability via
	 * the `wpai_publisher_capability` filter plus a role->add_cap() call.
	 *
	 * @return void
	 */
	function wpai_publisher_grant_capabilities() {
		if ( ! function_exists( 'get_role' ) ) {
			return;
		}

		$capability = wpai_publisher_capability();
		$admin      = get_role( 'administrator' );
		if ( $admin && ! $admin->has_cap( $capability ) ) {
			$admin->add_cap( $capability );
		}
	}
}

if ( ! function_exists( 'wpai_publisher_remove_capabilities' ) ) {
	/**
	 * Remove the plugin capability from all roles. Used by opt-in uninstall cleanup.
	 *
	 * @return void
	 */
	function wpai_publisher_remove_capabilities() {
		if ( ! function_exists( 'wp_roles' ) ) {
			return;
		}

		$capability = wpai_publisher_capability();
		foreach ( wp_roles()->role_objects as $role ) {
			if ( is_object( $role ) && $role->has_cap( $capability ) ) {
				$role->remove_cap( $capability );
			}
		}
	}
}

if ( ! function_exists( 'wpai_publisher_get_site_generation_context' ) ) {
	/**
	 * Collect existing site data to pass to the AI: tags, categories and internal
	 * link targets (published posts). Lists are filterable/limited to bound prompt size.
	 *
	 * @return array{tags:array<int,string>,categories:array<int,array{id:int,name:string}>,internal_links:array<int,array{title:string,url:string}>}
	 */
	function wpai_publisher_get_site_generation_context() {
		$tags = array();
		if ( function_exists( 'get_terms' ) ) {
			$max   = max( 0, (int) apply_filters( 'wpai_publisher_context_max_tags', 200 ) );
			$terms = $max > 0 ? get_terms( array( 'taxonomy' => 'post_tag', 'hide_empty' => false, 'number' => $max, 'orderby' => 'count', 'order' => 'DESC', 'fields' => 'names' ) ) : array();
			if ( ! is_wp_error( $terms ) ) {
				$tags = array_values( array_filter( array_map( 'sanitize_text_field', (array) $terms ) ) );
			}
		}

		$categories = array();
		if ( function_exists( 'get_categories' ) ) {
			foreach ( get_categories( array( 'hide_empty' => false ) ) as $category ) {
				$categories[] = array( 'id' => (int) $category->term_id, 'name' => sanitize_text_field( (string) $category->name ) );
			}
		}

		$internal_links = array();
		if ( function_exists( 'get_posts' ) ) {
			$max   = max( 0, (int) apply_filters( 'wpai_publisher_context_max_links', 50 ) );
			$posts = $max > 0 ? get_posts( array( 'post_status' => 'publish', 'post_type' => 'post', 'numberposts' => $max, 'orderby' => 'date', 'order' => 'DESC' ) ) : array();
			foreach ( (array) $posts as $post ) {
				$url = get_permalink( $post );
				if ( $url ) {
					$internal_links[] = array( 'title' => sanitize_text_field( get_the_title( $post ) ), 'url' => esc_url_raw( $url ) );
				}
			}
		}

		return array( 'tags' => $tags, 'categories' => $categories, 'internal_links' => $internal_links );
	}
}

if ( ! function_exists( 'wpai_publisher_get_openai_api_key' ) ) {
	/**
	 * Resolve the OpenAI API key for the direct Responses API channel.
	 *
	 * The key is never stored in the plugin settings/DB: it is read from the
	 * WPAIP_OPENAI_API_KEY constant (define it in wp-config.php) or supplied via
	 * the wpai_publisher_openai_api_key filter.
	 *
	 * @return string API key or empty string.
	 */
	function wpai_publisher_get_openai_api_key() {
		$key = defined( 'WPAIP_OPENAI_API_KEY' ) ? (string) WPAIP_OPENAI_API_KEY : '';

		/**
		 * Filter the OpenAI API key used by the direct Responses API channel.
		 *
		 * @param string $key API key.
		 */
		$key = (string) apply_filters( 'wpai_publisher_openai_api_key', $key );

		return trim( $key );
	}
}

if ( ! function_exists( 'wpai_publisher_get_openai_vector_store_ids' ) ) {
	/**
	 * Parse the configured OpenAI vector store IDs into a clean list.
	 *
	 * Accepts comma/space/newline separated values; keeps only plausible
	 * identifiers (alphanumeric, underscore, dash).
	 *
	 * @param string $raw Raw setting value (optional; reads settings when null).
	 * @return array<int,string>
	 */
	function wpai_publisher_get_openai_vector_store_ids( $raw = null ) {
		if ( null === $raw ) {
			$settings = wpai_publisher_get_settings();
			$raw      = (string) ( $settings['openai_vector_store_ids'] ?? '' );
		}
		$parts = preg_split( '/[\s,]+/', (string) $raw );
		$ids   = array();
		foreach ( (array) $parts as $part ) {
			$part = trim( (string) $part );
			if ( '' === $part ) {
				continue;
			}
			if ( preg_match( '/^[A-Za-z0-9_\-]+$/', $part ) ) {
				$ids[] = $part;
			}
		}

		return array_values( array_unique( $ids ) );
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



if ( ! function_exists( 'wpai_publisher_article_types_enabled' ) ) {
	function wpai_publisher_article_types_enabled() {
		return defined( 'WPAIP_ENABLE_ARTICLE_TYPE_REPOSITORY' ) ? true === WPAIP_ENABLE_ARTICLE_TYPE_REPOSITORY : true;
	}
}

if ( ! function_exists( 'wpai_publisher_article_types_available' ) ) {
	function wpai_publisher_article_types_available() {
		return wpai_publisher_article_types_enabled() && class_exists( '\WPAIPublisher\Article_Type_Repository' );
	}
}

if ( ! function_exists( 'wpai_publisher_article_type_repository' ) ) {
	function wpai_publisher_article_type_repository() {
		if ( ! wpai_publisher_article_types_available() ) { return null; }
		return new \WPAIPublisher\Article_Type_Repository();
	}
}

if ( ! function_exists( 'wpai_publisher_get_active_article_types_safe' ) ) {
	function wpai_publisher_get_active_article_types_safe() {
		$repo = wpai_publisher_article_type_repository();
		return $repo ? $repo->get_active_article_types() : array();
	}
}

if ( ! function_exists( 'wpai_publisher_is_active_article_type_safe' ) ) {
	function wpai_publisher_is_active_article_type_safe( $id ) {
		$repo = wpai_publisher_article_type_repository();
		return $repo ? $repo->is_active_article_type( absint( $id ) ) : false;
	}
}

if ( ! function_exists( 'wpai_publisher_get_article_type_config_safe' ) ) {
	function wpai_publisher_get_article_type_config_safe( $id ) {
		$fallback = array( 'id' => 0, 'ID' => 0, 'name' => '', 'title' => '', 'allowed_category_ids' => array(), 'active' => false, 'is_active' => false );
		$repo = wpai_publisher_article_type_repository();
		if ( ! $repo || ! $repo->is_active_article_type( absint( $id ) ) ) { return $fallback; }
		$config = $repo->get_article_type( absint( $id ) );
		return is_array( $config ) ? wp_parse_args( $config, $fallback ) : $fallback;
	}
}
