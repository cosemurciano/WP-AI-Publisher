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

		return isset( $settings['ai_model'] ) ? sanitize_text_field( (string) $settings['ai_model'] ) : '';
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
			'site_data'       => isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array(),
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
			$candidate = $this->normalize_article_candidate( $filtered, 'wordpress_ai', $builder, $generation_context, true );
			if ( ! is_wp_error( $candidate ) ) {
				$candidate['channel'] = 'filter';
				return $candidate;
			}
			$diagnostics['channel_attempts']['filter'] = $candidate->get_error_message();
		} else {
			$diagnostics['channel_attempts']['filter'] = $diagnostics['channel_filter'] ? 'filtro registrato ma ha restituito null' : 'nessun filtro registrato';
		}

		$openai_candidate = $this->try_generate_with_openai_responses( $prompt, $generation_context, $builder, true );
		if ( ! is_wp_error( $openai_candidate ) ) {
			$openai_candidate['channel'] = 'openai_responses';
			return $openai_candidate;
		}
		$diagnostics['channel_attempts']['openai_responses'] = $openai_candidate->get_error_message();

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
					$candidate = $this->normalize_article_candidate( $result, 'wordpress_ai', $builder, $generation_context, true );
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
					__( 'Il provider AI è stato raggiunto ma non ha risposto entro il timeout. Se "Verifica connettività OpenAI" (in Diagnostica AI) risulta Raggiungibile, la causa è una generazione troppo lenta (modello lento o output troppo lungo): aumenta il filtro wpai_publisher_ai_http_timeout, scegli un modello più veloce nel plugin AI, oppure riduci wpai_publisher_ai_max_output_tokens. Se invece la connettività risulta bloccata, chiedi all’hosting di abilitare le connessioni HTTPS in uscita verso api.openai.com.', 'wp-ai-publisher' ),
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
			'channel_openai_responses'    => $this->is_openai_responses_channel_ready(),
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
	/**
	 * Resolve AI generation parameters from settings, with filter overrides.
	 *
	 * @return array{model:string,http_timeout:int,max_output_tokens:int,temperature:float|null}
	 */
	public function get_ai_generation_params() {
		$settings    = wpai_publisher_get_settings();
		$model       = (string) apply_filters( 'wpai_publisher_ai_model', (string) ( $settings['ai_model'] ?? '' ) );
		$timeout     = (int) apply_filters( 'wpai_publisher_ai_http_timeout', (int) ( $settings['ai_http_timeout'] ?? 180 ) );
		$max_tokens  = (int) apply_filters( 'wpai_publisher_ai_max_output_tokens', (int) ( $settings['ai_max_output_tokens'] ?? 4000 ) );
		$raw_temp    = $settings['ai_temperature'] ?? '';
		$temperature = ( '' === $raw_temp || null === $raw_temp ) ? null : (float) $raw_temp;
		$temperature = apply_filters( 'wpai_publisher_ai_temperature', $temperature );

		return array(
			'model'             => sanitize_text_field( $model ),
			'http_timeout'      => max( 15, min( 600, $timeout ) ),
			'max_output_tokens' => max( 0, $max_tokens ),
			'temperature'       => ( null === $temperature ) ? null : (float) $temperature,
		);
	}

	/**
	 * Generate the article via the OpenAI Responses API with file_search (RAG).
	 *
	 * Opt-in channel: active only when the setting is enabled, an API key is
	 * available (constant/filter) and at least one Vector Store ID is set. The
	 * AI grounds the article on the documents stored in the OpenAI vector
	 * store(s). Returns a WP_Error (so the caller falls back) when not
	 * configured or on any failure.
	 *
	 * @param string                  $prompt Prompt text.
	 * @param array<string,mixed>     $generation_context Generation context.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @param bool                    $tolerant Tolerant normalization.
	 * @return array<string,mixed>|WP_Error
	 */
	/**
	 * Whether the OpenAI Responses (file_search) channel is fully configured.
	 *
	 * @return bool
	 */
	private function is_openai_responses_channel_ready() {
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['use_openai_file_search'] ) ) {
			return false;
		}
		if ( '' === wpai_publisher_get_openai_api_key() ) {
			return false;
		}
		return ! empty( wpai_publisher_get_openai_vector_store_ids( (string) ( $settings['openai_vector_store_ids'] ?? '' ) ) );
	}

	private function try_generate_with_openai_responses( $prompt, $generation_context, Classic_Content_Builder $builder, $tolerant = true ) {
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['use_openai_file_search'] ) ) {
			return new WP_Error( 'wpai_openai_responses_disabled', __( 'Knowledge base OpenAI non attiva.', 'wp-ai-publisher' ) );
		}

		$api_key = wpai_publisher_get_openai_api_key();
		if ( '' === $api_key ) {
			return new WP_Error( 'wpai_openai_responses_no_key', __( 'Chiave API OpenAI non configurata (costante WPAIP_OPENAI_API_KEY o filtro).', 'wp-ai-publisher' ) );
		}

		$vector_store_ids = wpai_publisher_get_openai_vector_store_ids( (string) ( $settings['openai_vector_store_ids'] ?? '' ) );
		if ( empty( $vector_store_ids ) ) {
			return new WP_Error( 'wpai_openai_responses_no_vector_store', __( 'Nessun Vector Store ID configurato per file_search.', 'wp-ai-publisher' ) );
		}

		$params = $this->get_ai_generation_params();
		$model  = sanitize_text_field( (string) ( $settings['openai_responses_model'] ?? '' ) );
		if ( '' === $model ) {
			$model = '' !== $params['model'] ? $params['model'] : 'gpt-4.1-mini';
		}
		/**
		 * Filter the model used by the OpenAI Responses channel.
		 *
		 * @param string $model Model id.
		 */
		$model = (string) apply_filters( 'wpai_publisher_openai_model', $model );

		$system = __( 'Sei un assistente editoriale WordPress. Usa i documenti recuperati come fonte autorevole. Restituisci esclusivamente l\'oggetto JSON richiesto.', 'wp-ai-publisher' );

		$body = array(
			'model'        => $model,
			'instructions' => $system,
			'input'        => $prompt,
			'tools'        => array(
				array(
					'type'             => 'file_search',
					'vector_store_ids' => array_values( $vector_store_ids ),
				),
			),
		);
		if ( $params['max_output_tokens'] > 0 ) {
			$body['max_output_tokens'] = (int) $params['max_output_tokens'];
		}
		if ( null !== $params['temperature'] ) {
			$body['temperature'] = (float) $params['temperature'];
		}

		/**
		 * Filter the OpenAI Responses API request body before sending.
		 *
		 * @param array<string,mixed> $body               Request body.
		 * @param array<string,mixed> $generation_context Generation context.
		 */
		$body = (array) apply_filters( 'wpai_publisher_openai_responses_body', $body, $generation_context );

		$encoded = wp_json_encode( $body );
		if ( false === $encoded ) {
			return new WP_Error( 'wpai_openai_responses_encode_failed', __( 'Impossibile codificare la richiesta OpenAI.', 'wp-ai-publisher' ) );
		}

		$response = wp_remote_post(
			'https://api.openai.com/v1/responses',
			array(
				'timeout' => max( 15, (int) $params['http_timeout'] ),
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => $encoded,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wpai_openai_responses_http_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 ) {
			$decoded = json_decode( $raw, true );
			$detail  = is_array( $decoded ) && isset( $decoded['error']['message'] ) ? (string) $decoded['error']['message'] : '';
			return new WP_Error( 'wpai_openai_responses_status_' . $code, sprintf( __( 'OpenAI Responses ha risposto HTTP %1$d. %2$s', 'wp-ai-publisher' ), $code, $detail ) );
		}

		$text = $this->extract_openai_responses_text( $raw );
		if ( '' === trim( $text ) ) {
			return new WP_Error( 'wpai_openai_responses_empty', __( 'OpenAI Responses non ha restituito testo.', 'wp-ai-publisher' ) );
		}

		return $this->normalize_article_candidate( $text, 'openai_responses', $builder, $generation_context, $tolerant );
	}

	/**
	 * Extract the assistant text from an OpenAI Responses API JSON payload.
	 *
	 * Handles both the aggregated "output_text" convenience field and the
	 * structured "output[].content[].text" form.
	 *
	 * @param string $raw Raw JSON response body.
	 * @return string
	 */
	private function extract_openai_responses_text( $raw ) {
		$data = json_decode( (string) $raw, true );
		if ( ! is_array( $data ) ) {
			return '';
		}
		if ( isset( $data['output_text'] ) && is_string( $data['output_text'] ) && '' !== trim( $data['output_text'] ) ) {
			return (string) $data['output_text'];
		}

		$parts = array();
		if ( isset( $data['output'] ) && is_array( $data['output'] ) ) {
			foreach ( $data['output'] as $item ) {
				if ( ! is_array( $item ) || ( isset( $item['type'] ) && 'message' !== $item['type'] ) ) {
					continue;
				}
				$content = isset( $item['content'] ) && is_array( $item['content'] ) ? $item['content'] : array();
				foreach ( $content as $chunk ) {
					if ( is_array( $chunk ) && isset( $chunk['text'] ) && is_string( $chunk['text'] ) ) {
						$parts[] = $chunk['text'];
					}
				}
			}
		}

		return trim( implode( "\n", $parts ) );
	}

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
			$params = $this->get_ai_generation_params();

			// Optionally select a specific model; otherwise the provider default is used.
			if ( '' !== $params['model'] && method_exists( $request, 'usingModel' ) ) {
				try {
					$request = $request->usingModel( $params['model'] );
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
			// Bound the output to keep generation within the request timeout.
			if ( $params['max_output_tokens'] > 0 && method_exists( $request, 'usingMaxTokens' ) ) {
				try {
					$request = $request->usingMaxTokens( $params['max_output_tokens'] );
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
			// Temperature is opt-in: some models (e.g. reasoning models) reject it.
			if ( null !== $params['temperature'] && method_exists( $request, 'usingTemperature' ) ) {
				try {
					$request = $request->usingTemperature( $params['temperature'] );
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
			if ( ! method_exists( $request, 'generateText' ) ) {
				return new WP_Error( 'wpai_php_ai_client_no_method', __( 'Il metodo generateText() non è disponibile nel PHP AI Client installato.', 'wp-ai-publisher' ) );
			}

			// AI text generation can take far longer than the 5s WordPress HTTP
			// default; raise the timeout only for the duration of this request.
			$timeout       = max( 15, (int) $params['http_timeout'] );
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
			return $this->normalize_article_candidate( $text, 'wordpress_ai', $builder, $generation_context, $tolerant );
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_php_ai_client_exception', $error->getMessage() );
		}
	}

	/**
	 * Generate an image from a prompt using the official WordPress PHP AI Client.
	 *
	 * Uses the provider/model configured on the site (e.g. OpenAI image model).
	 * Fully guarded; returns decoded image bytes + mime or a WP_Error.
	 *
	 * @param string $prompt Image prompt.
	 * @return array{bytes:string,mime:string}|WP_Error
	 */
	public function generate_image( $prompt ) {
		$prompt = sanitize_textarea_field( (string) $prompt );
		if ( '' === $prompt ) {
			return new WP_Error( 'wpai_image_empty_prompt', __( 'Prompt immagine vuoto.', 'wp-ai-publisher' ) );
		}
		$class = '\\WordPress\\AiClient\\AiClient';
		if ( ! class_exists( $class ) ) {
			return new WP_Error( 'wpai_image_client_unavailable', __( 'PHP AI Client non disponibile per la generazione immagini.', 'wp-ai-publisher' ) );
		}
		$params = $this->get_ai_generation_params();
		try {
			$request = call_user_func( array( $class, 'prompt' ), $prompt );
			if ( ! is_object( $request ) || ! method_exists( $request, 'generateImage' ) ) {
				return new WP_Error( 'wpai_image_no_method', __( 'Il metodo generateImage() non è disponibile nel PHP AI Client installato.', 'wp-ai-publisher' ) );
			}

			$timeout       = max( 15, (int) $params['http_timeout'] );
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
				$result = $request->generateImage();
			} finally {
				remove_filter( 'http_request_timeout', $raise_timeout, 9999 );
				remove_filter( 'http_request_args', $raise_args, 9999 );
			}

			$image = $this->extract_image_file( $result );
			if ( empty( $image['bytes'] ) ) {
				return new WP_Error( 'wpai_image_unreadable', sprintf( __( 'Risultato immagine non leggibile (%s).', 'wp-ai-publisher' ), is_object( $result ) ? get_class( $result ) : gettype( $result ) ) );
			}
			return $image;
		} catch ( Throwable $error ) {
			return new WP_Error( 'wpai_image_exception', $error->getMessage() );
		}
	}

	/**
	 * Extract image bytes + mime from a heterogeneous generateImage() result.
	 *
	 * @param mixed $result Result (string/array/object/file).
	 * @return array{bytes:string,mime:string}|null
	 */
	private function extract_image_file( $result ) {
		if ( is_string( $result ) ) {
			return $this->decode_image_payload( $result, '' );
		}
		if ( is_array( $result ) ) {
			$mime = (string) ( $result['mimeType'] ?? $result['mime'] ?? $result['mime_type'] ?? '' );
			foreach ( array( 'base64Data', 'base64', 'data', 'bytes', 'content' ) as $key ) {
				if ( ! empty( $result[ $key ] ) && is_string( $result[ $key ] ) ) {
					$decoded = $this->decode_image_payload( $result[ $key ], $mime );
					if ( ! empty( $decoded['bytes'] ) ) {
						return $decoded;
					}
				}
			}
			foreach ( array( 'url', 'uri', 'sourceUrl', 'src' ) as $key ) {
				if ( ! empty( $result[ $key ] ) && is_string( $result[ $key ] ) ) {
					$downloaded = $this->image_from_url( $result[ $key ], $mime );
					if ( ! empty( $downloaded['bytes'] ) ) {
						return $downloaded;
					}
				}
			}
			return null;
		}
		if ( is_object( $result ) ) {
			$mime = '';
			foreach ( array( 'getMimeType', 'getMediaType', 'mimeType' ) as $method ) {
				if ( method_exists( $result, $method ) ) {
					try {
						$value = $result->{$method}();
						if ( is_string( $value ) && '' !== $value ) {
							$mime = $value;
							break;
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
			foreach ( array( 'getBase64Data', 'getBase64', 'toBase64', 'getData', 'getBytes', 'getBinaryData', 'getContents', 'getBlob', '__toString' ) as $method ) {
				if ( method_exists( $result, $method ) ) {
					try {
						$value = $result->{$method}();
						if ( is_string( $value ) && '' !== trim( $value ) ) {
							$decoded = $this->decode_image_payload( $value, $mime );
							if ( ! empty( $decoded['bytes'] ) ) {
								return $decoded;
							}
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
			foreach ( array( 'getUrl', 'getUri', 'getSourceUrl' ) as $method ) {
				if ( method_exists( $result, $method ) ) {
					try {
						$value = $result->{$method}();
						if ( is_string( $value ) && 1 === preg_match( '#^https?://#i', $value ) ) {
							$downloaded = $this->image_from_url( $value, $mime );
							if ( ! empty( $downloaded['bytes'] ) ) {
								return $downloaded;
							}
						}
					} catch ( Throwable $error ) {
						unset( $error );
					}
				}
			}
			if ( method_exists( $result, 'toArray' ) ) {
				try {
					$array = $result->toArray();
					if ( is_array( $array ) ) {
						return $this->extract_image_file( $array );
					}
				} catch ( Throwable $error ) {
					unset( $error );
				}
			}
		}
		return null;
	}

	/**
	 * Decode an image payload that may be a data URI, raw base64, or binary.
	 *
	 * @param string $payload Payload.
	 * @param string $mime Known mime type, if any.
	 * @return array{bytes:string,mime:string}|null
	 */
	private function decode_image_payload( $payload, $mime = '' ) {
		$payload = (string) $payload;
		if ( '' === trim( $payload ) ) {
			return null;
		}
		if ( 1 === preg_match( '#^https?://#i', $payload ) ) {
			return $this->image_from_url( $payload, $mime );
		}
		if ( 1 === preg_match( '#^data:([^;,]+)?(;base64)?,(.*)$#is', $payload, $matches ) ) {
			$mime    = '' !== $mime ? $mime : (string) ( $matches[1] ?? '' );
			$is_b64  = '' !== (string) ( $matches[2] ?? '' );
			$content = (string) ( $matches[3] ?? '' );
			$bytes   = $is_b64 ? base64_decode( $content, true ) : rawurldecode( $content );
			return ( false !== $bytes && '' !== $bytes ) ? array( 'bytes' => $bytes, 'mime' => $mime ?: 'image/png' ) : null;
		}
		$decoded = base64_decode( $payload, true );
		if ( false !== $decoded && '' !== $decoded && strlen( $decoded ) > 8 ) {
			return array( 'bytes' => $decoded, 'mime' => $mime ?: 'image/png' );
		}
		return null;
	}

	/**
	 * Download image bytes from a URL via the WordPress HTTP API.
	 *
	 * @param string $url URL.
	 * @param string $mime Known mime type, if any.
	 * @return array{bytes:string,mime:string}|null
	 */
	private function image_from_url( $url, $mime = '' ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url ) {
			return null;
		}
		$response = wp_remote_get( $url, array( 'timeout' => max( 15, (int) $this->get_ai_generation_params()['http_timeout'] ) ) );
		if ( is_wp_error( $response ) ) {
			return null;
		}
		$bytes = (string) wp_remote_retrieve_body( $response );
		if ( '' === $bytes ) {
			return null;
		}
		$header_mime = (string) wp_remote_retrieve_header( $response, 'content-type' );
		return array( 'bytes' => $bytes, 'mime' => $mime ?: ( '' !== $header_mime ? $header_mime : 'image/png' ) );
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
			return $this->normalize_article_candidate( $text, 'wordpress_ai', $builder, $generation_context, $tolerant );
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
		$prompt_text = sanitize_textarea_field( (string) ( $article_type['prompt'] ?? '' ) );
		$has_prompt  = '' !== trim( $prompt_text );

		// The single "Prompt principale" is the primary instruction. Legacy fields
		// (tone/length/required sections/etc.) are included only for older article
		// types that were created before the single-prompt UI and have no prompt yet.
		$required_sections = array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) ( $article_type['required_sections'] ?? '' ) ) ) ) );
		$sections_line     = ( ! $has_prompt && ! empty( $required_sections ) )
			? "\nUsa esattamente queste sezioni come titoli H2, nello stesso ordine: " . implode( ' | ', $required_sections ) . '.'
			: '';

		$constraints = array(
			'article_type_prompt' => $prompt_text,
			'site'                => array(
				'description'      => $site_context['site_description'],
				'niche'            => $site_context['content_niche'],
				'default_audience' => $site_context['default_audience'],
				'writing_rules'    => $site_context['writing_rules'],
				'forbidden_claims' => $site_context['forbidden_claims'],
				'brand_terms'      => $site_context['brand_terms'],
			),
		);
		if ( ! $has_prompt ) {
			$constraints['tone']               = sanitize_textarea_field( (string) ( $article_type['tone'] ?? '' ) );
			$constraints['length']             = sanitize_textarea_field( (string) ( $article_type['length'] ?? '' ) );
			$constraints['search_intent']      = sanitize_textarea_field( (string) ( $article_type['search_intent'] ?? '' ) );
			$constraints['reader_level']       = sanitize_textarea_field( (string) ( $article_type['reader_level'] ?? '' ) );
			$constraints['required_sections']  = $required_sections;
			$constraints['forbidden_patterns'] = sanitize_textarea_field( (string) ( $article_type['forbidden_patterns'] ?? '' ) );
			$constraints['quality_checklist']  = sanitize_textarea_field( (string) ( $article_type['quality_checklist'] ?? '' ) );
		}
		// Existing site data the AI should reuse: tags, allowed categories and
		// internal link targets (real published URLs).
		$site_data       = isset( $generation_context['site_data'] ) && is_array( $generation_context['site_data'] ) ? $generation_context['site_data'] : array();
		$existing_tags   = array_slice( array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $site_data['tags'] ?? array() ) ) ) ), 0, 200 );
		$categories      = array();
		foreach ( (array) ( $site_data['categories'] ?? array() ) as $cat ) {
			if ( is_array( $cat ) && ! empty( $cat['id'] ) ) {
				$categories[] = array( 'id' => absint( $cat['id'] ), 'name' => sanitize_text_field( (string) ( $cat['name'] ?? '' ) ) );
			}
		}
		$internal_links = array();
		foreach ( (array) ( $site_data['internal_links'] ?? array() ) as $link ) {
			if ( is_array( $link ) && ! empty( $link['url'] ) ) {
				$internal_links[] = array( 'title' => sanitize_text_field( (string) ( $link['title'] ?? '' ) ), 'url' => esc_url_raw( (string) $link['url'] ) );
			}
		}

		$constraints['existing_tags']    = $existing_tags;
		$constraints['categories']       = $categories;
		$constraints['internal_links']   = $internal_links;
		$constraints_json = wp_json_encode( $constraints, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		// When inline image generation is enabled, instruct the model to place
		// image markers; the plugin turns each marker into a real generated image.
		$settings    = wpai_publisher_get_settings();
		$image_line  = '';
		if ( ! empty( $settings['generate_inline_images'] ) && (int) ( $settings['max_inline_images'] ?? 0 ) > 0 ) {
			$max_images = max( 0, min( 10, (int) $settings['max_inline_images'] ) );
			$image_line = sprintf(
				"\nDove un'immagine aiuta davvero la comprensione, inserisci nel campo html un segnaposto su una riga a sé (tipicamente tra due paragrafi) nel formato esatto [[wpai-image: descrizione visiva dettagliata della scena]]. Usa al massimo %d segnaposto, descrizioni concrete e pertinenti al testo vicino; non inserire tag <img> né URL di immagini, ci pensa il sistema a generarle e inserirle.",
				$max_images
			);
		}

		return sprintf(
			"Scrivi un articolo completo, originale e pubblicabile per il lettore (non una scaletta), pronto per una bozza WordPress in Editor Classico.\n" .
			"Restituisci SOLO un oggetto JSON valido (nessun testo fuori dal JSON, nessun markdown) con questi campi:\n" .
			"- \"html\": l'articolo in HTML pulito Classic Editor. Usa solo i tag consentiti: p, h2, h3, ul, ol, li, strong, em, blockquote, code, pre, br, a. Niente blocchi Gutenberg, script, iframe, style inline, shortcode. Almeno tre sezioni H2 con contenuto sostanziale. Non includere nel corpo prompt, tono, regole editoriali o note interne.\n" .
			"- \"tags\": array di stringhe. Riusa i tag esistenti pertinenti (campo existing_tags) e aggiungine di nuovi solo se utili.\n" .
			"- \"category_ids\": array di ID interi scelti ESCLUSIVAMENTE tra le categorie fornite (campo categories). Scegli 1-2 categorie coerenti.\n" .
			"- \"meta_title\": titolo SEO (max ~60 caratteri). \"meta_description\": descrizione SEO (max ~160 caratteri).\n" .
			"Inserisci nel campo html alcuni link interni pertinenti usando ESCLUSIVAMENTE gli URL reali forniti in internal_links (tag <a href>), dove hanno senso nel testo; non inventare URL. Non inventare dati tecnici, prezzi, normative o date non verificabili. Usa il Contesto editoriale del sito come quadro generale e la Tipologia articolo come istruzione principale.%1\$s%6\$s\n" .
			"Lingua dell'articolo: %2\$s.\nArgomento principale: %3\$s.\nKeyword principale: %4\$s.\nVincoli, dati del sito e istruzioni:\n%5\$s",
			$sections_line,
			$generation_context['language'],
			$generation_context['topic'],
			$generation_context['keyword'],
			false !== $constraints_json ? $constraints_json : '{}',
			$image_line
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
	 * Normalize a (possibly structured) article candidate.
	 *
	 * Accepts either clean HTML or a JSON object with html/tags/category_ids/
	 * meta_title/meta_description. The html is validated via the publishability
	 * normalizer; structured fields are attached to the returned candidate.
	 *
	 * @param mixed                   $raw Raw AI result.
	 * @param string                  $source Source label.
	 * @param Classic_Content_Builder $builder Builder/validator.
	 * @param array<string,mixed>     $context Generation context.
	 * @param bool                    $tolerant Tolerant acceptance.
	 * @return array<string,mixed>|WP_Error
	 */
	private function normalize_article_candidate( $raw, $source, Classic_Content_Builder $builder, $context = array(), $tolerant = false ) {
		$structured = $this->extract_structured_article( $raw );
		$candidate  = $this->normalize_full_article_candidate( $structured['html'], $source, $builder, $context, $tolerant );
		if ( is_wp_error( $candidate ) ) {
			return $candidate;
		}
		$candidate['tags']             = $structured['tags'];
		$candidate['category_ids']     = $structured['category_ids'];
		$candidate['meta_title']       = $structured['meta_title'];
		$candidate['meta_description'] = $structured['meta_description'];
		return $candidate;
	}

	/**
	 * Extract structured article fields from a raw AI result (JSON or HTML).
	 *
	 * @param mixed $raw Raw result.
	 * @return array{html:string,tags:array<int,string>,category_ids:array<int,int>,meta_title:string,meta_description:string}
	 */
	private function extract_structured_article( $raw ) {
		$data = null;
		if ( is_array( $raw ) ) {
			$data = $raw;
		} elseif ( is_string( $raw ) ) {
			$decoded = json_decode( $this->extract_json_block( $raw ), true );
			$data    = is_array( $decoded ) ? $decoded : array( 'html' => $raw );
		} elseif ( is_object( $raw ) ) {
			$text    = $this->stringify_ai_result( $raw );
			$decoded = json_decode( $this->extract_json_block( $text ), true );
			$data    = is_array( $decoded ) ? $decoded : array( 'html' => $text );
		} else {
			$data = array( 'html' => '' );
		}

		return array(
			'html'             => (string) ( $data['html'] ?? $data['content'] ?? $data['post_content'] ?? '' ),
			'tags'             => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $data['tags'] ?? array() ) ) ) ),
			'category_ids'     => array_values( array_filter( array_map( 'absint', (array) ( $data['category_ids'] ?? array() ) ) ) ),
			'meta_title'       => sanitize_text_field( (string) ( $data['meta_title'] ?? '' ) ),
			'meta_description' => sanitize_text_field( (string) ( $data['meta_description'] ?? '' ) ),
		);
	}

	/**
	 * Isolate a JSON object from a raw string (strips markdown code fences).
	 *
	 * @param string $raw Raw text.
	 * @return string JSON candidate or the original string.
	 */
	private function extract_json_block( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return $raw;
		}
		$raw = (string) preg_replace( '/^```(?:json)?\s*|\s*```$/i', '', $raw );
		$start = strpos( $raw, '{' );
		$end   = strrpos( $raw, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			return substr( $raw, $start, $end - $start + 1 );
		}
		return $raw;
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
	 * Stub for future text generation through WordPress AI only.
	 *
	 * @param mixed $payload Request payload.
	 * @return WP_Error
	 */
	public function generate_text( $payload ) {
		unset( $payload );

		return new WP_Error( 'wpai_text_generation_not_implemented', __( 'La generazione testo tramite WordPress AI non è ancora implementata in questa fase.', 'wp-ai-publisher' ) );
	}

	

	
}
