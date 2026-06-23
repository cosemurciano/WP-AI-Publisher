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
	 * Cached draft creator.
	 *
	 * @var Draft_Creator|null
	 */
	private $draft_creator;

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
	 * Return a shared Draft_Creator instance.
	 *
	 * @return Draft_Creator
	 */
	private function draft_creator() {
		if ( ! $this->draft_creator instanceof Draft_Creator ) {
			$this->draft_creator = new Draft_Creator( $this->db, $this->logger );
		}

		return $this->draft_creator;
	}

	/**
	 * Create a new content idea.
	 *
	 * @param array<string,mixed> $data Input data.
	 * @return int|WP_Error Inserted idea ID or error.
	 */
	public function create_idea( $data ) {
		$nonce = sanitize_text_field( (string) ( $data['_wpnonce'] ?? '' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wpai_publisher_create_content_idea' ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_nonce', __( 'Nonce non valido.', 'wp-ai-publisher' ) );
		}

		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			return new WP_Error( 'wpai_content_idea_forbidden', __( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		return $this->insert_idea_row( $data );
	}

	/**
	 * Create an idea from a trusted programmatic caller (e.g. an authenticated
	 * webhook), bypassing the admin nonce/capability checks.
	 *
	 * The CALLER is responsible for authenticating the request (Telegram secret,
	 * signature, etc.) before invoking this method.
	 *
	 * @param array<string,mixed> $data Idea data (topic, keyword, language, article_type_id, scheduled_at).
	 * @return int|WP_Error Idea ID or error.
	 */
	public function create_idea_programmatic( $data ) {
		return $this->insert_idea_row( is_array( $data ) ? $data : array() );
	}

	/**
	 * Update an existing idea before it has been turned into a draft.
	 *
	 * @param int                 $id Idea ID.
	 * @param array<string,mixed> $data Fields (topic, keyword, language, article_type_id, scheduled_at).
	 * @return true|WP_Error
	 */
	public function update_idea( $id, $data ) {
		global $wpdb;

		$id   = absint( $id );
		$idea = $this->get_idea( $id );
		if ( ! $idea ) {
			return new WP_Error( 'wpai_content_idea_not_found', __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}

		$editable = array( 'new', 'scheduled', 'draft_failed', 'timeout', 'dry_run_failed' );
		if ( ! in_array( (string) $idea->status, $editable, true ) ) {
			return new WP_Error( 'wpai_content_idea_locked', __( 'Questa idea non è più modificabile (bozza in lavorazione o già creata).', 'wp-ai-publisher' ) );
		}

		$topic = sanitize_textarea_field( (string) ( $data['topic'] ?? '' ) );
		if ( '' === trim( $topic ) ) {
			return new WP_Error( 'wpai_content_idea_empty_topic', __( 'L’argomento principale è obbligatorio.', 'wp-ai-publisher' ) );
		}

		$language      = sanitize_key( (string) ( $data['language'] ?? 'it' ) );
		$allowed_langs = array( 'it', 'en', 'fr', 'es', 'de' );
		if ( ! in_array( $language, $allowed_langs, true ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_language', __( 'Lingua non valida.', 'wp-ai-publisher' ) );
		}

		$article_types_enabled = wpai_publisher_article_types_enabled();
		$article_type_id       = $article_types_enabled ? absint( $data['article_type_id'] ?? 0 ) : 0;
		if ( $article_types_enabled && $article_type_id > 0 && ! wpai_publisher_is_active_article_type_safe( $article_type_id ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_article_type', __( 'Seleziona una Tipologia articolo attiva.', 'wp-ai-publisher' ) );
		}

		$scheduled_at = $this->normalize_scheduled_at( $data['scheduled_at'] ?? '' );
		$status       = ( '' !== $scheduled_at ) ? 'scheduled' : 'new';
		$category_ids = $this->sanitize_category_ids( $data['category_ids'] ?? array() );

		if ( method_exists( $this->db, 'ensure_content_ideas_category_ids_column' ) ) {
			$this->db->ensure_content_ideas_category_ids_column();
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'updated_at'      => current_time( 'mysql' ),
				'status'          => $status,
				'scheduled_at'    => '' !== $scheduled_at ? $scheduled_at : null,
				'topic'           => $topic,
				'keyword'         => sanitize_text_field( (string) ( $data['keyword'] ?? '' ) ),
				'language'        => $language,
				'article_type_id' => $article_type_id,
				'category_ids'    => ! empty( $category_ids ) ? wp_json_encode( $category_ids ) : null,
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' ),
			array( '%d' )
		);

		if ( false === $updated ) {
			return new WP_Error( 'wpai_content_idea_update_failed', __( 'Impossibile aggiornare l’idea.', 'wp-ai-publisher' ) );
		}

		return true;
	}

	/**
	 * Validate and insert a content idea row.
	 *
	 * @param array<string,mixed> $data Idea data.
	 * @return int|WP_Error Idea ID or error.
	 */
	private function insert_idea_row( $data ) {
		global $wpdb;

		$topic = sanitize_textarea_field( (string) ( $data['topic'] ?? '' ) );
		if ( '' === trim( $topic ) ) {
			return new WP_Error( 'wpai_content_idea_empty_topic', __( 'L’argomento principale è obbligatorio.', 'wp-ai-publisher' ) );
		}

		$language       = sanitize_key( (string) ( $data['language'] ?? 'it' ) );
		$allowed_langs  = array( 'it', 'en', 'fr', 'es', 'de' );
		$article_types_enabled = wpai_publisher_article_types_enabled();
		$article_type_id      = $article_types_enabled ? absint( $data['article_type_id'] ?? 0 ) : 0;

		if ( ! in_array( $language, $allowed_langs, true ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_language', __( 'Lingua non valida.', 'wp-ai-publisher' ) );
		}

		if ( $article_types_enabled && $article_type_id > 0 && ! wpai_publisher_is_active_article_type_safe( $article_type_id ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_article_type', __( 'Seleziona una Tipologia articolo attiva.', 'wp-ai-publisher' ) );
		}
		if ( method_exists( $this->db, 'ensure_content_ideas_article_type_column' ) ) {
			$this->db->ensure_content_ideas_article_type_column();
		}
		if ( method_exists( $this->db, 'ensure_content_ideas_scheduled_column' ) ) {
			$this->db->ensure_content_ideas_scheduled_column();
		}
		if ( method_exists( $this->db, 'ensure_content_ideas_category_ids_column' ) ) {
			$this->db->ensure_content_ideas_category_ids_column();
		}

		$category_ids = $this->sanitize_category_ids( $data['category_ids'] ?? array() );

		// Optional scheduling: a future date moves the idea to the 'scheduled' state
		// until the cron picks it up.
		$scheduled_at = $this->normalize_scheduled_at( $data['scheduled_at'] ?? '' );
		$status       = ( '' !== $scheduled_at ) ? 'scheduled' : 'new';

		$table_name = $this->get_table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
		if ( $table_exists !== $table_name ) {
			$this->logger->error( __( 'Tabella idee contenuto non disponibile.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'table' => $table_name, 'db_error' => $wpdb->last_error ) );
			return new WP_Error( 'wpai_content_ideas_table_missing', __( 'La tabella delle idee contenuto non è disponibile. Apri Stato sistema o riattiva il plugin per eseguire la migrazione.', 'wp-ai-publisher' ) );
		}

		$inserted = $wpdb->insert(
			$table_name,
			array(
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => null,
				'status'          => $status,
				'scheduled_at'    => '' !== $scheduled_at ? $scheduled_at : null,
				'topic'           => $topic,
				'keyword'         => sanitize_text_field( (string) ( $data['keyword'] ?? '' ) ),
				'language'        => $language,
				'target_audience' => '',
				'tutorial_level'  => null,
				'article_type_id' => $article_type_id,
				'category_ids'    => ! empty( $category_ids ) ? wp_json_encode( $category_ids ) : null,
				'notes'           => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->error( __( 'Creazione idea contenuto non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'db_error' => $wpdb->last_error ) );
			return new WP_Error( 'wpai_content_idea_insert_failed', __( 'Impossibile salvare l’idea contenuto. Controlla i log del plugin o lo stato del database.', 'wp-ai-publisher' ), array( 'db_error' => $wpdb->last_error ) );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Count content ideas, optionally filtered.
	 *
	 * @param array<string,mixed> $args Filters (status, article_type_id, category_id).
	 * @return int
	 */
	public function count_all( $args = array() ) {
		global $wpdb;
		$where = $this->build_ideas_where( $args );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->get_table_name()} WHERE {$where}" );
	}

	/**
	 * Build a sanitized WHERE clause for the idea list filters.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return string
	 */
	private function build_ideas_where( $args ) {
		global $wpdb;
		$where = array( '1=1' );

		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		if ( '' !== $status ) {
			$where[] = $wpdb->prepare( 'status = %s', $status );
		}
		$article_type_id = absint( $args['article_type_id'] ?? 0 );
		if ( $article_type_id > 0 ) {
			$where[] = $wpdb->prepare( 'article_type_id = %d', $article_type_id );
		}
		$category_id = absint( $args['category_id'] ?? 0 );
		if ( $category_id > 0 ) {
			// category_ids holds a JSON array of ints (e.g. [12,34]); match the
			// exact integer with non-digit boundaries to avoid 12 matching 120.
			$where[] = "category_ids REGEXP '(^|[^0-9])" . $category_id . "([^0-9]|$)'";
		}

		return implode( ' AND ', $where );
	}

	/**
	 * Resolve the ORDER BY clause from filter args.
	 *
	 * @param array<string,mixed> $args Filters.
	 * @return string
	 */
	private function build_ideas_orderby( $args ) {
		$orderby = in_array( (string) ( $args['orderby'] ?? '' ), array( 'created_at', 'scheduled_at', 'updated_at' ), true ) ? (string) $args['orderby'] : 'created_at';
		$order   = strtoupper( (string) ( $args['order'] ?? 'DESC' ) ) === 'ASC' ? 'ASC' : 'DESC';
		return "{$orderby} {$order}, id {$order}";
	}

	/**
	 * Get a page of content ideas, optionally filtered/ordered.
	 *
	 * @param int                 $page Page number (1-based).
	 * @param int                 $per_page Items per page.
	 * @param array<string,mixed> $args Filters (status, article_type_id, category_id, orderby, order).
	 * @return array<int,object>
	 */
	public function get_ideas_paginated( $page = 1, $per_page = 20, $args = array() ) {
		global $wpdb;
		$per_page = min( 100, max( 1, absint( $per_page ) ) );
		$page     = max( 1, absint( $page ) );
		$offset   = ( $page - 1 ) * $per_page;
		if ( method_exists( $this->db, 'ensure_content_ideas_article_type_column' ) ) {
			$this->db->ensure_content_ideas_article_type_column();
		}
		if ( method_exists( $this->db, 'ensure_content_ideas_category_ids_column' ) ) {
			$this->db->ensure_content_ideas_category_ids_column();
		}
		$where   = $this->build_ideas_where( $args );
		$orderby = $this->build_ideas_orderby( $args );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE {$where} ORDER BY {$orderby} LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);
	}

	/**
	 * Normalize a scheduling datetime (e.g. from a datetime-local field) to MySQL.
	 *
	 * @param string $value Raw datetime.
	 * @return string MySQL datetime or '' if empty/invalid.
	 */
	private function normalize_scheduled_at( $value ) {
		$value = trim( str_replace( 'T', ' ', (string) $value ) );
		if ( '' === $value ) {
			return '';
		}
		// Interpret the incoming wall-clock time in the site's timezone and store
		// it as UTC (scheduling comparisons run against gmdate()).
		$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new \DateTimeZone( 'UTC' );
		try {
			$dt = new \DateTime( $value, $tz );
		} catch ( \Exception $e ) {
			$ts = strtotime( $value );
			return false !== $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '';
		}
		$dt->setTimezone( new \DateTimeZone( 'UTC' ) );
		return $dt->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Sanitize a list of category IDs (array or comma string) to existing IDs.
	 *
	 * @param mixed $value IDs as array or comma-separated string.
	 * @return array<int,int>
	 */
	private function sanitize_category_ids( $value ) {
		// Accept an array of IDs, or a comma-separated string of IDs/names (the
		// tag-box UI submits category names separated by commas).
		if ( is_string( $value ) ) {
			$value = explode( ',', $value );
		}
		$ids = array();
		foreach ( (array) $value as $token ) {
			if ( is_int( $token ) || ( is_string( $token ) && ctype_digit( trim( $token ) ) ) ) {
				$ids[] = absint( $token );
				continue;
			}
			$name = trim( wp_strip_all_tags( (string) $token ) );
			if ( '' === $name ) {
				continue;
			}
			$term = get_term_by( 'name', $name, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return array();
		}
		// Keep only categories that actually exist.
		$existing = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids', 'include' => $ids ) );
		$existing = is_wp_error( $existing ) ? array() : array_map( 'absint', (array) $existing );
		return array_values( array_intersect( $ids, $existing ) );
	}

	/**
	 * Decode the stored category IDs for an idea row.
	 *
	 * @param object|null $idea Idea row.
	 * @return array<int,int>
	 */
	public function get_idea_category_ids( $idea ) {
		if ( ! $idea ) {
			return array();
		}
		$decoded = ! empty( $idea->category_ids ) ? json_decode( (string) $idea->category_ids, true ) : array();
		$ids     = is_array( $decoded ) ? array_values( array_filter( array_map( 'absint', $decoded ) ) ) : array();

		// Backward compatibility: ideas imported before the category column existed
		// stored their categories in a legacy option keyed by idea ID.
		if ( empty( $ids ) && ! empty( $idea->id ) ) {
			$legacy = get_option( 'wpai_publisher_bulk_import_categories', array() );
			$key    = absint( $idea->id );
			if ( is_array( $legacy ) && ! empty( $legacy[ $key ] ) && is_array( $legacy[ $key ] ) ) {
				$ids = array_values( array_filter( array_map( 'absint', $legacy[ $key ] ) ) );
			}
		}

		return $ids;
	}

	/**
	 * Resolve idea category IDs to category names.
	 *
	 * @param object|null $idea Idea row.
	 * @return array<int,string>
	 */
	public function get_idea_category_names( $idea ) {
		$names = array();
		foreach ( $this->get_idea_category_ids( $idea ) as $cid ) {
			$term = get_term( $cid, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return $names;
	}

	/**
	 * Get scheduled ideas whose time is due (status 'scheduled', scheduled_at <= now).
	 *
	 * @param int $limit Max rows.
	 * @return array<int,object>
	 */
	public function get_due_scheduled_ideas( $limit = 20 ) {
		global $wpdb;
		if ( method_exists( $this->db, 'ensure_content_ideas_scheduled_column' ) ) {
			$this->db->ensure_content_ideas_scheduled_column();
		}
		$limit = min( 50, max( 1, absint( $limit ) ) );
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE status = 'scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC LIMIT %d",
				gmdate( 'Y-m-d H:i:s' ),
				$limit
			)
		);
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
		if ( method_exists( $this->db, 'ensure_content_ideas_article_type_column' ) ) {
			$this->db->ensure_content_ideas_article_type_column();
		}

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
		if ( method_exists( $this->db, 'ensure_content_ideas_article_type_column' ) ) {
			$this->db->ensure_content_ideas_article_type_column();
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d",
				$id
			)
		);
	}

	public function assign_article_type( $idea_id, $article_type_id ) {
		global $wpdb;
		$idea_id = absint( $idea_id );
		$article_type_id = absint( $article_type_id );
		if ( 0 === $idea_id || 0 === $article_type_id ) {
			return false;
		}
		if ( ! wpai_publisher_is_active_article_type_safe( $article_type_id ) ) {
			$this->logger->warning( __( 'Assegnazione tipologia rifiutata: tipologia non attiva o non disponibile.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'article_type_id' => $article_type_id ) );
			return false;
		}
		if ( method_exists( $this->db, 'ensure_content_ideas_article_type_column' ) ) {
			$this->db->ensure_content_ideas_article_type_column();
		}
		if ( method_exists( $this->db, 'has_content_ideas_article_type_column' ) && ! $this->db->has_content_ideas_article_type_column() ) {
			return false;
		}
		$idea = $this->get_idea( $idea_id );
		if ( ! $idea ) {
			return false;
		}
		return false !== $wpdb->update(
			$this->get_table_name(),
			array( 'article_type_id' => $article_type_id, 'updated_at' => current_time( 'mysql' ) ),
			array( 'id' => $idea_id ),
			array( '%d', '%s' ),
			array( '%d' )
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

	public function mark_stale_processing_ideas( $minutes = 15 ) {
		global $wpdb;
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $minutes ) ) * MINUTE_IN_SECONDS );
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->get_table_name()} SET status = 'timeout', draft_error = %s, updated_at = %s WHERE status = 'processing' AND updated_at IS NOT NULL AND updated_at < %s",
				__( 'Il job è rimasto in lavorazione oltre il tempo previsto.', 'wp-ai-publisher' ),
				current_time( 'mysql' ),
				$cutoff
			)
		);
		if ( $updated > 0 ) {
			$this->logger->warning( __( 'Job creazione bozza scaduti.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_timeout', 'count' => (int) $updated ) );
		}
		return false !== $updated;
	}


	public function attach_job_to_idea( $id, $job_id ) {
		global $wpdb;
		$id = absint( $id );
		$job_id = absint( $job_id );
		if ( 0 === $id || 0 === $job_id ) {
			return false;
		}
		return false !== $wpdb->update( $this->get_table_name(), array( 'job_id' => $job_id, 'updated_at' => current_time( 'mysql' ) ), array( 'id' => $id ), array( '%d', '%s' ), array( '%d' ) );
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
	 * Create an idea and process it up to a WordPress draft.
	 *
	 * @param array<string,mixed> $data Input data.
	 * @return array<string,mixed>
	 */
	public function create_idea_and_draft( $data ) {
		$idea_id = $this->create_idea( $data );
		if ( is_wp_error( $idea_id ) ) {
			return array( 'success' => false, 'idea_id' => 0, 'post_id' => 0, 'status' => 'new', 'step_failed' => 'create_idea', 'message' => $idea_id->get_error_message() );
		}
		return $this->process_idea_to_draft( (int) $idea_id );
	}

	/**
	 * Process an existing idea through dry-run, full article validation and draft creation.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array<string,mixed>
	 */
	public function process_idea_to_draft( $idea_id ) {
		$idea_id = absint( $idea_id );
		$idea = $this->get_idea( $idea_id );
		$article_types_enabled = wpai_publisher_article_types_enabled();
		$article_type_id      = $idea && $article_types_enabled ? absint( $idea->article_type_id ?? 0 ) : 0;
		$existing_post_id = $idea ? absint( $idea->draft_post_id ?? 0 ) : 0;
		if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
			return array( 'success' => true, 'idea_id' => $idea_id, 'post_id' => $existing_post_id, 'status' => 'draft_created', 'message' => __( 'La bozza esiste già.', 'wp-ai-publisher' ) );
		}
		if ( ! $idea || ( $article_types_enabled && ( 0 === $article_type_id || ! wpai_publisher_is_active_article_type_safe( $article_type_id ) ) ) ) {
			$this->mark_draft_failed( $idea_id, $article_types_enabled ? __( 'Assegna una Tipologia articolo prima di generare la bozza.', 'wp-ai-publisher' ) : __( 'Idea non valida.', 'wp-ai-publisher' ) );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => $article_types_enabled ? 'article_type' : 'idea', 'message' => $article_types_enabled ? __( 'Assegna una Tipologia articolo prima di generare la bozza.', 'wp-ai-publisher' ) : __( 'Idea non valida.', 'wp-ai-publisher' ) );
		}
		$this->update_idea_status( $idea_id, 'processing' );

		return $this->generate_article_and_create_draft( $idea_id );
	}

	/**
	 * Simplified single-call draft generation.
	 *
	 * Sends the content idea together with its article type prompt to the AI,
	 * receives one complete Classic Editor article and creates the WordPress
	 * draft. There is no separate structured dry-run step and no local filler:
	 * if the AI cannot produce a publishable article the idea is marked failed
	 * with a clear, actionable message.
	 *
	 * @param int $idea_id Idea ID.
	 * @return array<string,mixed>
	 */
	private function generate_article_and_create_draft( $idea_id ) {
		$idea = $this->get_idea( $idea_id );
		if ( ! $idea ) {
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => 'idea', 'message' => __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}

		$site_context    = wpai_publisher_get_site_context();
		$article_type_id = wpai_publisher_article_types_enabled() ? absint( $idea->article_type_id ?? 0 ) : 0;
		$article_type    = $article_type_id > 0 ? wpai_publisher_get_article_type_config_safe( $article_type_id ) : array();

		$category_boundary = wpai_publisher_resolve_allowed_category_ids( $article_type, $site_context );
		if ( ! empty( $category_boundary['conflict'] ) ) {
			$this->mark_draft_failed( $idea_id, (string) $category_boundary['message'] );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => 'category', 'message' => (string) $category_boundary['message'] );
		}

		$ai_diagnostics = method_exists( $this->ai_provider, 'get_ai_generation_diagnostics' ) ? $this->ai_provider->get_ai_generation_diagnostics() : array();
		$this->logger->info( __( 'Generazione articolo da idea avviata.', 'wp-ai-publisher' ), array_merge( array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'event' => 'article_generation_started', 'article_type_id' => $article_type_id ), $ai_diagnostics ) );
		$site_data = function_exists( 'wpai_publisher_get_site_generation_context' ) ? wpai_publisher_get_site_generation_context() : array();
		$target_categories = $this->get_idea_category_names( $idea );
		$article   = $this->ai_provider->generate_article_from_idea(
			array(
				'topic'             => (string) $idea->topic,
				'keyword'           => (string) $idea->keyword,
				'language'          => (string) $idea->language,
				'context'           => $site_data,
				'target_categories' => $target_categories,
			),
			$site_context,
			$article_type
		);
		if ( is_wp_error( $article ) ) {
			$error_data = $article->get_error_data();
			$this->logger->error( __( 'Generazione articolo da idea fallita.', 'wp-ai-publisher' ), array_merge(
				array( 'source' => 'ai_generation', 'idea_id' => $idea_id, 'event' => 'job_failed', 'step' => 'generation', 'error_code' => $article->get_error_code(), 'message' => $article->get_error_message() ),
				is_array( $error_data ) ? $error_data : array()
			) );
			$this->mark_draft_failed( $idea_id, $article->get_error_message() );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => 'generation', 'error_code' => $article->get_error_code(), 'message' => $article->get_error_message() );
		}
		$this->logger->info( __( 'Articolo generato dall’AI.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => $idea_id, 'event' => 'article_generated', 'channel' => (string) ( $article['channel'] ?? 'unknown' ), 'ai_source' => (string) ( $article['source'] ?? '' ), 'quality_notes' => count( (array) ( $article['validation_notes'] ?? array() ) ) ) );

		// Prefer the AI-generated title/slug; fall back to the idea topic only
		// when the AI did not provide a usable title.
		$ai_title = sanitize_text_field( (string) ( $article['title'] ?? '' ) );
		$title    = '' !== $ai_title ? $ai_title : wp_trim_words( wp_strip_all_tags( (string) $idea->topic ), 14, '' );
		$slug     = sanitize_title( (string) ( $article['slug'] ?? '' ) );

		// Category precedence: AI choice → categories saved on the idea → a forced
		// set provided by a trusted caller (e.g. the Telegram inline keyboard).
		$category_ids = array_values( array_filter( array_map( 'absint', (array) ( $article['category_ids'] ?? array() ) ) ) );
		$idea_categories = $this->get_idea_category_ids( $idea );
		if ( ! empty( $idea_categories ) ) {
			$category_ids = $idea_categories;
		}
		/**
		 * Filter the category IDs to assign to the draft, overriding the AI choice.
		 *
		 * @param array<int,int> $forced  Forced category IDs (empty to keep AI choice).
		 * @param int            $idea_id Idea ID.
		 */
		$forced_categories = array_values( array_filter( array_map( 'absint', (array) apply_filters( 'wpai_publisher_forced_category_ids', array(), $idea_id ) ) ) );
		if ( ! empty( $forced_categories ) ) {
			$category_ids = $forced_categories;
		}
		$output = array(
			'title'              => $title,
			'slug'               => $slug,
			'excerpt'            => (string) ( $article['plain_text_summary'] ?? '' ),
			'content_outline'    => array(),
			'category_ids'       => $category_ids,
			'categories'         => array(),
			'tags'               => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $article['tags'] ?? array() ) ) ) ),
			'meta_title'         => sanitize_text_field( (string) ( $article['meta_title'] ?? '' ) ),
			'meta_description'   => sanitize_text_field( (string) ( $article['meta_description'] ?? '' ) ),
			'featured_image_alt' => sanitize_text_field( (string) ( $article['featured_image_alt'] ?? '' ) ),
			'article_type'       => $article_type,
			'full_article'     => array(
				'html'             => (string) ( $article['html'] ?? '' ),
				'source'           => (string) ( $article['source'] ?? 'wordpress_ai' ),
				'validation_notes' => array_values( (array) ( $article['validation_notes'] ?? array() ) ),
			),
			'source'           => (string) ( $article['source'] ?? 'wordpress_ai' ),
			'validation_notes' => array_values( (array) ( $article['validation_notes'] ?? array() ) ),
		);

		$saved = $this->save_dry_run_output( $idea_id, $output, $output['validation_notes'] );
		if ( is_wp_error( $saved ) ) {
			$this->mark_draft_failed( $idea_id, $saved->get_error_message() );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => 'save', 'message' => $saved->get_error_message() );
		}

		$this->update_idea_status( $idea_id, 'full_article_ready' );
		$this->approve_idea( $idea_id );

		$creator = $this->draft_creator();
		$post_id = $creator->create_draft_from_idea( $this->get_idea( $idea_id ), array( 'automatic' => true, 'content_ideas' => $this ) );
		if ( is_wp_error( $post_id ) ) {
			$this->logger->warning( __( 'Creazione bozza fallita.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'event' => 'job_failed', 'step' => 'draft', 'error_code' => $post_id->get_error_code(), 'message' => $post_id->get_error_message() ) );
			$this->mark_draft_failed( $idea_id, $post_id->get_error_message() );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'draft_failed', 'step_failed' => 'draft', 'error_code' => $post_id->get_error_code(), 'message' => $post_id->get_error_message(), 'details' => $post_id->get_error_data() );
		}

		$this->logger->info( __( 'Bozza creata da idea + tipologia.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'event' => 'draft_created', 'step' => 'draft_created', 'post_id' => absint( $post_id ) ) );
		// Featured image first (independent, most visible asset) so it is set
		// even if the slower inline-image phase is interrupted.
		$this->maybe_generate_featured_image( absint( $post_id ), $idea_id, $article_type, $output['featured_image_alt'] );
		$this->maybe_generate_inline_images( absint( $post_id ), $idea_id, $article_type );

		/**
		 * Fires after a draft has been created AND its featured/inline images have
		 * been generated and inserted. Use this (not wpai_publisher_idea_draft_created)
		 * to notify or act on a fully assembled draft.
		 *
		 * @param int $idea_id Idea ID.
		 * @param int $post_id Draft post ID.
		 */
		do_action( 'wpai_publisher_idea_draft_finalized', absint( $idea_id ), absint( $post_id ) );

		return array( 'success' => true, 'idea_id' => $idea_id, 'post_id' => absint( $post_id ), 'status' => 'draft_created', 'message' => __( 'Bozza creata correttamente.', 'wp-ai-publisher' ) );
	}

	/**
	 * Replace AI image markers in the body with real generated images.
	 *
	 * The AI places markers like [[wpai-image: descrizione]] where an
	 * illustrative image helps. For each marker (up to the configured limit)
	 * the plugin generates a real image, uploads it to the media library and
	 * substitutes the marker with an <img>. Any leftover/failed marker is
	 * removed so no placeholder text is ever left in the published draft.
	 *
	 * Opt-in (setting generate_inline_images) and strictly non-blocking.
	 *
	 * @param int                 $post_id Draft post ID.
	 * @param int                 $idea_id Idea ID.
	 * @param array<string,mixed> $article_type Article type config.
	 * @return void
	 */
	private function maybe_generate_inline_images( $post_id, $idea_id, $article_type ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return;
		}
		$post = get_post( $post_id );
		if ( ! $post || ! is_string( $post->post_content ) ) {
			return;
		}
		$content = (string) $post->post_content;

		// Accept both our marker syntax and any <img>/<figure> the AI emitted on
		// its own (e.g. when the article-type prompt asks for figure markup):
		// normalize everything to the [[wpai-image: ...]] marker first.
		$content = $this->convert_ai_image_markup_to_markers( $content );

		// Marker syntax: [[wpai-image: descrizione della scena]] (descrizione opzionale).
		$pattern = '/\[\[\s*wpai-image\s*:?\s*(.*?)\s*\]\]/is';
		if ( ! preg_match( $pattern, $content ) ) {
			return;
		}

		$settings = wpai_publisher_get_settings();
		$enabled  = ! empty( $settings['generate_inline_images'] );
		$max      = max( 0, (int) ( $settings['max_inline_images'] ?? 3 ) );

		// When the feature is off (or the limit is 0, or image generation is
		// unavailable) strip every marker so no placeholder survives.
		if ( ! $enabled || $max <= 0 || ! method_exists( $this->ai_provider, 'generate_image' ) ) {
			$cleaned = (string) preg_replace( $pattern, '', $content );
			if ( $cleaned !== (string) $post->post_content ) {
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $cleaned ) );
			}
			return;
		}

		$this->raise_runtime_limits_for_images();

		$style  = trim( (string) ( $article_type['image_prompt'] ?? '' ) );
		$title  = get_the_title( $post_id );
		$count  = 0;
		$placed = 0;

		$this->logger->info( __( 'Generazione immagini nel corpo avviata.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'inline_images_started' ) );

		// Process markers one at a time and PERSIST the post after each image, so
		// a slow/failed later image cannot roll back the images already inserted
		// (image generation is slow and a request timeout would otherwise lose
		// every replacement done in memory).
		$current = $content;
		while ( $count < $max && preg_match( $pattern, $current, $m ) ) {
			$count++;
			$marker      = (string) $m[0];
			$pos         = strpos( $current, $marker );
			$description = trim( (string) ( $m[1] ?? '' ) );
			$prompt      = '' !== $description ? $description : sprintf( __( 'Illustrazione editoriale pertinente per un articolo intitolato: %s.', 'wp-ai-publisher' ), $title );
			if ( '' !== $style ) {
				$prompt = $style . "\n" . $prompt;
			}
			$prompt .= "\n" . __( 'Senza testo nell’immagine.', 'wp-ai-publisher' );

			$replacement = '';
			$image       = $this->ai_provider->generate_image( $prompt );
			if ( is_wp_error( $image ) ) {
				$this->logger->warning( __( 'Immagine nel corpo non generata.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'inline_image_failed', 'error_code' => $image->get_error_code(), 'message' => $image->get_error_message() ) );
			} else {
				// The marker description is the image's own title/alt: it drives
				// the file name and media title (not the article title).
				$image_title   = '' !== $description ? $description : $title;
				$attachment_id = $this->import_image_attachment( $post_id, $image, absint( $idea_id ), $image_title, $image_title );
				if ( $attachment_id > 0 ) {
					$src = wp_get_attachment_image_url( $attachment_id, 'full' );
					if ( ! $src ) {
						$src = wp_get_attachment_url( $attachment_id );
					}
					if ( $src ) {
						$replacement = $this->build_inline_figure_markup( (string) $src, $image_title );
						$placed++;
					}
				}
			}

			if ( false !== $pos ) {
				$current = substr_replace( $current, $replacement, $pos, strlen( $marker ) );
				wp_update_post( array( 'ID' => $post_id, 'post_content' => $current ) );
			}
		}

		// Remove any remaining markers (beyond the limit or not processed).
		$cleaned = (string) preg_replace( $pattern, '', $current );
		if ( $cleaned !== $current ) {
			$current = $cleaned;
			wp_update_post( array( 'ID' => $post_id, 'post_content' => $current ) );
		}

		$this->logger->info( __( 'Immagini nel corpo inserite.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'inline_images_done', 'placed' => $placed, 'detected' => $count ) );
	}

	/**
	 * Raise PHP time/memory limits for the (slow, synchronous) image phase.
	 *
	 * @return void
	 */
	private function raise_runtime_limits_for_images() {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_set_time_limit
		}
		if ( function_exists( 'wp_raise_memory_limit' ) ) {
			wp_raise_memory_limit( 'image' );
		}
	}

	/**
	 * Normalize AI-emitted image markup (<figure>/<img>) into image markers.
	 *
	 * Lets a custom article-type prompt that asks for <figure>/<img> markup work
	 * with the marker pipeline: the alt text (or figcaption) becomes the image
	 * briefing. Existing [[wpai-image: ...]] markers are left untouched.
	 *
	 * @param string $html Article HTML.
	 * @return string
	 */
	private function convert_ai_image_markup_to_markers( $html ) {
		$html = (string) $html;

		// <figure>…<img …>…[<figcaption>…</figcaption>]…</figure> → marker.
		$html = (string) preg_replace_callback(
			'/<figure\b[^>]*>(.*?)<\/figure>/is',
			function ( $m ) {
				$inner   = (string) ( $m[1] ?? '' );
				$alt     = $this->extract_attr_from_tag( $inner, 'alt' );
				$caption = '';
				if ( preg_match( '/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', $inner, $cap ) ) {
					$caption = trim( wp_strip_all_tags( (string) $cap[1] ) );
				}
				$desc = '' !== $alt ? $alt : $caption;
				return '[[wpai-image: ' . sanitize_text_field( $desc ) . ']]';
			},
			$html
		);

		// Any remaining bare <img …> → marker (use alt as the briefing).
		$html = (string) preg_replace_callback(
			'/<img\b[^>]*>/is',
			function ( $m ) {
				$alt = $this->extract_attr_from_tag( (string) $m[0], 'alt' );
				return '[[wpai-image: ' . sanitize_text_field( $alt ) . ']]';
			},
			$html
		);

		return $html;
	}

	/**
	 * Extract an HTML attribute value from a tag/markup fragment.
	 *
	 * @param string $tag Markup fragment.
	 * @param string $attr Attribute name.
	 * @return string
	 */
	private function extract_attr_from_tag( $tag, $attr ) {
		$attr = preg_quote( (string) $attr, '/' );
		if ( preg_match( '/\b' . $attr . '\s*=\s*("|\')(.*?)\1/is', (string) $tag, $m ) ) {
			return trim( (string) $m[2] );
		}
		return '';
	}

	/**
	 * Build the <figure> markup for an inserted inline image.
	 *
	 * @param string $src Image URL.
	 * @param string $alt Alt text.
	 * @return string
	 */
	private function build_inline_figure_markup( $src, $alt ) {
		$alt = sanitize_text_field( (string) $alt );
		return sprintf(
			'<figure class="aligncenter"><img class="aligncenter" src="%1$s" alt="%2$s" loading="lazy" /></figure>',
			esc_url( $src ),
			esc_attr( $alt )
		);
	}

	/**
	 * Optionally generate and attach an AI featured image to the created draft.
	 *
	 * Opt-in (setting generate_featured_image) and strictly non-blocking: any
	 * failure is logged and never affects the already-created draft.
	 *
	 * @param int                 $post_id Draft post ID.
	 * @param int                 $idea_id Idea ID.
	 * @param array<string,mixed> $article_type Article type config.
	 * @return void
	 */
	private function maybe_generate_featured_image( $post_id, $idea_id, $article_type, $featured_alt = '' ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! function_exists( 'set_post_thumbnail' ) ) {
			return;
		}
		$settings = wpai_publisher_get_settings();
		if ( empty( $settings['generate_featured_image'] ) ) {
			return;
		}
		if ( function_exists( 'has_post_thumbnail' ) && has_post_thumbnail( $post_id ) ) {
			return;
		}
		if ( ! method_exists( $this->ai_provider, 'generate_image' ) ) {
			return;
		}

		$this->raise_runtime_limits_for_images();

		$image_prompt = trim( (string) ( $article_type['image_prompt'] ?? '' ) );
		$title        = get_the_title( $post_id );
		if ( '' === $image_prompt ) {
			$image_prompt = sprintf( __( 'Immagine di copertina editoriale, professionale e pertinente, per un articolo intitolato: %s. Senza testo nell’immagine.', 'wp-ai-publisher' ), $title );
		} else {
			$image_prompt .= "\n" . sprintf( __( 'Argomento dell’articolo: %s.', 'wp-ai-publisher' ), $title );
		}

		// The image's own descriptive title/alt drives its file name and media
		// title; fall back to the article title only when the AI gave none.
		$image_title = trim( (string) $featured_alt );
		if ( '' === $image_title ) {
			$image_title = $title;
		}

		$this->logger->info( __( 'Generazione immagine in evidenza avviata.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'featured_image_started' ) );
		$image = $this->ai_provider->generate_image( $image_prompt );
		if ( is_wp_error( $image ) ) {
			$this->logger->warning( __( 'Immagine in evidenza non generata.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'featured_image_failed', 'error_code' => $image->get_error_code(), 'message' => $image->get_error_message() ) );
			return;
		}

		$attachment_id = $this->import_featured_image( $post_id, $image, absint( $idea_id ), $image_title );
		if ( $attachment_id > 0 ) {
			$this->logger->info( __( 'Immagine in evidenza impostata.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'idea_id' => absint( $idea_id ), 'post_id' => $post_id, 'event' => 'featured_image_set', 'attachment_id' => $attachment_id ) );
		}
	}

	/**
	 * Import image bytes into the media library and set them as featured image.
	 *
	 * @param int                          $post_id Post ID.
	 * @param array{bytes:string,mime:string} $image Image data.
	 * @param int                          $idea_id Idea ID.
	 * @param string                       $image_title Descriptive title for the image.
	 * @return int Attachment ID or 0 on failure.
	 */
	private function import_featured_image( $post_id, $image, $idea_id, $image_title ) {
		$attach_id = $this->import_image_attachment( $post_id, $image, $idea_id, $image_title );
		if ( $attach_id > 0 ) {
			set_post_thumbnail( $post_id, $attach_id );
		}
		return $attach_id;
	}

	/**
	 * Import image bytes into the media library, attached to a post.
	 *
	 * The file name and the attachment title are derived from the image's own
	 * descriptive title (alt/briefing), not from the article title.
	 *
	 * @param int                             $post_id Post ID.
	 * @param array{bytes:string,mime:string} $image Image data.
	 * @param int                             $idea_id Idea ID.
	 * @param string                          $image_title Descriptive title for this image.
	 * @param string                          $alt Alt text (defaults to the image title).
	 * @return int Attachment ID or 0 on failure.
	 */
	private function import_image_attachment( $post_id, $image, $idea_id, $image_title, $alt = '' ) {
		if ( empty( $image['bytes'] ) ) {
			return 0;
		}
		if ( ! function_exists( 'wp_upload_bits' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$image_title = trim( (string) $image_title );
		$alt         = trim( (string) $alt );
		if ( '' === $alt ) {
			$alt = $image_title;
		}

		$mime = (string) ( $image['mime'] ?? 'image/png' );
		$ext  = $this->mime_to_extension( $mime );

		// File name from the image title (slug), so it reflects the image itself.
		$slug = sanitize_title( $image_title );
		if ( '' === $slug ) {
			$slug = 'immagine-' . absint( $idea_id );
		}
		$slug     = substr( $slug, 0, 80 );
		$filename = $slug . '-' . wp_rand( 1000, 9999 ) . '.' . $ext;
		$upload   = wp_upload_bits( $filename, null, (string) $image['bytes'] );
		if ( ! is_array( $upload ) || ! empty( $upload['error'] ) || empty( $upload['file'] ) ) {
			$this->logger->warning( __( 'Upload immagine non riuscito.', 'wp-ai-publisher' ), array( 'source' => 'ai_generation', 'post_id' => absint( $post_id ), 'error' => is_array( $upload ) ? (string) ( $upload['error'] ?? '' ) : 'unknown' ) );
			return 0;
		}

		$filetype   = wp_check_filetype( $upload['file'], null );
		$attachment = array(
			'post_mime_type' => ! empty( $filetype['type'] ) ? $filetype['type'] : $mime,
			'post_title'     => '' !== $image_title ? $image_title : __( 'Immagine articolo', 'wp-ai-publisher' ),
			'post_content'   => '',
			'post_excerpt'   => $alt,
			'post_status'    => 'inherit',
		);
		$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id, true );
		if ( is_wp_error( $attach_id ) || ! $attach_id ) {
			return 0;
		}
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		$metadata = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
		if ( is_array( $metadata ) ) {
			wp_update_attachment_metadata( $attach_id, $metadata );
		}
		if ( '' !== $alt ) {
			update_post_meta( (int) $attach_id, '_wp_attachment_image_alt', sanitize_text_field( $alt ) );
		}
		return (int) $attach_id;
	}

	/**
	 * Map an image mime type to a safe file extension.
	 *
	 * @param string $mime Mime type.
	 * @return string
	 */
	private function mime_to_extension( $mime ) {
		$map = array(
			'image/png'  => 'png',
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/webp' => 'webp',
			'image/gif'  => 'gif',
		);
		$mime = strtolower( trim( (string) $mime ) );
		return $map[ $mime ] ?? 'png';
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
			'draft_failed'   => __( 'Creazione bozza fallita', 'wp-ai-publisher' ),
			'processing'      => __( 'In lavorazione', 'wp-ai-publisher' ),
			'timeout'         => __( 'Scaduto', 'wp-ai-publisher' ),
			'full_article_ready' => __( 'Articolo pronto', 'wp-ai-publisher' ),
			'scheduled'          => __( 'Programmata', 'wp-ai-publisher' ),
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
		return array( 'new', 'scheduled', 'processing', 'timeout', 'dry_run_ready', 'dry_run_failed', 'full_article_ready', 'approved', 'rejected', 'draft_created', 'draft_failed' );
	}


	/**
	 * Approve a dry-run ready idea.
	 *
	 * @param int $id Idea ID.
	 * @return bool|WP_Error
	 */
	public function approve_idea( $id ) {
		global $wpdb;

		$idea = $this->get_idea( $id );
		if ( ! $idea ) {
			return new WP_Error( 'wpai_content_idea_not_found', __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}
		if ( empty( $idea->dry_run_output ) ) {
			return new WP_Error( 'wpai_content_idea_missing_dry_run', __( 'Dry-run assente.', 'wp-ai-publisher' ) );
		}

		return false !== $wpdb->update(
			$this->get_table_name(),
			array(
				'status'      => 'approved',
				'approved_at' => current_time( 'mysql' ),
				'updated_at'  => current_time( 'mysql' ),
			),
			array( 'id' => absint( $id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Reject an idea dry-run.
	 *
	 * @param int $id Idea ID.
	 * @return bool|WP_Error
	 */
	public function reject_idea( $id ) {
		$idea = $this->get_idea( $id );
		if ( ! $idea ) {
			return new WP_Error( 'wpai_content_idea_not_found', __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}

		return $this->update_idea_status( $id, 'rejected' );
	}

	/**
	 * Mark a draft as created.
	 *
	 * @param int    $id Idea ID.
	 * @param int    $post_id Draft post ID.
	 * @param string $status Post status.
	 * @return bool
	 */
	public function mark_draft_created( $id, $post_id, $status ) {
		$creator = $this->draft_creator();
		return $creator->update_idea_after_draft_created( $id, $post_id, $status );
	}

	/**
	 * Mark draft creation as failed.
	 *
	 * @param int    $id Idea ID.
	 * @param string $error_message Error message.
	 * @return bool
	 */
	public function mark_draft_failed( $id, $error_message ) {
		$creator = $this->draft_creator();
		return $creator->update_idea_after_draft_failed( $id, $error_message );
	}

	/**
	 * Get linked draft post ID.
	 *
	 * @param int $id Idea ID.
	 * @return int
	 */
	public function get_draft_post_id( $id ) {
		$idea = $this->get_idea( $id );
		return $idea ? absint( $idea->draft_post_id ?? 0 ) : 0;
	}

	/**
	 * Get edit URL for linked draft.
	 *
	 * @param int $id Idea ID.
	 * @return string
	 */
	public function get_edit_draft_url( $id ) {
		$post_id = $this->get_draft_post_id( $id );
		if ( $post_id <= 0 || ! get_post( $post_id ) ) {
			return '';
		}

		$url = get_edit_post_link( $post_id, '' );
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Delete a single content idea. The linked draft post (if any) is kept.
	 *
	 * @param int $id Idea ID.
	 * @return bool
	 */
	public function delete_idea( $id ) {
		global $wpdb;
		$id = absint( $id );
		if ( 0 === $id ) {
			return false;
		}
		return false !== $wpdb->delete( $this->get_table_name(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Count ideas by status.
	 *
	 * @return array<string,int>
	 */
	public function count_by_status() {
		global $wpdb;

		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$this->get_table_name()} GROUP BY status" );
		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ sanitize_key( (string) $row->status ) ] = absint( $row->total );
		}

		return $counts;
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

	

	

	

	
}
