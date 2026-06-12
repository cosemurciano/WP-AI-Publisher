<?php
/**
 * AI provider adapter.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use Throwable;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central adapter for all future AI calls.
 *
 * The plugin intentionally uses only the WordPress AI system configured on the site.
 * No custom OpenAI key, direct HTTP client, or provider fallback is handled here.
 */
class AI_Provider_Adapter {
	/**
	 * Return aggregate AI connection status without remote calls.
	 *
	 * @return array<string,mixed>
	 */
	public function get_status() {
		$models    = $this->get_available_models( 'text' );
		$abilities = $this->get_available_abilities();

		return array(
			'provider_preference'             => $this->get_provider_preference(),
			'wordpress_ai_client_available'   => $this->is_wordpress_ai_client_available(),
			'wordpress_ai_client_status'      => $this->is_wordpress_ai_client_available() ? 'detected' : 'not_detected',
			'available_text_models'           => $models,
			'available_text_models_count'     => count( $models ),
			'available_abilities'             => $abilities,
			'available_abilities_count'       => count( $abilities ),
			'selected_text_model'             => $this->get_selected_text_model(),
			'openai_direct_available'         => false,
			'openai_status'                   => 'disabled',
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
			'\\WordPress\\AI\\Services\\Services_API',
			'\\WordPress\\AI\\Services\\AI_Service',
			'WP_AI_Abilities_Registry',
			'\\WordPress\\AI\\Abilities\\Registry',
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
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
			'wp_ai_services',
			'wp_ai_get_abilities',
			'wp_ai_get_available_abilities',
		);

		foreach ( $functions as $function_name ) {
			if ( function_exists( $function_name ) ) {
				return true;
			}
		}

		/**
		 * Allows the active WordPress AI integration to explicitly declare availability.
		 *
		 * @param bool $available Detected availability.
		 */
		return (bool) apply_filters( 'wpai_publisher_wordpress_ai_available', false );
	}

	/**
	 * Direct OpenAI is intentionally disabled.
	 *
	 * @return bool
	 */
	public function is_openai_direct_available() {
		return false;
	}

	/**
	 * Return configured provider preference.
	 *
	 * @return string
	 */
	public function get_provider_preference() {
		return 'wordpress_ai_client_only';
	}

	/**
	 * Return selected model from settings.
	 *
	 * @return string
	 */
	public function get_selected_text_model() {
		$settings = wpai_publisher_get_settings();

		return isset( $settings['default_text_model'] ) ? sanitize_text_field( (string) $settings['default_text_model'] ) : '';
	}

	/**
	 * Return available models exposed by the active WordPress AI system.
	 *
	 * The method does not call OpenAI directly. It only reads local PHP APIs, classes,
	 * functions, or filters exposed by the configured WordPress AI layer.
	 *
	 * @param string $type Model type, for example text or image.
	 * @return array<int,array<string,string>>
	 */
	public function get_available_models( $type = 'text' ) {
		$type   = sanitize_key( (string) $type );
		$models = array();

		$models = array_merge( $models, $this->models_from_known_functions( $type ) );
		$models = array_merge( $models, $this->models_from_known_clients( $type ) );

		/**
		 * Allows the site's WordPress AI integration to expose available models.
		 *
		 * Expected shapes accepted by the plugin:
		 * - array( 'gpt-4.1-mini', 'gpt-4.1' )
		 * - array( array( 'id' => 'model-id', 'label' => 'Model label' ) )
		 * - array( 'model-id' => 'Model label' )
		 *
		 * @param array<int|string,mixed> $models Current model list.
		 * @param string                  $type   Model type.
		 */
		$models = apply_filters( 'wpai_publisher_available_ai_models', $models, $type );

		return $this->normalize_models( $models );
	}

	/**
	 * Return abilities exposed locally by the WordPress AI / Abilities API.
	 *
	 * This method is intentionally defensive and read-only: it never performs
	 * remote calls and does not depend on one specific WordPress AI API shape.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_available_abilities() {
		$abilities = array();

		$abilities = array_merge( $abilities, $this->abilities_from_known_functions() );
		$abilities = array_merge( $abilities, $this->abilities_from_known_registries() );

		/**
		 * Allows the site's WordPress AI integration to expose available abilities.
		 *
		 * Expected shapes accepted by the plugin:
		 * - array( 'ability-id', 'another-ability' )
		 * - array( array( 'id' => 'ability-id', 'label' => 'Label', 'description' => 'Description' ) )
		 * - array( 'ability-id' => 'Label' )
		 *
		 * @param array<int|string,mixed> $abilities Current ability list.
		 */
		$abilities = apply_filters( 'wpai_publisher_available_ai_abilities', $abilities );

		return $this->normalize_abilities( $abilities );
	}

	/**
	 * Return abilities that appear relevant for future publisher workflows.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_relevant_abilities() {
		$abilities = $this->get_available_abilities();
		$keywords  = array( 'article', 'content', 'generate', 'image', 'seo', 'link', 'index', 'file', 'idea', 'text', 'write', 'media' );
		$relevant  = array();

		foreach ( $abilities as $ability ) {
			$haystack = strtolower( implode( ' ', array( $ability['id'], $ability['label'], $ability['description'] ) ) );

			foreach ( $keywords as $keyword ) {
				if ( false !== strpos( $haystack, $keyword ) ) {
					$relevant[] = $ability;
					continue 2;
				}
			}
		}

		return $relevant;
	}

	/**
	 * Try model discovery through known WordPress AI functions.
	 *
	 * @param string $type Model type.
	 * @return array<int|string,mixed>
	 */
	private function models_from_known_functions( $type ) {
		$models    = array();
		$functions = array(
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
		);

		foreach ( $functions as $function_name ) {
			if ( ! function_exists( $function_name ) ) {
				continue;
			}

			try {
				$result = call_user_func( $function_name, $type );
				if ( is_array( $result ) ) {
					$models = array_merge( $models, $result );
				}
			} catch ( Throwable $error ) {
				unset( $error );
				try {
					$result = call_user_func( $function_name );
					if ( is_array( $result ) ) {
						$models = array_merge( $models, $result );
					}
				} catch ( Throwable $fallback_error ) {
					unset( $fallback_error );
				}
			}
		}

		return $models;
	}

	/**
	 * Try model discovery through known WordPress AI client objects.
	 *
	 * @param string $type Model type.
	 * @return array<int|string,mixed>
	 */
	private function models_from_known_clients( $type ) {
		$models          = array();
		$client_factories = array( 'wp_ai_client', 'wp_get_ai_client', 'wp_ai_services' );

		foreach ( $client_factories as $factory ) {
			if ( ! function_exists( $factory ) ) {
				continue;
			}

			try {
				$client = call_user_func( $factory );
			} catch ( Throwable $error ) {
				unset( $error );
				continue;
			}

			if ( ! is_object( $client ) ) {
				continue;
			}

			foreach ( array( 'get_models', 'list_models', 'models', 'get_available_models' ) as $method ) {
				if ( ! method_exists( $client, $method ) ) {
					continue;
				}

				try {
					$result = $client->{$method}( $type );
					if ( is_array( $result ) ) {
						$models = array_merge( $models, $result );
					}
				} catch ( Throwable $error ) {
					unset( $error );
					try {
						$result = $client->{$method}();
						if ( is_array( $result ) ) {
							$models = array_merge( $models, $result );
						}
					} catch ( Throwable $fallback_error ) {
						unset( $fallback_error );
					}
				}
			}
		}

		return $models;
	}

	/**
	 * Try ability discovery through known WordPress AI functions.
	 *
	 * @return array<int|string,mixed>
	 */
	private function abilities_from_known_functions() {
		$abilities = array();
		$functions = array(
			'wp_ai_get_abilities',
			'wp_get_ai_abilities',
			'wp_ai_get_available_abilities',
			'wp_get_ai_available_abilities',
		);

		foreach ( $functions as $function_name ) {
			if ( ! function_exists( $function_name ) ) {
				continue;
			}

			try {
				$result = call_user_func( $function_name );
				if ( is_array( $result ) ) {
					$abilities = array_merge( $abilities, $result );
				}
			} catch ( Throwable $error ) {
				unset( $error );
			}
		}

		return $abilities;
	}

	/**
	 * Try ability discovery through known registry objects and classes.
	 *
	 * @return array<int|string,mixed>
	 */
	private function abilities_from_known_registries() {
		$abilities  = array();
		$registries = array();

		foreach ( array( 'wp_ai_abilities', 'wp_get_ai_abilities_registry', 'wp_ai_abilities_registry' ) as $factory ) {
			if ( ! function_exists( $factory ) ) {
				continue;
			}

			try {
				$registry = call_user_func( $factory );
				if ( is_object( $registry ) ) {
					$registries[] = $registry;
				}
			} catch ( Throwable $error ) {
				unset( $error );
			}
		}

		$classes = array(
			'WP_AI_Abilities_Registry',
			'WP_AI_Ability_Registry',
			'\WordPress\AI\Abilities\Registry',
			'\WordPress\AI\Abilities\Abilities_Registry',
			'\WP_AI\Abilities\Registry',
		);

		foreach ( $classes as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}

			foreach ( array( 'get_instance', 'instance' ) as $method ) {
				if ( ! method_exists( $class_name, $method ) ) {
					continue;
				}

				try {
					$registry = call_user_func( array( $class_name, $method ) );
					if ( is_object( $registry ) ) {
						$registries[] = $registry;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		foreach ( $registries as $registry ) {
			foreach ( array( 'get_abilities', 'get_all', 'get_registered', 'all', 'list_abilities', 'abilities' ) as $method ) {
				if ( ! method_exists( $registry, $method ) ) {
					continue;
				}

				try {
					$result = $registry->{$method}();
					if ( is_array( $result ) ) {
						$abilities = array_merge( $abilities, $result );
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		return $abilities;
	}

	/**
	 * Normalize model definitions to id/label rows.
	 *
	 * @param mixed $models Raw model list.
	 * @return array<int,array<string,string>>
	 */
	private function normalize_models( $models ) {
		if ( ! is_array( $models ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $models as $key => $model ) {
			$id    = '';
			$label = '';

			if ( is_string( $model ) ) {
				$id    = $model;
				$label = is_string( $key ) ? $key : $model;
			} elseif ( is_array( $model ) ) {
				$id    = $model['id'] ?? $model['model'] ?? $model['name'] ?? ( is_string( $key ) ? $key : '' );
				$label = $model['label'] ?? $model['title'] ?? $model['name'] ?? $id;
			} elseif ( is_object( $model ) ) {
				$id    = $model->id ?? $model->model ?? $model->name ?? ( is_string( $key ) ? $key : '' );
				$label = $model->label ?? $model->title ?? $model->name ?? $id;
			}

			$id    = sanitize_text_field( (string) $id );
			$label = sanitize_text_field( (string) $label );

			if ( '' === $id ) {
				continue;
			}

			$normalized[ $id ] = array(
				'id'    => $id,
				'label' => '' !== $label ? $label : $id,
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Normalize ability definitions to id/label/description rows.
	 *
	 * @param mixed $abilities Raw ability list.
	 * @return array<int,array<string,string>>
	 */
	private function normalize_abilities( $abilities ) {
		if ( ! is_array( $abilities ) ) {
			return array();
		}

		$normalized = array();

		foreach ( $abilities as $key => $ability ) {
			$id          = '';
			$label       = '';
			$description = '';

			if ( is_string( $ability ) ) {
				$id    = $ability;
				$label = is_string( $key ) ? $key : $ability;
			} elseif ( is_array( $ability ) ) {
				$id          = $ability['id'] ?? $ability['name'] ?? $ability['slug'] ?? ( is_string( $key ) ? $key : '' );
				$label       = $ability['label'] ?? $ability['title'] ?? $ability['name'] ?? $id;
				$description = $ability['description'] ?? $ability['summary'] ?? '';
			} elseif ( is_object( $ability ) ) {
				$id          = $ability->id ?? $ability->name ?? $ability->slug ?? ( is_string( $key ) ? $key : '' );
				$label       = $ability->label ?? $ability->title ?? $ability->name ?? $id;
				$description = $ability->description ?? $ability->summary ?? '';
			}

			$id          = sanitize_text_field( (string) $id );
			$label       = sanitize_text_field( (string) $label );
			$description = sanitize_textarea_field( (string) $description );

			if ( '' === $id ) {
				continue;
			}

			$normalized[ $id ] = array(
				'id'          => $id,
				'label'       => '' !== $label ? $label : $id,
				'description' => $description,
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Stub for future text generation through WordPress AI only.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function generate_text( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_text_generation_not_implemented', __( 'La generazione testo tramite WordPress AI non è ancora implementata in questa fase.', 'wp-ai-publisher' ) );
	}

	/**
	 * Stub for future image generation through WordPress AI only.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function generate_image( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_image_generation_not_implemented', __( 'La generazione immagini tramite WordPress AI non è ancora implementata in questa fase.', 'wp-ai-publisher' ) );
	}

	/**
	 * Stub for future embeddings through WordPress AI only.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function create_embedding( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_embeddings_not_implemented', __( 'Gli embedding tramite WordPress AI non sono ancora implementati in questa fase.', 'wp-ai-publisher' ) );
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
			return new WP_Error( 'wpai_empty_structured_output', __( 'L’output strutturato non può essere vuoto.', 'wp-ai-publisher' ) );
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

			return new WP_Error( 'wpai_invalid_structured_output', __( 'L’output strutturato deve essere JSON valido, un array o un oggetto.', 'wp-ai-publisher' ) );
		}

		return true;
	}
}
