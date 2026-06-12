<?php
/**
 * AI provider adapter.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central adapter for all future AI calls.
 */
class AI_Provider_Adapter {
	/**
	 * Return aggregate AI connection status without remote calls.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status() {
		return array(
			'provider_preference'          => $this->get_provider_preference(),
			'wordpress_ai_client_available' => $this->is_wordpress_ai_client_available(),
			'openai_direct_available'       => $this->is_openai_direct_available(),
			'openai_status'                 => $this->is_openai_direct_available() ? 'configured' : 'not_verified',
		);
	}

	/**
	 * Detect possible WordPress AI Client/API availability defensively.
	 *
	 * @return bool
	 */
	public function is_wordpress_ai_client_available() {
		$classes = array(
			'WP_AI_Client',
			'\\WordPress\\AI\\Client',
			'\\WP_AI\\Client',
		);

		foreach ( $classes as $class_name ) {
			if ( class_exists( $class_name ) ) {
				return true;
			}
		}

		$functions = array(
			'wp_ai_client',
			'wp_get_ai_client',
			'wp_ai_generate_text',
		);

		foreach ( $functions as $function_name ) {
			if ( function_exists( $function_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Detect OpenAI direct configuration without making remote requests.
	 *
	 * @return bool
	 */
	public function is_openai_direct_available() {
		if ( defined( 'OPENAI_API_KEY' ) && '' !== trim( (string) OPENAI_API_KEY ) ) {
			return true;
		}

		if ( defined( 'WPAIP_OPENAI_API_KEY' ) && '' !== trim( (string) WPAIP_OPENAI_API_KEY ) ) {
			return true;
		}

		$environment_key = getenv( 'OPENAI_API_KEY' );
		return is_string( $environment_key ) && '' !== trim( $environment_key );
	}

	/**
	 * Return configured provider preference.
	 *
	 * @return string
	 */
	public function get_provider_preference() {
		$settings = wpai_publisher_get_settings();
		$allowed  = array( 'wordpress_ai_client_first', 'openai_direct_only', 'disabled' );
		$value    = isset( $settings['ai_provider_preference'] ) ? (string) $settings['ai_provider_preference'] : 'wordpress_ai_client_first';

		return in_array( $value, $allowed, true ) ? $value : 'wordpress_ai_client_first';
	}

	/**
	 * Stub for future text generation.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function generate_text( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_text_generation_not_implemented', __( 'Text generation not implemented in phase 1', 'wp-ai-publisher' ) );
	}

	/**
	 * Stub for future image generation.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function generate_image( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_image_generation_not_implemented', __( 'Image generation not implemented in phase 1', 'wp-ai-publisher' ) );
	}

	/**
	 * Stub for future embeddings.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function create_embedding( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_embeddings_not_implemented', __( 'Embeddings not implemented in phase 1', 'wp-ai-publisher' ) );
	}

	/**
	 * Validate structured output basics.
	 *
	 * @param mixed $output Output payload.
	 * @param mixed $schema Optional schema descriptor.
	 * @return true|WP_Error
	 */
	public function validate_structured_output( $output, $schema ) {
		if ( null === $output || '' === $output || array() === $output ) {
			return new WP_Error( 'wpai_empty_structured_output', __( 'Structured output cannot be empty.', 'wp-ai-publisher' ) );
		}

		$requires_json = false;
		if ( is_array( $schema ) && ! empty( $schema['type'] ) && in_array( $schema['type'], array( 'object', 'array', 'json' ), true ) ) {
			$requires_json = true;
		}

		if ( is_object( $schema ) && isset( $schema->type ) && in_array( $schema->type, array( 'object', 'array', 'json' ), true ) ) {
			$requires_json = true;
		}

		if ( $requires_json ) {
			if ( is_array( $output ) || is_object( $output ) ) {
				return true;
			}

			if ( is_string( $output ) ) {
				json_decode( $output, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					return true;
				}
			}

			return new WP_Error( 'wpai_invalid_structured_output', __( 'Structured output must be valid JSON, an array, or an object.', 'wp-ai-publisher' ) );
		}

		return true;
	}
}
