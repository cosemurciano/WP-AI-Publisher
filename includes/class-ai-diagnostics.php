<?php
/**
 * Runtime AI diagnostics for WordPress AI integrations.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Discovers local WordPress AI runtime capabilities without exposing secrets.
 */
class AI_Diagnostics {
	/**
	 * AI provider adapter.
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Logger service.
	 *
	 * @var Logger|null
	 */
	private $logger;

	/**
	 * Function names to inspect.
	 *
	 * @var array<int,string>
	 */
	private $function_names = array(
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
		'wp_register_ability',
		'wp_get_abilities',
		'wp_get_ability',
		'wp_invoke_ability',
		'wp_interactivity_process_directives',
		'ai_services',
		'ai_services_get_connector',
		'ai_services_get_connectors',
	);

	/**
	 * Class names to inspect.
	 *
	 * @var array<int,string>
	 */
	private $class_names = array(
		'WP_AI_Client',
		'WP_AI_Abilities_Registry',
		'WP_AI_Ability_Registry',
		'WordPress\\AI\\Client',
		'WordPress\\AI\\Services\\Services_API',
		'WordPress\\AI\\Services\\AI_Service',
		'WordPress\\AI\\Abilities\\Registry',
		'WordPress\\AI\\Abilities\\Abilities_Registry',
		'WP_AI\\Client',
		'WP_AI\\Abilities\\Registry',
		'Felix_Arntz\\AI_Services',
		'Felix_Arntz\\AI_Services\\Services_API',
		'Felix_Arntz\\AI_Services\\Plugin',
		'AI_Services',
		'AI_Services_Plugin',
		'AI_Experiments',
		'AI_Features',
		'AI_Connectors',
	);

	/**
	 * Keywords used to identify REST routes and plugins.
	 *
	 * @var array<int,string>
	 */
	private $keywords = array( 'ai', 'ability', 'abilities', 'connector', 'connectors', 'openai', 'generation', 'generate', 'experiments', 'classification', 'excerpt', 'meta', 'summary', 'title', 'image' );

	/**
	 * Constructor.
	 *
	 * @param AI_Provider_Adapter $ai_provider AI adapter.
	 * @param Logger|null         $logger Optional logger.
	 */
	public function __construct( AI_Provider_Adapter $ai_provider, Logger $logger = null ) {
		$this->ai_provider = $ai_provider;
		$this->logger      = $logger;
	}

	/**
	 * Return full diagnostics report.
	 *
	 * @return array<string,mixed>
	 */
	public function get_report() {
		if ( function_exists( 'wpai_publisher_get_settings' ) ) {
			wpai_publisher_get_settings();
		}

		$functions = $this->get_detected_functions();
		$classes   = $this->get_detected_classes();
		$routes    = $this->get_detected_rest_routes();
		$options   = $this->get_detected_options();
		$abilities = $this->get_detected_wp_abilities();
		$plugins   = $this->get_detected_plugins();
		$experiments = $this->get_detected_ai_experiments();
		$paths       = $this->get_possible_generation_paths();

		$available_paths = array_filter(
			$paths,
			static function ( $path ) {
				return isset( $path['status'] ) && 'available' === $path['status'];
			}
		);
		$maybe_paths = array_filter(
			$paths,
			static function ( $path ) {
				return isset( $path['status'] ) && 'maybe' === $path['status'];
			}
		);

		return array(
			'summary'                   => array(
				'wordpress_ai_available' => $this->ai_provider->is_wordpress_ai_client_available(),
				'functions_found'        => count( array_filter( $functions, array( $this, 'item_exists' ) ) ),
				'classes_found'          => count( array_filter( $classes, array( $this, 'item_exists' ) ) ),
				'rest_routes_found'      => count( $routes ),
				'options_found'          => count( $options ),
				'abilities_found'        => count( $abilities ),
				'plugins_found'          => count( $plugins ),
				'experiments_detected'   => count(
					array_filter(
						$experiments,
						static function ( $experiment ) {
							return ! empty( $experiment['detected'] );
						}
					)
				),
				'generation_paths'       => count( $paths ),
				'available_paths'        => count( $available_paths ),
				'maybe_paths'            => count( $maybe_paths ),
			),
			'functions'                 => $functions,
			'classes'                   => $classes,
			'rest_routes'               => $routes,
			'options'                   => $options,
			'abilities'                 => $abilities,
			'plugins'                   => $plugins,
			'experiments'               => $experiments,
			'possible_generation_paths' => $paths,
			'recommendations'           => $this->get_recommendations( $paths, $routes ),
		);
	}

	/**
	 * Detect AI-related functions.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_functions() {
		$read_only_functions = array(
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
			'wp_ai_get_abilities',
			'wp_get_ai_abilities',
			'wp_ai_get_available_abilities',
			'wp_get_ai_available_abilities',
			'wp_ai_abilities',
			'wp_get_ai_abilities_registry',
			'wp_ai_abilities_registry',
			'wp_get_abilities',
			'ai_services_get_connectors',
		);
		$rows = array();

		foreach ( $this->function_names as $function_name ) {
			$exists  = function_exists( $function_name );
			$row = array(
				'name'        => $function_name,
				'exists'      => $exists,
				'callable'    => $exists && is_callable( $function_name ),
				'result_type' => '',
				'notes'       => $exists ? __( 'Funzione presente nel runtime.', 'wp-ai-publisher' ) : __( 'Non rilevata.', 'wp-ai-publisher' ),
			);

			if ( $row['callable'] && in_array( $function_name, $read_only_functions, true ) ) {
				try {
					$result             = call_user_func( $function_name );
					$row['result_type'] = $this->describe_value( $result );
					$row['notes']       = __( 'Invocata in sola lettura per rilevare il tipo risultato.', 'wp-ai-publisher' );
				} catch ( \Throwable $error ) {
					$row['result_type'] = 'error';
					$row['notes']       = sprintf(
						/* translators: %s: exception message. */
						__( 'Presente, ma la chiamata read-only non è riuscita: %s', 'wp-ai-publisher' ),
						$this->sanitize_message( $error->getMessage() )
					);
				}
			} elseif ( $row['callable'] && in_array( $function_name, array( 'wp_ai_client', 'wp_get_ai_client', 'wp_ai_services', 'ai_services', 'ai_services_get_connector' ), true ) ) {
				try {
					$result             = call_user_func( $function_name );
					$row['result_type'] = $this->describe_value( $result );
					$row['notes']       = __( 'Factory locale invocata senza prompt remoto per identificare il tipo client.', 'wp-ai-publisher' );
				} catch ( \Throwable $error ) {
					$row['result_type'] = 'error';
					$row['notes']       = sprintf( __( 'Factory presente, ma non invocabile senza argomenti: %s', 'wp-ai-publisher' ), $this->sanitize_message( $error->getMessage() ) );
				}
			} elseif ( $row['callable'] && in_array( $function_name, array( 'wp_ai_generate_text', 'wp_invoke_ability' ), true ) ) {
				$row['notes'] = __( 'Funzione potenzialmente generativa: non invocata automaticamente dalla diagnostica.', 'wp-ai-publisher' );
			}

			$rows[] = $row;
		}

		return $rows;
	}

	/**
	 * Detect AI-related classes.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_classes() {
		$rows = array();

		foreach ( $this->class_names as $class_name ) {
			$exists  = class_exists( $class_name );
			$methods = array();
			$notes   = $exists ? __( 'Classe presente nel runtime.', 'wp-ai-publisher' ) : __( 'Non rilevata.', 'wp-ai-publisher' );

			if ( $exists ) {
				try {
					$reflection = new \ReflectionClass( $class_name );
					foreach ( $reflection->getMethods( \ReflectionMethod::IS_PUBLIC ) as $method ) {
						if ( 0 === strpos( $method->getName(), '__' ) ) {
							continue;
						}
						$methods[] = $method->getName();
						if ( count( $methods ) >= 12 ) {
							break;
						}
					}
				} catch ( \Throwable $error ) {
					$notes = sprintf( __( 'Classe presente, introspezione metodi non riuscita: %s', 'wp-ai-publisher' ), $this->sanitize_message( $error->getMessage() ) );
				}
			}

			$rows[] = array(
				'name'    => $class_name,
				'exists'  => $exists,
				'methods' => $methods,
				'notes'   => $notes,
			);
		}

		return $rows;
	}

	/**
	 * Detect registered REST routes related to AI without calling them.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_rest_routes() {
		if ( ! function_exists( 'rest_get_server' ) ) {
			return array();
		}

		$routes = array();
		try {
			$server = rest_get_server();
			if ( ! $server || ! method_exists( $server, 'get_routes' ) ) {
				return array();
			}
			$registered_routes = $server->get_routes();
		} catch ( \Throwable $error ) {
			unset( $error );
			return array();
		}

		foreach ( $registered_routes as $route => $handlers ) {
			$route_string = strtolower( (string) $route );
			if ( ! $this->contains_keyword( $route_string, $this->keywords ) ) {
				continue;
			}

			foreach ( (array) $handlers as $handler ) {
				if ( ! is_array( $handler ) || empty( $handler['callback'] ) ) {
					continue;
				}

				$routes[] = array(
					'route'                   => (string) $route,
					'namespace'               => $this->extract_route_namespace( (string) $route ),
					'methods'                 => $this->normalize_route_methods( $handler['methods'] ?? array() ),
					'callback'                => $this->callback_to_string( $handler['callback'] ),
					'permission_callback'     => ! empty( $handler['permission_callback'] ),
					'permission_callback_ref' => ! empty( $handler['permission_callback'] ) ? $this->callback_to_string( $handler['permission_callback'] ) : '',
				);
			}
		}

		return $routes;
	}

	/**
	 * Detect AI-related options with masked values.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_options() {
		global $wpdb;

		if ( ! $wpdb || empty( $wpdb->options ) ) {
			return array();
		}

		$likes = array( '%ai%', '%openai%', '%connector%', '%ability%', '%abilities%', '%experiment%' );
		$where = array();
		$args  = array();
		foreach ( $likes as $like ) {
			$where[] = 'option_name LIKE %s';
			$args[]  = $like;
		}
		$args[] = 100;

		$sql = $wpdb->prepare(
			"SELECT option_name, option_value, autoload FROM {$wpdb->options} WHERE " . implode( ' OR ', $where ) . ' ORDER BY option_name ASC LIMIT %d',
			$args
		);
		$results = $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared above with dynamic LIKE clauses only.
		$rows    = array();

		foreach ( (array) $results as $option ) {
			$name        = (string) $option->option_name;
			$raw_value   = maybe_unserialize( $option->option_value );
			$sensitive   = $this->is_sensitive_key( $name );
			$type        = is_object( $raw_value ) ? 'object:' . get_class( $raw_value ) : gettype( $raw_value );
			$length      = $this->estimate_value_length( $raw_value );
			$preview_row = $this->build_option_preview( $raw_value, $sensitive );

			$rows[] = array(
				'option_name' => $name,
				'autoload'    => (string) $option->autoload,
				'type'        => $type,
				'length'      => $length,
				'preview'     => $preview_row['preview'],
				'sensitive'   => $sensitive,
				'masked'      => $preview_row['masked'],
			);
		}

		return $rows;
	}

	/**
	 * Detect WordPress Abilities API registrations without exposing large payloads.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_detected_wp_abilities() {
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			return array();
		}

		try {
			$abilities = wp_get_abilities();
		} catch ( \Throwable $error ) {
			unset( $error );
			return array();
		}

		$rows  = array();
		$count = 0;
		foreach ( (array) $abilities as $key => $ability ) {
			if ( $count >= 50 ) {
				break;
			}

			$row = $this->summarize_ability( $key, $ability );
			if ( '' === $row['name'] ) {
				continue;
			}

			$rows[] = $row;
			++$count;
		}

		return $rows;
	}

	/**
	 * Detect active AI-related plugins.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_plugins() {
		if ( ! function_exists( 'get_plugins' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$active_plugins = (array) get_option( 'active_plugins', array() );
		$network_active = function_exists( 'get_site_option' ) ? (array) get_site_option( 'active_sitewide_plugins', array() ) : array();
		$all_plugins    = function_exists( 'get_plugins' ) ? get_plugins() : array();
		$needles        = array( 'ai', 'openai', 'connector', 'services', 'abilities', 'git updater', 'aioseo', 'classic editor', 'disable gutenberg' );
		$rows           = array();

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			$is_active = in_array( $plugin_file, $active_plugins, true ) || isset( $network_active[ $plugin_file ] );
			if ( ! $is_active ) {
				continue;
			}

			$haystack = strtolower( $plugin_file . ' ' . ( $plugin_data['Name'] ?? '' ) . ' ' . ( $plugin_data['Description'] ?? '' ) );
			if ( ! $this->contains_keyword( $haystack, $needles ) ) {
				continue;
			}

			$rows[] = array(
				'plugin'      => $plugin_file,
				'name'        => sanitize_text_field( (string) ( $plugin_data['Name'] ?? $plugin_file ) ),
				'version'     => sanitize_text_field( (string) ( $plugin_data['Version'] ?? '' ) ),
				'description' => wp_trim_words( sanitize_text_field( (string) ( $plugin_data['Description'] ?? '' ) ), 30, '…' ),
				'active'      => true,
			);
		}

		return $rows;
	}

	/**
	 * Detect enabled AI experiments from options already discovered.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_detected_ai_experiments() {
		$experiments = array(
			'content_classification'  => __( 'Content Classification', 'wp-ai-publisher' ),
			'content_resizing'        => __( 'Content Resizing', 'wp-ai-publisher' ),
			'excerpt_generation'      => __( 'Excerpt Generation', 'wp-ai-publisher' ),
			'alt_text_generation'     => __( 'Alt Text Generation', 'wp-ai-publisher' ),
			'meta_description_generation' => __( 'Meta Description Generation', 'wp-ai-publisher' ),
			'editorial_notes'         => __( 'Editorial Notes', 'wp-ai-publisher' ),
			'editorial_updates'       => __( 'Editorial Updates', 'wp-ai-publisher' ),
			'content_summarization'   => __( 'Content Summarization', 'wp-ai-publisher' ),
			'title_generation'        => __( 'Title Generation', 'wp-ai-publisher' ),
			'ai_request_logging'      => __( 'AI Request Logging', 'wp-ai-publisher' ),
			'connector_approval'      => __( 'Connector Approval', 'wp-ai-publisher' ),
			'abilities_explorer'      => __( 'Abilities Explorer', 'wp-ai-publisher' ),
			'image_generation'        => __( 'Image Generation and Editing', 'wp-ai-publisher' ),
		);
		$options = $this->get_detected_options();
		$rows    = array();

		foreach ( $experiments as $slug => $label ) {
			$detected = false;
			$active   = null;
			$sources  = array();
			$needles  = $this->experiment_needles( $slug );

			foreach ( $options as $option ) {
				$source_text = strtolower( (string) $option['option_name'] . ' ' . ( is_scalar( $option['preview'] ) ? (string) $option['preview'] : wp_json_encode( $option['preview'] ) ) );
				if ( ! $this->contains_keyword( $source_text, $needles ) ) {
					continue;
				}

				$detected  = true;
				$sources[] = $option['option_name'];
				$deduced   = $this->deduce_bool_from_option_value( $option['preview'] );
				if ( null !== $deduced ) {
					$active = $deduced;
				}
			}

			$rows[] = array(
				'slug'     => $slug,
				'label'    => $label,
				'detected' => $detected,
				'active'   => $active,
				'source'   => implode( ', ', array_slice( array_unique( $sources ), 0, 5 ) ),
			);
		}

		return $rows;
	}

	/**
	 * Determine possible text generation paths.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_possible_generation_paths() {
		$functions = $this->get_detected_functions();
		$routes    = $this->get_detected_rest_routes();
		$paths     = array();

		$has_generate_text = $this->function_exists_in_rows( $functions, 'wp_ai_generate_text' );
		$has_client        = $this->function_exists_in_rows( $functions, 'wp_ai_client' ) || $this->function_exists_in_rows( $functions, 'wp_get_ai_client' );
		$has_services      = $this->function_exists_in_rows( $functions, 'wp_ai_services' ) || $this->function_exists_in_rows( $functions, 'ai_services' );
		$has_ability       = $this->function_exists_in_rows( $functions, 'wp_invoke_ability' ) || $this->function_exists_in_rows( $functions, 'wp_get_ability' );
		$has_generation_route = false;

		foreach ( $routes as $route ) {
			$route_text = strtolower( $route['route'] . ' ' . $route['callback'] );
			if ( $this->contains_keyword( $route_text, array( 'generate', 'generation', 'completion', 'text' ) ) ) {
				$has_generation_route = true;
				break;
			}
		}

		$paths[] = array(
			'label'      => __( 'Funzione wp_ai_generate_text disponibile', 'wp-ai-publisher' ),
			'status'     => $has_generate_text ? 'available' : 'unavailable',
			'confidence' => $has_generate_text ? 'high' : 'low',
			'notes'      => $has_generate_text ? __( 'È il percorso più diretto per un test controllato via sistema AI WordPress.', 'wp-ai-publisher' ) : __( 'La funzione non è presente.', 'wp-ai-publisher' ),
		);
		$paths[] = array(
			'label'      => __( 'Client wp_ai_client disponibile', 'wp-ai-publisher' ),
			'status'     => $has_client ? 'maybe' : 'unavailable',
			'confidence' => $has_client ? 'medium' : 'low',
			'notes'      => $has_client ? __( 'È presente una factory client; serve confermare quale metodo pubblico genera testo.', 'wp-ai-publisher' ) : __( 'Nessuna factory client nota rilevata.', 'wp-ai-publisher' ),
		);
		$paths[] = array(
			'label'      => __( 'Services API AI disponibile', 'wp-ai-publisher' ),
			'status'     => $has_services ? 'maybe' : 'unavailable',
			'confidence' => $has_services ? 'medium' : 'low',
			'notes'      => $has_services ? __( 'È presente una factory services; la diagnostica non assume un connector specifico.', 'wp-ai-publisher' ) : __( 'Nessuna factory services nota rilevata.', 'wp-ai-publisher' ),
		);
		$paths[] = array(
			'label'      => __( 'REST route AI generation disponibile', 'wp-ai-publisher' ),
			'status'     => $has_generation_route ? 'maybe' : 'unavailable',
			'confidence' => $has_generation_route ? 'medium' : 'low',
			'notes'      => $has_generation_route ? __( 'Sono state trovate route REST candidate. Non vengono chiamate automaticamente.', 'wp-ai-publisher' ) : __( 'Nessuna route REST di generazione candidata rilevata.', 'wp-ai-publisher' ),
		);
		$paths[] = array(
			'label'      => __( 'Ability generation disponibile', 'wp-ai-publisher' ),
			'status'     => $has_ability ? 'maybe' : 'unavailable',
			'confidence' => $has_ability ? 'medium' : 'low',
			'notes'      => $has_ability ? __( 'Le API ability sembrano presenti; usare Abilities Explorer per identificare l’ability corretta.', 'wp-ai-publisher' ) : __( 'Nessuna funzione ability invocabile rilevata.', 'wp-ai-publisher' ),
		);
		$paths[] = array(
			'label'      => __( 'Solo fallback locale disponibile', 'wp-ai-publisher' ),
			'status'     => ( $has_generate_text || $has_client || $has_services || $has_ability || $has_generation_route ) ? 'unavailable' : 'available',
			'confidence' => ( $has_generate_text || $has_client || $has_services || $has_ability || $has_generation_route ) ? 'low' : 'high',
			'notes'      => __( 'Fallback interno non remoto: utile solo quando il sistema AI non espone un percorso invocabile.', 'wp-ai-publisher' ),
		);

		return $paths;
	}

	/**
	 * Run a manual safe generation test through local WordPress AI APIs only.
	 *
	 * @return array<string,mixed>
	 */
	public function run_safe_generation_test() {
		$prompt  = 'Rispondi solo con JSON valido: {"ok":true,"message":"test"}';
		$payload = array(
			'prompt' => $prompt,
			'task'   => 'safe_generation_test',
			'format' => 'json',
		);
		$schema  = array(
			'ok'      => 'boolean',
			'message' => 'string',
		);

		if ( function_exists( 'has_filter' ) && has_filter( 'wpai_publisher_generate_structured_content_dry_run' ) ) {
			$result = $this->run_filter_generation_test( 'wpai_publisher_generate_structured_content_dry_run', $payload, $schema );
			if ( ! empty( $result['attempted'] ) ) {
				return $result;
			}
		}

		if ( function_exists( 'has_filter' ) && has_filter( 'wpai_publisher_structured_content_dry_run' ) ) {
			$result = $this->run_filter_generation_test( 'wpai_publisher_structured_content_dry_run', array( 'idea' => $payload ), $schema );
			if ( ! empty( $result['attempted'] ) ) {
				return $result;
			}
		}

		$result = $this->run_wp_ability_generation_test( $prompt );
		if ( ! empty( $result['attempted'] ) ) {
			return $result;
		}

		if ( function_exists( 'wp_ai_generate_text' ) && is_callable( 'wp_ai_generate_text' ) ) {
			return $this->run_generation_function_test( 'wp_ai_generate_text', $prompt );
		}

		foreach ( array( 'wp_ai_client', 'wp_get_ai_client', 'wp_ai_services', 'ai_services' ) as $factory ) {
			if ( ! function_exists( $factory ) || ! is_callable( $factory ) ) {
				continue;
			}

			$result = $this->run_client_generation_test( $factory, $prompt );
			if ( ! empty( $result['attempted'] ) ) {
				return $result;
			}
		}

		return array(
			'path'          => '',
			'ability'       => '',
			'success'       => false,
			'error'         => __( 'Nessun percorso di generazione AI invocabile è stato rilevato. Usa Abilities Explorer per individuare la callback o registra un bridge tramite filtro WP AI Publisher.', 'wp-ai-publisher' ),
			'response_type' => '',
			'excerpt'       => '',
			'attempted'     => false,
		);
	}

	/**
	 * Estimate display length of a value without exposing its content.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	private function estimate_value_length( $value ) {
		if ( is_scalar( $value ) || null === $value ) {
			return strlen( (string) $value );
		}

		$encoded = wp_json_encode( $value );
		return false === $encoded ? 0 : strlen( $encoded );
	}

	/**
	 * Build a safe option preview with maximum 120 characters.
	 *
	 * @param mixed $value Raw value.
	 * @param bool  $sensitive Whether option name is sensitive.
	 * @return array{preview:string,masked:bool}
	 */
	private function build_option_preview( $value, $sensitive ) {
		if ( $sensitive ) {
			return array(
				'preview' => '[nascosto]',
				'masked'  => true,
			);
		}

		if ( is_array( $value ) || is_object( $value ) ) {
			return array(
				'preview' => sprintf(
					/* translators: %s: estimated serialized value length. */
					__( 'array, lunghezza stimata %s caratteri', 'wp-ai-publisher' ),
					(string) $this->estimate_value_length( $value )
				),
				'masked'  => true,
			);
		}

		$text   = sanitize_text_field( (string) $value );
		$masked = false;
		if ( preg_match( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text ) ) {
			$text   = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email nascosta]', $text );
			$masked = true;
		}

		$text = (string) $this->mask_sensitive_value( $text );
		if ( false !== strpos( $text, '[nascosto]' ) ) {
			$masked = true;
		}

		$preview = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 120 ) : substr( $text, 0, 120 );
		return array(
			'preview' => $preview,
			'masked'  => $masked || strlen( $text ) > strlen( $preview ),
		);
	}


	/**
	 * Return ability names explicitly allowed for diagnostics dry-run tests.
	 *
	 * @return array<int,string>
	 */
	private function get_safe_ai_ability_names() {
		$settings = wpai_publisher_get_settings();
		$names    = array();
		foreach ( (array) preg_split( '/\r\n|\r|\n/', (string) ( $settings['safe_ai_ability_names'] ?? '' ) ) as $line ) {
			$name = sanitize_text_field( trim( (string) $line ) );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		$names = apply_filters( 'wpai_publisher_safe_ai_ability_names', array_values( array_unique( $names ) ) );
		return is_array( $names ) ? array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $names ) ) ) ) : array();
	}

	/**
	 * Return dangerous safety signals found in ability metadata.
	 *
	 * @param array<string,mixed> $metadata Ability metadata.
	 * @return array<int,string>
	 */
	private function get_ability_dangerous_signals( $metadata ) {
		$signals  = array( 'create_post', 'insert_post', 'publish_post', 'publish', 'delete', 'delete_post', 'remove_post', 'update_post', 'edit_post', 'save_post', 'create_media', 'upload_media', 'media_upload', 'delete_media', 'attachment', 'update_option', 'delete_option', 'settings_update', 'create_user', 'delete_user', 'send_email', 'webhook', 'remote_request', 'install_plugin', 'activate_plugin', 'deactivate_plugin', 'filesystem', 'database_write', 'schedule_event', 'pubblica', 'pubblicare', 'elimina', 'eliminare', 'rimuovi', 'rimuovere', 'aggiorna articolo', 'modifica articolo', 'salva articolo', 'carica media', 'crea allegato', 'elimina allegato', 'aggiorna opzione', 'crea utente', 'elimina utente', 'invia email', 'installa plugin', 'attiva plugin', 'disattiva plugin' );
		$haystack = $this->build_ability_safety_haystack( $metadata );
		$found    = array();
		foreach ( $signals as $signal ) {
			if ( $this->metadata_contains_safety_signal( $haystack, array( $signal ) ) ) {
				$found[] = $signal;
			}
		}
		return array_values( array_unique( $found ) );
	}

	/**
	 * Detect read-only/text-generation signals.
	 *
	 * @param array<string,mixed> $metadata Ability metadata.
	 * @return bool
	 */
	private function ability_has_readonly_signals( $metadata ) {
		$signals  = array( 'readonly', 'read_only', 'read-only', 'pure', 'no_side_effects', 'non_destructive', 'text', 'text_generation', 'generate_text', 'completion', 'summary', 'title', 'excerpt', 'classification', 'meta_description', 'sola_lettura', 'non_distruttiva', 'testo', 'generazione_testo', 'riassunto', 'titolo', 'estratto', 'classificazione', 'descrizione_meta' );
		$haystack = $this->build_ability_safety_haystack( $metadata );
		return $this->metadata_contains_safety_signal( $haystack, $signals );
	}


	/**
	 * Match safety signals with token/boundary semantics.
	 *
	 * @param string            $haystack Text to inspect.
	 * @param array<int,string> $signals Signals.
	 * @return bool
	 */
	private function metadata_contains_safety_signal( $haystack, $signals ) {
		$haystack = strtolower( remove_accents( (string) $haystack ) );
		foreach ( $signals as $signal ) {
			$signal = strtolower( remove_accents( trim( (string) $signal ) ) );
			if ( '' === $signal || strlen( $signal ) < 4 ) {
				continue;
			}

			$pattern_signal = preg_quote( $signal, '/' );
			$pattern_signal = str_replace( array( '\ ', '\-' ), '[^a-z0-9_]+', $pattern_signal );
			if ( 1 === preg_match( '/(^|[^a-z0-9_])' . $pattern_signal . '([^a-z0-9_]|$)/i', $haystack ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check dry-run safety and explain the reason.
	 *
	 * @param mixed               $ability Ability object/definition/name.
	 * @param array<string,mixed> $metadata Ability metadata.
	 * @return array{safe:bool,reason:string,dangerous_signals:array<int,string>}
	 */
	private function get_ability_dry_run_safety( $ability, $metadata ) {
		$name      = sanitize_text_field( (string) ( $metadata['name'] ?? '' ) );
		$safe_list = $this->get_safe_ai_ability_names();
		$signals   = $this->get_ability_dangerous_signals( $metadata );

		if ( '' !== $name && in_array( $name, $safe_list, true ) ) {
			return array( 'safe' => true, 'reason' => __( 'Allowlist impostazioni/filtro', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
		}

		$filtered = apply_filters( 'wpai_publisher_is_ability_safe_for_dry_run', null, $ability, $metadata );
		if ( true === $filtered ) {
			return array( 'safe' => true, 'reason' => __( 'Filtro wpai_publisher_is_ability_safe_for_dry_run', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
		}
		if ( false === $filtered ) {
			return array( 'safe' => false, 'reason' => __( 'Bloccata dal filtro sicurezza', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
		}

		$settings = wpai_publisher_get_settings();
		if ( ! empty( $settings['allow_unverified_ai_abilities'] ) ) {
			return empty( $signals )
				? array( 'safe' => true, 'reason' => __( 'Abilities non verificate consentite, senza segnali pericolosi', 'wp-ai-publisher' ), 'dangerous_signals' => $signals )
				: array( 'safe' => false, 'reason' => __( 'Segnali pericolosi rilevati', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
		}

		if ( empty( $signals ) && $this->ability_has_readonly_signals( $metadata ) ) {
			return array( 'safe' => true, 'reason' => __( 'Metadata read-only / text generation', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
		}

		return array( 'safe' => false, 'reason' => empty( $signals ) ? __( 'Non verificata in modo conservativo', 'wp-ai-publisher' ) : __( 'Segnali pericolosi rilevati', 'wp-ai-publisher' ), 'dangerous_signals' => $signals );
	}

	/**
	 * Build compact safety haystack including schema/meta without displaying them.
	 *
	 * @param array<string,mixed> $metadata Ability metadata.
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
	 * Summarize one ability row defensively.
	 *
	 * @param mixed $key Ability array key.
	 * @param mixed $ability Ability data.
	 * @return array<string,string>
	 */
	private function summarize_ability( $key, $ability ) {
		$data = $this->extract_ability_metadata( $ability, is_string( $key ) ? $key : '' );
		$name = '' !== $data['name'] ? $data['name'] : ( is_string( $key ) ? sanitize_text_field( $key ) : '' );

		$safety               = $this->get_ability_dry_run_safety( $ability, $data );
		$generation_candidate = $this->contains_keyword( $data['haystack'], $this->get_generation_ability_keywords() );
		$dangerous_signals    = ! empty( $safety['dangerous_signals'] );
		$read_only            = $this->ability_has_readonly_signals( $data );
		$invocable_bool       = false;
		$invocable = __( 'Non deducibile', 'wp-ai-publisher' );
		if ( is_object( $ability ) ) {
			foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $method ) {
				if ( method_exists( $ability, $method ) && is_callable( array( $ability, $method ) ) ) {
					$invocable_bool = true;
					$invocable      = __( 'Sì', 'wp-ai-publisher' );
					break;
				}
			}
		} elseif ( is_callable( $ability ) || ( is_array( $ability ) && ! empty( $ability['callback'] ) ) ) {
			$invocable_bool = true;
			$invocable      = __( 'Sì', 'wp-ai-publisher' );
		}

		return array(
			'name'                 => $name,
			'label'                => wp_trim_words( sanitize_text_field( $data['label'] ), 12, '…' ),
			'description'          => $this->trim_text( sanitize_text_field( $data['description'] ), 160 ),
			'category'             => sanitize_text_field( $data['category'] ),
			'input_schema'         => ! empty( $data['input_schema'] ) ? __( 'Presente', 'wp-ai-publisher' ) : __( 'Assente', 'wp-ai-publisher' ),
			'output_schema'        => ! empty( $data['output_schema'] ) ? __( 'Presente', 'wp-ai-publisher' ) : __( 'Assente', 'wp-ai-publisher' ),
			'generation_candidate_bool' => $generation_candidate,
			'generation_candidate'      => $generation_candidate ? __( 'Sì', 'wp-ai-publisher' ) : __( 'No', 'wp-ai-publisher' ),
			'safe_for_dry_run_bool'     => (bool) $safety['safe'],
			'safe_for_dry_run'          => $safety['safe'] ? __( 'Sì', 'wp-ai-publisher' ) : __( 'No', 'wp-ai-publisher' ),
			'safe_for_dry_run_label'    => $safety['safe'] ? __( 'Sì', 'wp-ai-publisher' ) : __( 'No', 'wp-ai-publisher' ),
			'safety_reason'             => $safety['reason'],
			'dangerous_signals_bool'    => $dangerous_signals,
			'dangerous_signals'         => empty( $safety['dangerous_signals'] ) ? __( 'Nessuno', 'wp-ai-publisher' ) : implode( ', ', $safety['dangerous_signals'] ),
			'read_only_bool'            => $read_only,
			'read_only'                 => $read_only ? __( 'Sì', 'wp-ai-publisher' ) : __( 'No', 'wp-ai-publisher' ),
			'invocable_bool'            => $invocable_bool,
			'invocable'                 => $invocable,
		);
	}

	/**
	 * Extract safe metadata from a WP_Ability-compatible object, array, or string.
	 *
	 * @param mixed  $ability Ability definition.
	 * @param string $fallback_name Fallback registry key.
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
			foreach ( array( 'get_name' => 'name', 'get_label' => 'label', 'get_description' => 'description', 'get_category' => 'category', 'get_input_schema' => 'input_schema', 'get_output_schema' => 'output_schema', 'get_meta' => 'meta' ) as $getter => $field ) {
				if ( ! method_exists( $ability, $getter ) ) {
					continue;
				}
				try {
					$this->assign_ability_metadata_value( $metadata, $field, $ability->{$getter}() );
				} catch ( \Throwable $error ) {
					unset( $error );
				}
			}

			foreach ( array( 'name' => 'name', 'id' => 'name', 'slug' => 'name', 'label' => 'label', 'title' => 'label', 'description' => 'description', 'category' => 'category', 'input_schema' => 'input_schema', 'output_schema' => 'output_schema', 'meta' => 'meta' ) as $property => $field ) {
				if ( isset( $ability->{$property} ) && ( is_scalar( $ability->{$property} ) || in_array( $field, array( 'input_schema', 'output_schema', 'meta' ), true ) ) ) {
					$this->assign_ability_metadata_value( $metadata, $field, $ability->{$property} );
				}
			}
		} elseif ( is_array( $ability ) ) {
			foreach ( array( 'name' => 'name', 'id' => 'name', 'slug' => 'name', 'label' => 'label', 'title' => 'label', 'description' => 'description', 'category' => 'category', 'input_schema' => 'input_schema', 'output_schema' => 'output_schema', 'meta' => 'meta' ) as $source => $field ) {
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
	 * Assign one extracted metadata field.
	 *
	 * @param array<string,mixed> $metadata Metadata accumulator.
	 * @param string              $field Field name.
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
	 * Build compact metadata haystack without dumping full schemas.
	 *
	 * @param array<string,mixed> $metadata Metadata.
	 * @return string
	 */
	private function build_ability_haystack( $metadata ) {
		$parts = array( $metadata['name'] ?? '', $metadata['label'] ?? '', $metadata['description'] ?? '', $metadata['category'] ?? '' );
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
		$parts = array_merge( $parts, $this->extract_schema_keywords( $metadata['input_schema'] ?? array() ), $this->extract_schema_keywords( $metadata['output_schema'] ?? array() ) );
		return strtolower( implode( ' ', array_values( array_unique( array_filter( $parts ) ) ) ) );
	}

	/**
	 * Extract compact keys from schema.
	 *
	 * @param mixed $schema Schema.
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
	 * Return generation candidate keywords.
	 *
	 * @return array<int,string>
	 */
	private function get_generation_ability_keywords() {
		return array( 'generate', 'generation', 'text', 'content', 'title', 'excerpt', 'summary', 'editorial', 'seo', 'meta', 'classification', 'completion', 'prompt', 'ai', 'assistant', 'write', 'writing', 'resize', 'rewrite', 'summarize', 'generazione', 'testo', 'contenuto', 'titolo', 'estratto', 'riassunto', 'editoriale', 'scrittura', 'metadati', 'descrizione' );
	}

	/**
	 * Trim text to a maximum character length.
	 *
	 * @param string $text Text.
	 * @param int    $max Maximum length.
	 * @return string
	 */
	private function trim_text( $text, $max ) {
		$text = sanitize_text_field( $text );
		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			return mb_strlen( $text ) > $max ? mb_substr( $text, 0, $max - 1 ) . '…' : $text;
		}
		return strlen( $text ) > $max ? substr( $text, 0, $max - 1 ) . '…' : $text;
	}

	/**
	 * Run a registered WP AI Publisher filter bridge test.
	 *
	 * @param string              $filter Filter name.
	 * @param array<string,mixed> $payload Test payload.
	 * @param array<string,mixed> $schema Test schema.
	 * @return array<string,mixed>
	 */
	private function run_filter_generation_test( $filter, $payload, $schema ) {
		try {
			if ( 'wpai_publisher_generate_structured_content_dry_run' === $filter ) {
				$result = apply_filters( $filter, null, $payload, $schema );
			} else {
				$result = apply_filters( $filter, null, $payload );
			}

			if ( null === $result ) {
				return array(
					'path'          => 'filter:' . $filter,
					'ability'       => '',
					'success'       => false,
					'error'         => __( 'Il filtro è registrato ma non ha restituito risposta per il test controllato.', 'wp-ai-publisher' ),
					'response_type' => 'null',
					'excerpt'       => '',
					'attempted'     => true,
				);
			}

			return $this->format_test_result( 'filter:' . $filter, $result, true );
		} catch ( \Throwable $error ) {
			return $this->format_test_error( 'filter:' . $filter, $error );
		}
	}

	/**
	 * Run a test through wp_get_abilities/wp_get_ability when possible.
	 *
	 * @param string $prompt Prompt.
	 * @return array<string,mixed>
	 */
	private function run_wp_ability_generation_test( $prompt ) {
		if ( ! function_exists( 'wp_get_abilities' ) || ! function_exists( 'wp_get_ability' ) ) {
			return array( 'attempted' => false );
		}

		try {
			$abilities = wp_get_abilities();
		} catch ( \Throwable $error ) {
			return $this->format_test_error( 'wp_get_abilities()', $error );
		}

		foreach ( (array) $abilities as $key => $ability_data ) {
			$row      = $this->summarize_ability( $key, $ability_data );
			$name     = $row['name'];
			$haystack = strtolower( $name . ' ' . $row['label'] . ' ' . $row['description'] . ' ' . $row['category'] );
			if ( '' === $name || empty( $row['generation_candidate_bool'] ) || empty( $row['safe_for_dry_run_bool'] ) ) {
				continue;
			}

			try {
				$ability = wp_get_ability( $name );
			} catch ( \Throwable $error ) {
				unset( $error );
				continue;
			}

			if ( ! is_object( $ability ) ) {
				continue;
			}

			$runtime_metadata = $this->extract_ability_metadata( $ability, $name );
			$runtime_safety   = $this->get_ability_dry_run_safety( $ability, $runtime_metadata );
			if ( empty( $runtime_safety['safe'] ) ) {
				continue;
			}

			$input = array(
				'prompt'  => $prompt,
				'input'   => $prompt,
				'content' => $prompt,
				'text'    => $prompt,
				'format'  => 'json',
			);
			foreach ( array( 'execute', 'run', 'invoke', 'call', 'perform' ) as $method ) {
				if ( ! method_exists( $ability, $method ) || ! is_callable( array( $ability, $method ) ) ) {
					continue;
				}

				$path = 'wp_get_ability(' . $name . ')->' . $method;
				try {
					$result = $ability->{$method}( $input );
					return $this->format_test_result( $path, $result, true, $name );
				} catch ( \Throwable $error ) {
					try {
						$result = $ability->{$method}( $prompt );
						return $this->format_test_result( $path, $result, true, $name );
					} catch ( \Throwable $fallback_error ) {
						return $this->format_test_error( $path, $fallback_error, $name );
					}
				}
			}
		}

		return array(
			'path'          => 'wp_get_abilities()',
			'ability'       => '',
			'success'       => false,
			'error'         => __( 'Nessuna ability AI sicura per il test controllato è stata trovata. Aggiungi il nome dell’ability nelle impostazioni o registra il filtro wpai_publisher_safe_ai_ability_names.', 'wp-ai-publisher' ),
			'response_type' => '',
			'excerpt'       => '',
			'attempted'     => true,
		);
	}

	/**
	 * Mask potentially sensitive values.
	 *
	 * @param mixed $value Raw value.
	 * @return mixed
	 */
	public function mask_sensitive_value( $value ) {
		if ( is_array( $value ) ) {
			$masked = array();
			$count  = 0;
			foreach ( $value as $key => $item ) {
				if ( $count >= 20 ) {
					$masked['…'] = __( 'Altri valori omessi', 'wp-ai-publisher' );
					break;
				}
				$masked[ $key ] = $this->is_sensitive_key( (string) $key ) ? '[nascosto]' : $this->mask_sensitive_value( $item );
				++$count;
			}
			return $masked;
		}

		if ( is_object( $value ) ) {
			return sprintf( '[object %s]', get_class( $value ) );
		}

		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}

		if ( null === $value ) {
			return 'null';
		}

		$text = (string) $value;
		$text = preg_replace( '/(sk-[A-Za-z0-9_\-]{8,})/', '[nascosto]', $text );
		$text = preg_replace( '/(bearer\s+)[A-Za-z0-9._\-]+/i', '$1[nascosto]', $text );
		$text = preg_replace( '/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[email nascosta]', $text );
		$text = preg_replace( '/(["\']?(?:api[_-]?key|token|secret|password|credential|authorization|openai)["\']?\s*[:=]\s*)["\']?[^,"\'\s}]+/i', '$1[nascosto]', $text );

		return wp_trim_words( sanitize_text_field( $text ), 40, '…' );
	}

	/**
	 * Whether an option/key name is sensitive.
	 *
	 * @param string $key Key name.
	 * @return bool
	 */
	public function is_sensitive_key( $key ) {
		$key = strtolower( (string) $key );
		foreach ( array( 'key', 'token', 'secret', 'password', 'pass', 'api', 'bearer', 'auth', 'credential', 'openai', 'access', 'license', 'email', 'mail' ) as $needle ) {
			if ( false !== strpos( $key, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check report item exists flag.
	 *
	 * @param array<string,mixed> $item Item.
	 * @return bool
	 */
	private function item_exists( $item ) {
		return ! empty( $item['exists'] );
	}

	/**
	 * Return recommendations from diagnostics.
	 *
	 * @param array<int,array<string,string>> $paths Generation paths.
	 * @param array<int,array<string,mixed>>  $routes REST routes.
	 * @return array<int,string>
	 */
	private function get_recommendations( $paths, $routes ) {
		$available = array_filter(
			$paths,
			static function ( $path ) {
				return 'available' === ( $path['status'] ?? '' ) && false === stripos( $path['label'] ?? '', 'fallback' );
			}
		);

		$recommendations = array();
		if ( ! empty( $available ) ) {
			$recommendations[] = __( 'Esegui il test AI controllato e verifica path usato, tipo risposta ed estratto.', 'wp-ai-publisher' );
		} else {
			$recommendations[] = __( 'Usa Abilities Explorer per identificare l’ability o la callback esposta dal sistema AI.', 'wp-ai-publisher' );
			$recommendations[] = __( 'Registra un bridge tramite filtro WP AI Publisher senza inserire chiavi API nel plugin.', 'wp-ai-publisher' );
		}

		if ( ! empty( $routes ) ) {
			$recommendations[] = __( 'Le route REST candidate sono solo elencate: non usarle dal plugin finché non è chiara permission_callback e payload richiesto.', 'wp-ai-publisher' );
		}

		return $recommendations;
	}

	/**
	 * Run function generation test.
	 *
	 * @param string $function_name Function name.
	 * @param string $prompt Prompt.
	 * @return array<string,mixed>
	 */
	private function run_generation_function_test( $function_name, $prompt ) {
		try {
			$result = call_user_func(
				$function_name,
				array(
					'prompt'      => $prompt,
					'temperature' => 0,
					'format'      => 'json',
				)
			);

			return $this->format_test_result( $function_name, $result, true );
		} catch ( \Throwable $error ) {
			return $this->format_test_error( $function_name, $error );
		}
	}

	/**
	 * Run client generation test.
	 *
	 * @param string $factory Factory function.
	 * @param string $prompt Prompt.
	 * @return array<string,mixed>
	 */
	private function run_client_generation_test( $factory, $prompt ) {
		try {
			$client = call_user_func( $factory );
		} catch ( \Throwable $error ) {
			return $this->format_test_error( $factory, $error );
		}

		if ( ! is_object( $client ) ) {
			return array( 'attempted' => false );
		}

		foreach ( array( 'generate_text', 'generate', 'complete', 'prompt', 'request', 'create_response', 'text' ) as $method ) {
			if ( ! method_exists( $client, $method ) ) {
				continue;
			}

			$path = $factory . '()->' . $method;
			try {
				$result = $client->{$method}(
					array(
						'prompt'      => $prompt,
						'temperature' => 0,
						'format'      => 'json',
					)
				);
				return $this->format_test_result( $path, $result, true );
			} catch ( \Throwable $error ) {
				try {
					$result = $client->{$method}( $prompt );
					return $this->format_test_result( $path, $result, true );
				} catch ( \Throwable $fallback_error ) {
					return $this->format_test_error( $path, $fallback_error );
				}
			}
		}

		return array( 'attempted' => false );
	}

	/**
	 * Format successful test result.
	 *
	 * @param string $path Path.
	 * @param mixed  $result Result.
	 * @param bool   $attempted Attempted flag.
	 * @return array<string,mixed>
	 */
	private function format_test_result( $path, $result, $attempted, $ability = '' ) {
		return array(
			'path'          => $path,
			'ability'       => sanitize_text_field( (string) $ability ),
			'success'       => true,
			'error'         => '',
			'response_type' => $this->describe_value( $result ),
			'excerpt'       => $this->excerpt_value( $result ),
			'attempted'     => $attempted,
		);
	}

	/**
	 * Format test error.
	 *
	 * @param string     $path Path.
	 * @param \Throwable $error Error.
	 * @return array<string,mixed>
	 */
	private function format_test_error( $path, \Throwable $error, $ability = '' ) {
		return array(
			'path'          => $path,
			'ability'       => sanitize_text_field( (string) $ability ),
			'success'       => false,
			'error'         => $this->sanitize_message( $error->getMessage() ),
			'response_type' => 'error',
			'excerpt'       => '',
			'attempted'     => true,
		);
	}

	/**
	 * Return concise value description.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function describe_value( $value ) {
		if ( is_object( $value ) ) {
			return 'object:' . get_class( $value );
		}

		if ( is_array( $value ) ) {
			return 'array:' . count( $value );
		}

		return gettype( $value );
	}

	/**
	 * Return masked excerpt.
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private function excerpt_value( $value ) {
		$masked = $this->mask_sensitive_value( $value );
		$text   = is_scalar( $masked ) ? (string) $masked : wp_json_encode( $masked );
		return function_exists( 'mb_substr' ) ? mb_substr( (string) $text, 0, 500 ) : substr( (string) $text, 0, 500 );
	}

	/**
	 * Sanitize error messages.
	 *
	 * @param string $message Message.
	 * @return string
	 */
	private function sanitize_message( $message ) {
		return (string) $this->mask_sensitive_value( $message );
	}

	/**
	 * Whether text contains any keyword.
	 *
	 * @param string           $text Text.
	 * @param array<int,string> $keywords Keywords.
	 * @return bool
	 */
	private function contains_keyword( $text, $keywords ) {
		$text = strtolower( (string) $text );
		foreach ( $keywords as $keyword ) {
			if ( false !== strpos( $text, strtolower( $keyword ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Extract namespace-like route prefix.
	 *
	 * @param string $route Route.
	 * @return string
	 */
	private function extract_route_namespace( $route ) {
		$parts = array_values( array_filter( explode( '/', trim( $route, '/' ) ) ) );
		return $parts[0] ?? '';
	}

	/**
	 * Normalize REST methods.
	 *
	 * @param mixed $methods Methods.
	 * @return string
	 */
	private function normalize_route_methods( $methods ) {
		if ( is_array( $methods ) ) {
			$method_names = array();
			foreach ( $methods as $method => $enabled ) {
				$method_names[] = is_string( $method ) ? $method : (string) $enabled;
			}
			return implode( ', ', array_filter( array_unique( $method_names ) ) );
		}

		return (string) $methods;
	}

	/**
	 * Convert callback to safe string.
	 *
	 * @param mixed $callback Callback.
	 * @return string
	 */
	private function callback_to_string( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}

		if ( is_array( $callback ) && isset( $callback[0], $callback[1] ) ) {
			$class = is_object( $callback[0] ) ? get_class( $callback[0] ) : (string) $callback[0];
			return $class . '::' . (string) $callback[1];
		}

		if ( $callback instanceof \Closure ) {
			return 'Closure';
		}

		return gettype( $callback );
	}

	/**
	 * Whether a function row exists.
	 *
	 * @param array<int,array<string,mixed>> $rows Rows.
	 * @param string                         $name Function name.
	 * @return bool
	 */
	private function function_exists_in_rows( $rows, $name ) {
		foreach ( $rows as $row ) {
			if ( $name === ( $row['name'] ?? '' ) && ! empty( $row['exists'] ) && ! empty( $row['callable'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Experiment keyword variants.
	 *
	 * @param string $slug Experiment slug.
	 * @return array<int,string>
	 */
	private function experiment_needles( $slug ) {
		$base = array( $slug, str_replace( '_', '-', $slug ), str_replace( '_', ' ', $slug ) );
		if ( 'image_generation' === $slug ) {
			$base[] = 'image_generation_editing';
			$base[] = 'image generation';
		}
		return $base;
	}

	/**
	 * Try to deduce boolean state from masked option value.
	 *
	 * @param mixed $value Value.
	 * @return bool|null
	 */
	private function deduce_bool_from_option_value( $value ) {
		if ( true === $value || 'true' === $value || '1' === $value || 1 === $value || 'yes' === $value || 'on' === $value || 'enabled' === $value ) {
			return true;
		}
		if ( false === $value || 'false' === $value || '0' === $value || 0 === $value || 'no' === $value || 'off' === $value || 'disabled' === $value ) {
			return false;
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				$deduced = $this->deduce_bool_from_option_value( $item );
				if ( null !== $deduced ) {
					return $deduced;
				}
			}
		}

		return null;
	}
}
