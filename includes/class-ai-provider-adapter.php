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
 * No custom OpenAI key, direct HTTP client, or remote provider fallback is handled here.
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
		foreach ( $this->get_ai_indicator_classes() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				return true;
			}
		}

		foreach ( $this->get_ai_indicator_functions() as $function_name ) {
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
	 * Candidate class names that signal an active WordPress AI integration.
	 *
	 * Centralizing the speculative detection surface keeps it explicit, easy to
	 * audit, and unit-testable. Detection is intentionally broad because the
	 * native WordPress AI APIs and popular integrations are still evolving.
	 *
	 * @return array<int,string>
	 */
	public function get_ai_indicator_classes() {
		return array(
			'WP_AI_Client',
			'WP_AI_Abilities_Registry',
			'WP_AI_Ability_Registry',
			'\\WordPress\\AiClient\\AiClient',
			'\\WordPress\\AI\\Client',
			'\\WordPress\\AI\\Services\\Services_API',
			'\\WordPress\\AI\\Services\\AI_Service',
			'\\WordPress\\AI\\Abilities\\Registry',
			'\\WordPress\\AI\\Abilities\\Abilities_Registry',
			'\\WP_AI\\Client',
			'\\WP_AI\\Abilities\\Registry',
			'\\Felix_Arntz\\AI_Services',
			'\\Felix_Arntz\\AI_Services\\Services_API',
			'\\Felix_Arntz\\AI_Services\\Plugin',
			'AI_Services',
			'AI_Services_Plugin',
			'AI_Experiments',
			'AI_Features',
			'AI_Connectors',
		);
	}

	/**
	 * Candidate function names that signal an active WordPress AI integration.
	 *
	 * @return array<int,string>
	 */
	public function get_ai_indicator_functions() {
		return array(
			'wp_ai_client',
			'wp_get_ai_client',
			'wp_ai_generate_text',
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
			'wp_ai_services',
			'wp_ai_get_abilities',
			'wp_get_ai_abilities',
			'wp_ai_get_available_abilities',
			'wp_get_ai_available_abilities',
			'wp_ai_abilities',
			'wp_get_ai_abilities_registry',
			'wp_ai_abilities_registry',
			'wp_get_abilities',
			'wp_get_ability',
			'wp_invoke_ability',
			'ai_services',
			'ai_services_get_connector',
			'ai_services_get_connectors',
		);
	}

	/**
	 * Known function names used to discover available text/image models.
	 *
	 * @return array<int,string>
	 */
	public function get_model_discovery_functions() {
		return array(
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
		);
	}

	/**
	 * Known factory functions that may return a WordPress AI client object.
	 *
	 * @return array<int,string>
	 */
	public function get_model_client_factories() {
		return array( 'wp_ai_client', 'wp_get_ai_client', 'wp_ai_services' );
	}

	/**
	 * Known function names used to discover available AI abilities.
	 *
	 * @return array<int,string>
	 */
	public function get_ability_discovery_functions() {
		return array(
			'wp_ai_get_abilities',
			'wp_get_ai_abilities',
			'wp_ai_get_available_abilities',
			'wp_get_ai_available_abilities',
		);
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
		$keywords  = array_merge( $this->get_generation_ability_keywords(), array( 'article', 'image', 'link', 'index', 'file', 'idea', 'media' ) );
		$relevant  = array();

		foreach ( $abilities as $ability ) {
			$haystack = strtolower( (string) ( $ability['haystack'] ?? implode( ' ', array( $ability['id'], $ability['label'], $ability['description'] ) ) ) );

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
		$functions = $this->get_model_discovery_functions();

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
		$client_factories = $this->get_model_client_factories();

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
		$functions = $this->get_ability_discovery_functions();

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
			$metadata = $this->extract_ability_metadata( $ability, is_string( $key ) ? $key : '' );
			$id       = '' !== $metadata['name'] ? $metadata['name'] : ( is_string( $key ) ? sanitize_text_field( $key ) : '' );

			if ( '' === $id ) {
				continue;
			}

			$normalized[ $id ] = array(
				'id'          => $id,
				'label'       => '' !== $metadata['label'] ? $metadata['label'] : $id,
				'description' => $metadata['description'],
				'category'    => $metadata['category'],
				'haystack'    => $metadata['haystack'],
			);
		}

		return array_values( $normalized );
	}

	/**
	 * Extract safe metadata from a WordPress ability definition.
	 *
	 * WP_Ability exposes data through getters, so getters are preferred and
	 * every call is isolated to avoid fatal errors from experimental APIs.
	 *
	 * @param mixed  $ability Ability object, array, string, or callback.
	 * @param string $fallback_name Fallback name from registry key.
	 * @return array{name:string,label:string,description:string,category:string,input_schema:array<string,mixed>,output_schema:array<string,mixed>,meta:array<string,mixed>,haystack:string}
	 */
	private function extract_ability_metadata( $ability, $fallback_name = '' ) {
		$metadata = array(
			'name'          => sanitize_text_field( (string) $fallback_name ),
			'label'         => '',
			'description'   => '',
			'category'      => '',
			'input_schema'  => array(),
			'output_schema' => array(),
			'meta'          => array(),
			'haystack'      => '',
		);

		if ( is_object( $ability ) ) {
			$getter_map = array(
				'get_name'          => 'name',
				'get_label'         => 'label',
				'get_description'   => 'description',
				'get_category'      => 'category',
				'get_input_schema'  => 'input_schema',
				'get_output_schema' => 'output_schema',
				'get_meta'          => 'meta',
			);

			foreach ( $getter_map as $getter => $field ) {
				if ( ! method_exists( $ability, $getter ) ) {
					continue;
				}

				try {
					$value = $ability->{$getter}();
				} catch ( Throwable $error ) {
					unset( $error );
					continue;
				}

				$this->assign_ability_metadata_value( $metadata, $field, $value );
			}

			$property_map = array(
				'name'          => 'name',
				'id'            => 'name',
				'slug'          => 'name',
				'label'         => 'label',
				'title'         => 'label',
				'description'   => 'description',
				'category'      => 'category',
				'input_schema'  => 'input_schema',
				'output_schema' => 'output_schema',
				'meta'          => 'meta',
			);

			foreach ( $property_map as $property => $field ) {
				if ( ! isset( $ability->{$property} ) ) {
					continue;
				}

				$value = $ability->{$property};
				if ( in_array( $field, array( 'input_schema', 'output_schema', 'meta' ), true ) || is_scalar( $value ) ) {
					$this->assign_ability_metadata_value( $metadata, $field, $value );
				}
			}
		} elseif ( is_array( $ability ) ) {
			$array_map = array(
				'name'          => 'name',
				'id'            => 'name',
				'slug'          => 'name',
				'label'         => 'label',
				'title'         => 'label',
				'description'   => 'description',
				'category'      => 'category',
				'input_schema'  => 'input_schema',
				'output_schema' => 'output_schema',
				'meta'          => 'meta',
			);

			foreach ( $array_map as $source => $field ) {
				if ( array_key_exists( $source, $ability ) ) {
					$this->assign_ability_metadata_value( $metadata, $field, $ability[ $source ] );
				}
			}
		} elseif ( is_string( $ability ) ) {
			$metadata['name']  = sanitize_text_field( $ability );
			$metadata['label'] = sanitize_text_field( $ability );
		}

		if ( '' === $metadata['label'] && '' !== $metadata['name'] ) {
			$metadata['label'] = $metadata['name'];
		}

		$metadata['haystack'] = $this->build_ability_haystack( $metadata );

		return $metadata;
	}

	/**
	 * Assign one extracted ability metadata value safely.
	 *
	 * @param array<string,mixed> $metadata Metadata accumulator.
	 * @param string              $field Target field.
	 * @param mixed               $value Raw value.
	 * @return void
	 */
	private function assign_ability_metadata_value( &$metadata, $field, $value ) {
		if ( in_array( $field, array( 'input_schema', 'output_schema', 'meta' ), true ) ) {
			if ( is_array( $value ) ) {
				$metadata[ $field ] = $value;
			}
			return;
		}

		if ( is_scalar( $value ) ) {
			$value = sanitize_text_field( (string) $value );
			if ( '' !== $value ) {
				$metadata[ $field ] = $value;
			}
		}
	}

	/**
	 * Build a compact searchable string without exposing full schemas.
	 *
	 * @param array<string,mixed> $metadata Ability metadata.
	 * @return string
	 */
	private function build_ability_haystack( $metadata ) {
		$parts = array(
			$metadata['name'] ?? '',
			$metadata['label'] ?? '',
			$metadata['description'] ?? '',
			$metadata['category'] ?? '',
		);

		foreach ( (array) ( $metadata['meta'] ?? array() ) as $key => $value ) {
			if ( is_scalar( $key ) ) {
				$parts[] = sanitize_text_field( (string) $key );
			}
			if ( is_scalar( $value ) ) {
				$text = sanitize_text_field( (string) $value );
				if ( '' !== $text && strlen( $text ) <= 120 ) {
					$parts[] = $text;
				}
			}
		}

		$parts = array_merge( $parts, $this->extract_schema_keywords( $metadata['input_schema'] ?? array() ) );
		$parts = array_merge( $parts, $this->extract_schema_keywords( $metadata['output_schema'] ?? array() ) );

		return strtolower( implode( ' ', array_values( array_unique( array_filter( $parts ) ) ) ) );
	}

	/**
	 * Extract compact field/key keywords from an ability schema.
	 *
	 * @param mixed $schema Schema array.
	 * @return array<int,string>
	 */
	private function extract_schema_keywords( $schema ) {
		$keywords = array();
		if ( ! is_array( $schema ) ) {
			return $keywords;
		}

		$walker = function ( $value, $depth = 0 ) use ( &$walker, &$keywords ) {
			if ( $depth > 3 || count( $keywords ) >= 40 || ! is_array( $value ) ) {
				return;
			}

			foreach ( $value as $key => $item ) {
				if ( is_scalar( $key ) ) {
					$keywords[] = sanitize_text_field( (string) $key );
				}
				if ( is_scalar( $item ) && in_array( (string) $key, array( 'name', 'title', 'description', 'type', 'format' ), true ) ) {
					$text = sanitize_text_field( (string) $item );
					if ( '' !== $text && strlen( $text ) <= 120 ) {
						$keywords[] = $text;
					}
				}
				if ( is_array( $item ) ) {
					$walker( $item, $depth + 1 );
				}
			}
		};

		$walker( $schema );

		return array_values( array_unique( array_filter( $keywords ) ) );
	}

	/**
	 * Keywords that identify text/content generation abilities.
	 *
	 * @return array<int,string>
	 */
	private function get_generation_ability_keywords() {
		return array(
			'generate',
			'generation',
			'text',
			'content',
			'title',
			'excerpt',
			'summary',
			'editorial',
			'seo',
			'meta',
			'classification',
			'completion',
			'prompt',
			'ai',
			'assistant',
			'write',
			'writing',
			'resize',
			'rewrite',
			'summarize',
			'generazione',
			'testo',
			'contenuto',
			'titolo',
			'estratto',
			'riassunto',
			'editoriale',
			'scrittura',
			'metadati',
			'descrizione',
		);
	}


	/**
	 * Return the required structured dry-run schema descriptor.
	 *
	 * @return array<string,mixed>
	 */
	public function get_content_dry_run_schema() {
		return array(
			'title'                  => 'string',
			'slug'                   => 'string',
			'excerpt'                => 'string',
			'content_outline'        => array(
				array(
					'heading' => 'string',
					'level'   => 2,
					'summary' => 'string',
				),
			),
			'category_ids'            => array( 'integer' ),
			'categories'             => array( 'string' ),
			'tags'                   => array( 'string' ),
			'meta_title'             => 'string',
			'meta_description'       => 'string',
			'open_graph_title'       => 'string',
			'open_graph_description' => 'string',
			'twitter_title'          => 'string',
			'twitter_description'    => 'string',
			'featured_image_prompt'  => 'string',
			'internal_image_prompts' => array( 'string' ),
			'image_alt_texts'        => array( 'string' ),
			'image_captions'         => array( 'string' ),
			'internal_link_targets'  => array( 'string' ),
			'knowledge_summary'      => 'string',
			'entities'               => array( 'string' ),
			'search_intent'          => 'string',
			'tutorial_level'         => 'base|intermedio|avanzato',
			'cluster_topic'          => 'string',
			'subtopic'               => 'string',
			'validation_notes'       => array( 'string' ),
			'language'               => 'it',
			'source'                 => 'wordpress_ai|local_fallback',
			'classic_editor_preview' => array(
				'html'               => 'string',
				'plain_text_summary' => 'string',
				'validation_notes'   => array( 'string' ),
			),
			'full_article' => array(
				'html'               => 'string',
				'plain_text_summary' => 'string',
				'source'             => 'wordpress_ai|local_fallback',
				'validation_notes'   => array( 'string' ),
				'quality_notes'      => array( 'string' ),
			),
		);
	}

	/**
	 * Generate a structured content dry-run through WordPress AI when available.
	 *
	 * This method never calls OpenAI directly and never reads or stores custom API keys.
	 * When no usable WordPress AI output is available, a local deterministic fallback
	 * is returned only if the caller explicitly sets allow_local_fallback to true.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_structured_content_dry_run( $payload ) {
		if ( ! is_array( $payload ) ) {
			return new WP_Error( 'wpai_invalid_dry_run_payload', __( 'Payload dry-run non valido.', 'wp-ai-publisher' ) );
		}

		$original_payload = $payload;
		$payload          = $this->normalize_dry_run_payload( $payload );
		$schema           = ! empty( $payload['required_schema'] ) && is_array( $payload['required_schema'] ) ? $payload['required_schema'] : $this->get_content_dry_run_schema();
		$prompt           = $this->build_structured_dry_run_prompt( $payload, $schema );
		$errors           = array();

		/**
		 * Allows a WordPress AI integration to provide the structured dry-run output.
		 *
		 * Returning null leaves control to the adapter. Valid arrays/JSON are treated as
		 * WordPress AI or external AI output and receive source=wordpress_ai.
		 *
		 * @param mixed               $result  Dry-run result.
		 * @param array<string,mixed> $payload Request payload.
		 * @param array<string,mixed> $schema  Required schema descriptor.
		 */
		$filtered = apply_filters( 'wpai_publisher_generate_structured_content_dry_run', null, $payload, $schema );
		if ( null !== $filtered ) {
			$normalized = $this->normalize_real_ai_candidate( $filtered );
			if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
				return $normalized;
			}
			$errors[] = is_wp_error( $normalized ) ? $normalized->get_error_message() : __( 'Il filtro di integrazione ha restituito un output non utilizzabile.', 'wp-ai-publisher' );
		}

		/**
		 * Backward compatibility with integrations built against WP AI Publisher 0.3.0.
		 *
		 * The new wpai_publisher_generate_structured_content_dry_run filter remains the
		 * primary hook. The legacy hook is called only when the primary hook returns
		 * null or unusable output, so a valid new integration is never invoked twice.
		 *
		 * @param mixed               $result  Dry-run result.
		 * @param array<string,mixed> $payload 0.3.0-compatible legacy payload.
		 */
		$legacy_payload = $original_payload;
		if ( empty( $legacy_payload['idea'] ) || ! is_array( $legacy_payload['idea'] ) ) {
			$legacy_payload['idea'] = array(
				'topic'           => $payload['topic'],
				'keyword'         => $payload['keyword'],
				'language'        => $payload['language'],
				'target_audience' => $payload['target_audience'],
				'tutorial_level'  => $payload['tutorial_level'],
				'notes'           => $payload['notes'],
			);
		}

		$legacy_filtered = apply_filters( 'wpai_publisher_structured_content_dry_run', null, $legacy_payload );
		if ( null !== $legacy_filtered ) {
			$legacy_normalized = $this->normalize_real_ai_candidate( $legacy_filtered );
			if ( ! is_wp_error( $legacy_normalized ) && $this->is_usable_dry_run_candidate( $legacy_normalized ) ) {
				$legacy_normalized['source']           = ! empty( $legacy_normalized['source'] ) ? $legacy_normalized['source'] : 'wordpress_ai';
				$legacy_normalized['validation_notes'] = isset( $legacy_normalized['validation_notes'] ) && is_array( $legacy_normalized['validation_notes'] ) ? $legacy_normalized['validation_notes'] : array();
				$legacy_normalized['validation_notes'][] = __( 'Output generato tramite filtro legacy wpai_publisher_structured_content_dry_run.', 'wp-ai-publisher' );

				return $legacy_normalized;
			}
			$errors[] = is_wp_error( $legacy_normalized ) ? $legacy_normalized->get_error_message() : __( 'Il filtro legacy ha restituito un output non utilizzabile.', 'wp-ai-publisher' );
		}

		$wp_abilities_result = $this->try_generate_with_wp_abilities_api( $payload, $schema, $prompt );
		if ( ! is_wp_error( $wp_abilities_result ) ) {
			return $wp_abilities_result;
		}
		$errors[] = $wp_abilities_result->get_error_message();

		$abilities_result = $this->try_generate_with_abilities( $payload, $schema, $prompt );
		if ( ! is_wp_error( $abilities_result ) ) {
			return $abilities_result;
		}
		$errors[] = $abilities_result->get_error_message();

		$functions_result = $this->try_generate_with_known_functions( $payload, $schema, $prompt );
		if ( ! is_wp_error( $functions_result ) ) {
			return $functions_result;
		}
		$errors[] = $functions_result->get_error_message();

		$clients_result = $this->try_generate_with_known_clients( $payload, $schema, $prompt );
		if ( ! is_wp_error( $clients_result ) ) {
			return $clients_result;
		}
		$errors[] = $clients_result->get_error_message();

		if ( ! empty( $payload['allow_local_fallback'] ) ) {
			$fallback = $this->generate_local_structured_content_dry_run( $payload );
			if ( ! empty( $errors ) ) {
				$fallback['validation_notes'][] = sprintf(
					/* translators: %s: short list of failed AI attempts. */
					__( 'Tentativi WordPress AI non utilizzabili: %s', 'wp-ai-publisher' ),
					implode( ' | ', array_slice( array_filter( $errors ), 0, 3 ) )
				);
			}
			return $fallback;
		}

		return new WP_Error( 'wpai_wordpress_ai_dry_run_not_available', __( 'Nessun output WordPress AI utilizzabile per il dry-run strutturato.', 'wp-ai-publisher' ) );
	}

	/**
	 * Generate a complete Classic Editor article from dry-run output.
	 *
	 * This method only uses WordPress-provided AI integration hooks/functions; it never
	 * calls OpenAI directly. If no safe AI output is usable, a local deterministic
	 * fallback builds reader-facing HTML from the approved structure.
	 *
	 * @param array<string,mixed> $dry_run_output Dry-run output.
	 * @param array<string,mixed> $site_context Internal editorial context.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_full_classic_article( $dry_run_output, $site_context = array(), $article_type = array() ) {
		$dry_run_output = is_array( $dry_run_output ) ? $dry_run_output : array();
		$site_context   = wpai_publisher_normalize_site_context( ! empty( $site_context ) ? $site_context : wpai_publisher_get_site_context() );
		$article_type = is_array( $article_type ) && ! empty( $article_type ) ? $article_type : ( isset( $dry_run_output['article_type'] ) && is_array( $dry_run_output['article_type'] ) ? $dry_run_output['article_type'] : array() );
		$prompt         = $this->build_full_article_prompt( $dry_run_output, $site_context, $article_type );
		$builder        = new Classic_Content_Builder( $site_context, $article_type );

		$filtered = apply_filters( 'wpai_publisher_generate_full_classic_article', null, $dry_run_output, $site_context, $prompt, $article_type );
		if ( null !== $filtered ) {
			$candidate = $this->normalize_full_article_candidate( $filtered, 'wordpress_ai', $builder, $dry_run_output );
			if ( ! is_wp_error( $candidate ) ) {
				return $candidate;
			}
		}

		$ability_candidate = $this->try_generate_full_article_with_wp_abilities( $dry_run_output, $site_context, $prompt, $builder );
		if ( ! is_wp_error( $ability_candidate ) ) {
			return $ability_candidate;
		}

		if ( function_exists( 'wp_ai_generate_text' ) ) {
			foreach ( array( array( 'prompt' => $prompt, 'temperature' => 0.2, 'format' => 'html' ), $prompt ) as $args ) {
				try {
					$result = call_user_func( 'wp_ai_generate_text', $args );
					$candidate = $this->normalize_full_article_candidate( $result, 'wordpress_ai', $builder, $dry_run_output );
					if ( ! is_wp_error( $candidate ) ) {
						return $candidate;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		$dry_run_output['article_type'] = $article_type;
		$fallback = $builder->build_full_article_from_dry_run( $dry_run_output, $site_context );
		$fallback['source'] = 'local_fallback';
		$validation = $builder->validate_publishable_article_html( (string) ( $fallback['html'] ?? '' ) );
		if ( empty( $validation['valid'] ) ) {
			return new WP_Error( 'wpai_full_article_fallback_invalid', __( 'Il fallback locale non ha prodotto un articolo completo pubblicabile.', 'wp-ai-publisher' ), $validation );
		}
		return $fallback;
	}

	/**
	 * Generate a complete, publishable Classic Editor article directly from a
	 * content idea and its article type, in a single AI call.
	 *
	 * This is the simplified "idea + article type -> draft" path. It only uses
	 * WordPress-provided AI integration hooks/functions and intentionally has NO
	 * local filler fallback: if no real AI output is usable it returns a WP_Error
	 * so the caller can ask the user to configure a WordPress AI system.
	 *
	 * @param array<string,mixed> $payload      Idea data (topic, keyword, language).
	 * @param array<string,mixed> $site_context Editorial site context.
	 * @param array<string,mixed> $article_type Article type configuration.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_article_from_idea( $payload, $site_context = array(), $article_type = array() ) {
		$payload      = is_array( $payload ) ? $payload : array();
		$site_context = wpai_publisher_normalize_site_context( ! empty( $site_context ) ? $site_context : wpai_publisher_get_site_context() );
		$article_type = is_array( $article_type ) ? $article_type : array();

		// A guiding outline built from the article type required sections lets the
		// normalizer shape plain-text AI output into the requested H2 sections.
		$generation_context = array(
			'topic'           => sanitize_textarea_field( (string) ( $payload['topic'] ?? '' ) ),
			'keyword'         => sanitize_text_field( (string) ( $payload['keyword'] ?? '' ) ),
			'language'        => sanitize_key( (string) ( $payload['language'] ?? $site_context['default_language'] ) ),
			'article_type'    => $article_type,
			'content_outline' => $this->build_outline_from_article_type( $article_type ),
		);

		$prompt  = $this->build_article_from_idea_prompt( $generation_context, $site_context, $article_type );
		$builder = new Classic_Content_Builder( $site_context, $article_type );
		$diagnostics = $this->get_ai_generation_diagnostics();
		$diagnostics['channel_attempts'] = array();

		/**
		 * Allows a WordPress AI integration to provide the full article HTML.
		 *
		 * Returning null leaves control to the adapter.
		 *
		 * @param mixed               $result             Article result.
		 * @param array<string,mixed> $generation_context Idea + article type context.
		 * @param array<string,mixed> $site_context       Editorial site context.
		 * @param string              $prompt             Generated prompt.
		 * @param array<string,mixed> $article_type       Article type configuration.
		 */
		$filtered = apply_filters( 'wpai_publisher_generate_article_from_idea', null, $generation_context, $site_context, $prompt, $article_type );
		if ( null !== $filtered ) {
			$candidate = $this->normalize_full_article_candidate( $filtered, 'wordpress_ai', $builder, $generation_context, true );
			if ( ! is_wp_error( $candidate ) ) {
				$candidate['channel'] = 'filter';
				return $candidate;
			}
			$diagnostics['channel_attempts']['filter'] = $candidate->get_error_message();
		} else {
			$diagnostics['channel_attempts']['filter'] = $diagnostics['channel_filter'] ? 'filtro registrato ma ha restituito null' : 'nessun filtro registrato';
		}

		$client_candidate = $this->try_generate_with_php_ai_client( $prompt, $generation_context, $builder, true );
		if ( ! is_wp_error( $client_candidate ) ) {
			$client_candidate['channel'] = 'php_ai_client';
			return $client_candidate;
		}
		$diagnostics['channel_attempts']['php_ai_client'] = $client_candidate->get_error_message();

		$ability_candidate = $this->try_generate_full_article_with_wp_abilities( $generation_context, $site_context, $prompt, $builder, true );
		if ( ! is_wp_error( $ability_candidate ) ) {
			$ability_candidate['channel'] = 'abilities_api';
			return $ability_candidate;
		}
		$diagnostics['channel_attempts']['abilities_api'] = $ability_candidate->get_error_message();
		$ability_data = $ability_candidate->get_error_data();
		if ( is_array( $ability_data ) && ! empty( $ability_data['abilities'] ) ) {
			$diagnostics['abilities_detail'] = $ability_data['abilities'];
		}

		$ai_services_candidate = $this->try_generate_with_ai_services( $prompt, $generation_context, $builder, true );
		if ( ! is_wp_error( $ai_services_candidate ) ) {
			$ai_services_candidate['channel'] = 'ai_services';
			return $ai_services_candidate;
		}
		$diagnostics['channel_attempts']['ai_services'] = $ai_services_candidate->get_error_message();

		if ( function_exists( 'wp_ai_generate_text' ) ) {
			foreach ( array( array( 'prompt' => $prompt, 'temperature' => 0.4, 'format' => 'html' ), $prompt ) as $args ) {
				try {
					$result    = call_user_func( 'wp_ai_generate_text', $args );
					$candidate = $this->normalize_full_article_candidate( $result, 'wordpress_ai', $builder, $generation_context, true );
					if ( ! is_wp_error( $candidate ) ) {
						$candidate['channel'] = 'wp_ai_generate_text';
						return $candidate;
					}
					$diagnostics['channel_attempts']['wp_ai_generate_text'] = $candidate->get_error_message();
				} catch ( Throwable $error ) {
					$diagnostics['channel_attempts']['wp_ai_generate_text'] = $error->getMessage();
				}
			}
		} else {
			$diagnostics['channel_attempts']['wp_ai_generate_text'] = 'funzione wp_ai_generate_text assente';
		}

		// No usable AI output: never fabricate a local filler draft.
		if ( ! $diagnostics['ai_available'] ) {
			return new WP_Error(
				'wpai_article_no_ai',
				__( 'Nessun sistema AI di WordPress attivo. Configura un sistema AI di WordPress (o un filtro di integrazione) e riprova: la creazione bozza richiede una generazione AI reale.', 'wp-ai-publisher' ),
				$diagnostics
			);
		}

		// If a real generation channel reached the provider but failed with a
		// network/timeout error, surface that as the primary cause: it is a server
		// outbound-connectivity problem (firewall/proxy/DNS), not a plugin bug.
		$network_haystack = strtolower( (string) ( $diagnostics['channel_attempts']['php_ai_client'] ?? '' ) . ' ' . (string) ( $diagnostics['channel_attempts']['ai_services'] ?? '' ) );
		foreach ( array( 'curl error 28', 'timed out', '0 bytes', 'could not resolve', 'errore di rete', 'connection timed out', 'failed to connect' ) as $needle ) {
			if ( false !== strpos( $network_haystack, $needle ) ) {
				return new WP_Error(
					'wpai_article_network_error',
					__( 'Il server WordPress ha raggiunto il provider AI ma la richiesta è andata in timeout / non ha ricevuto risposta. È quasi certamente un blocco delle connessioni in uscita verso api.openai.com (firewall hosting, proxy o DNS), non un problema del plugin. Esegui "Verifica connettività OpenAI" in Diagnostica AI e, se necessario, chiedi all’hosting di abilitare HTTPS in uscita. Se invece la connessione funziona ma il modello è lento, aumenta il filtro wpai_publisher_ai_http_timeout.', 'wp-ai-publisher' ),
					$diagnostics
				);
			}
		}

		return new WP_Error(
			'wpai_article_no_ai_output',
			__( 'Un sistema AI è rilevato ma nessun canale ha generato l’articolo. Le ability disponibili potrebbero non includere la generazione di testo/articoli (spesso sono specifiche: immagini, classificazione, SEO) o richiedere permessi non disponibili durante l’esecuzione pianificata (WP-Cron senza utente). Soluzione consigliata: collega un generatore di testo con il filtro wpai_publisher_generate_article_from_idea (vedi README → Integrazione AI). Dettagli per-ability in Stato sistema → Dettaglio log critici interni.', 'wp-ai-publisher' ),
			$diagnostics
		);
	}

	/**
	 * Report which AI integrations and generation channels are detected.
	 *
	 * Read-only diagnostics used for logging and the System Status page so the
	 * exact reason a generation produced no content can be identified (e.g. an
	 * integration is detected by class name but no callable invocation channel
	 * matches its real API).
	 *
	 * @return array<string,mixed>
	 */
	public function get_ai_generation_diagnostics() {
		$present_classes = array();
		foreach ( $this->get_ai_indicator_classes() as $class_name ) {
			if ( class_exists( $class_name ) ) {
				$present_classes[] = $class_name;
			}
		}
		$present_functions = array();
		foreach ( array_merge( $this->get_ai_indicator_functions(), array( 'ai_services' ) ) as $function_name ) {
			if ( function_exists( $function_name ) ) {
				$present_functions[] = $function_name;
			}
		}

		return array(
			'ai_available'                => $this->is_wordpress_ai_client_available(),
			'present_classes'             => array_values( array_unique( $present_classes ) ),
			'present_functions'           => array_values( array_unique( $present_functions ) ),
			'channel_filter'              => (bool) ( function_exists( 'has_filter' ) ? has_filter( 'wpai_publisher_generate_article_from_idea' ) : false ),
			'channel_php_ai_client'       => class_exists( '\\WordPress\\AiClient\\AiClient' ),
			'channel_abilities_api'       => function_exists( 'wp_get_abilities' ) && function_exists( 'wp_get_ability' ),
			'channel_ai_services'         => function_exists( 'ai_services' ),
			'channel_wp_ai_generate_text' => function_exists( 'wp_ai_generate_text' ),
		);
	}

	/**
	 * Try generating with the official WordPress PHP AI Client SDK.
	 *
	 * This is the canonical, cross-plugin text-generation entrypoint bundled by
	 * the WordPress/ai plugin and used by "AI Provider for OpenAI":
	 *   WordPress\AiClient\AiClient::prompt( $prompt )->generateText()
	 * It uses the provider/model configured on the site (e.g. OpenAI). Fully
	 * guarded so any API change is reported instead of fataling.
	 *
	 * @param string                  $prompt Prompt.
	 * @param array<string,mixed>     $generation_context Generation context.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @param bool                    $tolerant Tolerant acceptance mode.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_php_ai_client( $prompt, $generation_context, Classic_Content_Builder $builder, $tolerant = true ) {
		$class = '\\WordPress\\AiClient\\AiClient';
		if ( ! class_exists( $class ) ) {
			return new WP_Error( 'wpai_php_ai_client_unavailable', __( 'PHP AI Client (WordPress\\AiClient) non disponibile.', 'wp-ai-publisher' ) );
		}
		try {
			$request = call_user_func( array( $class, 'prompt' ), $prompt );
			if ( ! is_object( $request ) ) {
				return new WP_Error( 'wpai_php_ai_client_no_builder', __( 'AiClient::prompt() non ha restituito un builder.', 'wp-ai-publisher' ) );
			}
			if ( method_exists( $request, 'usingSystemInstruction' ) ) {
				try {
					$request = $request->usingSystemInstruction( __( 'Sei un assistente editoriale WordPress. Restituisci solo HTML pulito compatibile con Editor Classico (p, h2, h3, ul, ol, li, strong, em, blockquote, code, pre, br), senza blocchi Gutenberg, senza markdown e senza note interne.', 'wp-ai-publisher' ) );
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
			if ( ! method_exists( $request, 'generateText' ) ) {
				return new WP_Error( 'wpai_php_ai_client_no_method', __( 'Il metodo generateText() non è disponibile nel PHP AI Client installato.', 'wp-ai-publisher' ) );
			}

			// AI text generation can take far longer than the 5s WordPress HTTP
			// default; raise the timeout only for the duration of this request.
			$timeout       = max( 15, (int) apply_filters( 'wpai_publisher_ai_http_timeout', 90 ) );
			$raise_timeout = static function ( $value ) use ( $timeout ) {
				return max( (float) $value, (float) $timeout );
			};
			$raise_args = static function ( $args ) use ( $timeout ) {
				if ( is_array( $args ) ) {
					$args['timeout'] = max( (float) ( $args['timeout'] ?? 0 ), (float) $timeout );
				}
				return $args;
			};
			add_filter( 'http_request_timeout', $raise_timeout, 9999 );
			add_filter( 'http_request_args', $raise_args, 9999 );
			try {
				$generated = $request->generateText();
			} finally {
				remove_filter( 'http_request_timeout', $raise_timeout, 9999 );
				remove_filter( 'http_request_args', $raise_args, 9999 );
			}

			$text = $this->stringify_ai_result( $generated );
			if ( '' === trim( (string) $text ) ) {
				return new WP_Error( 'wpai_php_ai_client_empty', __( 'Il PHP AI Client non ha restituito testo.', 'wp-ai-publisher' ) );
			}
			return $this->normalize_full_article_candidate( $text, 'wordpress_ai', $builder, $generation_context, $tolerant );
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_php_ai_client_exception', $error->getMessage() );
		}
	}

	/**
	 * Try generating with the "AI Services" plugin (felix-arntz/ai-services).
	 *
	 * Fully guarded: any incompatible API shape is caught and reported instead of
	 * fataling, so the caller falls back to a clear diagnostic error.
	 *
	 * @param string                  $prompt Prompt.
	 * @param array<string,mixed>     $generation_context Generation context.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @param bool                    $tolerant Tolerant acceptance mode.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_ai_services( $prompt, $generation_context, Classic_Content_Builder $builder, $tolerant = true ) {
		if ( ! function_exists( 'ai_services' ) ) {
			return new WP_Error( 'wpai_ai_services_unavailable', __( 'Plugin AI Services non disponibile.', 'wp-ai-publisher' ) );
		}
		try {
			$services = ai_services();
			if ( is_object( $services ) && method_exists( $services, 'has_available_services' ) && ! $services->has_available_services() ) {
				return new WP_Error( 'wpai_ai_services_no_service', __( 'AI Services è installato ma nessun servizio/modello è configurato.', 'wp-ai-publisher' ) );
			}
			$service = is_object( $services ) && method_exists( $services, 'get_available_service' ) ? $services->get_available_service() : null;
			if ( ! is_object( $service ) ) {
				return new WP_Error( 'wpai_ai_services_no_service', __( 'Nessun servizio AI disponibile da AI Services.', 'wp-ai-publisher' ) );
			}
			$text = $this->extract_ai_services_text( $service, $prompt );
			if ( '' === trim( (string) $text ) ) {
				return new WP_Error( 'wpai_ai_services_empty', __( 'AI Services non ha restituito testo utilizzabile.', 'wp-ai-publisher' ) );
			}
			return $this->normalize_full_article_candidate( $text, 'wordpress_ai', $builder, $generation_context, $tolerant );
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_ai_services_exception', $error->getMessage() );
		}
	}

	/**
	 * Best-effort text extraction from the AI Services API across versions.
	 *
	 * @param object $service AI Services service object.
	 * @param string $prompt Prompt.
	 * @return string
	 */
	private function extract_ai_services_text( $service, $prompt ) {
		if ( method_exists( $service, 'generate_text' ) ) {
			try {
				$text = $this->stringify_ai_result( $service->generate_text( $prompt ) );
				if ( '' !== trim( $text ) ) {
					return $text;
				}
			} catch ( Throwable $error ) {
				unset( $error );
			}
		}
		if ( method_exists( $service, 'get_model' ) ) {
			foreach ( array( array( 'feature' => 'wp-ai-publisher' ), array() ) as $model_args ) {
				try {
					$model = $service->get_model( $model_args );
					if ( is_object( $model ) && method_exists( $model, 'generate_text' ) ) {
						$text = $this->stringify_ai_result( $model->generate_text( $prompt ) );
						if ( '' !== trim( $text ) ) {
							return $text;
						}
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}
		return '';
	}

	/**
	 * Reduce a heterogeneous AI result (string/array/object/candidates) to text.
	 *
	 * @param mixed $result AI result.
	 * @return string
	 */
	private function stringify_ai_result( $result ) {
		if ( is_string( $result ) ) {
			return $result;
		}
		if ( is_array( $result ) ) {
			return (string) ( $result['text'] ?? $result['content'] ?? $result['html'] ?? '' );
		}
		if ( is_object( $result ) ) {
			foreach ( array( 'toText', 'getText', 'get_text', 'to_text', '__toString' ) as $method ) {
				if ( method_exists( $result, $method ) ) {
					try {
						$text = $result->{$method}();
						if ( is_string( $text ) && '' !== trim( $text ) ) {
							return $text;
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
			if ( method_exists( $result, 'get_candidates' ) ) {
				try {
					foreach ( (array) $result->get_candidates() as $candidate ) {
						$text = $this->stringify_ai_result( $candidate );
						if ( '' !== trim( $text ) ) {
							return $text;
						}
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
			foreach ( array( 'text', 'content', 'html' ) as $property ) {
				if ( isset( $result->$property ) && is_string( $result->$property ) ) {
					return $result->$property;
				}
			}
		}
		return '';
	}

	/**
	 * Build a guiding outline (H2 headings) from the article type required
	 * sections, falling back to its free-form structure field.
	 *
	 * @param array<string,mixed> $article_type Article type configuration.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_outline_from_article_type( $article_type ) {
		$source   = '' !== trim( (string) ( $article_type['required_sections'] ?? '' ) ) ? (string) $article_type['required_sections'] : (string) ( $article_type['structure'] ?? '' );
		$headings = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $source ) ) ) );
		$outline  = array();
		foreach ( $headings as $heading ) {
			$outline[] = array( 'heading' => sanitize_text_field( $heading ), 'level' => 2, 'summary' => '' );
		}
		return $outline;
	}

	/**
	 * Build the single-call prompt that turns an idea plus its article type into
	 * a complete, reader-facing Classic Editor article.
	 *
	 * @param array<string,mixed> $generation_context Idea + outline context.
	 * @param array<string,mixed> $site_context       Editorial site context.
	 * @param array<string,mixed> $article_type       Article type configuration.
	 * @return string
	 */
	private function build_article_from_idea_prompt( $generation_context, $site_context, $article_type ) {
		$required_sections = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $article_type['required_sections'] ?? '' ) ) ) ) );
		$sections_line     = ! empty( $required_sections )
			? "\nUsa esattamente queste sezioni come titoli H2, nello stesso ordine: " . implode( ' | ', $required_sections ) . '.'
			: '';

		$constraints = array(
			'article_type_prompt' => sanitize_textarea_field( (string) ( $article_type['prompt'] ?? '' ) ),
			'tone'                => sanitize_textarea_field( (string) ( $article_type['tone'] ?? '' ) ),
			'length'              => sanitize_textarea_field( (string) ( $article_type['length'] ?? '' ) ),
			'search_intent'       => sanitize_textarea_field( (string) ( $article_type['search_intent'] ?? '' ) ),
			'reader_level'        => sanitize_textarea_field( (string) ( $article_type['reader_level'] ?? '' ) ),
			'required_sections'   => $required_sections,
			'forbidden_patterns'  => sanitize_textarea_field( (string) ( $article_type['forbidden_patterns'] ?? '' ) ),
			'quality_checklist'   => sanitize_textarea_field( (string) ( $article_type['quality_checklist'] ?? '' ) ),
			'site'                => array(
				'description'      => $site_context['site_description'],
				'niche'            => $site_context['content_niche'],
				'default_audience' => $site_context['default_audience'],
				'writing_rules'    => $site_context['writing_rules'],
				'forbidden_claims' => $site_context['forbidden_claims'],
				'brand_terms'      => $site_context['brand_terms'],
			),
		);
		$constraints_json = wp_json_encode( $constraints, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		return sprintf(
			"Scrivi un articolo completo, originale e pubblicabile per il lettore (non una scaletta), pronto per una bozza WordPress in Editor Classico. Restituisci SOLO HTML pulito, senza markdown, senza blocchi di codice e senza spiegazioni fuori dall’HTML. Usa solo i tag consentiti: p, h2, h3, ul, ol, li, strong, em, blockquote, code, pre, br. Non usare blocchi Gutenberg, script, iframe, style inline, shortcode o JSON. Non includere nel corpo dell’articolo il prompt, il pubblico target, il tono, le regole editoriali, note interne o prompt immagini. Non inventare dati tecnici, prezzi, normative o date non verificabili. Produci almeno tre sezioni H2 e contenuto sostanziale per ciascuna. Usa il Contesto editoriale del sito come quadro generale e la Tipologia articolo come istruzione specifica principale per struttura, tono, lunghezza, intento di ricerca e livello lettore.%1\$s\nLingua dell’articolo: %2\$s.\nArgomento principale: %3\$s.\nKeyword principale: %4\$s.\nVincoli e istruzioni della Tipologia articolo e del sito:\n%5\$s",
			$sections_line,
			$generation_context['language'],
			$generation_context['topic'],
			$generation_context['keyword'],
			false !== $constraints_json ? $constraints_json : '{}'
		);
	}

	/**
	 * Try safe WordPress Abilities API candidates for full article generation.
	 *
	 * @param array<string,mixed>     $dry_run_output Dry-run output or generation context.
	 * @param array<string,mixed>     $site_context Site context.
	 * @param string                  $prompt Prompt.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_full_article_with_wp_abilities( $dry_run_output, $site_context, $prompt, Classic_Content_Builder $builder, $tolerant = false ) {
		unset( $site_context );
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'wpai_full_article_abilities_unavailable', __( 'WordPress Abilities API non disponibile.', 'wp-ai-publisher' ) );
		}
		try {
			$abilities = wp_get_abilities();
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_full_article_abilities_read_failed', $error->getMessage() );
		}

		$keywords = array( 'article', 'content', 'text', 'testo', 'contenuto', 'generate', 'genera', 'write', 'scriv', 'complete', 'completion', 'chat', 'prompt', 'llm', 'language model' );
		$diag     = array();
		foreach ( (array) $abilities as $key => $ability_def ) {
			$metadata = $this->extract_ability_metadata( $ability_def, is_string( $key ) ? $key : '' );
			$name     = sanitize_text_field( (string) ( $metadata['name'] ?? '' ) );
			$haystack = strtolower( (string) ( $metadata['haystack'] ?? '' ) );

			$matched = false;
			foreach ( $keywords as $keyword ) {
				if ( false !== strpos( $haystack, $keyword ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				continue;
			}

			$entry = array( 'name' => $name, 'input_schema_keys' => implode( ',', $this->get_schema_property_names( $metadata['input_schema'] ?? array() ) ), 'result' => '' );

			// For article generation we only exclude abilities with destructive/side-effect
			// signals (publish/create/delete/etc.); a plain text-generation ability is allowed
			// even without explicit read-only markers.
			if ( $this->ability_has_dangerous_signals( $metadata ) ) {
				$entry['result'] = 'esclusa: segnali di azione/lato-effetto';
				$diag[]          = $entry;
				continue;
			}
			if ( '' === $name ) {
				$entry['result'] = 'esclusa: nome ability assente';
				$diag[]          = $entry;
				continue;
			}
			try {
				$ability = wp_get_ability( $name );
			} catch ( Throwable $error ) {
				$entry['result'] = 'wp_get_ability eccezione: ' . $error->getMessage();
				$diag[]          = $entry;
				continue;
			}
			if ( ! is_object( $ability ) ) {
				$entry['result'] = 'ability non istanziabile';
				$diag[]          = $entry;
				continue;
			}

			$inputs   = $this->get_ability_invocation_inputs( $prompt, array(), array(), $metadata['input_schema'] ?? array() );
			$inputs[] = array( 'prompt' => $prompt, 'dry_run_output' => $dry_run_output, 'format' => 'html' );
			$last     = '';
			foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $method ) {
				if ( ! method_exists( $ability, $method ) || ! is_callable( array( $ability, $method ) ) ) {
					continue;
				}
				foreach ( $inputs as $input ) {
					try {
						$result = $ability->{$method}( $input );
						if ( is_wp_error( $result ) ) {
							$last = $result->get_error_message();
							continue;
						}
						$candidate = $this->normalize_full_article_candidate( $result, 'wordpress_ai', $builder, $dry_run_output, $tolerant );
						if ( ! is_wp_error( $candidate ) ) {
							$candidate['quality_notes'][] = sprintf( __( 'Articolo generato tramite ability WordPress: %s.', 'wp-ai-publisher' ), $name );
							return $candidate;
						}
						$last = $candidate->get_error_message();
					} catch ( Throwable $error ) {
						$last = $error->getMessage();
					}
				}
			}
			$entry['result'] = '' !== $last ? 'invocata senza output valido: ' . $last : 'nessun metodo eseguibile';
			$diag[]          = $entry;
			if ( count( $diag ) >= 12 ) {
				break;
			}
		}

		return new WP_Error(
			'wpai_full_article_abilities_unusable',
			empty( $diag ) ? __( 'Nessuna ability WordPress pertinente alla generazione testo è stata trovata.', 'wp-ai-publisher' ) : __( 'Nessuna ability WordPress ha prodotto un articolo utilizzabile.', 'wp-ai-publisher' ),
			array( 'abilities' => $diag )
		);
	}

	/**
	 * Build the full-article prompt for WordPress AI integrations.
	 *
	 * @param array<string,mixed> $dry_run_output Dry-run output.
	 * @param array<string,mixed> $site_context Internal site context.
	 * @return string
	 */
	private function build_full_article_prompt( $dry_run_output, $site_context, $article_type = array() ) {
		$payload = array(
			'title'            => $dry_run_output['title'] ?? '',
			'excerpt'          => $dry_run_output['excerpt'] ?? '',
			'content_outline'  => $dry_run_output['content_outline'] ?? array(),
			'knowledge_summary'=> $dry_run_output['knowledge_summary'] ?? '',
			'entities'         => $dry_run_output['entities'] ?? array(),
			'search_intent'    => $dry_run_output['search_intent'] ?? '',
			'tutorial_level'   => $dry_run_output['tutorial_level'] ?? '',
			'reader_level'     => $dry_run_output['reader_level'] ?? ( $article_type['reader_level'] ?? '' ),
			'site_context'     => $site_context,
			'article_type'     => $article_type,
			'validation_notes' => $dry_run_output['validation_notes'] ?? array(),
		);
		return "Scrivi l’articolo finale per il lettore, non una scaletta editoriale. Restituisci solo HTML pulito compatibile con Editor Classico. Non usare frasi come ‘Spiegare’, ‘Descrivere’, ‘Indicare’, ‘Mostrare’. Non includere il pubblico target, tono, regole editoriali o note interne nel corpo dell’articolo. Usa solo tag consentiti: p, h2, h3, ul, ol, li, strong, em, blockquote, code, pre, br. Non usare Markdown. Non usare blocchi Gutenberg. Non usare script, iframe, style inline, shortcode. Non includere metadati o prompt immagini nel corpo articolo. Non pubblicare, non creare immagini e non scrivere AIOSEO: produci solo HTML per bozza. Usa il Contesto editoriale del sito come quadro generale. Usa la Tipologia articolo come istruzione specifica principale. Rispetta struttura, sezioni obbligatorie, pattern vietati, tono, lunghezza, livello lettore e checklist qualità della Tipologia articolo. Non inserire il prompt o le regole editoriali nel contenuto finale. Dati e vincoli interni:
" . wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
	}

	/**
	 * Normalize and validate full article candidate.
	 *
	 * @param mixed                   $candidate Candidate output.
	 * @param string                  $source Source label.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @param array<string,mixed>     $dry_run_output Normalization context.
	 * @param bool                    $tolerant When true, accept any non-empty article and treat failed publishability checks as quality notes instead of blocking.
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_full_article_candidate( $candidate, $source, Classic_Content_Builder $builder, $dry_run_output = array(), $tolerant = false ) {
		$html = '';
		if ( is_array( $candidate ) ) {
			$html = (string) ( $candidate['html'] ?? $candidate['content'] ?? $candidate['post_content'] ?? '' );
		} elseif ( is_object( $candidate ) ) {
			$html = (string) ( $candidate->html ?? $candidate->content ?? $candidate->text ?? '' );
		} elseif ( is_string( $candidate ) ) {
			$html = $candidate;
		}
		$dry_run_output = is_array( $dry_run_output ) ? $dry_run_output : array();
		$candidate_context = is_array( $candidate ) ? $candidate : array();
		$normalization_context = array_merge( $dry_run_output, $candidate_context );
		if ( ! empty( $dry_run_output['content_outline'] ) ) {
			$normalization_context['content_outline'] = $dry_run_output['content_outline'];
		}
		$html = $builder->normalize_full_article_html( $html, $normalization_context );
		$validation = $builder->validate_publishable_article_html( $html );
		if ( empty( $validation['valid'] ) ) {
			// Tolerant mode (single-call idea -> draft): the article type prompt only
			// guides writing quality; it must never block draft creation. Any
			// non-empty, sanitized article is accepted and the failed checks become
			// quality notes the editor can review on the created draft.
			if ( $tolerant && '' !== trim( wp_strip_all_tags( $html ) ) ) {
				return array(
					'html'               => $html,
					'plain_text_summary' => wp_trim_words( wp_strip_all_tags( $html ), 55, '…' ),
					'source'             => $source,
					'validation_notes'   => $validation['notes'],
					'quality_notes'      => $validation['notes'],
				);
			}
			return new WP_Error( 'wpai_full_article_invalid', __( 'Output articolo completo non pubblicabile.', 'wp-ai-publisher' ), $validation );
		}
		return array(
			'html'               => $html,
			'plain_text_summary' => wp_trim_words( wp_strip_all_tags( $html ), 55, '…' ),
			'source'             => $source,
			'validation_notes'   => $validation['notes'],
			'quality_notes'      => array( __( 'Articolo completo valido per bozza Classic Editor.', 'wp-ai-publisher' ) ),
		);
	}

	/**
	 * Normalize payload shape and preserve backward compatibility with previous nested idea payloads.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>
	 */
	private function normalize_dry_run_payload( $payload ) {
		$idea            = isset( $payload['idea'] ) && is_array( $payload['idea'] ) ? $payload['idea'] : array();
		$site_context    = wpai_publisher_normalize_site_context( $payload['site_context'] ?? wpai_publisher_get_site_context() );
		$target_audience = sanitize_text_field( (string) ( $site_context['default_audience'] ?? '' ) );
		if ( '' === $target_audience ) {
			$target_audience = sanitize_text_field( (string) ( $payload['target_audience'] ?? $idea['target_audience'] ?? '' ) );
		}

		$sanitize_free_text = static function ( $value ) {
			return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
		};
		$article_type = isset( $payload['article_type'] ) && is_array( $payload['article_type'] ) ? $payload['article_type'] : array();
		foreach ( array( 'description', 'tone', 'length', 'search_intent', 'reader_level', 'reader_level_guidance', 'prompt', 'structure', 'required_sections', 'forbidden_patterns', 'preferred_tags', 'quality_checklist' ) as $field ) {
			if ( isset( $article_type[ $field ] ) ) {
				$article_type[ $field ] = $sanitize_free_text( $article_type[ $field ] );
			}
		}

		$reader_level = $sanitize_free_text( $payload['reader_level'] ?? $payload['reader_level_guidance'] ?? $idea['reader_level'] ?? $idea['reader_level_guidance'] ?? $article_type['reader_level'] ?? '' );
		$tutorial_level = sanitize_key( (string) ( $payload['tutorial_level'] ?? $idea['tutorial_level'] ?? '' ) );
		if ( '' === $tutorial_level ) {
			$tutorial_level = '' !== $reader_level && $reader_level === sanitize_key( $reader_level ) ? $reader_level : 'custom';
		}

		return array(
			'task'                 => 'structured_content_dry_run',
			'topic'                => sanitize_textarea_field( (string) ( $payload['topic'] ?? $idea['topic'] ?? '' ) ),
			'keyword'              => sanitize_text_field( (string) ( $payload['keyword'] ?? $idea['keyword'] ?? '' ) ),
			'language'             => sanitize_key( (string) ( $payload['language'] ?? $idea['language'] ?? 'it' ) ),
			'target_audience'      => $target_audience,
			'tone'                 => $sanitize_free_text( $payload['tone'] ?? $idea['tone'] ?? $article_type['tone'] ?? '' ),
			'length'               => $sanitize_free_text( $payload['length'] ?? $idea['length'] ?? $article_type['length'] ?? '' ),
			'search_intent'        => $sanitize_free_text( $payload['search_intent'] ?? $idea['search_intent'] ?? $article_type['search_intent'] ?? '' ),
			'reader_level'         => $reader_level,
			'tutorial_level'       => $tutorial_level,
			'article_type'         => $article_type,
			'notes'                => sanitize_textarea_field( (string) ( $payload['notes'] ?? $idea['notes'] ?? '' ) ),
			'required_schema'      => isset( $payload['required_schema'] ) && is_array( $payload['required_schema'] ) ? $payload['required_schema'] : $this->get_content_dry_run_schema(),
			'allow_local_fallback' => ! empty( $payload['allow_local_fallback'] ),
			'safety'               => isset( $payload['safety'] ) && is_array( $payload['safety'] ) ? $payload['safety'] : array(),
			'site_context'         => $site_context,
		);
	}


	/**
	 * Return AI ability names explicitly allowed for dry-run invocation.
	 *
	 * @return array<int,string>
	 */
	private function get_safe_ai_ability_names() {
		$settings = wpai_publisher_get_settings();
		$raw      = (string) ( $settings['safe_ai_ability_names'] ?? '' );
		$names    = array();

		foreach ( (array) preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$name = sanitize_text_field( trim( (string) $line ) );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}

		/**
		 * Filters ability names considered safe for WP AI Publisher dry-runs.
		 *
		 * @param array<int,string> $names Safe ability names.
		 */
		$names = apply_filters( 'wpai_publisher_safe_ai_ability_names', array_values( array_unique( $names ) ) );
		if ( ! is_array( $names ) ) {
			return array();
		}

		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $names ) ) ) );
	}

	/**
	 * Check whether an ability may be invoked during a dry-run.
	 *
	 * @param mixed               $ability Ability object/definition/name.
	 * @param array<string,mixed> $metadata Extracted metadata.
	 * @return bool
	 */
	private function is_ability_safe_for_dry_run( $ability, $metadata ) {
		$name       = sanitize_text_field( (string) ( $metadata['name'] ?? ( is_string( $ability ) ? $ability : '' ) ) );
		$safe_names = $this->get_safe_ai_ability_names();

		if ( '' !== $name && in_array( $name, $safe_names, true ) ) {
			return true;
		}

		/**
		 * Allows integrations to explicitly allow or deny a dry-run ability.
		 *
		 * Return true to allow, false to deny, null to keep conservative automatic checks.
		 *
		 * @param bool|null           $safe Current decision.
		 * @param mixed               $ability Ability object/definition/name.
		 * @param array<string,mixed> $metadata Extracted metadata.
		 */
		$filtered = apply_filters( 'wpai_publisher_is_ability_safe_for_dry_run', null, $ability, $metadata );
		if ( true === $filtered ) {
			return true;
		}
		if ( false === $filtered ) {
			return false;
		}

		$dangerous = $this->ability_has_dangerous_signals( $metadata );
		$settings  = wpai_publisher_get_settings();
		if ( ! empty( $settings['allow_unverified_ai_abilities'] ) ) {
			return ! $dangerous;
		}

		return ! $dangerous && $this->ability_has_readonly_signals( $metadata );
	}

	/**
	 * Detect signals that suggest side effects or destructive behavior.
	 *
	 * @param array<string,mixed> $metadata Extracted metadata.
	 * @return bool
	 */
	private function ability_has_dangerous_signals( $metadata ) {
		$dangerous = array( 'create_post', 'insert_post', 'publish_post', 'delete_post', 'remove_post', 'update_post', 'edit_post', 'save_post', 'create_media', 'upload_media', 'media_upload', 'delete_media', 'update_option', 'delete_option', 'update_setting', 'create_user', 'delete_user', 'send_email', 'webhook', 'remote_request', 'install_plugin', 'activate_plugin', 'deactivate_plugin', 'filesystem', 'database_write', 'schedule_event', 'pubblica', 'pubblicare', 'elimina', 'eliminare', 'rimuovi', 'rimuovere', 'aggiorna articolo', 'modifica articolo', 'salva articolo', 'carica media', 'crea allegato', 'elimina allegato', 'aggiorna opzione', 'crea utente', 'elimina utente', 'invia email', 'installa plugin', 'attiva plugin', 'disattiva plugin' );
		$haystack  = $this->build_ability_safety_haystack( $metadata );
		if ( $this->metadata_contains_safety_signal( $haystack, $dangerous ) ) {
			return true;
		}

		$tokens = $this->tokenize_safety_text( $haystack );
		foreach ( array( 'publish', 'delete', 'remove', 'insert', 'pubblica', 'pubblicare', 'elimina', 'eliminare', 'rimuovi', 'rimuovere' ) as $token ) {
			if ( in_array( $token, $tokens, true ) ) {
				return true;
			}
		}

		$objects = array( 'post', 'content', 'media', 'option', 'setting', 'user', 'file', 'database', 'article', 'articolo', 'contenuto', 'opzione', 'utente', 'allegato' );
		if ( array_intersect( array( 'update', 'create', 'aggiorna', 'crea' ), $tokens ) && array_intersect( $objects, $tokens ) ) {
			return true;
		}

		foreach ( (array) ( $metadata['meta'] ?? array() ) as $key => $value ) {
			if ( in_array( sanitize_key( (string) $key ), array( 'action', 'operation', 'capability' ), true ) ) {
				$meta_tokens = $this->tokenize_safety_text( is_scalar( $value ) ? (string) $value : '' );
				if ( array_intersect( array( 'update', 'create' ), $meta_tokens ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Detect signals compatible with read-only text generation.
	 *
	 * @param array<string,mixed> $metadata Extracted metadata.
	 * @return bool
	 */
	private function ability_has_readonly_signals( $metadata ) {
		$readonly = array( 'readonly', 'read_only', 'read-only', 'pure', 'no_side_effects', 'non_destructive', 'text', 'text_generation', 'generate_text', 'completion', 'summary', 'title', 'excerpt', 'classification', 'meta_description', 'sola_lettura', 'non_distruttiva', 'testo', 'generazione_testo', 'riassunto', 'titolo', 'estratto', 'classificazione', 'descrizione_meta' );
		$haystack = $this->build_ability_safety_haystack( $metadata );
		return $this->metadata_contains_safety_signal( $haystack, $readonly );
	}

	/**
	 * Build compact metadata text for safety checks.
	 *
	 * @param array<string,mixed> $metadata Extracted metadata.
	 * @return string
	 */
	private function build_ability_safety_haystack( $metadata ) {
		$parts = array( $metadata['name'] ?? '', $metadata['label'] ?? '', $metadata['description'] ?? '', $metadata['category'] ?? '', $metadata['haystack'] ?? '' );
		foreach ( array( 'input_schema', 'output_schema', 'meta' ) as $field ) {
			$encoded = wp_json_encode( $metadata[ $field ] ?? array() );
			if ( false !== $encoded ) {
				$parts[] = $encoded;
			}
		}
		return strtolower( implode( ' ', array_map( 'sanitize_text_field', array_filter( $parts, 'is_scalar' ) ) ) );
	}

	/**
	 * Tokenize metadata text for exact safety checks.
	 *
	 * @param string $text Text to tokenize.
	 * @return array<int,string>
	 */
	private function tokenize_safety_text( $text ) {
		$normalized = strtolower( remove_accents( (string) $text ) );
		$normalized = preg_replace( '/[^a-z0-9]+/i', ' ', $normalized );
		return array_values( array_filter( preg_split( '/\s+/', trim( (string) $normalized ) ) ) );
	}

	/**
	 * Match a safety signal with token/boundary semantics in compact metadata text.
	 *
	 * @param string            $haystack Text to inspect.
	 * @param array<int,string> $signals Signal words.
	 * @return bool
	 */
	private function metadata_contains_safety_signal( $haystack, $signals ) {
		$normalized_haystack = strtolower( remove_accents( (string) $haystack ) );
		$normalized_haystack = preg_replace( '/[^a-z0-9]+/i', ' ', $normalized_haystack );
		$normalized_haystack = trim( preg_replace( '/\s+/', ' ', (string) $normalized_haystack ) );

		foreach ( $signals as $signal ) {
			$normalized_signal = strtolower( remove_accents( trim( (string) $signal ) ) );
			$normalized_signal = preg_replace( '/[_\-\s]+/', ' ', $normalized_signal );
			$normalized_signal = preg_replace( '/[^a-z0-9]+/i', ' ', (string) $normalized_signal );
			$normalized_signal = trim( preg_replace( '/\s+/', ' ', (string) $normalized_signal ) );

			if ( '' === $normalized_signal || strlen( $normalized_signal ) < 4 ) {
				continue;
			}

			$tokens  = preg_split( '/\s+/', $normalized_signal );
			$parts   = array_map( static function ( $token ) { return preg_quote( $token, '/' ); }, $tokens );
			$pattern = '/(^|\s)' . implode( '\s+', $parts ) . '(\s|$)/i';
			if ( 1 === preg_match( $pattern, $normalized_haystack ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Try WordPress 7 Abilities API through wp_get_abilities/wp_get_ability.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @param string              $prompt Prompt.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_wp_abilities_api( $payload, $schema, $prompt ) {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return new WP_Error( 'wpai_wp_abilities_api_unavailable', __( 'wp_get_abilities non è disponibile nel runtime WordPress.', 'wp-ai-publisher' ) );
		}

		try {
			$abilities = wp_get_abilities();
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_wp_abilities_read_failed', $error->getMessage() );
		}

		$candidates = $this->filter_wp_abilities_api_generation_candidates( $abilities );
		if ( empty( $candidates ) ) {
			return new WP_Error( 'wpai_wp_abilities_no_candidate', __( 'Nessuna ability candidata per generazione contenuti trovata tramite wp_get_abilities.', 'wp-ai-publisher' ) );
		}

		$safe_candidates = array();
		foreach ( $candidates as $candidate ) {
			$metadata = $candidate['metadata'];
			if ( $this->is_ability_safe_for_dry_run( $candidate['name'], $metadata ) ) {
				$safe_candidates[] = $candidate;
			}
		}

		if ( empty( $safe_candidates ) ) {
			return new WP_Error( 'wpai_wp_abilities_no_safe_candidate', __( 'Nessuna ability AI sicura per il dry-run è stata trovata. Aggiungi il nome dell’ability nelle impostazioni o registra il filtro wpai_publisher_safe_ai_ability_names.', 'wp-ai-publisher' ) );
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new WP_Error( 'wpai_wp_get_ability_unavailable', __( 'wp_get_ability non è disponibile per invocare le ability candidate.', 'wp-ai-publisher' ) );
		}

		foreach ( $safe_candidates as $candidate ) {
			$name = $candidate['name'];
			try {
				$ability = wp_get_ability( $name );
			} catch ( Throwable $error ) {
				unset( $error );
				continue;
			}

			if ( ! is_object( $ability ) ) {
				continue;
			}

			$metadata = $this->extract_ability_metadata( $ability, $name );
			if ( ! $this->is_ability_safe_for_dry_run( $ability, $metadata ) ) {
				continue;
			}
			$inputs   = $this->get_ability_invocation_inputs( $prompt, $payload, $schema, $metadata['input_schema'] );

			foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $method ) {
				if ( ! method_exists( $ability, $method ) || ! is_callable( array( $ability, $method ) ) ) {
					continue;
				}

				foreach ( $inputs as $arguments ) {
					try {
						$result     = $ability->{$method}( $arguments );
						$normalized = $this->normalize_real_ai_candidate( $result );
						if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
							$normalized['source']           = 'wordpress_ai';
							$normalized['validation_notes'] = isset( $normalized['validation_notes'] ) && is_array( $normalized['validation_notes'] ) ? $normalized['validation_notes'] : array();
							$normalized['validation_notes'][] = __( 'Output generato tramite WordPress Abilities API.', 'wp-ai-publisher' );
							return $normalized;
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
		}

		return new WP_Error( 'wpai_wp_abilities_generation_unusable', __( 'Le ability candidate ottenute con wp_get_ability non hanno restituito output JSON utilizzabile.', 'wp-ai-publisher' ) );
	}

	/**
	 * Filter raw wp_get_abilities output to text/content generation ability names.
	 *
	 * @param mixed $abilities Raw abilities.
	 * @return array<int,array{name:string,metadata:array<string,mixed>}>
	 */
	private function filter_wp_abilities_api_generation_candidates( $abilities ) {
		$keywords   = $this->get_generation_ability_keywords();
		$candidates = array();

		foreach ( (array) $abilities as $key => $ability ) {
			$metadata = $this->extract_ability_metadata( $ability, is_string( $key ) ? $key : '' );
			$name     = '' !== $metadata['name'] ? $metadata['name'] : ( is_string( $key ) ? sanitize_text_field( $key ) : '' );

			if ( '' === $name ) {
				continue;
			}

			foreach ( $keywords as $keyword ) {
				if ( false !== strpos( $metadata['haystack'], strtolower( $keyword ) ) ) {
					$candidates[ $name ] = array(
						'name'     => $name,
						'metadata' => $metadata,
					);
					break;
				}
			}
		}

		return array_values( $candidates );
	}

	/**
	 * Try to invoke a relevant WordPress AI / Abilities API ability defensively.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @param string              $prompt Prompt.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_abilities( $payload, $schema, $prompt ) {
		$abilities = $this->get_relevant_generation_abilities();
		if ( empty( $abilities ) ) {
			return new WP_Error( 'wpai_no_relevant_ability', __( 'Nessuna ability WordPress AI rilevante rilevata.', 'wp-ai-publisher' ) );
		}

		$registries = $this->get_possible_ability_registries();
		foreach ( $abilities as $ability ) {
			$ability_id = $ability['id'];
			foreach ( $registries as $registry ) {
				$result = $this->invoke_ability_from_registry( $registry, $ability_id, $payload, $schema, $prompt );
				if ( ! is_wp_error( $result ) && $this->is_usable_dry_run_candidate( $result ) ) {
					return $result;
				}
			}
		}

		return new WP_Error( 'wpai_ability_generation_unusable', __( 'Le ability rilevanti non hanno restituito JSON utilizzabile.', 'wp-ai-publisher' ) );
	}

	/**
	 * Try known global WordPress AI generation functions.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @param string              $prompt Prompt.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_known_functions( $payload, $schema, $prompt ) {
		if ( function_exists( 'wp_ai_generate_text' ) ) {
			$calls = array(
				array(
					'prompt'      => $prompt,
					'temperature' => 0.2,
					'format'      => 'json',
					'schema'      => $schema,
				),
				$prompt,
			);

			foreach ( $calls as $args ) {
				try {
					$result     = call_user_func( 'wp_ai_generate_text', $args );
					$normalized = $this->normalize_real_ai_candidate( $result );
					if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
						return $normalized;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		return new WP_Error( 'wpai_known_function_generation_unusable', __( 'Le funzioni WordPress AI note non sono disponibili o non hanno restituito output valido.', 'wp-ai-publisher' ) );
	}

	/**
	 * Try generation through known WordPress AI client/service objects.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @param string              $prompt Prompt.
	 * @return array<string,mixed>|WP_Error
	 */
	private function try_generate_with_known_clients( $payload, $schema, $prompt ) {
		unset( $payload );

		$client_factories = array( 'wp_ai_client', 'wp_get_ai_client', 'wp_ai_services' );
		$methods          = array( 'generate_text', 'generate', 'complete', 'prompt', 'request', 'create_response', 'text' );

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

			foreach ( $methods as $method ) {
				if ( ! method_exists( $client, $method ) ) {
					continue;
				}

				foreach ( $this->get_client_generation_arguments( $prompt, $schema ) as $arguments ) {
					try {
						$result     = call_user_func_array( array( $client, $method ), $arguments );
						$normalized = $this->normalize_real_ai_candidate( $result );
						if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
							return $normalized;
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
		}

		return new WP_Error( 'wpai_client_generation_unusable', __( 'I client WordPress AI noti non sono disponibili o non hanno restituito output valido.', 'wp-ai-publisher' ) );
	}

	/**
	 * Return cautious client argument shapes.
	 *
	 * @param string              $prompt Prompt.
	 * @param array<string,mixed> $schema Required schema.
	 * @return array<int,array<int,mixed>>
	 */
	private function get_client_generation_arguments( $prompt, $schema ) {
		return array(
			array(
				array(
					'prompt'      => $prompt,
					'temperature' => 0.2,
					'format'      => 'json',
					'schema'      => $schema,
				),
			),
			array( $prompt ),
		);
	}

	/**
	 * Get relevant abilities for text/content generation.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_relevant_generation_abilities() {
		$abilities = $this->get_relevant_abilities();
		$keywords  = $this->get_generation_ability_keywords();
		$relevant  = array();

		foreach ( $abilities as $ability ) {
			$metadata = array(
				'name'          => (string) ( $ability['id'] ?? '' ),
				'label'         => (string) ( $ability['label'] ?? '' ),
				'description'   => (string) ( $ability['description'] ?? '' ),
				'category'      => (string) ( $ability['category'] ?? '' ),
				'input_schema'  => array(),
				'output_schema' => array(),
				'meta'          => array(),
				'haystack'      => (string) ( $ability['haystack'] ?? '' ),
			);
			if ( ! $this->is_ability_safe_for_dry_run( $ability, $metadata ) ) {
				continue;
			}
			$haystack = strtolower( (string) ( $ability['haystack'] ?? implode( ' ', array( $ability['id'], $ability['label'], $ability['description'] ) ) ) );
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
	 * Collect possible ability registries.
	 *
	 * @return array<int,object>
	 */
	private function get_possible_ability_registries() {
		$registries = array();
		foreach ( array( 'wp_ai_abilities', 'wp_get_ai_abilities_registry', 'wp_ai_abilities_registry' ) as $factory ) {
			if ( function_exists( $factory ) ) {
				try {
					$registry = call_user_func( $factory );
					if ( is_object( $registry ) ) {
						$registries[] = $registry;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		foreach ( array( 'WP_AI_Abilities_Registry', 'WP_AI_Ability_Registry', '\WordPress\AI\Abilities\Registry', '\WordPress\AI\Abilities\Abilities_Registry', '\WP_AI\Abilities\Registry' ) as $class_name ) {
			if ( ! class_exists( $class_name ) ) {
				continue;
			}
			foreach ( array( 'get_instance', 'instance' ) as $method ) {
				if ( method_exists( $class_name, $method ) ) {
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
		}

		return $registries;
	}

	/**
	 * Return default and schema-aware ability input shapes.
	 *
	 * @param string              $prompt Prompt.
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required output schema.
	 * @param array<string,mixed> $input_schema Ability input schema.
	 * @return array<int,mixed>
	 */
	private function get_ability_invocation_inputs( $prompt, $payload, $schema, $input_schema = array() ) {
		$default = array(
			'prompt'  => $prompt,
			'input'   => $prompt,
			'content' => $prompt,
			'text'    => $prompt,
			'payload' => $payload,
			'schema'  => $schema,
			'format'  => 'json',
		);

		$inputs = array( $default );
		$schema_properties = $this->get_schema_property_names( $input_schema );
		if ( ! empty( $schema_properties ) ) {
			$schema_aware = array();
			foreach ( array( 'prompt', 'input', 'text', 'content', 'instructions', 'context', 'post_content' ) as $field ) {
				if ( in_array( $field, $schema_properties, true ) ) {
					$schema_aware[ $field ] = $prompt;
				}
			}
			if ( in_array( 'payload', $schema_properties, true ) ) {
				$schema_aware['payload'] = $payload;
			}
			if ( in_array( 'schema', $schema_properties, true ) ) {
				$schema_aware['schema'] = $schema;
			}
			if ( in_array( 'format', $schema_properties, true ) ) {
				$schema_aware['format'] = 'json';
			}
			if ( ! empty( $schema_aware ) ) {
				$inputs[] = $schema_aware;
			}
		}

		$inputs[] = $prompt;

		return $inputs;
	}

	/**
	 * Extract declared property names from JSON-schema-like input schema.
	 *
	 * @param mixed $schema Input schema.
	 * @return array<int,string>
	 */
	private function get_schema_property_names( $schema ) {
		if ( ! is_array( $schema ) ) {
			return array();
		}

		$properties = array();
		if ( isset( $schema['properties'] ) && is_array( $schema['properties'] ) ) {
			$properties = array_merge( $properties, array_keys( $schema['properties'] ) );
		}
		foreach ( $schema as $key => $value ) {
			if ( is_scalar( $key ) ) {
				$properties[] = (string) $key;
			}
			if ( is_array( $value ) && isset( $value['properties'] ) && is_array( $value['properties'] ) ) {
				$properties = array_merge( $properties, array_keys( $value['properties'] ) );
			}
		}

		return array_values( array_unique( array_map( 'sanitize_key', $properties ) ) );
	}

	/**
	 * Invoke a named ability from a registry using flexible method names.
	 *
	 * @param object              $registry Registry object.
	 * @param string              $ability_id Ability ID.
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @param string              $prompt Prompt.
	 * @return array<string,mixed>|WP_Error
	 */
	private function invoke_ability_from_registry( $registry, $ability_id, $payload, $schema, $prompt ) {
		$input = array(
			'prompt'      => $prompt,
			'input'       => $prompt,
			'content'     => $prompt,
			'text'        => $prompt,
			'payload'     => $payload,
			'schema'      => $schema,
			'format'      => 'json',
			'temperature' => 0.2,
		);

		foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $method ) {
			if ( ! method_exists( $registry, $method ) ) {
				continue;
			}

			foreach ( array( array( $ability_id, $input ), array( $ability_id, $prompt ), array( $input ) ) as $arguments ) {
				try {
					$result     = call_user_func_array( array( $registry, $method ), $arguments );
					$normalized = $this->normalize_real_ai_candidate( $result );
					if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
						return $normalized;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		foreach ( array( 'get', 'get_ability', 'ability' ) as $method ) {
			if ( ! method_exists( $registry, $method ) ) {
				continue;
			}

			try {
				$ability = $registry->{$method}( $ability_id );
			} catch ( Throwable $error ) {
				unset( $error );
				continue;
			}

			if ( is_object( $ability ) ) {
				$metadata = $this->extract_ability_metadata( $ability, $ability_id );
				if ( ! $this->is_ability_safe_for_dry_run( $ability, $metadata ) ) {
					continue;
				}
				$inputs   = $this->get_ability_invocation_inputs( $prompt, $payload, $schema, $metadata['input_schema'] );
				foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $ability_method ) {
					if ( method_exists( $ability, $ability_method ) ) {
						foreach ( $inputs as $ability_input ) {
							try {
								$result     = $ability->{$ability_method}( $ability_input );
								$normalized = $this->normalize_real_ai_candidate( $result );
								if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
									return $normalized;
								}
							} catch ( Throwable $error ) {
								unset( $error );
							}
						}
					}
				}
			} elseif ( is_callable( $ability ) ) {
				try {
					$result     = call_user_func( $ability, $input );
					$normalized = $this->normalize_real_ai_candidate( $result );
					if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
						return $normalized;
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}

		return new WP_Error( 'wpai_ability_not_invoked', __( 'Ability non invocabile o output non valido.', 'wp-ai-publisher' ) );
	}

	/**
	 * Normalize a real AI candidate result and mark it as WordPress AI.
	 *
	 * @param mixed $result Raw result.
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_real_ai_candidate( $result ) {
		$normalized = $this->normalize_structured_dry_run_result( $result );
		if ( is_wp_error( $normalized ) ) {
			return $normalized;
		}

		$normalized['source'] = 'wordpress_ai';
		return $normalized;
	}

	/**
	 * Check whether a dry-run candidate satisfies the minimum schema.
	 *
	 * @param mixed $candidate Candidate output.
	 * @return bool
	 */
	private function is_usable_dry_run_candidate( $candidate ) {
		if ( is_wp_error( $candidate ) ) {
			return false;
		}

		if ( class_exists( __NAMESPACE__ . '\Structured_Output_Validator' ) ) {
			$validator = new Structured_Output_Validator();
			$validated = $validator->validate_content_dry_run( $candidate );
			return ! empty( $validated['is_valid'] );
		}

		foreach ( array( 'title', 'slug', 'excerpt', 'content_outline', 'categories', 'tags', 'meta_title', 'meta_description' ) as $field ) {
			if ( empty( $candidate[ $field ] ) ) {
				return false;
			}
		}

		return is_array( $candidate['content_outline'] ) && is_array( $candidate['categories'] ) && is_array( $candidate['tags'] );
	}

	/**
	 * Normalize structured dry-run result.
	 *
	 * @param mixed $result Raw result.
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_structured_dry_run_result( $result ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( is_array( $result ) ) {
			if ( isset( $result['text'] ) && is_string( $result['text'] ) ) {
				$text_decoded = json_decode( $result['text'], true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $text_decoded ) ) {
					return $text_decoded;
				}
			}
			if ( isset( $result['content'] ) && is_string( $result['content'] ) ) {
				$content_decoded = json_decode( $result['content'], true );
				if ( JSON_ERROR_NONE === json_last_error() && is_array( $content_decoded ) ) {
					return $content_decoded;
				}
			}
			return $result;
		}

		if ( is_object( $result ) ) {
			foreach ( array( 'text', 'content', 'response', 'output' ) as $property ) {
				if ( isset( $result->{$property} ) && is_string( $result->{$property} ) ) {
					$decoded = json_decode( $result->{$property}, true );
					if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
						return $decoded;
					}
				}
			}

			$encoded = wp_json_encode( $result );
			if ( false !== $encoded ) {
				$decoded = json_decode( $encoded, true );
				if ( is_array( $decoded ) ) {
					return $decoded;
				}
			}
		}

		if ( is_string( $result ) ) {
			$cleaned = trim( $result );
			$cleaned = preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $cleaned );
			$decoded = json_decode( (string) $cleaned, true );
			if ( JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		return new WP_Error( 'wpai_invalid_wordpress_ai_dry_run', __( 'La risposta WordPress AI non contiene JSON strutturato valido.', 'wp-ai-publisher' ) );
	}

	/**
	 * Build a strict prompt for WordPress AI integrations.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @param array<string,mixed> $schema Required schema.
	 * @return string
	 */
	private function build_structured_dry_run_prompt( $payload, $schema = array() ) {
		$topic = sanitize_textarea_field( (string) ( $payload['topic'] ?? '' ) );
		if ( '' === $topic ) {
			return '';
		}

		$site_context = wpai_publisher_normalize_site_context( $payload['site_context'] ?? wpai_publisher_get_site_context() );
		$context_for_prompt = array(
			'site_profile_name'                    => $site_context['site_profile_name'],
			'site_description'                     => $site_context['site_description'],
			'content_niche'                        => $site_context['content_niche'],
			'default_audience'                     => $site_context['default_audience'],
			'default_language'                     => $site_context['default_language'],
			'default_editor'                       => $site_context['default_editor'],
			'default_post_status_after_generation' => $site_context['default_post_status_after_generation'],
			'global_allowed_category_ids'          => array_values( array_map( 'absint', (array) ( $site_context['allowed_category_ids'] ?? array() ) ) ),
			'internal_link_strategy'               => $site_context['internal_link_strategy'],
			'seo_plugin_preference'                => $site_context['seo_plugin_preference'],
			'writing_rules'                        => $site_context['writing_rules'],
			'forbidden_claims'                     => $site_context['forbidden_claims'],
			'brand_terms'                          => $site_context['brand_terms'],
			'content_format_preference'            => $site_context['content_format_preference'],
		);

		$schema_json  = wp_json_encode( ! empty( $schema ) ? $schema : $this->get_content_dry_run_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$article_type = isset( $payload['article_type'] ) && is_array( $payload['article_type'] ) ? $payload['article_type'] : array();
		$category_boundary = wpai_publisher_resolve_allowed_category_ids( $article_type, $site_context );
		$article_type['final_allowed_category_ids'] = $category_boundary['ids'];
		$article_type['category_boundary_conflict'] = $category_boundary['conflict'];
		$article_type_json = wp_json_encode( $article_type, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$context_json = wp_json_encode( $context_for_prompt, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$sanitize_free_text = static function ( $value ) {
			return is_scalar( $value ) ? sanitize_textarea_field( (string) $value ) : '';
		};
		$editorial_guidance = array();
		foreach ( array(
			'tone'          => 'Tono editoriale',
			'length'        => 'Lunghezza desiderata',
			'search_intent' => 'Intento di ricerca',
			'reader_level'  => 'Livello lettore / guida editoriale per il pubblico',
		) as $field => $label ) {
			$value = $sanitize_free_text( $payload[ $field ] ?? $article_type[ $field ] ?? '' );
			if ( '' !== $value ) {
				$editorial_guidance[] = $label . ': ' . $value;
			}
		}
		$editorial_guidance_section = ! empty( $editorial_guidance ) ? "\nIstruzioni editoriali dalla Tipologia articolo:\n" . implode( "\n", $editorial_guidance ) : '';

		return sprintf(
			"Agisci come assistente editoriale WordPress per un dry-run strutturato. Genera SOLO JSON valido, senza markdown, senza blocchi di codice e senza spiegazioni fuori dal JSON. Non creare post, non pubblicare, non generare immagini reali, non scrivere metadati AIOSEO e non inventare dati tecnici non verificabili. Usa HTML pulito compatibile con Editor Classico per eventuali anteprime e non generare blocchi Gutenberg. Gerarchia prompt: 1 Sicurezza sistema/developer, 2 Contesto globale sito, 3 Regole editoriali globali, 4 Tipologia articolo, 5 Idea utente, 6 Dati di conoscenza, 7 Schema output. In caso di sovrapposizione la Tipologia articolo vince su contesto e vecchie impostazioni globali. Usa il Contesto editoriale del sito come quadro generale. Usa la Tipologia articolo come istruzione specifica principale per outline, full_article, meta, tag, link interni, stile, lunghezza, intento di ricerca e livello lettore. Non creare categorie nuove. Scegli solo tra final_allowed_category_ids quando presenti; sono già l’intersezione tra vincolo globale e Tipologia articolo. Se nessuna categoria consentita è pertinente, lascia categories vuoto o usa una categoria fallback già esistente indicata dal sistema. Non inserire il prompt o le regole editoriali nel contenuto finale. Usa il contesto sito per lingua, target generale, limiti editoriali e claim vietati. Non assumere che il sito sia wptutorial.ai o un sito WordPress tutorial se il contesto editoriale indica altro. Usa le categorie consentite se presenti. Usa i tag preferiti della Tipologia articolo solo se pertinenti; ignora vecchi tag globali quando la Tipologia li definisce. Rispetta gli argomenti esclusi. Non inventare dati tecnici, prezzi, normative, date o promesse se non presenti nel contesto. content_outline deve essere un array di oggetti con heading stringa, level numerico intero e summary stringa. I link interni devono essere target semantici realistici, non URL inventati. Imposta source a wordpress_ai. Schema obbligatorio: %8\$s\nsite_context: %7\$s\narticle_type: %9\$s\nArgomento: %1\$s\nKeyword: %2\$s\nLingua richiesta: %3\$s\nPubblico target: %4\$s%10\$s\nLivello tutorial compatibilità schema: %5\$s",
			$topic,
			sanitize_text_field( (string) ( $payload['keyword'] ?? '' ) ),
			sanitize_key( (string) ( $payload['language'] ?? $site_context['default_language'] ) ),
			sanitize_text_field( (string) ( $payload['target_audience'] ?: $site_context['default_audience'] ) ),
			sanitize_key( (string) ( $payload['tutorial_level'] ?? 'base' ) ),
			sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			false !== $context_json ? $context_json : '{}',
			false !== $schema_json ? $schema_json : '{}',
			false !== $article_type_json ? $article_type_json : '{}',
			$editorial_guidance_section
		);
	}

	/**
	 * Generate deterministic local dry-run output for development workflow testing.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>
	 */
	private function generate_local_structured_content_dry_run( $payload ) {
		$topic           = sanitize_textarea_field( (string) ( $payload['topic'] ?? __( 'Idea contenuto', 'wp-ai-publisher' ) ) );
		$keyword         = sanitize_text_field( (string) ( $payload['keyword'] ?? '' ) );
		$site_context    = wpai_publisher_normalize_site_context( $payload['site_context'] ?? wpai_publisher_get_site_context() );
		$language        = sanitize_key( (string) ( $payload['language'] ?? $site_context['default_language'] ) );
		$target_audience = sanitize_text_field( (string) ( $payload['target_audience'] ?: $site_context['default_audience'] ) );
		$tutorial_level  = sanitize_key( (string) ( $payload['tutorial_level'] ?? 'base' ) );
		$profile         = $this->get_contextual_local_profile( $topic, $keyword, $site_context );
		$title           = $this->limit_local_title( $this->build_contextual_local_title( $topic, $keyword ) );
		$slug            = $this->build_contextual_local_slug( $title, $profile );
		$audience_text   = '' !== $target_audience ? $target_audience : ( '' !== $site_context['default_audience'] ? $site_context['default_audience'] : __( 'lettori del sito', 'wp-ai-publisher' ) );
		$article_type    = isset( $payload['article_type'] ) && is_array( $payload['article_type'] ) ? $payload['article_type'] : array();
		$outline         = $this->build_contextual_local_outline( $topic, $keyword, $site_context );
		if ( ! empty( $article_type['structure'] ) ) { $outline = array_map( static function( $heading ) { return array( 'heading' => sanitize_text_field( $heading ), 'level' => 2, 'summary' => sprintf( __( 'Sezione richiesta dalla Tipologia articolo: %s.', 'wp-ai-publisher' ), sanitize_text_field( $heading ) ) ); }, array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $article_type['structure'] ) ) ) ); }
		$meta_title      = $this->limit_local_title( $profile['meta_title'] ?? $title );
		$category_boundary = wpai_publisher_resolve_allowed_category_ids( $article_type, $site_context );
		$validation_notes = ! empty( $category_boundary['conflict'] ) ? array( (string) $category_boundary['message'] ) : array();

		return array(
			'title'                  => $title,
			'slug'                   => $slug,
			'excerpt'                => sprintf( __( 'Traccia editoriale per spiegare %1$s a %2$s con HTML pulito per Editor Classico, senza creare bozze o pubblicare contenuti.', 'wp-ai-publisher' ), $topic, $audience_text ),
			'content_outline'        => $outline,
			'category_ids'            => $category_boundary['ids'],
			'categories'             => array(),
			'tags'                   => $this->get_contextual_local_tags( $keyword, $topic, $site_context, $profile ),
			'meta_title'             => $meta_title,
			'meta_description'       => sprintf( __( 'Struttura preliminare per %s, utile per validare flusso e outline senza pubblicare nulla.', 'wp-ai-publisher' ), $topic ),
			'open_graph_title'       => $title,
			'open_graph_description' => sprintf( __( 'Anteprima editoriale controllata per %s.', 'wp-ai-publisher' ), $topic ),
			'twitter_title'          => $title,
			'twitter_description'    => sprintf( __( 'Dry-run editoriale: %s.', 'wp-ai-publisher' ), $topic ),
			'featured_image_prompt'  => sprintf( __( 'Illustrazione editoriale concettuale per %s, senza generare immagini reali.', 'wp-ai-publisher' ), $topic ),
			'internal_image_prompts' => array(
				sprintf( __( 'Schema visuale dei passaggi operativi per %s.', 'wp-ai-publisher' ), $topic ),
				sprintf( __( 'Checklist visiva di verifica finale per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'image_alt_texts'        => array(
				sprintf( __( 'Schema editoriale per %s.', 'wp-ai-publisher' ), $topic ),
				sprintf( __( 'Checklist di controllo per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'image_captions'         => array(
				sprintf( __( 'Percorso operativo proposto per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'internal_link_targets'  => $profile['internal_link_targets'],
			'knowledge_summary'      => sprintf( __( 'Sintesi locale basata sull’argomento inserito: %s. Il contenuto resta da verificare con revisione umana prima di qualsiasi pubblicazione.', 'wp-ai-publisher' ), $topic ),
			'entities'               => array_values( array_unique( array_filter( array( $this->extract_primary_entity( $topic, $keyword ), $keyword, $site_context['content_niche'] ) ) ) ),
			'search_intent'          => $this->get_contextual_search_intent( $site_context ),
			'tutorial_level'         => in_array( $tutorial_level, array( 'principianti', 'intermedi', 'avanzati', 'misto', 'base', 'intermedio', 'avanzato' ), true ) ? $tutorial_level : 'principianti',
			'article_type'           => $article_type,
			'cluster_topic'          => '' !== $keyword ? $keyword : wp_trim_words( $topic, 4, '' ),
			'subtopic'               => $topic,
			'validation_notes'       => array(
				__( 'Dry-run generato in modalità locale perché il sistema WordPress AI non ha restituito un output utilizzabile.', 'wp-ai-publisher' ),
			),
			'language'               => '' !== $language ? $language : 'it',
			'source'                 => 'local_fallback',
		);
	}

	/**
	 * Build contextual fallback outline from topic and keyword.
	 *
	 * @param string $topic Topic.
	 * @param string $keyword Keyword.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_contextual_local_outline( $topic, $keyword, $site_context = array() ) {
		$site_context = wpai_publisher_normalize_site_context( $site_context );
		$profile = $this->get_contextual_local_profile( $topic, $keyword, $site_context );
		$outline = array();

		foreach ( $profile['outline'] as $section ) {
			$outline[] = array(
				'heading' => $section['heading'],
				'level'   => 2,
				'summary' => $section['summary'],
			);
		}

		return $outline;
	}

	/**
	 * Build a readable fallback title from topic and keyword.
	 *
	 * @param string $topic Topic.
	 * @param string $keyword Keyword.
	 * @return string
	 */
	private function build_contextual_local_title( $topic, $keyword ) {
		$profile = $this->get_contextual_local_profile( $topic, $keyword, wpai_publisher_get_site_context() );
		if ( ! empty( $profile['title'] ) ) {
			return $profile['title'];
		}

		$raw = trim( '' !== $topic ? $topic : $keyword );
		$raw = $this->normalize_brand_capitalization( $raw );
		$raw = preg_replace( '/\s*,\s*/', ': ', $raw );
		$raw = trim( preg_replace( '/\s+/', ' ', (string) $raw ) );

		if ( '' === $raw ) {
			return __( 'Guida pratica', 'wp-ai-publisher' );
		}

		if ( preg_match( '/\bcome\b/i', $raw ) ) {
			$clean = preg_replace( '/^come\s+/i', '', (string) $raw );
			$clean = trim( preg_replace( '/\s+/', ' ', (string) $clean ) );
			if ( '' !== $clean ) {
				return $this->limit_local_title( 'Come ' . lcfirst( $this->normalize_brand_capitalization( $clean ) ) );
			}
		}

		return $this->limit_local_title( ucfirst( $raw ) );
	}

	/**
	 * Build a clean slug from the generated fallback title.
	 *
	 * @param string              $title Title.
	 * @param array<string,mixed> $profile Optional fallback profile.
	 * @return string
	 */
	private function build_contextual_local_slug( $title, $profile = array() ) {
		if ( ! empty( $profile['slug'] ) ) {
			return sanitize_title( (string) $profile['slug'] );
		}

		$slug       = sanitize_title( str_replace( 'menù', 'menu', $title ) );
		$stop_words = array( 'un', 'una', 'il', 'lo', 'la', 'le', 'gli', 'di', 'a', 'in', 'per', 'con', 'e', 'nei', 'nelle', 'del', 'della' );
		$parts      = array_values( array_filter( explode( '-', $slug ), static function ( $part ) use ( $stop_words ) {
			return '' !== $part && ! in_array( $part, $stop_words, true );
		} ) );

		return implode( '-', array_slice( $parts, 0, 7 ) );
	}

	/**
	 * Limit a fallback title to an SEO-friendly length where possible.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	private function limit_local_title( $title ) {
		$title = trim( preg_replace( '/\s+/', ' ', $this->normalize_brand_capitalization( (string) $title ) ) );
		if ( function_exists( 'mb_strlen' ) && mb_strlen( $title ) <= 60 ) {
			return $title;
		}
		if ( ! function_exists( 'mb_strlen' ) && strlen( $title ) <= 60 ) {
			return $title;
		}

		$short = wp_trim_words( $title, 8, '' );
		return '' !== $short ? $short : $title;
	}

	/**
	 * Return contextual fallback data for common WordPress topics.
	 *
	 * @param string $topic Topic.
	 * @param string $keyword Keyword.
	 * @return array<string,mixed>
	 */
	private function get_contextual_local_profile( $topic, $keyword, $site_context = array() ) {
		$site_context = wpai_publisher_normalize_site_context( $site_context );
		$niche_profile = $this->get_niche_local_profile( $topic, $keyword, $site_context );
		if ( ! empty( $niche_profile ) ) {
			return $niche_profile;
		}

		$patterns = $this->detect_wordpress_editorial_patterns( $topic, $keyword );
		$entity   = $this->extract_primary_entity( $topic, $keyword );
		if ( '' === $entity ) {
			$entity = __( 'il tema scelto', 'wp-ai-publisher' );
		}

		$profiles = array(
			'widget' => array(
				'title'                 => __( 'Widget WordPress: cosa sono e come usarli nei temi', 'wp-ai-publisher' ),
				'meta_title'            => __( 'Widget WordPress: cosa sono e come usarli', 'wp-ai-publisher' ),
				'slug'                  => 'widget-wordpress-cosa-sono-come-usarli',
				'internal_link_targets' => array(
					__( 'Guida ai temi WordPress', 'wp-ai-publisher' ),
					__( 'Come personalizzare il footer in WordPress', 'wp-ai-publisher' ),
					__( 'Differenza tra widget e blocchi', 'wp-ai-publisher' ),
					__( 'Come usare il Customizer di WordPress', 'wp-ai-publisher' ),
				),
				'outline'               => array(
					array( 'heading' => __( 'Cosa sono i widget in WordPress', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare che i widget sono elementi riutilizzabili che permettono di aggiungere contenuti o funzioni in aree predisposte dal tema, come sidebar, footer o altre zone widget.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Dove si trovano i widget nel pannello di amministrazione', 'wp-ai-publisher' ), 'summary' => __( 'Mostrare dove trovare la sezione dei widget nel pannello WordPress e chiarire che il percorso può variare in base al tema e alla configurazione del sito.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Che differenza c’è tra widget, blocchi e aree widget', 'wp-ai-publisher' ), 'summary' => __( 'Chiarire che i widget sono contenuti o funzioni, i blocchi sono elementi dell’editor moderno e le aree widget sono gli spazi del tema in cui inserirli.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come aggiungere un widget a una sidebar o al footer', 'wp-ai-publisher' ), 'summary' => __( 'Descrivere l’inserimento di un widget in un’area disponibile, scegliendo il contenuto, salvando le modifiche e controllando che la posizione sia quella prevista.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come ordinare e rimuovere i widget', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare come cambiare l’ordine dei widget, rimuovere quelli non necessari e mantenere una struttura semplice per evitare confusione nel layout.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come dipendono i widget dal tema attivo', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare che non tutti i temi espongono le stesse aree widget e che sidebar, footer e header possono comportarsi in modo diverso.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come verificare il risultato nel sito pubblico', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire una verifica dal front-end dopo ogni modifica, soprattutto su desktop e mobile, controllando leggibilità, spaziature e coerenza con il tema.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Errori comuni da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Evidenziare gli errori più frequenti: inserire troppi widget, duplicare contenuti, ignorare la versione mobile o modificare aree non visibili nel tema attivo.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Quando usare un plugin per widget avanzati', 'wp-ai-publisher' ), 'summary' => __( 'Indicare quando può servire un plugin dedicato, per esempio per regole di visibilità, widget personalizzati o layout più complessi non gestiti dal tema.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Prossimi passi', 'wp-ai-publisher' ), 'summary' => __( 'Invitare a documentare le aree widget del tema, testare ogni modifica e pianificare eventuali personalizzazioni solo dopo una revisione del layout.', 'wp-ai-publisher' ) ),
				),
			),
			'menu' => array(
				'title'                 => __( 'Come creare un menù di navigazione in WordPress', 'wp-ai-publisher' ),
				'meta_title'            => __( 'Menù WordPress: crearli e gestirli', 'wp-ai-publisher' ),
				'slug'                  => 'menu-navigazione-wordpress-crearli-gestirli',
				'internal_link_targets' => array( __( 'Come creare pagine in WordPress', 'wp-ai-publisher' ), __( 'Guida ai menu di navigazione', 'wp-ai-publisher' ), __( 'Come gestire header e footer del tema', 'wp-ai-publisher' ) ),
				'outline'               => array(
					array( 'heading' => __( 'Cos’è un menù di navigazione in WordPress', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare che il menù aiuta gli utenti a raggiungere pagine, categorie e sezioni importanti del sito con un percorso chiaro.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Dove si gestiscono i menù nel pannello di WordPress', 'wp-ai-publisher' ), 'summary' => __( 'Indicare le aree del pannello in cui possono essere gestiti i menù e chiarire che tema e configurazione possono modificare il percorso.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come creare un nuovo menù', 'wp-ai-publisher' ), 'summary' => __( 'Descrivere la creazione di un menù assegnando un nome riconoscibile e preparando le voci principali prima di salvarlo.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come aggiungere pagine, articoli e categorie al menù', 'wp-ai-publisher' ), 'summary' => __( 'Mostrare quali contenuti possono diventare voci di navigazione e consigliare di scegliere solo collegamenti utili per l’utente.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come ordinare le voci del menù', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare come organizzare le voci in ordine logico, mettendo prima le pagine più importanti e riducendo i passaggi inutili.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come assegnare il menù a una posizione del tema', 'wp-ai-publisher' ), 'summary' => __( 'Chiarire che un menù diventa visibile solo se viene assegnato a una posizione prevista dal tema, come header, footer o area mobile.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come creare sottovoci e menù a tendina', 'wp-ai-publisher' ), 'summary' => __( 'Descrivere l’uso delle sottovoci per raggruppare contenuti correlati senza rendere la navigazione troppo profonda.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Come verificare il menù sul sito', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire di provare il menù da desktop e mobile, controllando apertura, link, gerarchie e leggibilità.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Errori comuni da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Evidenziare voci duplicate, link rotti, etichette poco chiare e menù troppo lunghi che rendono difficile la navigazione.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Prossimi passi', 'wp-ai-publisher' ), 'summary' => __( 'Invitare a rivedere periodicamente il menù quando cambiano pagine, categorie o struttura editoriale del sito.', 'wp-ai-publisher' ) ),
				),
			),
			'wpml' => array(
				'title'                 => __( 'WPML in WordPress: configurazione e traduzioni', 'wp-ai-publisher' ),
				'meta_title'            => __( 'WPML WordPress: guida alle traduzioni', 'wp-ai-publisher' ),
				'slug'                  => 'wpml-wordpress-configurazione-traduzioni',
				'internal_link_targets' => array( __( 'Come creare un sito multilingua WordPress', 'wp-ai-publisher' ), __( 'Tradurre pagine e articoli in WordPress', 'wp-ai-publisher' ), __( 'SEO per siti multilingua', 'wp-ai-publisher' ) ),
				'outline'               => array(
					array( 'heading' => __( 'Cos’è WPML e a cosa serve', 'wp-ai-publisher' ), 'summary' => __( 'Presentare WPML come plugin per gestire contenuti multilingua, collegando versioni tradotte di pagine, articoli, tassonomie e parti del tema.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Quando conviene usare WPML', 'wp-ai-publisher' ), 'summary' => __( 'Chiarire in quali casi WPML è adatto: siti con più lingue, redazioni strutturate, URL localizzati e necessità di controllo sulle traduzioni.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Requisiti prima dell’installazione', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire backup, verifica compatibilità del tema e controllo dei plugin attivi prima di modificare la gestione linguistica del sito.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Installazione e attivazione del plugin', 'wp-ai-publisher' ), 'summary' => __( 'Descrivere il flusso generale di installazione e ricordare che licenza, moduli aggiuntivi e permessi devono essere gestiti nel pannello WordPress.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Configurazione guidata delle lingue', 'wp-ai-publisher' ), 'summary' => __( 'Spiegare la scelta della lingua principale, delle lingue secondarie e del formato URL più adatto alla struttura del sito.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Traduzione di pagine e articoli', 'wp-ai-publisher' ), 'summary' => __( 'Illustrare come collegare ogni contenuto alla relativa traduzione e verificare che titoli, slug, excerpt e contenuto siano coerenti.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Traduzione menu e stringhe del tema', 'wp-ai-publisher' ), 'summary' => __( 'Evidenziare che menu, widget e stringhe del tema possono richiedere pannelli o moduli specifici rispetto alla traduzione dei post.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'SEO multilingua e URL', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire di verificare slug, meta title, descrizioni e collegamenti hreflang generati dall’integrazione SEO compatibile.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Errori comuni da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Segnalare contenuti tradotti solo in parte, menu non allineati, URL incoerenti e modifiche senza backup preventivo.', 'wp-ai-publisher' ) ),
					array( 'heading' => __( 'Verifica finale', 'wp-ai-publisher' ), 'summary' => __( 'Consigliare un controllo front-end lingua per lingua, testando navigazione, link interni, switcher e contenuti principali.', 'wp-ai-publisher' ) ),
				),
			),
		);

		$generic_labels = array(
			'plugin'         => array( __( 'Plugin WordPress: installazione e configurazione', 'wp-ai-publisher' ), 'plugin-wordpress-installazione-configurazione', array( __( 'Come scegliere plugin WordPress affidabili', 'wp-ai-publisher' ), __( 'Checklist compatibilità plugin', 'wp-ai-publisher' ), __( 'Aggiornare plugin WordPress in sicurezza', 'wp-ai-publisher' ) ) ),
			'tema'           => array( __( 'Tema WordPress: scelta, impostazioni e verifiche', 'wp-ai-publisher' ), 'tema-wordpress-scelta-impostazioni-verifiche', array( __( 'Guida ai temi WordPress', 'wp-ai-publisher' ), __( 'Come usare il Customizer di WordPress', 'wp-ai-publisher' ), __( 'Child theme: quando usarlo', 'wp-ai-publisher' ) ) ),
			'categorie'      => array( __( 'Categorie WordPress: organizzarle senza errori', 'wp-ai-publisher' ), 'categorie-wordpress-organizzarle', array( __( 'Differenza tra categorie e tag', 'wp-ai-publisher' ), __( 'Archivi WordPress e tassonomie', 'wp-ai-publisher' ), __( 'SEO per categorie WordPress', 'wp-ai-publisher' ) ) ),
			'tag'            => array( __( 'Tag WordPress: quando usarli e come gestirli', 'wp-ai-publisher' ), 'tag-wordpress-quando-usarli', array( __( 'Differenza tra categorie e tag', 'wp-ai-publisher' ), __( 'Come organizzare gli archivi WordPress', 'wp-ai-publisher' ), __( 'Pulizia tag duplicati', 'wp-ai-publisher' ) ) ),
			'permalink'      => array( __( 'Permalink WordPress: struttura e controlli SEO', 'wp-ai-publisher' ), 'permalink-wordpress-struttura-controlli-seo', array( __( 'Impostazioni permalink WordPress', 'wp-ai-publisher' ), __( 'Redirect dopo cambio URL', 'wp-ai-publisher' ), __( 'SEO tecnica per WordPress', 'wp-ai-publisher' ) ) ),
			'media_library'  => array( __( 'Media Library WordPress: gestione ordinata dei file', 'wp-ai-publisher' ), 'media-library-wordpress-gestione-file', array( __( 'Ottimizzare immagini WordPress', 'wp-ai-publisher' ), __( 'Testi alternativi per immagini', 'wp-ai-publisher' ), __( 'Pulizia file media inutilizzati', 'wp-ai-publisher' ) ) ),
			'seo'            => array( __( 'SEO WordPress: controlli base prima di pubblicare', 'wp-ai-publisher' ), 'seo-wordpress-controlli-base', array( __( 'Meta title e meta description', 'wp-ai-publisher' ), __( 'Link interni WordPress', 'wp-ai-publisher' ), __( 'Checklist SEO on-page', 'wp-ai-publisher' ) ) ),
			'sicurezza'      => array( __( 'Sicurezza WordPress: controlli essenziali', 'wp-ai-publisher' ), 'sicurezza-wordpress-controlli-essenziali', array( __( 'Aggiornamenti WordPress sicuri', 'wp-ai-publisher' ), __( 'Ruoli e permessi utente', 'wp-ai-publisher' ), __( 'Backup prima degli aggiornamenti', 'wp-ai-publisher' ) ) ),
			'backup'         => array( __( 'Backup WordPress: cosa salvare e come verificarlo', 'wp-ai-publisher' ), 'backup-wordpress-cosa-salvare', array( __( 'Backup database WordPress', 'wp-ai-publisher' ), __( 'Ripristino sito WordPress', 'wp-ai-publisher' ), __( 'Backup prima degli aggiornamenti', 'wp-ai-publisher' ) ) ),
			'classic_editor' => array( __( 'Classic Editor: usare HTML pulito in WordPress', 'wp-ai-publisher' ), 'classic-editor-html-pulito-wordpress', array( __( 'Editor Classico vs Gutenberg', 'wp-ai-publisher' ), __( 'Formattazione HTML sicura', 'wp-ai-publisher' ), __( 'Workflow editoriale WordPress', 'wp-ai-publisher' ) ) ),
			'woocommerce'    => array( __( 'WooCommerce: impostazioni iniziali da verificare', 'wp-ai-publisher' ), 'woocommerce-impostazioni-iniziali', array( __( 'Configurare pagamenti WooCommerce', 'wp-ai-publisher' ), __( 'Spedizioni WooCommerce', 'wp-ai-publisher' ), __( 'Schede prodotto ottimizzate', 'wp-ai-publisher' ) ) ),
			'elementor'      => array( __( 'Elementor in WordPress: uso sicuro e ordinato', 'wp-ai-publisher' ), 'elementor-wordpress-uso-sicuro', array( __( 'Tema compatibile con Elementor', 'wp-ai-publisher' ), __( 'Header e footer con Elementor', 'wp-ai-publisher' ), __( 'Performance pagine Elementor', 'wp-ai-publisher' ) ) ),
		);

		foreach ( array( 'widget', 'menu', 'wpml' ) as $priority_pattern ) {
			if ( in_array( $priority_pattern, $patterns, true ) ) {
				return $profiles[ $priority_pattern ];
			}
		}

		foreach ( $generic_labels as $pattern => $data ) {
			if ( in_array( $pattern, $patterns, true ) ) {
				return array(
					'title'                 => $data[0],
					'meta_title'            => $data[0],
					'slug'                  => $data[1],
					'internal_link_targets' => $data[2],
					'outline'               => $this->build_generic_local_sections( $entity, $pattern ),
				);
			}
		}

		return array(
			'title'                 => sprintf( __( 'Guida informativa: %s', 'wp-ai-publisher' ), $this->normalize_brand_capitalization( $entity ) ),
			'meta_title'            => sprintf( __( '%s: guida informativa', 'wp-ai-publisher' ), $this->normalize_brand_capitalization( $entity ) ),
			'slug'                  => sanitize_title( 'guida-informativa-' . $entity ),
			'internal_link_targets' => array( __( 'Approfondimenti correlati', 'wp-ai-publisher' ), __( 'Guide introduttive', 'wp-ai-publisher' ), __( 'Checklist editoriale', 'wp-ai-publisher' ) ),
			'outline'               => $this->build_informational_local_sections( $this->normalize_brand_capitalization( $entity ) ),
		);
	}

	/**
	 * Return a local fallback profile based on configured editorial niche.
	 *
	 * @param string              $topic Topic.
	 * @param string              $keyword Keyword.
	 * @param array<string,mixed> $site_context Site context.
	 * @return array<string,mixed>
	 */
	private function get_niche_local_profile( $topic, $keyword, $site_context ) {
		$haystack = strtolower( remove_accents( implode( ' ', array( $site_context['content_niche'] ?? '', $site_context['content_format_preference'] ?? '', $topic, $keyword ) ) ) );
		$entity   = $this->extract_primary_entity( $topic, $keyword );
		if ( '' === $entity ) {
			$entity = __( 'il tema scelto', 'wp-ai-publisher' );
		}
		$entity_label = $this->normalize_brand_capitalization( $entity );

		if ( preg_match( '/\b(viaggi|travel|turismo)\b/', $haystack ) ) {
			return array(
				'title'                 => sprintf( __( 'Guida viaggio: %s', 'wp-ai-publisher' ), $entity_label ),
				'meta_title'            => sprintf( __( 'Guida viaggio: %s', 'wp-ai-publisher' ), $entity_label ),
				'slug'                  => sanitize_title( 'guida-viaggio-' . $entity ),
				'internal_link_targets' => array( __( 'Itinerari correlati', 'wp-ai-publisher' ), __( 'Consigli pratici di viaggio', 'wp-ai-publisher' ), __( 'Cosa vedere nella stessa area', 'wp-ai-publisher' ) ),
				'outline'               => $this->build_travel_local_sections( $entity_label ),
			);
		}

		if ( preg_match( '/\b(giardinaggio|agricoltura|e-commerce|ecommerce|prodotti|prodotto)\b/', $haystack ) ) {
			return array(
				'title'                 => sprintf( __( 'Guida acquisto e uso: %s', 'wp-ai-publisher' ), $entity_label ),
				'meta_title'            => sprintf( __( '%s: guida pratica e criteri di scelta', 'wp-ai-publisher' ), $entity_label ),
				'slug'                  => sanitize_title( 'guida-acquisto-uso-' . $entity ),
				'internal_link_targets' => array( __( 'Categorie prodotto correlate', 'wp-ai-publisher' ), __( 'Guide alla scelta dei materiali', 'wp-ai-publisher' ), __( 'Consigli di manutenzione', 'wp-ai-publisher' ) ),
				'outline'               => $this->build_product_local_sections( $entity_label ),
			);
		}

		if ( preg_match( '/\b(ristorante|food|locale|ristorazione)\b/', $haystack ) ) {
			return array(
				'title'                 => sprintf( __( 'Guida locale: %s', 'wp-ai-publisher' ), $entity_label ),
				'meta_title'            => sprintf( __( '%s: esperienza, servizi e informazioni utili', 'wp-ai-publisher' ), $entity_label ),
				'slug'                  => sanitize_title( 'guida-locale-' . $entity ),
				'internal_link_targets' => array( __( 'Servizi correlati', 'wp-ai-publisher' ), __( 'Esperienze nella zona', 'wp-ai-publisher' ), __( 'Informazioni pratiche per i clienti', 'wp-ai-publisher' ) ),
				'outline'               => $this->build_local_experience_sections( $entity_label ),
			);
		}

		if ( preg_match( '/\b(wordpress|plugin|tema|seo wordpress)\b/', $haystack ) ) {
			return array();
		}

		if ( '' !== trim( (string) ( $site_context['content_niche'] ?? '' ) ) ) {
			return array(
				'title'                 => sprintf( __( 'Guida informativa: %s', 'wp-ai-publisher' ), $entity_label ),
				'meta_title'            => sprintf( __( '%s: guida informativa', 'wp-ai-publisher' ), $entity_label ),
				'slug'                  => sanitize_title( 'guida-informativa-' . $entity ),
				'internal_link_targets' => array( __( 'Approfondimenti correlati', 'wp-ai-publisher' ), __( 'Guide introduttive', 'wp-ai-publisher' ), __( 'Checklist editoriale', 'wp-ai-publisher' ) ),
				'outline'               => $this->build_informational_local_sections( $entity_label ),
			);
		}

		return array();
	}

	/** @return array<int,array<string,string>> */
	private function build_travel_local_sections( $entity ) {
		return array(
			array( 'heading' => sprintf( __( 'Perché visitare %s', 'wp-ai-publisher' ), $entity ), 'summary' => __( 'Inquadrare il valore della destinazione senza inventare date, prezzi o informazioni non presenti nel contesto.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Cosa vedere e cosa fare', 'wp-ai-publisher' ), 'summary' => __( 'Organizzare attrazioni, esperienze e attività come spunti da verificare editorialmente prima della pubblicazione.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Itinerario consigliato', 'wp-ai-publisher' ), 'summary' => __( 'Proporre una sequenza logica di visita, mantenendo indicazioni generiche se mancano dati locali certi.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Consigli pratici per il viaggio', 'wp-ai-publisher' ), 'summary' => __( 'Evidenziare trasporti, periodo, budget e documenti come aspetti da verificare, senza inventare normative o costi.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Errori da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire controlli su orari, prenotazioni e condizioni locali prima di finalizzare il contenuto.', 'wp-ai-publisher' ) ),
		);
	}

	/** @return array<int,array<string,string>> */
	private function build_product_local_sections( $entity ) {
		return array(
			array( 'heading' => sprintf( __( 'A cosa serve %s', 'wp-ai-publisher' ), $entity ), 'summary' => __( 'Descrivere l’utilità del prodotto o tema senza promesse garantite e senza dati tecnici inventati.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Come scegliere in base alle esigenze', 'wp-ai-publisher' ), 'summary' => __( 'Confrontare criteri pratici, materiali, uso previsto e limiti, rimandando i dettagli specifici alle schede verificate.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Caratteristiche da controllare', 'wp-ai-publisher' ), 'summary' => __( 'Elencare aspetti controllabili come dimensioni, compatibilità, manutenzione e condizioni d’uso senza inventare valori.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Uso, cura e manutenzione', 'wp-ai-publisher' ), 'summary' => __( 'Fornire consigli generici e sicuri, adattabili a giardinaggio, agricoltura o cataloghi e-commerce.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Quando chiedere una consulenza', 'wp-ai-publisher' ), 'summary' => __( 'Indicare i casi in cui servono verifiche professionali, schede tecniche ufficiali o assistenza del venditore.', 'wp-ai-publisher' ) ),
		);
	}

	/** @return array<int,array<string,string>> */
	private function build_local_experience_sections( $entity ) {
		return array(
			array( 'heading' => sprintf( __( 'Esperienza proposta da %s', 'wp-ai-publisher' ), $entity ), 'summary' => __( 'Descrivere atmosfera, proposta e pubblico ideale senza inventare menu, prezzi, orari o disponibilità.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Servizi e punti di forza', 'wp-ai-publisher' ), 'summary' => __( 'Organizzare i servizi come traccia editoriale da completare con informazioni confermate dal sito o dal cliente.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'A chi è adatto', 'wp-ai-publisher' ), 'summary' => __( 'Collegare la proposta a esigenze reali dei clienti e al pubblico target configurato.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Informazioni pratiche da verificare', 'wp-ai-publisher' ), 'summary' => __( 'Segnalare che orari, prezzi, prenotazioni, allergeni o normative devono essere controllati prima della bozza reale.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Prossimi passi per il cliente', 'wp-ai-publisher' ), 'summary' => __( 'Proporre chiamate all’azione informative e non aggressive, coerenti con il contesto locale.', 'wp-ai-publisher' ) ),
		);
	}

	/** @return array<int,array<string,string>> */
	private function build_informational_local_sections( $entity ) {
		return array(
			array( 'heading' => sprintf( __( 'Cos’è %s', 'wp-ai-publisher' ), $entity ), 'summary' => __( 'Definire il tema in modo chiaro e coerente con la nicchia configurata.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Perché è rilevante per il pubblico', 'wp-ai-publisher' ), 'summary' => __( 'Collegare l’argomento ai bisogni informativi del target senza assumere un contesto WordPress se non configurato.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Aspetti principali da conoscere', 'wp-ai-publisher' ), 'summary' => __( 'Organizzare concetti, benefici, limiti e verifiche in sezioni brevi e revisionabili.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Errori o fraintendimenti da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Evidenziare claim da non fare, dati da controllare e informazioni da non inventare.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Approfondimenti consigliati', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire collegamenti interni come target semantici e non come URL inventati.', 'wp-ai-publisher' ) ),
		);
	}

	/**
	 * Return context-aware fallback categories.
	 *
	 * @param array<string,mixed> $site_context Site context.
	 * @param array<string,mixed> $profile Local profile.
	 * @return array<int,string>
	 */
	private function get_contextual_local_categories( $site_context, $profile ) {
		$allowed = array();
		if ( ! empty( $allowed ) ) {
			return array_slice( $allowed, 0, 3 );
		}

		$niche = strtolower( remove_accents( (string) ( $site_context['content_niche'] ?? '' ) ) );
		if ( false !== strpos( $niche, 'viaggi' ) || false !== strpos( $niche, 'travel' ) || false !== strpos( $niche, 'turismo' ) ) {
			return array( __( 'Guide viaggio', 'wp-ai-publisher' ), __( 'Consigli di viaggio', 'wp-ai-publisher' ) );
		}
		if ( false !== strpos( $niche, 'giardinaggio' ) || false !== strpos( $niche, 'agricoltura' ) || false !== strpos( $niche, 'e-commerce' ) ) {
			return array( __( 'Guide prodotto', 'wp-ai-publisher' ), __( 'Consigli pratici', 'wp-ai-publisher' ) );
		}
		if ( false !== strpos( $niche, 'ristorante' ) || false !== strpos( $niche, 'food' ) || false !== strpos( $niche, 'locale' ) ) {
			return array( __( 'Guide locali', 'wp-ai-publisher' ), __( 'Esperienze', 'wp-ai-publisher' ) );
		}

		$profile_title = strtolower( remove_accents( (string) ( $profile['title'] ?? '' ) ) );
		if ( false !== strpos( $niche, 'wordpress' ) || false !== strpos( $profile_title, 'wordpress' ) ) {
			return array( __( 'Guide WordPress', 'wp-ai-publisher' ), __( 'Tutorial', 'wp-ai-publisher' ) );
		}

		return array( __( 'Guide', 'wp-ai-publisher' ), __( 'Approfondimenti', 'wp-ai-publisher' ) );
	}

	/**
	 * Return context-aware fallback tags.
	 *
	 * @param string              $keyword Keyword.
	 * @param string              $topic Topic.
	 * @param array<string,mixed> $site_context Site context.
	 * @param array<string,mixed> $profile Local profile.
	 * @return array<int,string>
	 */
	private function get_contextual_local_tags( $keyword, $topic, $site_context, $profile ) {
		$tags = array_filter( array( $keyword, $this->extract_primary_entity( $topic, $keyword ) ) );
		if ( empty( $profile['article_type_has_tags'] ) ) { $tags = array_merge( $tags, wpai_publisher_split_context_list( (string) ( $site_context['preferred_tags'] ?? '' ) ) ); }
		$profile_title = strtolower( remove_accents( (string) ( $profile['title'] ?? '' ) ) );
		if ( false !== strpos( $profile_title, 'wordpress' ) ) {
			$tags[] = __( 'wordpress', 'wp-ai-publisher' );
			$tags[] = __( 'tutorial', 'wp-ai-publisher' );
		}

		return array_values( array_unique( array_filter( array_slice( $tags, 0, 8 ) ) ) );
	}

	/**
	 * Return search intent from site context.
	 *
	 * @param array<string,mixed> $site_context Site context.
	 * @return string
	 */
	private function get_contextual_search_intent( $site_context ) {
		$format = (string) ( $site_context['content_format_preference'] ?? '' );
		if ( in_array( $format, array( 'product_sheet', 'affiliate_content' ), true ) ) {
			return __( 'Commerciale informazionale / valutazione', 'wp-ai-publisher' );
		}
		if ( 'local_guide' === $format ) {
			return __( 'Locale / informazionale', 'wp-ai-publisher' );
		}

		return __( 'Informazionale', 'wp-ai-publisher' );
	}

	/**
	 * Build generic but concrete fallback sections.
	 *
	 * @param string $entity Main entity.
	 * @param string $pattern Pattern key.
	 * @return array<int,array<string,string>>
	 */
	private function build_generic_local_sections( $entity, $pattern ) {
		$entity = $this->normalize_brand_capitalization( $entity );

		return array(
			array( 'heading' => sprintf( __( 'Cos’è %s in WordPress', 'wp-ai-publisher' ), $entity ), 'summary' => sprintf( __( 'Definire %s con parole semplici, indicando quale problema risolve nel sito e quali limiti deve conoscere chi lo usa.', 'wp-ai-publisher' ), $entity ) ),
			array( 'heading' => sprintf( __( 'Quando conviene lavorare su %s', 'wp-ai-publisher' ), $entity ), 'summary' => sprintf( __( 'Spiegare gli scenari in cui %s è davvero utile, distinguendo esigenze editoriali, tecniche e di manutenzione.', 'wp-ai-publisher' ), $entity ) ),
			array( 'heading' => __( 'Prerequisiti e controlli iniziali', 'wp-ai-publisher' ), 'summary' => __( 'Consigliare backup, verifica dei permessi utente, controllo del tema attivo e revisione dei plugin coinvolti prima di procedere.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Percorso operativo passo passo', 'wp-ai-publisher' ), 'summary' => __( 'Organizzare i passaggi in ordine pratico: aprire il pannello corretto, modificare una sola impostazione alla volta, salvare e annotare il cambiamento.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Impostazioni da controllare nel pannello WordPress', 'wp-ai-publisher' ), 'summary' => __( 'Indicare quali opzioni amministrative verificare e ricordare che nomi e posizioni possono cambiare in base a tema, plugin e lingua del sito.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Verifiche sul sito pubblico', 'wp-ai-publisher' ), 'summary' => __( 'Suggerire una verifica dal front-end dopo ogni modifica, includendo desktop, mobile, navigazione utente e coerenza con il layout esistente.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Errori comuni da evitare', 'wp-ai-publisher' ), 'summary' => __( 'Elencare problemi ricorrenti come modifiche non documentate, impostazioni duplicate, assenza di backup e controlli effettuati solo nell’admin.', 'wp-ai-publisher' ) ),
			array( 'heading' => __( 'Prossimi passi consigliati', 'wp-ai-publisher' ), 'summary' => sprintf( __( 'Proporre una revisione periodica di %s e collegare l’attività a una checklist editoriale o tecnica prima della pubblicazione.', 'wp-ai-publisher' ), $entity ) ),
		);
	}

	/**
	 * Detect common WordPress editorial patterns.
	 *
	 * @param string $topic Topic.
	 * @param string $keyword Keyword.
	 * @return array<int,string>
	 */
	private function detect_wordpress_editorial_patterns( $topic, $keyword ) {
		$haystack = strtolower( remove_accents( $topic . ' ' . $keyword ) );
		$map      = array(
			'widget'         => array( 'widget', 'widgets', 'area widget', 'aree widget', 'sidebar', 'footer' ),
			'menu'           => array( 'menu', 'menu di navigazione', 'navigazione' ),
			'plugin'         => array( 'plugin' ),
			'tema'           => array( 'tema', 'theme' ),
			'pagina'         => array( 'pagina', 'pagine' ),
			'articolo'       => array( 'articolo', 'articoli', 'post' ),
			'categorie'      => array( 'categoria', 'categorie' ),
			'tag'            => array( 'tag' ),
			'permalink'      => array( 'permalink' ),
			'seo'            => array( 'seo' ),
			'sicurezza'      => array( 'sicurezza', 'security' ),
			'backup'         => array( 'backup' ),
			'woocommerce'    => array( 'woocommerce' ),
			'wpml'           => array( 'wpml' ),
			'elementor'      => array( 'elementor' ),
			'classic_editor' => array( 'classic editor', 'editor classico' ),
			'media_library'  => array( 'media library', 'libreria media' ),
		);

		$detected = array();
		foreach ( $map as $pattern => $needles ) {
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $haystack, $needle ) ) {
					$detected[] = $pattern;
					break;
				}
			}
		}

		return array_values( array_unique( $detected ) );
	}

	/**
	 * Normalize well-known product and platform capitalization.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private function normalize_brand_capitalization( $text ) {
		$replacements = array(
			'/\bwordpress\b/i'      => 'WordPress',
			'/\bwpml\b/i'           => 'WPML',
			'/\bseo\b/i'            => 'SEO',
			'/\bwoocommerce\b/i'    => 'WooCommerce',
			'/\belementor\b/i'      => 'Elementor',
			'/\bclassic editor\b/i' => 'Classic Editor',
		);

		return preg_replace( array_keys( $replacements ), array_values( $replacements ), (string) $text );
	}

	/**
	 * Extract a readable primary entity for fallback output.
	 *
	 * @param string $topic Topic.
	 * @param string $keyword Keyword.
	 * @return string
	 */
	private function extract_primary_entity( $topic, $keyword ) {
		$text = '' !== trim( $keyword ) ? $keyword : $topic;
		$text = preg_replace( '/\b(come|usare|utilizzare|plugin|wordpress|guida|tutorial|per|con|su|il|lo|la|i|gli|le|un|una)\b/i', ' ', $text );
		$text = trim( preg_replace( '/\s+/', ' ', (string) $text ) );

		return sanitize_text_field( wp_trim_words( $text, 4, '' ) );
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
