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

		$payload = array(
			'task'                 => 'structured_content_dry_run',
			'allow_local_fallback' => true,
			'idea'                 => array(
				'id'              => (int) $idea->id,
				'topic'           => (string) $idea->topic,
				'keyword'         => (string) $idea->keyword,
				'language'        => (string) $idea->language,
				'target_audience' => (string) $idea->target_audience,
				'tutorial_level'  => (string) $idea->tutorial_level,
				'notes'           => (string) $idea->notes,
			),
			'safety'               => array(
				'create_posts'       => false,
				'publish_content'    => false,
				'generate_images'    => false,
				'write_seo_metadata' => false,
			),
		);

		$output = $this->ai_provider->generate_structured_content_dry_run( $payload );
		if ( is_wp_error( $output ) ) {
			$this->save_dry_run_output( (int) $idea->id, array(), array( $output->get_error_message() ) );
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
			return $output;
		}

		$output     = $this->normalize_dry_run_output( $output );
		$validation = $this->validate_dry_run_output( $output );
		$notes      = $validation['notes'];

		if ( ! empty( $output['validation_notes'] ) && is_array( $output['validation_notes'] ) ) {
			$notes = array_merge( $notes, $output['validation_notes'] );
		}

		$saved = $this->save_dry_run_output( (int) $idea->id, $output, $notes );
		if ( is_wp_error( $saved ) ) {
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
			return $saved;
		}

		if ( ! empty( $validation['valid'] ) ) {
			$this->update_idea_status( (int) $idea->id, 'dry_run_ready' );
			$this->maybe_create_completed_job_record( (int) $idea->id, $payload, $output );
		} else {
			$this->update_idea_status( (int) $idea->id, 'dry_run_failed' );
		}

		return array(
			'output'           => $output,
			'validation_notes' => $notes,
			'valid'            => ! empty( $validation['valid'] ),
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
	private function sanitize_structured_output( $value ) {
		if ( is_array( $value ) ) {
			$sanitized = array();
			foreach ( $value as $key => $item ) {
				$sanitized[ sanitize_key( (string) $key ) ] = $this->sanitize_structured_output( $item );
			}

			return $sanitized;
		}

		if ( is_scalar( $value ) ) {
			return sanitize_textarea_field( (string) $value );
		}

		return null;
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
