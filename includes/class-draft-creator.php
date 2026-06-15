<?php
/**
 * Draft creation from approved content ideas.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates safe Classic Editor WordPress drafts from approved dry-runs.
 */
class Draft_Creator {
	/**
	 * Database service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param DB     $db Database service.
	 * @param Logger $logger Logger service.
	 */
	public function __construct( DB $db, Logger $logger ) {
		$this->db     = $db;
		$this->logger = $logger;
	}

	/**
	 * Create a WordPress draft/pending post from an approved idea dry-run.
	 *
	 * @param mixed $idea Idea row object.
	 * @return int|WP_Error Created post ID or error.
	 */
	public function create_draft_from_idea( $idea, $args = array() ) {
		$args = is_array( $args ) ? $args : array();
		$automatic = ! empty( $args['automatic'] );
		if ( ! is_object( $idea ) || empty( $idea->id ) ) {
			return new WP_Error( 'wpai_draft_creator_invalid_idea', __( 'Idea non valida.', 'wp-ai-publisher' ) );
		}

		if ( ! in_array( sanitize_key( (string) ( $idea->status ?? '' ) ), array( 'approved', 'full_article_ready' ), true ) ) {
			return new WP_Error( 'wpai_draft_creator_not_approved', __( 'Non puoi creare una bozza da un dry-run non approvato.', 'wp-ai-publisher' ) );
		}
		$article_type_id = absint( $idea->article_type_id ?? 0 );
		if ( 0 === $article_type_id || ! class_exists( __NAMESPACE__ . '\Article_Types' ) || ! Article_Types::is_active_article_type( $article_type_id ) ) {
			return new WP_Error( 'wpai_draft_creator_missing_article_type', __( 'Assegna una Tipologia articolo prima di generare la bozza.', 'wp-ai-publisher' ) );
		}

		$existing_post_id = absint( $idea->draft_post_id ?? 0 );
		if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
			return $existing_post_id;
		}

		if ( empty( $idea->dry_run_output ) ) {
			$error = new WP_Error( 'wpai_draft_creator_missing_dry_run', __( 'Dry-run assente.', 'wp-ai-publisher' ) );
			$this->update_idea_after_draft_failed( (int) $idea->id, $error->get_error_message() );
			return $error;
		}

		$dry_run = json_decode( (string) $idea->dry_run_output, true );
		if ( ! is_array( $dry_run ) ) {
			$error = new WP_Error( 'wpai_draft_creator_invalid_dry_run', __( 'Dry-run non decodificabile.', 'wp-ai-publisher' ) );
			$this->update_idea_after_draft_failed( (int) $idea->id, $error->get_error_message() );
			return $error;
		}

		if ( empty( $dry_run['full_article']['html'] ) || ! is_string( $dry_run['full_article']['html'] ) ) {
			if ( $automatic && ! empty( $args['content_ideas'] ) && is_object( $args['content_ideas'] ) && method_exists( $args['content_ideas'], 'generate_full_article' ) ) {
				$generated = $args['content_ideas']->generate_full_article( (int) $idea->id );
				if ( ! is_wp_error( $generated ) ) {
					$refreshed = method_exists( $args['content_ideas'], 'get_idea' ) ? $args['content_ideas']->get_idea( (int) $idea->id ) : null;
					if ( $refreshed && ! empty( $refreshed->dry_run_output ) ) {
						$idea = $refreshed;
						$dry_run = json_decode( (string) $idea->dry_run_output, true );
					}
				}
			}
			if ( empty( $dry_run['full_article']['html'] ) || ! is_string( $dry_run['full_article']['html'] ) ) {
				$this->logger->warning( __( 'Articolo completo mancante per la bozza.', 'wp-ai-publisher' ), array( 'source' => 'draft_creator', 'idea_id' => (int) $idea->id, 'step' => 'draft_missing_full_article', 'error_code' => 'wpai_draft_creator_missing_full_article', 'message' => __( 'Genera prima l’articolo completo, poi crea la bozza.', 'wp-ai-publisher' ), 'word_count' => 0, 'h2_count' => 0 ) );
				return new WP_Error( 'wpai_draft_creator_missing_full_article', __( 'Genera prima l’articolo completo, poi crea la bozza.', 'wp-ai-publisher' ) );
			}
		}

		$article_type = isset( $dry_run['article_type'] ) && is_array( $dry_run['article_type'] ) ? $dry_run['article_type'] : Article_Types::get_article_type_config( $article_type_id );
		$dry_run['article_type'] = $article_type;
		$builder = new Classic_Content_Builder( null, $article_type );
		$original_full_article_html = (string) $dry_run['full_article']['html'];
		$dry_run['full_article']['html'] = $builder->normalize_full_article_html( $original_full_article_html, $dry_run );
		$validation = $builder->validate_publishable_article_html( (string) $dry_run['full_article']['html'] );
		if ( ! empty( $validation['valid'] ) && $original_full_article_html !== (string) $dry_run['full_article']['html'] && ! empty( $args['content_ideas'] ) && is_object( $args['content_ideas'] ) && method_exists( $args['content_ideas'], 'save_dry_run_output' ) ) {
			$dry_run['full_article']['validation_notes'] = $validation['notes'];
			$args['content_ideas']->save_dry_run_output( (int) $idea->id, $dry_run, isset( $dry_run['validation_notes'] ) && is_array( $dry_run['validation_notes'] ) ? $dry_run['validation_notes'] : array() );
		}
		if ( empty( $validation['valid'] ) ) {
			$error = new WP_Error( 'wpai_draft_creator_full_article_not_publishable', __( 'L’articolo completo è stato generato ma non è stato possibile strutturarlo in HTML per Editor Classico.', 'wp-ai-publisher' ), $validation );
			$this->logger->warning( $error->get_error_message(), array( 'source' => 'draft_creator', 'idea_id' => (int) $idea->id, 'step' => 'normalize_full_article', 'error_code' => $error->get_error_code(), 'message' => $error->get_error_message(), 'word_count' => (int) ( $validation['word_count'] ?? 0 ), 'h2_count' => (int) ( $validation['h2_count'] ?? 0 ) ) );
			$this->update_idea_after_draft_failed( (int) $idea->id, $error->get_error_message() );
			return $error;
		}

		$post_data = $this->prepare_post_data( $idea, $dry_run );
		if ( is_wp_error( $post_data ) ) {
			$this->update_idea_after_draft_failed( (int) $idea->id, $post_data->get_error_message() );
			return $post_data;
		}

		$post_id = wp_insert_post( $post_data, true );
		if ( is_wp_error( $post_id ) ) {
			$this->update_idea_after_draft_failed( (int) $idea->id, $post_id->get_error_message() );
			return $post_id;
		}

		$post_id = absint( $post_id );
		$this->assign_categories_and_tags( $post_id, $dry_run );
		$this->update_idea_after_draft_created( (int) $idea->id, $post_id, (string) $post_data['post_status'] );
		$this->logger->info( __( 'Bozza creata da full_article.', 'wp-ai-publisher' ), array( 'source' => 'draft_creator', 'idea_id' => (int) $idea->id, 'step' => 'draft_created', 'error_code' => '', 'message' => __( 'Bozza creata correttamente.', 'wp-ai-publisher' ), 'word_count' => (int) ( $validation['word_count'] ?? 0 ), 'h2_count' => (int) ( $validation['h2_count'] ?? 0 ) ) );

		return $post_id;
	}

	/**
	 * Get safe target post status for version 0.5.0.
	 *
	 * @return string
	 */
	public function get_target_post_status() {
		$settings = wpai_publisher_get_settings();
		$context  = wpai_publisher_normalize_site_context( $settings['site_context'] ?? array() );
		$status   = sanitize_key( (string) ( $context['default_post_status_after_generation'] ?? 'draft' ) );

		if ( ! in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ) {
			$status = 'draft';
		}

		$status = apply_filters( 'wpai_publisher_draft_creator_target_status', $status, $settings );
		$status = sanitize_key( (string) $status );
		if ( ! in_array( $status, array( 'draft', 'pending', 'publish' ), true ) ) {
			$status = 'draft';
		}

		if ( 'publish' === $status && ( ! defined( 'WPAIP_ALLOW_DIRECT_PUBLISH' ) || true !== WPAIP_ALLOW_DIRECT_PUBLISH ) ) {
			$this->logger->warning( __( 'Pubblicazione automatica richiesta ma bloccata in 0.5.0: uso draft.', 'wp-ai-publisher' ), array( 'source' => 'draft_creator' ) );
			$status = 'draft';
		}

		return $status;
	}

	/**
	 * Sanitize Classic Editor HTML with a strict allowlist.
	 *
	 * @param string $html Raw HTML.
	 * @return string|WP_Error Sanitized HTML or error.
	 */
	public function sanitize_post_content( $html, $article_type = array() ) {
		$html = (string) $html;
		if ( $this->contains_gutenberg_blocks( $html ) ) {
			return new WP_Error( 'wpai_draft_creator_gutenberg_blocks', __( 'Il contenuto contiene blocchi Gutenberg non consentiti.', 'wp-ai-publisher' ) );
		}

		$allowed_html = array(
			'p'          => array(),
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'a'          => array(
				'href'   => true,
				'title'  => true,
				'target' => true,
				'rel'    => true,
			),
			'blockquote' => array(),
			'code'       => array(),
			'pre'        => array(),
			'br'         => array(),
		);

		$sanitized = wp_kses( $html, $allowed_html );
		$builder = new Classic_Content_Builder( null, is_array( $article_type ) ? $article_type : array() );
		$validation = $builder->validate_publishable_article_html( $sanitized );
		if ( empty( $validation['valid'] ) ) {
			return new WP_Error( 'wpai_draft_creator_unpublishable_content', __( 'Il contenuto completo non è pubblicabile come bozza Classic Editor.', 'wp-ai-publisher' ), $validation );
		}
		if ( '' === trim( wp_strip_all_tags( $sanitized ) ) ) {
			return new WP_Error( 'wpai_draft_creator_empty_content', __( 'Il contenuto è vuoto dopo la sanitizzazione.', 'wp-ai-publisher' ) );
		}

		return $sanitized;
	}

	/**
	 * Prepare wp_insert_post data.
	 *
	 * @param object              $idea Idea row.
	 * @param array<string,mixed> $dry_run Dry-run data.
	 * @return array<string,mixed>|WP_Error
	 */
	public function prepare_post_data( $idea, $dry_run ) {
		$article_type = isset( $dry_run['article_type'] ) && is_array( $dry_run['article_type'] ) ? $dry_run['article_type'] : array();
		$builder = new Classic_Content_Builder( null, $article_type );
		$html = $builder->normalize_full_article_html( (string) ( $dry_run['full_article']['html'] ?? '' ), $dry_run );
		$content = $this->sanitize_post_content( $html, $article_type );
		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$title = sanitize_text_field( (string) ( $dry_run['title'] ?? '' ) );
		if ( '' === $title ) {
			$title = wp_trim_words( wp_strip_all_tags( (string) ( $idea->topic ?? '' ) ), 12, '' );
		}
		if ( '' === $title ) {
			return new WP_Error( 'wpai_draft_creator_missing_title', __( 'Titolo mancante.', 'wp-ai-publisher' ) );
		}

		$author_id = get_current_user_id();
		if ( $author_id <= 0 ) {
			$author_id = 1;
		}

		return array(
			'post_title'   => $title,
			'post_name'    => sanitize_title( (string) ( $dry_run['slug'] ?? '' ) ),
			'post_excerpt' => sanitize_textarea_field( (string) ( $dry_run['excerpt'] ?? '' ) ),
			'post_content' => $content,
			'post_status'  => $this->get_target_post_status(),
			'post_type'    => 'post',
			'post_author'  => absint( $author_id ),
		);
	}

	/**
	 * Create or get term IDs for a taxonomy.
	 *
	 * @param string           $taxonomy Taxonomy name.
	 * @param array<int,mixed> $terms Terms.
	 * @return array<int,int>
	 */
	public function create_or_get_terms( $taxonomy, $terms ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		$ids      = array();
		if ( ! taxonomy_exists( $taxonomy ) ) {
			return $ids;
		}

		foreach ( (array) $terms as $term ) {
			$name = sanitize_text_field( is_scalar( $term ) ? (string) $term : '' );
			if ( '' === trim( $name ) ) {
				continue;
			}
			$existing = term_exists( $name, $taxonomy );
			if ( is_array( $existing ) && ! empty( $existing['term_id'] ) ) {
				$ids[] = absint( $existing['term_id'] );
				continue;
			}
			$created = wp_insert_term( $name, $taxonomy );
			if ( is_wp_error( $created ) ) {
				$this->logger->warning( __( 'Creazione termine non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'draft_creator', 'taxonomy' => $taxonomy, 'term' => $name, 'error' => $created->get_error_message() ) );
				continue;
			}
			$ids[] = absint( $created['term_id'] );
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Assign categories and tags suggested by dry-run.
	 *
	 * @param int                 $post_id Post ID.
	 * @param array<string,mixed> $dry_run Dry-run data.
	 * @return void
	 */
	public function assign_categories_and_tags( $post_id, $dry_run ) {
		$post_id = absint( $post_id );
		if ( 0 === $post_id ) { return; }

		$article_type = isset( $dry_run['article_type'] ) && is_array( $dry_run['article_type'] ) ? $dry_run['article_type'] : array();
		$allowed_category_ids = array_values( array_filter( array_map( 'absint', (array) ( $article_type['allowed_category_ids'] ?? array() ) ) ) );
		$existing_category_ids = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids' ) );
		$existing_category_ids = is_wp_error( $existing_category_ids ) ? array() : array_map( 'absint', (array) $existing_category_ids );
		$requested_ids = array_values( array_filter( array_map( 'absint', (array) ( $dry_run['category_ids'] ?? array() ) ) ) );

		if ( empty( $requested_ids ) && ! empty( $dry_run['categories'] ) ) {
			foreach ( (array) $dry_run['categories'] as $category_name ) {
				$term = term_exists( sanitize_text_field( (string) $category_name ), 'category' );
				if ( is_array( $term ) && ! empty( $term['term_id'] ) ) { $requested_ids[] = absint( $term['term_id'] ); }
			}
		}

		$category_ids = array_values( array_intersect( $requested_ids, $existing_category_ids ) );
		if ( empty( $allowed_category_ids ) ) {
			$this->logger->info( __( 'Nessuna categoria consentita configurata nella tipologia articolo.', 'wp-ai-publisher' ), array( 'source' => 'draft_creator', 'post_id' => $post_id ) );
			$category_ids = array();
		} else {
			$category_ids = array_values( array_intersect( $category_ids, $allowed_category_ids ) );
		}
		if ( ! empty( $category_ids ) ) { wp_set_post_categories( $post_id, $category_ids, false ); }

		$tags = array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $dry_run['tags'] ?? array() ) ) ) );
		$preferred = wpai_publisher_split_context_list( (string) ( $article_type['preferred_tags'] ?? '' ) );
		$tags = array_slice( array_values( array_unique( array_merge( $preferred, $tags ) ) ), 0, 12 );
		$tag_ids = $this->create_or_get_terms( 'post_tag', $tags );
		if ( ! empty( $tag_ids ) ) { wp_set_post_terms( $post_id, $tag_ids, 'post_tag', false ); }
	}

	/**
	 * Update idea after draft creation.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param int    $post_id Post ID.
	 * @param string $status Post status.
	 * @return bool
	 */
	public function update_idea_after_draft_created( $idea_id, $post_id, $status ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->db->get_content_ideas_table_name(),
			array(
				'status'           => 'draft_created',
				'updated_at'       => current_time( 'mysql' ),
				'draft_created_at' => current_time( 'mysql' ),
				'draft_post_id'    => absint( $post_id ),
				'draft_status'     => sanitize_key( (string) $status ),
				'draft_error'      => null,
			),
			array( 'id' => absint( $idea_id ) ),
			array( '%s', '%s', '%s', '%d', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Update idea after draft creation failure.
	 *
	 * @param int    $idea_id Idea ID.
	 * @param string $error_message Error message.
	 * @return bool
	 */
	public function update_idea_after_draft_failed( $idea_id, $error_message ) {
		global $wpdb;

		return false !== $wpdb->update(
			$this->db->get_content_ideas_table_name(),
			array(
				'status'      => 'draft_failed',
				'updated_at'  => current_time( 'mysql' ),
				'draft_error' => sanitize_textarea_field( (string) $error_message ),
			),
			array( 'id' => absint( $idea_id ) ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Detect Gutenberg block markers.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	private function contains_gutenberg_blocks( $html ) {
		return false !== stripos( $html, '<!-- wp:' ) || false !== stripos( $html, 'wp-block' );
	}
}
