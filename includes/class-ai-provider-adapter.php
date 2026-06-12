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

		$payload = $this->normalize_dry_run_payload( $payload );
		$schema  = ! empty( $payload['required_schema'] ) && is_array( $payload['required_schema'] ) ? $payload['required_schema'] : $this->get_content_dry_run_schema();
		$prompt  = $this->build_structured_dry_run_prompt( $payload, $schema );
		$errors  = array();

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
	 * Normalize payload shape and preserve backward compatibility with previous nested idea payloads.
	 *
	 * @param array<string,mixed> $payload Request payload.
	 * @return array<string,mixed>
	 */
	private function normalize_dry_run_payload( $payload ) {
		$idea = isset( $payload['idea'] ) && is_array( $payload['idea'] ) ? $payload['idea'] : array();

		return array(
			'task'                 => 'structured_content_dry_run',
			'topic'                => sanitize_textarea_field( (string) ( $payload['topic'] ?? $idea['topic'] ?? '' ) ),
			'keyword'              => sanitize_text_field( (string) ( $payload['keyword'] ?? $idea['keyword'] ?? '' ) ),
			'language'             => sanitize_key( (string) ( $payload['language'] ?? $idea['language'] ?? 'it' ) ),
			'target_audience'      => sanitize_text_field( (string) ( $payload['target_audience'] ?? $idea['target_audience'] ?? '' ) ),
			'tutorial_level'       => sanitize_key( (string) ( $payload['tutorial_level'] ?? $idea['tutorial_level'] ?? 'base' ) ),
			'notes'                => sanitize_textarea_field( (string) ( $payload['notes'] ?? $idea['notes'] ?? '' ) ),
			'required_schema'      => isset( $payload['required_schema'] ) && is_array( $payload['required_schema'] ) ? $payload['required_schema'] : $this->get_content_dry_run_schema(),
			'allow_local_fallback' => ! empty( $payload['allow_local_fallback'] ),
			'safety'               => isset( $payload['safety'] ) && is_array( $payload['safety'] ) ? $payload['safety'] : array(),
		);
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
		$keywords  = array( 'generate', 'generation', 'text', 'content', 'title', 'excerpt', 'summary', 'editorial', 'seo', 'meta', 'classification' );
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
				foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $ability_method ) {
					if ( method_exists( $ability, $ability_method ) ) {
						try {
							$result     = $ability->{$ability_method}( $input );
							$normalized = $this->normalize_real_ai_candidate( $result );
							if ( ! is_wp_error( $normalized ) && $this->is_usable_dry_run_candidate( $normalized ) ) {
								return $normalized;
							}
						} catch ( Throwable $error ) {
							unset( $error );
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

		$schema_json = wp_json_encode( ! empty( $schema ) ? $schema : $this->get_content_dry_run_schema(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return sprintf(
			"Agisci come assistente editoriale WordPress. Genera SOLO JSON valido, senza markdown, senza blocchi di codice e senza spiegazioni fuori dal JSON. Non creare post, non pubblicare, non generare immagini reali, non scrivere metadati AIOSEO e non inventare dati tecnici non verificabili. Crea una struttura utile per un tutorial WordPress in italiano corretto, chiaro, didattico e operativo. Target: utenti WordPress di livello %5\$s. Genera categorie e tag realistici. content_outline deve essere un array di oggetti con heading stringa, level numerico intero e summary stringa. I link interni devono essere target semantici realistici, non URL inventati. Imposta source a wordpress_ai e language a it. Schema obbligatorio: %7\$s\nArgomento: %1\$s\nKeyword: %2\$s\nLingua richiesta: %3\$s\nPubblico target: %4\$s\nLivello tutorial: %5\$s\nNote editoriali: %6\$s",
			$topic,
			sanitize_text_field( (string) ( $payload['keyword'] ?? '' ) ),
			sanitize_key( (string) ( $payload['language'] ?? 'it' ) ),
			sanitize_text_field( (string) ( $payload['target_audience'] ?? '' ) ),
			sanitize_key( (string) ( $payload['tutorial_level'] ?? 'base' ) ),
			sanitize_textarea_field( (string) ( $payload['notes'] ?? '' ) ),
			false !== $schema_json ? $schema_json : '{}'
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
		$language        = sanitize_key( (string) ( $payload['language'] ?? 'it' ) );
		$target_audience = sanitize_text_field( (string) ( $payload['target_audience'] ?? '' ) );
		$tutorial_level  = sanitize_key( (string) ( $payload['tutorial_level'] ?? 'base' ) );
		$title_seed      = '' !== $keyword ? $keyword : wp_trim_words( $topic, 8, '' );
		$title           = sprintf( __( 'Guida pratica: %s', 'wp-ai-publisher' ), $title_seed );
		$slug            = sanitize_title( $title );
		$audience_text   = '' !== $target_audience ? $target_audience : __( 'utenti WordPress', 'wp-ai-publisher' );
		$outline         = $this->build_contextual_local_outline( $topic, $keyword );

		return array(
			'title'                  => $title,
			'slug'                   => $slug,
			'excerpt'                => sprintf( __( 'Dry-run editoriale su %1$s pensato per %2$s, senza creare bozze o pubblicare contenuti.', 'wp-ai-publisher' ), $topic, $audience_text ),
			'content_outline'        => $outline,
			'categories'             => array( __( 'Guide WordPress', 'wp-ai-publisher' ), __( 'Tutorial', 'wp-ai-publisher' ) ),
			'tags'                   => array_values( array_unique( array_filter( array( $keyword, $this->extract_primary_entity( $topic, $keyword ), __( 'wordpress', 'wp-ai-publisher' ), __( 'tutorial', 'wp-ai-publisher' ) ) ) ) ),
			'meta_title'             => wp_trim_words( $title, 10, '' ),
			'meta_description'       => sprintf( __( 'Struttura preliminare per un tutorial WordPress su %s, utile per validare flusso e outline senza pubblicare nulla.', 'wp-ai-publisher' ), $topic ),
			'open_graph_title'       => $title,
			'open_graph_description' => sprintf( __( 'Anteprima editoriale controllata per %s.', 'wp-ai-publisher' ), $topic ),
			'twitter_title'          => $title,
			'twitter_description'    => sprintf( __( 'Dry-run tutorial WordPress: %s.', 'wp-ai-publisher' ), $topic ),
			'featured_image_prompt'  => sprintf( __( 'Illustrazione editoriale concettuale per un tutorial WordPress su %s, senza generare immagini reali.', 'wp-ai-publisher' ), $topic ),
			'internal_image_prompts' => array(
				sprintf( __( 'Schema visuale dei passaggi operativi per %s.', 'wp-ai-publisher' ), $topic ),
				sprintf( __( 'Checklist visiva di verifica finale per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'image_alt_texts'        => array(
				sprintf( __( 'Schema tutorial WordPress per %s.', 'wp-ai-publisher' ), $topic ),
				sprintf( __( 'Checklist di controllo per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'image_captions'         => array(
				sprintf( __( 'Percorso operativo proposto per %s.', 'wp-ai-publisher' ), $topic ),
			),
			'internal_link_targets'  => array(
				__( 'Guida introduttiva a WordPress', 'wp-ai-publisher' ),
				__( 'Archivio tutorial plugin WordPress', 'wp-ai-publisher' ),
				__( 'Checklist sicurezza e aggiornamenti WordPress', 'wp-ai-publisher' ),
			),
			'knowledge_summary'      => sprintf( __( 'Sintesi locale basata sull’argomento inserito: %s. Il contenuto resta da verificare con revisione umana prima di qualsiasi pubblicazione.', 'wp-ai-publisher' ), $topic ),
			'entities'               => array_values( array_unique( array_filter( array( $this->extract_primary_entity( $topic, $keyword ), $keyword, 'WordPress' ) ) ) ),
			'search_intent'          => __( 'Informazionale / tutorial operativo', 'wp-ai-publisher' ),
			'tutorial_level'         => in_array( $tutorial_level, array( 'base', 'intermedio', 'avanzato' ), true ) ? $tutorial_level : 'base',
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
	private function build_contextual_local_outline( $topic, $keyword ) {
		$entity = $this->extract_primary_entity( $topic, $keyword );
		if ( '' === $entity ) {
			$entity = __( 'il tema scelto', 'wp-ai-publisher' );
		}

		$topic_lower = strtolower( $topic . ' ' . $keyword );
		if ( false !== strpos( $topic_lower, 'wpml' ) ) {
			$headings = array(
				__( 'Cos’è WPML e quando usarlo', 'wp-ai-publisher' ),
				__( 'Requisiti prima dell’installazione', 'wp-ai-publisher' ),
				__( 'Installazione e attivazione del plugin', 'wp-ai-publisher' ),
				__( 'Configurazione guidata delle lingue', 'wp-ai-publisher' ),
				__( 'Traduzione di pagine e articoli', 'wp-ai-publisher' ),
				__( 'Traduzione menu, stringhe e tassonomie', 'wp-ai-publisher' ),
				__( 'SEO multilingua e URL', 'wp-ai-publisher' ),
				__( 'Errori comuni da evitare', 'wp-ai-publisher' ),
				__( 'Verifica finale', 'wp-ai-publisher' ),
			);
		} else {
			$headings = array(
				sprintf( __( 'Cos’è %s', 'wp-ai-publisher' ), $entity ),
				sprintf( __( 'Quando serve %s', 'wp-ai-publisher' ), $entity ),
				__( 'Prerequisiti e controlli iniziali', 'wp-ai-publisher' ),
				__( 'Installazione o preparazione operativa', 'wp-ai-publisher' ),
				__( 'Configurazione passo passo', 'wp-ai-publisher' ),
				__( 'Uso pratico nel sito WordPress', 'wp-ai-publisher' ),
				__( 'Errori comuni da evitare', 'wp-ai-publisher' ),
				__( 'Verifica finale e prossimi passi', 'wp-ai-publisher' ),
			);
		}

		$outline = array();
		foreach ( $headings as $heading ) {
			$outline[] = array(
				'heading' => $heading,
				'level'   => 2,
				'summary' => sprintf( __( 'Descrivere in modo pratico e verificabile il passaggio “%1$s” nel contesto di “%2$s”, evitando dettagli tecnici non confermati.', 'wp-ai-publisher' ), $heading, $topic ),
			);
		}

		return $outline;
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
