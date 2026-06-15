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

		$nonce = sanitize_text_field( (string) ( $data['_wpnonce'] ?? '' ) );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'wpai_publisher_create_content_idea' ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_nonce', __( 'Nonce non valido.', 'wp-ai-publisher' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return new WP_Error( 'wpai_content_idea_forbidden', __( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$topic = sanitize_textarea_field( (string) ( $data['topic'] ?? '' ) );
		if ( '' === trim( $topic ) ) {
			return new WP_Error( 'wpai_content_idea_empty_topic', __( 'L’argomento principale è obbligatorio.', 'wp-ai-publisher' ) );
		}

		$language       = sanitize_key( (string) ( $data['language'] ?? 'it' ) );
		$allowed_langs  = array( 'it', 'en', 'fr', 'es', 'de' );
		$article_type_id = absint( $data['article_type_id'] ?? 0 );

		if ( ! in_array( $language, $allowed_langs, true ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_language', __( 'Lingua non valida.', 'wp-ai-publisher' ) );
		}

		if ( 0 === $article_type_id || ! Article_Types::is_active_article_type( $article_type_id ) ) {
			return new WP_Error( 'wpai_content_idea_invalid_article_type', __( 'Seleziona una Tipologia articolo attiva.', 'wp-ai-publisher' ) );
		}

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
				'status'          => 'new',
				'topic'           => $topic,
				'keyword'         => sanitize_text_field( (string) ( $data['keyword'] ?? '' ) ),
				'language'        => $language,
				'target_audience' => '',
				'tutorial_level'  => null,
				'article_type_id' => $article_type_id,
				'notes'           => '',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		if ( false === $inserted ) {
			$this->logger->error( __( 'Creazione idea contenuto non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'db_error' => $wpdb->last_error ) );
			return new WP_Error( 'wpai_content_idea_insert_failed', __( 'Impossibile salvare l’idea contenuto. Controlla i log del plugin o lo stato del database.', 'wp-ai-publisher' ), array( 'db_error' => $wpdb->last_error ) );
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
	 * Save full article output inside dry_run_output without changing schema.
	 *
	 * @param int                 $id Idea ID.
	 * @param array<string,mixed> $full_article Full article output.
	 * @return bool|WP_Error
	 */
	public function save_full_article_output( $id, $full_article ) {
		$idea = $this->get_idea( $id );
		if ( ! $idea || empty( $idea->dry_run_output ) ) {
			return new WP_Error( 'wpai_full_article_missing_dry_run', __( 'Dry-run assente.', 'wp-ai-publisher' ) );
		}
		$output = json_decode( (string) $idea->dry_run_output, true );
		if ( ! is_array( $output ) ) {
			return new WP_Error( 'wpai_full_article_invalid_dry_run', __( 'Dry-run non decodificabile.', 'wp-ai-publisher' ) );
		}
		$builder = new Classic_Content_Builder();
		$full_article['html'] = $builder->normalize_full_article_html( (string) ( $full_article['html'] ?? '' ), $output );
		$validation = $builder->validate_publishable_article_html( (string) ( $full_article['html'] ?? '' ) );
		if ( empty( $validation['valid'] ) ) {
			return new WP_Error( 'wpai_full_article_not_publishable', __( 'L’articolo completo è stato generato ma non è stato possibile strutturarlo in HTML per Editor Classico.', 'wp-ai-publisher' ), $validation );
		}
		$full_article['validation_notes'] = array_values( array_unique( array_merge( (array) ( $full_article['validation_notes'] ?? array() ), $validation['notes'] ) ) );
		$output['full_article'] = $full_article;
		$notes = isset( $output['validation_notes'] ) && is_array( $output['validation_notes'] ) ? $output['validation_notes'] : array();
		$saved = $this->save_dry_run_output( $id, $output, $notes );
		if ( ! is_wp_error( $saved ) && false !== $saved ) {
			$this->update_idea_status( $id, 'full_article_ready' );
		}
		return $saved;
	}

	/**
	 * Generate and persist a full article for a dry-run ready or approved idea.
	 *
	 * @param int $id Idea ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function generate_full_article( $id ) {
		$idea = $this->get_idea( $id );
		if ( ! $idea ) {
			return new WP_Error( 'wpai_content_idea_not_found', __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}
		if ( ! in_array( sanitize_key( (string) $idea->status ), array( 'dry_run_ready', 'approved', 'full_article_ready' ), true ) ) {
			return new WP_Error( 'wpai_full_article_invalid_status', __( 'Stato idea non valido per generare l’articolo completo.', 'wp-ai-publisher' ) );
		}
		$dry_run = json_decode( (string) $idea->dry_run_output, true );
		if ( ! is_array( $dry_run ) ) {
			return new WP_Error( 'wpai_full_article_missing_dry_run', __( 'Dry-run assente.', 'wp-ai-publisher' ) );
		}
		$article_type = ! empty( $dry_run['article_type']['id'] ) ? $dry_run['article_type'] : ( ! empty( $idea->article_type_id ) ? Article_Types::get_article_type_config( absint( $idea->article_type_id ) ) : array() );
		$full_article = $this->ai_provider->generate_full_classic_article( $dry_run, wpai_publisher_get_site_context(), $article_type );
		if ( is_wp_error( $full_article ) ) {
			$this->logger->warning( __( 'Generazione articolo completo fallita.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => (int) $id, 'step' => 'full_article_failed', 'error_code' => $full_article->get_error_code(), 'message' => $full_article->get_error_message() ) );
			return $full_article;
		}
		$saved = $this->save_full_article_output( $id, $full_article );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		return $full_article;
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

		$site_context     = wpai_publisher_get_site_context();
		$target_audience  = sanitize_text_field( (string) ( $site_context['default_audience'] ?? '' ) );
		$legacy_audience  = sanitize_text_field( (string) ( $idea->target_audience ?? '' ) );
		$target_audience  = '' !== $target_audience ? $target_audience : $legacy_audience;
		$article_type_id = absint( $idea->article_type_id ?? 0 );
		if ( 0 === $article_type_id || ! Article_Types::is_active_article_type( $article_type_id ) ) {
			return new WP_Error( 'wpai_content_idea_missing_article_type', __( 'Assegna una Tipologia articolo attiva prima di generare la bozza.', 'wp-ai-publisher' ) );
		}
		$article_type = Article_Types::get_article_type_config( $article_type_id );

		$payload = array(
			'task'                 => 'structured_content_dry_run',
			'topic'                => (string) $idea->topic,
			'keyword'              => (string) $idea->keyword,
			'language'             => (string) $idea->language,
			'target_audience'      => $target_audience,
			'tutorial_level'       => (string) ( $article_type['reader_level'] ?? '' ),
			'article_type'         => $article_type,
			'article_type_id'      => $article_type_id,
			'notes'                => '',
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
			$this->logger->warning( __( 'Dry-run fallito.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => (int) $idea->id, 'step' => 'dry_run_failed', 'error_code' => $output->get_error_code(), 'message' => $output->get_error_message() ) );
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

		$normalized['article_type'] = $article_type;
		$classic_builder    = new Classic_Content_Builder( $site_context, $article_type );
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
		$this->update_idea_status( $idea_id, 'processing' );

		$dry_run_result = $this->run_dry_run( $idea_id );
		if ( is_wp_error( $dry_run_result ) || empty( $dry_run_result['valid'] ) ) {
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'dry_run_failed', 'step_failed' => 'dry_run', 'message' => is_wp_error( $dry_run_result ) ? $dry_run_result->get_error_message() : __( 'Dry-run non valido.', 'wp-ai-publisher' ) );
		}
		$this->logger->info( __( 'Dry-run completato per workflow bozza.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'dry_run_ok' ) );

		$idea = $this->get_idea( $idea_id );
		$dry_run = $idea && ! empty( $idea->dry_run_output ) ? json_decode( (string) $idea->dry_run_output, true ) : array();
		if ( ! is_array( $dry_run ) || empty( $dry_run['full_article']['html'] ) ) {
			$full_article = $this->generate_full_article( $idea_id );
			if ( is_wp_error( $full_article ) ) {
				$this->logger->warning( __( 'Articolo completo non generato nel workflow bozza.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'draft_failed', 'error_code' => $full_article->get_error_code(), 'message' => $full_article->get_error_message() ) );
				$this->update_idea_status( $idea_id, 'dry_run_ready' );
				return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => 'dry_run_ready', 'step_failed' => 'full_article', 'message' => $full_article->get_error_message() );
			}
			$this->logger->info( __( 'Articolo completo generato per workflow bozza.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'full_article_generated' ) );
		}

		$idea = $this->get_idea( $idea_id );
		$dry_run = $idea && ! empty( $idea->dry_run_output ) ? json_decode( (string) $idea->dry_run_output, true ) : array();
		if ( is_array( $dry_run ) && ! empty( $dry_run['full_article']['html'] ) ) {
			$builder = new Classic_Content_Builder();
			$normalized_html = $builder->normalize_full_article_html( (string) $dry_run['full_article']['html'], $dry_run );
			$validation = $builder->validate_publishable_article_html( $normalized_html );
			if ( ! empty( $validation['valid'] ) && $normalized_html !== (string) $dry_run['full_article']['html'] ) {
				$dry_run['full_article']['html'] = $normalized_html;
				$dry_run['full_article']['validation_notes'] = $validation['notes'];
				$this->save_dry_run_output( $idea_id, $dry_run, isset( $dry_run['validation_notes'] ) && is_array( $dry_run['validation_notes'] ) ? $dry_run['validation_notes'] : array() );
				$this->logger->info( __( 'Articolo completo normalizzato per workflow bozza.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'full_article_normalized', 'word_count' => (int) ( $validation['word_count'] ?? 0 ), 'h2_count' => (int) ( $validation['h2_count'] ?? 0 ) ) );
			}
		}

		$this->approve_idea( $idea_id );
		$idea = $this->get_idea( $idea_id );
		$creator = new Draft_Creator( $this->db, $this->logger );
		$post_id = $creator->create_draft_from_idea( $idea, array( 'automatic' => true, 'content_ideas' => $this ) );
		if ( is_wp_error( $post_id ) ) {
			$this->logger->warning( __( 'Creazione bozza fallita nel workflow semplice.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'draft_failed', 'error_code' => $post_id->get_error_code(), 'message' => $post_id->get_error_message() ) );
			return array( 'success' => false, 'idea_id' => $idea_id, 'post_id' => 0, 'status' => sanitize_key( (string) ( $this->get_idea( $idea_id )->status ?? 'draft_failed' ) ), 'step_failed' => 'draft', 'error_code' => $post_id->get_error_code(), 'message' => $post_id->get_error_message() );
		}

		$this->logger->info( __( 'Bozza creata nel workflow semplice.', 'wp-ai-publisher' ), array( 'source' => 'content_ideas', 'idea_id' => $idea_id, 'step' => 'draft_created', 'post_id' => absint( $post_id ) ) );
		return array( 'success' => true, 'idea_id' => $idea_id, 'post_id' => absint( $post_id ), 'status' => 'draft_created', 'message' => __( 'Bozza creata correttamente.', 'wp-ai-publisher' ) );
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
			'full_article_ready' => __( 'Articolo pronto', 'wp-ai-publisher' ),
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
		return array( 'new', 'processing', 'dry_run_ready', 'dry_run_failed', 'full_article_ready', 'approved', 'rejected', 'draft_created', 'draft_failed' );
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
		$creator = new Draft_Creator( $this->db, $this->logger );
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
		$creator = new Draft_Creator( $this->db, $this->logger );
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
			'category_ids'            => array(),
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
			'full_article' => array(),
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

		foreach ( array( 'category_ids', 'categories', 'tags' ) as $array_field ) {
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
	 * Return non-blocking quality notes for editorial review.
	 *
	 * @param array<string,mixed> $output Normalized dry-run output.
	 * @return array<int,string>
	 */
	private function get_quality_review_notes( $output ) {
		$notes  = array();
		$checks = array(
			'title'            => array( 70, __( 'Nota lieve: title supera 70 caratteri. Da revisionare.', 'wp-ai-publisher' ) ),
			'meta_title'       => array( 60, __( 'Nota lieve: meta_title supera 60 caratteri. Da revisionare.', 'wp-ai-publisher' ) ),
			'meta_description' => array( 160, __( 'Nota lieve: meta_description supera 160 caratteri. Da revisionare.', 'wp-ai-publisher' ) ),
			'slug'             => array( 75, __( 'Nota lieve: slug supera 75 caratteri. Da revisionare.', 'wp-ai-publisher' ) ),
			'excerpt'          => array( 300, __( 'Nota lieve: excerpt supera 300 caratteri. Da revisionare.', 'wp-ai-publisher' ) ),
		);

		foreach ( $checks as $field => $check ) {
			$value  = isset( $output[ $field ] ) ? (string) $output[ $field ] : '';
			$length = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
			if ( $length > $check[0] ) {
				$notes[] = $check[1];
			}
		}

		if ( isset( $output['tags'] ) && is_array( $output['tags'] ) && count( $output['tags'] ) > 12 ) {
			$notes[] = __( 'Nota lieve: sono presenti più di 12 tag. Da revisionare.', 'wp-ai-publisher' );
		}

		if ( isset( $output['categories'] ) && is_array( $output['categories'] ) && count( $output['categories'] ) > 4 ) {
			$notes[] = __( 'Nota lieve: sono presenti più di 4 categorie. Da revisionare.', 'wp-ai-publisher' );
		}

		return $notes;
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
