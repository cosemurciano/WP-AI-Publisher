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

		$output['enable_logging']                = ! empty( $input['enable_logging'] );
		$output['log_retention_days']            = isset( $input['log_retention_days'] ) ? max( 1, min( 365, absint( $input['log_retention_days'] ) ) ) : $defaults['log_retention_days'];
		$output['safe_ai_ability_names']         = isset( $input['safe_ai_ability_names'] ) ? sanitize_textarea_field( $input['safe_ai_ability_names'] ) : $defaults['safe_ai_ability_names'];
		$output['allow_unverified_ai_abilities'] = ! empty( $input['allow_unverified_ai_abilities'] );
		$output['auto_create_draft_from_idea']   = ! empty( $input['auto_create_draft_from_idea'] );
		$output['delete_data_on_uninstall']      = ! empty( $input['delete_data_on_uninstall'] );
		$output['ai_model']                      = isset( $input['ai_model'] ) ? sanitize_text_field( $input['ai_model'] ) : $defaults['ai_model'];
		$output['ai_http_timeout']               = isset( $input['ai_http_timeout'] ) ? max( 15, min( 600, absint( $input['ai_http_timeout'] ) ) ) : $defaults['ai_http_timeout'];
		$output['ai_max_output_tokens']          = isset( $input['ai_max_output_tokens'] ) ? max( 0, min( 32000, absint( $input['ai_max_output_tokens'] ) ) ) : $defaults['ai_max_output_tokens'];
		$raw_temperature                         = isset( $input['ai_temperature'] ) ? trim( (string) $input['ai_temperature'] ) : '';
		$output['ai_temperature']                = ( '' === $raw_temperature ) ? '' : (string) max( 0, min( 2, (float) $raw_temperature ) );
		$output['generate_featured_image']       = ! empty( $input['generate_featured_image'] );
		$output['generate_inline_images']        = ! empty( $input['generate_inline_images'] );
		$output['max_inline_images']             = isset( $input['max_inline_images'] ) ? max( 0, min( 10, absint( $input['max_inline_images'] ) ) ) : $defaults['max_inline_images'];
		$output['use_openai_file_search']        = ! empty( $input['use_openai_file_search'] );
		$output['openai_vector_store_ids']       = isset( $input['openai_vector_store_ids'] ) ? sanitize_textarea_field( (string) $input['openai_vector_store_ids'] ) : $defaults['openai_vector_store_ids'];
		$output['openai_responses_model']        = isset( $input['openai_responses_model'] ) ? sanitize_text_field( (string) $input['openai_responses_model'] ) : $defaults['openai_responses_model'];
		$output['telegram_enabled']              = ! empty( $input['telegram_enabled'] );
		$output['telegram_allowed_chat_ids']     = isset( $input['telegram_allowed_chat_ids'] ) ? sanitize_textarea_field( (string) $input['telegram_allowed_chat_ids'] ) : $defaults['telegram_allowed_chat_ids'];
		$output['telegram_article_type_id']      = isset( $input['telegram_article_type_id'] ) ? absint( $input['telegram_article_type_id'] ) : $defaults['telegram_article_type_id'];
		$telegram_lang                           = isset( $input['telegram_language'] ) ? sanitize_key( (string) $input['telegram_language'] ) : $defaults['telegram_language'];
		$output['telegram_language']             = in_array( $telegram_lang, array( 'it', 'en', 'fr', 'es', 'de' ), true ) ? $telegram_lang : 'it';
		$output['telegram_reply_enabled']        = ! empty( $input['telegram_reply_enabled'] );
		$output['telegram_interactive']          = ! empty( $input['telegram_interactive'] );
		$output['facebook_enabled']              = ! empty( $input['facebook_enabled'] );
		$output['facebook_page_id']              = isset( $input['facebook_page_id'] ) ? sanitize_text_field( (string) $input['facebook_page_id'] ) : $defaults['facebook_page_id'];
		$fb_mode                                 = isset( $input['facebook_share_mode'] ) ? sanitize_key( (string) $input['facebook_share_mode'] ) : 'link';
		$output['facebook_share_mode']           = in_array( $fb_mode, array( 'link', 'photo' ), true ) ? $fb_mode : 'link';
		$output['facebook_message_template']     = isset( $input['facebook_message_template'] ) ? sanitize_textarea_field( (string) $input['facebook_message_template'] ) : $defaults['facebook_message_template'];
		$output['facebook_use_ai_caption']       = ! empty( $input['facebook_use_ai_caption'] );
		$output['facebook_default_share']        = ! empty( $input['facebook_default_share'] );
		$output['instagram_enabled']             = ! empty( $input['instagram_enabled'] );
		$output['instagram_user_id']             = isset( $input['instagram_user_id'] ) ? sanitize_text_field( (string) $input['instagram_user_id'] ) : $defaults['instagram_user_id'];
		$output['instagram_caption_template']    = isset( $input['instagram_caption_template'] ) ? sanitize_textarea_field( (string) $input['instagram_caption_template'] ) : $defaults['instagram_caption_template'];
		$output['instagram_use_ai_caption']      = ! empty( $input['instagram_use_ai_caption'] );
		$output['instagram_default_share']       = ! empty( $input['instagram_default_share'] );
		$output['linkedin_enabled']              = ! empty( $input['linkedin_enabled'] );
		$output['linkedin_org_id']               = isset( $input['linkedin_org_id'] ) ? sanitize_text_field( (string) $input['linkedin_org_id'] ) : $defaults['linkedin_org_id'];
		$output['linkedin_message_template']     = isset( $input['linkedin_message_template'] ) ? sanitize_textarea_field( (string) $input['linkedin_message_template'] ) : $defaults['linkedin_message_template'];
		$output['linkedin_use_ai_caption']       = ! empty( $input['linkedin_use_ai_caption'] );
		$output['linkedin_default_share']        = ! empty( $input['linkedin_default_share'] );
		$output['auto_share_imported']           = ! empty( $input['auto_share_imported'] );
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

}
