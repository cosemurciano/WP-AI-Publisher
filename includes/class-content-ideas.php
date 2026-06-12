<?php
/**
 * Content ideas workflow.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles editorial ideas and safe article dry-runs.
 */
class Content_Ideas {
	/**
	 * Database service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * AI provider adapter.
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param DB                  $db Database service.
	 * @param AI_Provider_Adapter $ai_provider AI provider adapter.
	 * @param Logger              $logger Logger service.
	 */
	public function __construct( DB $db, AI_Provider_Adapter $ai_provider, Logger $logger ) {
		$this->db          = $db;
		$this->ai_provider = $ai_provider;
		$this->logger      = $logger;
	}

	/**
	 * Return content ideas table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->db->get_content_ideas_table_name();
	}

	/**
	 * Create a new content idea.
	 *
	 * @param array<string,mixed> $data Input data.
	 * @return int|WP_Error Inserted idea ID or error.
	 */
	public function create_idea( $data ) {
		global $wpdb;

		$topic = sanitize_textarea_field( (string) ( $data['topic'] ?? '' ) );
		if ( '' === trim( $topic ) ) {
			return new WP_Error( 'wpai_content_idea_empty_topic', __( 'L’argomento principale è obbligatorio.', 'wp-ai-publisher' ) );
		}

		$language       = sanitize_key( (string) ( $data['language'] ?? 'it' ) );
		$allowed_langs  = array( 'it', 'en', 'fr', 'es', 'de' );
		$tutorial_level = sanitize_key( (string) ( $data['tutorial_level'] ?? '' ) );
		$allowed_levels = array( 'base', 'intermedio', 'avanzato' );

		if ( ! in_array( $language, $allowed_langs, true ) ) {
			$language = 'it';
		}

		if ( ! in_array( $tutorial_level, $allowed_levels, true ) ) {
			$tutorial_level = null;
		}

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => null,
				'status'          => 'new',
				'topic'           => $topic,
				'keyword'         => sanitize_text_field( (string) ( $data['keyword'] ?? '' ) ),
				'language'        => $language,
				'target_audience' => sanitize_text_field( (string) ( $data['target_audience'] ?? '' ) ),
				'tutorial_level'  => $tutorial_level,
				'notes'           => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->error( __( 'Creazione idea contenuto non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'db_error' => $wpdb->last_error ) );
			return new WP_Error( 'wpai_content_idea_insert_failed', __( 'Impossibile salvare l’idea contenuto.', 'wp-ai-publisher' ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Get recent content ideas.
	 *
	 * @param int $limit Maximum rows.
	 * @return array<int,object>
	 */
	public function get_recent_ideas( $limit = 20 ) {
		global $wpdb;

		$limit = min( 100, max( 1, absint( $limit ) ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} ORDER BY created_at DESC, id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get a single content idea.
	 *
	 * @param int $id Idea ID.
	 * @return object|null
	 */
	public function get_idea( $id ) {
		global $wpdb;

		$id = absint( $id );
		if ( 0 === $id ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Update idea status.
	 *
	 * @param int    $id Idea ID.
	 * @param string $status Status key.
	 * @return bool
	 */
	public function update_idea_status( $id, $status ) {
		global $wpdb;

		$id     = absint( $id );
		$status = sanitize_key( (string) $status );

		if ( 0 === $id || ! in_array( $status, $this->get_allowed_statuses(), true ) ) {
			return false;
		}

		return false !== $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Save validated dry-run output.
	 *
	 * @param int                $id Idea ID.
	 * @param array<string,mixed> $output Structured output.
	 * @param array<int,string>   $validation_notes Validation notes.
	 * @return bool|WP_Error
	 */
	public function save_dry_run_output( $id, $output, $validation_notes = array() ) {
		global $wpdb;

		$id = absint( $id );
		if ( 0 === $id ) {
			return new WP_Error( 'wpai_content_idea_invalid_id', __( 'Idea non valida.', 'wp-ai-publisher' ) );
		}

		$output           = $this->sanitize_structured_output( $output );
		$validation_notes = array_values( array_filter( array_map( 'sanitize_textarea_field', (array) $validation_notes ) ) );
		$output_json      = wp_json_encode( $output );
		$notes_json       = wp_json_encode( $validation_notes );

		if ( false === $output_json || false === $notes_json ) {
			return new WP_Error( 'wpai_content_idea_json_failed', __( 'Impossibile serializzare il risultato dry-run.', 'wp-ai-publisher' ) );
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'dry_run_output'   => $output_json,
				'validation_notes' => $notes_json,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'wpai_content_idea_update_failed', __( 'Impossibile salvare il risultato dry-run.', 'wp-ai-publisher' ) );
		}

		return true;
	}

	/**
	 * Run a safe structured content dry-run without creating posts or media.
	 *
	 * @param int $id Idea ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function run_dry_run( $id ) {
		$idea = $this->get_idea( $id );
		if ( ! $idea ) {
			return new WP_Error( 'wpai_content_idea_not_found', __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}

		$site_context = wpai_publisher_get_site_context();

		$payload = array(
			'task'                 => 'structured_content_dry_run',
			'topic'                => (string) $idea->topic,
			'keyword'              => (string) $idea->keyword,
			'language'             => (string) $idea->language,
			'target_audience'      => (string) $idea->target_audience,
			'tutorial_level'       => (string) $idea->tutorial_level,
			'notes'                => (string) $idea->notes,
			'required_schema'      => $this->ai_provider->get_content_dry_run_schema(),
			'allow_local_fallback' => true,
			'site_context'         => $site_context,
			'safety'               => array(
				'create_posts'       => false,
				'publish_content'    => false,
				'generate_images'    => false,
				'write_seo_metadata' => false,
			),
		);

		$output = $this->ai_provider->generate_structured_content_dry_run( $payload );
		if ( is_wp_error( $output ) ) {
			$failed_output = array(
				'title'            => '',
				'slug'             => '',
				'excerpt'          => '',
				'content_outline'  => array(),
				'categories'       => array(),
				'tags'             => array(),
				'meta_title'       => '',
				'meta_description' => '',
				'validation_notes' => array( $output->get_error_message() ),
				'language'         => (string) $idea->language,
				'source'           => 'unknown',
			);
			$this->save_dry_run_output( (int) $idea->id, $failed_output, array( $output->get_error_message() ) );
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
			return $output;
		}

		$validator  = class_exists( __NAMESPACE__ . '\Structured_Output_Validator' ) ? new Structured_Output_Validator() : null;
		$validation = $validator ? $validator->validate_content_dry_run( $output ) : $this->validate_dry_run_output( $this->normalize_dry_run_output( $output ) );
		$normalized = $validator ? $validation['output'] : $this->normalize_dry_run_output( $output );
		$notes      = $validator ? $validation['validation_notes'] : $validation['notes'];
		$is_valid   = $validator ? ! empty( $validation['is_valid'] ) : ! empty( $validation['valid'] );

		if ( empty( $normalized['source'] ) || ! in_array( $normalized['source'], array( 'wordpress_ai', 'local_fallback' ), true ) ) {
			$notes[]              = __( 'Origine generazione non disponibile o non riconosciuta.', 'wp-ai-publisher' );
			$normalized['source'] = 'unknown';
		}

		if ( 'local_fallback' === $normalized['source'] ) {
			$notes[] = __( 'Questo risultato è utile per testare il flusso, ma non è ancora stato prodotto dal sistema AI reale.', 'wp-ai-publisher' );
		} elseif ( 'wordpress_ai' === $normalized['source'] ) {
			$notes[] = __( 'Risultato prodotto tramite sistema AI di WordPress.', 'wp-ai-publisher' );
		}

		$classic_builder    = new Classic_Content_Builder( $site_context );
		$classic_preview    = $classic_builder->build_from_dry_run( $normalized );
		$classic_preview['validation_notes'] = $this->remove_generic_placeholder_preview_notes( $classic_preview['validation_notes'] ?? array() );
		$classic_validation = $this->validate_classic_editor_preview( $classic_preview );

		if ( ! $classic_validation['valid'] ) {
			$is_valid = false;
		}

		$notes = array_merge( $notes, $classic_preview['validation_notes'], $classic_validation['notes'] );
		$notes = array_values( array_unique( array_filter( array_map( 'strval', $notes ) ) ) );

		$classic_preview['validation_notes'] = array_values( array_unique( array_filter( array_merge( $classic_preview['validation_notes'], $classic_validation['notes'] ) ) ) );
		$normalized['classic_editor_preview'] = $classic_preview;
		$normalized['validation_notes']       = $notes;

		$saved = $this->save_dry_run_output( (int) $idea->id, $normalized, $notes );
		if ( is_wp_error( $saved ) ) {
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
			return $saved;
		}

		if ( $is_valid ) {
			$this->update_idea_status( (int) $idea->id, 'dry_run_ready' );
			$this->maybe_create_completed_job_record( (int) $idea->id, $payload, $normalized );
		} else {
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
		}

		return array(
			'output'           => $normalized,
			'validation_notes' => $notes,
			'valid'            => $is_valid,
			'source'           => $normalized['source'],
		);
	}

	/**
	 * Return translated status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	public function get_status_label( $status ) {
		$labels = array(
			'new'            => __( 'Nuova', 'wp-ai-publisher' ),
			'dry_run_ready'  => __( 'Dry-run pronto', 'wp-ai-publisher' ),
			'dry_run_failed' => __( 'Dry-run fallito', 'wp-ai-publisher' ),
			'approved'       => __( 'Approvata', 'wp-ai-publisher' ),
			'rejected'       => __( 'Rifiutata', 'wp-ai-publisher' ),
			'draft_created'  => __( 'Bozza creata', 'wp-ai-publisher' ),
		);

		$status = sanitize_key( (string) $status );

		return $labels[ $status ] ?? $status;
	}

	/**
	 * Return allowed statuses.
	 *
	 * @return array<int,string>
	 */
	public function get_allowed_statuses() {
		return array( 'new', 'dry_run_ready', 'dry_run_failed', 'approved', 'rejected', 'draft_created' );
	}


	/**
	 * Normalize dry-run output so all expected keys exist before storage.
	 *
	 * @param array<string,mixed> $output Dry-run output.
	 * @return array<string,mixed>
	 */
	private function normalize_dry_run_output( $output ) {
		$defaults = array(
			'title'                  => '',
			'slug'                   => '',
			'excerpt'                => '',
			'content_outline'        => array(),
			'categories'             => array(),
			'tags'                   => array(),
			'meta_title'             => '',
			'meta_description'       => '',
			'open_graph_title'       => '',
			'open_graph_description' => '',
			'twitter_title'          => '',
			'twitter_description'    => '',
			'featured_image_prompt'  => '',
			'internal_image_prompts' => array(),
			'image_alt_texts'        => array(),
			'image_captions'         => array(),
			'internal_link_targets'  => array(),
			'knowledge_summary'      => '',
			'entities'               => array(),
			'search_intent'          => '',
			'tutorial_level'         => '',
			'cluster_topic'          => '',
			'subtopic'               => '',
			'validation_notes'       => array(),
			'classic_editor_preview' => array(
				'html'               => '',
				'plain_text_summary' => '',
				'validation_notes'   => array(),
			),
		);

		if ( ! is_array( $output ) ) {
			return $defaults;
		}

		return wp_parse_args( $output, $defaults );
	}

	/**
	 * Validate dry-run output minimum schema.
	 *
	 * @param mixed $output AI output.
	 * @return array{valid:bool,notes:array<int,string>}
	 */
	private function validate_dry_run_output( $output ) {
		$notes = array();

		if ( ! is_array( $output ) ) {
			return array(
				'valid' => false,
				'notes' => array( __( 'Output dry-run gravemente invalido: il risultato non è un array.', 'wp-ai-publisher' ) ),
			);
		}

		$required_strings = array( 'title', 'slug', 'excerpt', 'meta_title', 'meta_description' );
		foreach ( $required_strings as $field ) {
			if ( empty( $output[ $field ] ) || ! is_string( $output[ $field ] ) ) {
				$notes[] = sprintf( __( 'Campo obbligatorio mancante o vuoto: %s.', 'wp-ai-publisher' ), $field );
			}
		}

		if ( empty( $output['content_outline'] ) || ! is_array( $output['content_outline'] ) ) {
			$notes[] = __( 'La struttura articolo deve essere un array non vuoto.', 'wp-ai-publisher' );
		}

		foreach ( array( 'categories', 'tags' ) as $array_field ) {
			if ( ! isset( $output[ $array_field ] ) || ! is_array( $output[ $array_field ] ) ) {
				$notes[] = sprintf( __( 'Il campo %s deve essere un array.', 'wp-ai-publisher' ), $array_field );
			}
		}

		return array(
			'valid' => empty( $notes ),
			'notes' => $notes,
		);
	}

	/**
	 * Sanitize structured output recursively.
	 *
	 * @param mixed $value Value to sanitize.
	 * @return mixed
	 */
	private function sanitize_structured_output( $value, $current_key = '' ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$sanitized_key               = sanitize_key( (string) $key );
				$sanitized[ $sanitized_key ] = $this->sanitize_structured_output( $item, $sanitized_key );
			}

			return $sanitized;
		}

		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_scalar( $value ) ) {
			if ( 'html' === $current_key ) {
				$classic_builder = new Classic_Content_Builder();
				return $classic_builder->sanitize_classic_html( (string) $value );
			}

			return sanitize_textarea_field( (string) $value );
		}

		return null;
	}

	/**
	 * Remove legacy generic placeholder notes so the standalone word passaggio is not a false positive.
	 *
	 * @param mixed $notes Preview notes.
	 * @return array<int,string>
	 */
	private function remove_generic_placeholder_preview_notes( $notes ) {
		$filtered = array();
		foreach ( (array) $notes as $note ) {
			$note_text = strtolower( remove_accents( (string) $note ) );
			if ( false !== strpos( $note_text, 'placeholder' ) ) {
				continue;
			}
			$filtered[] = (string) $note;
		}

		return array_values( array_unique( array_filter( $filtered ) ) );
	}

	/**
	 * Validate the Classic Editor preview contract.
	 *
	 * @param mixed $preview Preview array.
	 * @return array{valid:bool,notes:array<int,string>}
	 */
	private function validate_classic_editor_preview( $preview ) {
		$notes       = array();
		$grave_notes = array();

		if ( ! is_array( $preview ) ) {
			return array(
				'valid' => false,
				'notes' => array( __( 'Anteprima Classic Editor mancante o non valida.', 'wp-ai-publisher' ) ),
			);
		}

		$html = (string) ( $preview['html'] ?? '' );
		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			$grave_notes[] = __( 'Nota grave: classic_editor_preview.html è vuoto.', 'wp-ai-publisher' );
		}

		$grave_checks = array(
			'<!-- wp:' => __( 'Nota grave: classic_editor_preview contiene markup Gutenberg.', 'wp-ai-publisher' ),
			'wp-block' => __( 'Nota grave: classic_editor_preview contiene classi o stringhe Gutenberg.', 'wp-ai-publisher' ),
			'<script'  => __( 'Nota grave: classic_editor_preview contiene script non consentiti.', 'wp-ai-publisher' ),
			'<iframe'  => __( 'Nota grave: classic_editor_preview contiene iframe non consentiti.', 'wp-ai-publisher' ),
			' style='  => __( 'Nota grave: classic_editor_preview contiene style inline non consentiti.', 'wp-ai-publisher' ),
		);
		$light_checks = array(
			'descrivere in modo pratico',
			'descrivere in modo verificabile',
			'descrivere il passaggio',
			'nel contesto di',
			'evitando dettagli tecnici non confermati',
			'passaggio “',
			'passaggio "',
		);

		$lower_html = function_exists( 'mb_strtolower' ) ? mb_strtolower( $html ) : strtolower( $html );
		foreach ( $grave_checks as $needle => $message ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $needle ) : strtolower( $needle );
			if ( false !== strpos( $lower_html, $needle ) ) {
				$grave_notes[] = $message;
			}
		}

		foreach ( $light_checks as $needle ) {
			$needle = function_exists( 'mb_strtolower' ) ? mb_strtolower( $needle ) : strtolower( $needle );
			if ( false !== strpos( $lower_html, $needle ) ) {
				$notes[] = __( 'Nota lieve: l’anteprima contiene frasi placeholder e resta dry-run ready, ma è da revisionare.', 'wp-ai-publisher' );
				break;
			}
		}

		$notes = array_merge( $grave_notes, $notes );

		return array(
			'valid' => empty( $grave_notes ),
			'notes' => array_values( array_unique( array_filter( $notes ) ) ),
		);
	}

	/**
	 * Optionally record a completed job for audit only. Never blocks dry-run.
	 *
	 * @param int                 $idea_id Idea ID.
	 * @param array<string,mixed> $payload Dry-run payload.
	 * @param array<string,mixed> $output Dry-run output.
	 * @return void
	 */
	private function maybe_create_completed_job_record( $idea_id, $payload, $output ) {
		global $wpdb;

		$tables = $this->db->check_tables();
		if ( empty( $tables['jobs'] ) ) {
			return;
		}

		$payload_json = wp_json_encode( $payload );
		$output_json  = wp_json_encode( $output );

		if ( false === $payload_json || false === $output_json ) {
			return;
		}

		$inserted = $wpdb->insert(
			$this->db->get_jobs_table_name(),
			array(
				'job_type'    => 'create_content_idea',
				'status'      => 'completed',
				'priority'    => 10,
				'payload'     => $payload_json,
				'attempts'    => 1,
				'created_at'  => current_time( 'mysql' ),
				'finished_at' => current_time( 'mysql' ),
				'output'      => $output_json,
			),
			array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return;
		}

		$wpdb->update(
			$this->get_table_name(),
			array( 'job_id' => (int) $wpdb->insert_id ),
			array( 'id' => absint( $idea_id ) ),
			array( '%d' ),
			array( '%d' )
		);
	}
}
