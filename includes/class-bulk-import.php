<?php
/**
 * Bulk import of content ideas from a CSV file.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports content ideas in bulk; every imported idea is forced into scheduling.
 */
class Bulk_Import {

	const PAGE          = 'wp-ai-publisher-import-ideas';
	const NOTIFY_OPTION = 'wpai_publisher_bulk_import_notify';

	/** Per-user transient prefix holding the parsed file awaiting confirmation. */
	const PENDING_PREFIX = 'wpai_publisher_import_pending_';

	/** Maximum number of data rows handled per batch (preview + import). */
	const MAX_ROWS = 2000;

	/**
	 * DB service.
	 *
	 * @var DB
	 */
	private $db;

	/**
	 * Content ideas service.
	 *
	 * @var Content_Ideas
	 */
	private $content_ideas;

	/**
	 * Logger service.
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @param DB            $db DB service.
	 * @param Content_Ideas $content_ideas Content ideas service.
	 * @param Logger        $logger Logger service.
	 */
	public function __construct( DB $db, Content_Ideas $content_ideas, Logger $logger ) {
		$this->db            = $db;
		$this->content_ideas = $content_ideas;
		$this->logger        = $logger;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'register_page' ), 11 );
		add_action( 'admin_post_wpai_publisher_preview_ideas', array( $this, 'handle_preview' ) );
		add_action( 'admin_post_wpai_publisher_import_ideas', array( $this, 'handle_import' ) );
		add_action( 'admin_post_wpai_publisher_cancel_import', array( $this, 'handle_cancel' ) );
		add_action( 'admin_post_wpai_publisher_download_idea_sample', array( $this, 'handle_download_sample' ) );
	}

	/**
	 * Register the import page as a hidden admin page.
	 *
	 * Registered under a null parent so it is reachable by URL (opened from the
	 * button on the ideas page) but does not appear in the menu. Using
	 * remove_submenu_page() instead would strip it from the access whitelist and
	 * trigger "Sorry, you are not allowed to access this page".
	 *
	 * @return void
	 */
	public function register_page() {
		add_submenu_page(
			'',
			esc_html__( 'Importa idee', 'wp-ai-publisher' ),
			esc_html__( 'Importa idee', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			self::PAGE,
			array( $this, 'render_page' )
		);
	}

	/**
	 * URL of the import page.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'admin.php?page=' . self::PAGE );
	}

	/* ---------------------------------------------------------------------
	 * Sample CSV
	 * ------------------------------------------------------------------- */

	/**
	 * Stream a sample CSV file.
	 *
	 * @return void
	 */
	public function handle_download_sample() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_download_idea_sample' );

		$example_type = '';
		if ( wpai_publisher_article_types_enabled() ) {
			$types = wpai_publisher_get_active_article_types_safe();
			$example_type = ! empty( $types ) ? (string) $types[0]['name'] : __( 'Guida pratica', 'wp-ai-publisher' );
		}

		// Sample dates in the site timezone (that is how the importer reads them).
		$now      = (int) current_time( 'timestamp' );
		$tomorrow = gmdate( 'Y-m-d H:i', $now + DAY_IN_SECONDS );
		$after    = gmdate( 'Y-m-d H:i', $now + 2 * DAY_IN_SECONDS );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="esempio-idee.csv"' );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM so Excel opens accented characters correctly.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv(
			$out,
			array(
				'Argomento principale (Titolo dell\'articolo)',
				'Lingua (IT)',
				'Tipologia articolo',
				'Programma creazione',
				'Categorie | Sottocategorie',
				'Prompt dell\'immagine da inserire',
				'Prompt Social Facebook',
				'Prompt Social Instagram',
				'Prompt Social LinkedIn',
			)
		);
		fputcsv(
			$out,
			array(
				'Come scegliere una bici da città',
				'it',
				$example_type,
				$tomorrow,
				'GUIDE | Bici da città; Mobilità urbana',
				'Foto editoriale di una bici da città parcheggiata, luce naturale, senza testo.',
				'Scrivi un post Facebook coinvolgente collegato all\'articolo, 2-3 frasi, con invito alla lettura.',
				'Scrivi una caption Instagram breve con 3-5 hashtag pertinenti.',
				'Scrivi un post LinkedIn professionale collegato all\'articolo.',
			)
		);
		fputcsv(
			$out,
			array(
				'Best lightweight laptops for travel',
				'en',
				$example_type,
				$after,
				'TECH | Laptop; Travel gear',
				'Editorial photo of a lightweight laptop on a desk, soft light, no text.',
				'',
				'',
				'',
			)
		);
		fclose( $out );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Page rendering
	 * ------------------------------------------------------------------- */

	/**
	 * Render the import page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$feedback = get_transient( 'wpai_publisher_import_feedback_' . get_current_user_id() );
		if ( $feedback ) {
			delete_transient( 'wpai_publisher_import_feedback_' . get_current_user_id() );
		}

		// A pending preview (file uploaded, awaiting confirmation) takes over the
		// page until the user confirms or cancels it.
		$preview = null;
		$pending = get_transient( $this->pending_key() );
		if ( is_array( $pending ) && ! empty( $pending['report'] ) ) {
			$preview = $pending['report'];
		}

		$article_types_enabled = wpai_publisher_article_types_enabled();
		$ideas_url             = admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' );
		require WPAIP_PLUGIN_DIR . 'admin/views/import-ideas.php';
	}

	/**
	 * Per-user transient key holding the file awaiting confirmation.
	 *
	 * @return string
	 */
	private function pending_key() {
		return self::PENDING_PREFIX . get_current_user_id();
	}

	/* ---------------------------------------------------------------------
	 * Import handler
	 * ------------------------------------------------------------------- */

	/**
	 * Step 1: parse the uploaded CSV, build a preview report and stash the
	 * parsed rows for confirmation. Nothing is written yet.
	 *
	 * @return void
	 */
	public function handle_preview() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_preview_ideas' );

		$feedback = array( 'created' => 0, 'skipped' => 0, 'errors' => array(), 'total' => 0 );

		if ( empty( $_FILES['wpai_csv']['tmp_name'] ) || ! is_uploaded_file( $_FILES['wpai_csv']['tmp_name'] ) ) {
			$feedback['errors'][] = __( 'Nessun file caricato.', 'wp-ai-publisher' );
			$this->store_feedback_and_redirect( $feedback );
		}

		$tmp  = sanitize_text_field( wp_unslash( $_FILES['wpai_csv']['tmp_name'] ) );
		$rows = $this->parse_csv( $tmp );
		if ( is_wp_error( $rows ) ) {
			$feedback['errors'][] = $rows->get_error_message();
			$this->store_feedback_and_redirect( $feedback );
		}

		$truncated = false;
		if ( count( $rows ) > self::MAX_ROWS ) {
			$rows      = array_slice( $rows, 0, self::MAX_ROWS );
			$truncated = true;
		}

		$create_terms = ! empty( $_POST['wpai_create_terms'] );

		$report                 = $this->analyze_rows( $rows, $create_terms );
		$report['truncated']    = $truncated;
		$report['create_terms'] = $create_terms;
		$report['filename']     = isset( $_FILES['wpai_csv']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['wpai_csv']['name'] ) ) : '';

		set_transient(
			$this->pending_key(),
			array(
				'rows'         => $rows,
				'create_terms' => $create_terms,
				'report'       => $report,
			),
			30 * MINUTE_IN_SECONDS
		);

		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Step 2 (confirm): import the previously parsed rows, in file order, up to
	 * the requested quantity. Categories and ideas are created here.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_import_ideas' );

		$feedback = array( 'created' => 0, 'skipped' => 0, 'errors' => array(), 'total' => 0, 'limit' => 0, 'file_total' => 0 );

		$pending = get_transient( $this->pending_key() );
		if ( ! is_array( $pending ) || empty( $pending['rows'] ) ) {
			$feedback['errors'][] = __( 'Sessione di importazione scaduta o non trovata. Ricarica il file ed esegui di nuovo l’anteprima.', 'wp-ai-publisher' );
			$this->store_feedback_and_redirect( $feedback );
		}

		$rows                  = $pending['rows'];
		$create_terms          = ! empty( $pending['create_terms'] );
		$feedback['file_total'] = count( $rows );

		// Quantity to import, following file order. Empty / "all" => everything.
		$import_all = ! empty( $_POST['wpai_import_all'] );
		$limit      = isset( $_POST['wpai_limit'] ) ? absint( wp_unslash( $_POST['wpai_limit'] ) ) : 0;
		if ( $import_all || $limit < 1 ) {
			$limit = count( $rows );
		}
		$limit            = min( $limit, count( $rows ) );
		$feedback['limit'] = $limit;

		$type_map  = $this->article_type_name_map();
		$line      = 1; // header is line 1.
		$processed = 0;

		foreach ( $rows as $row ) {
			$line++;
			if ( $processed >= $limit ) {
				break;
			}
			$processed++;
			$feedback['total']++;

			$topic    = trim( (string) ( $row['topic'] ?? '' ) );
			$language = $this->normalize_language( (string) ( $row['language'] ?? '' ) );
			$schedule = trim( (string) ( $row['schedule'] ?? '' ) );
			$type_raw = trim( (string) ( $row['type'] ?? '' ) );
			$cats_raw = trim( (string) ( $row['categories'] ?? '' ) );

			if ( '' === $topic ) {
				$feedback['skipped']++;
				$feedback['errors'][] = sprintf( __( 'Riga %d: argomento principale mancante.', 'wp-ai-publisher' ), $line );
				continue;
			}
			if ( '' === $schedule || '' === $this->parse_datetime( $schedule ) ) {
				$feedback['skipped']++;
				$feedback['errors'][] = sprintf( __( 'Riga %d: data/ora di programmazione mancante o non valida.', 'wp-ai-publisher' ), $line );
				continue;
			}

			$article_type_id = 0;
			if ( wpai_publisher_article_types_enabled() ) {
				$key = $this->normalize_key( $type_raw );
				if ( '' === $key || ! isset( $type_map[ $key ] ) ) {
					$feedback['skipped']++;
					$feedback['errors'][] = sprintf( __( 'Riga %1$d: Tipologia articolo "%2$s" non trovata o non attiva.', 'wp-ai-publisher' ), $line, $type_raw );
					continue;
				}
				$article_type_id = (int) $type_map[ $key ];
			}

			// Resolve categories. Supports the 2-level format
			// "PRIMARIA | sub1; sub2" (creating the hierarchy when allowed) and
			// the legacy comma-separated list of existing names.
			$category_ids = array();
			if ( '' !== $cats_raw ) {
				$resolved     = $this->resolve_categories( $cats_raw, $create_terms );
				$category_ids = $resolved['ids'];
				if ( ! empty( $resolved['unknown'] ) ) {
					$feedback['errors'][] = sprintf(
						/* translators: 1: line, 2: category names */
						__( 'Riga %1$d: categorie ignorate (inesistenti, attiva “Crea categorie mancanti”): %2$s', 'wp-ai-publisher' ),
						$line,
						implode( ', ', $resolved['unknown'] )
					);
				}
			}

			$idea_id = $this->content_ideas->create_idea_programmatic(
				array(
					'topic'            => $topic,
					'language'         => $language,
					'article_type_id'  => $article_type_id,
					'category_ids'     => $category_ids,
					'image_prompt'     => trim( (string) ( $row['image'] ?? '' ) ),
					'social_facebook'  => trim( (string) ( $row['facebook'] ?? '' ) ),
					'social_instagram' => trim( (string) ( $row['instagram'] ?? '' ) ),
					'social_linkedin'  => trim( (string) ( $row['linkedin'] ?? '' ) ),
					'scheduled_at'     => $this->parse_datetime( $schedule ),
					'notes'            => __( 'Importazione massiva CSV', 'wp-ai-publisher' ),
				)
			);

			if ( is_wp_error( $idea_id ) ) {
				$feedback['skipped']++;
				$feedback['errors'][] = sprintf( __( 'Riga %1$d: %2$s', 'wp-ai-publisher' ), $line, $idea_id->get_error_message() );
				continue;
			}

			$this->flag_for_notify( (int) $idea_id );
			$feedback['created']++;
		}

		delete_transient( $this->pending_key() );

		$this->logger->info(
			__( 'Importazione massiva idee completata.', 'wp-ai-publisher' ),
			array( 'source' => 'bulk_import', 'event' => 'import_done', 'created' => $feedback['created'], 'skipped' => $feedback['skipped'] )
		);

		$this->store_feedback_and_redirect( $feedback );
	}

	/**
	 * Discard a pending preview without importing.
	 *
	 * @return void
	 */
	public function handle_cancel() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_cancel_import' );
		delete_transient( $this->pending_key() );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Build a non-destructive preview report from parsed rows.
	 *
	 * Validates each row exactly as the importer will, but creates nothing:
	 * category existence is probed read-only. Returns aggregate counts, the
	 * scheduling window, per-type / per-language breakdowns, the categories that
	 * would be created vs left missing, duplicate topics and row-level errors.
	 *
	 * @param array<int,array<string,string>> $rows         Parsed rows.
	 * @param bool                             $create_terms Whether missing categories may be created on import.
	 * @return array<string,mixed>
	 */
	private function analyze_rows( $rows, $create_terms ) {
		$type_map      = $this->article_type_name_map();
		$types_enabled = wpai_publisher_article_types_enabled();

		$report = array(
			'total'               => 0,
			'valid'               => 0,
			'invalid'             => 0,
			'errors'              => array(),
			'warnings'            => array(),
			'schedule_min'        => '',
			'schedule_max'        => '',
			'by_type'             => array(),
			'by_language'         => array(),
			'categories_create'   => array(),
			'categories_missing'  => array(),
			'duplicates'          => array(),
			'rows'                => array(),
		);

		$seen_topics = array();
		$line        = 1; // header is line 1.

		foreach ( $rows as $row ) {
			$line++;
			$report['total']++;

			$topic        = trim( (string) ( $row['topic'] ?? '' ) );
			$language     = $this->normalize_language( (string) ( $row['language'] ?? '' ) );
			$schedule_raw = trim( (string) ( $row['schedule'] ?? '' ) );
			$schedule     = $this->parse_datetime( $schedule_raw );
			$type_raw     = trim( (string) ( $row['type'] ?? '' ) );
			$cats_raw     = trim( (string) ( $row['categories'] ?? '' ) );

			$row_errors = array();

			if ( '' === $topic ) {
				$row_errors[] = __( 'argomento principale mancante', 'wp-ai-publisher' );
			}
			if ( '' === $schedule_raw ) {
				$row_errors[] = __( 'data/ora di programmazione mancante', 'wp-ai-publisher' );
			} elseif ( '' === $schedule ) {
				$row_errors[] = __( 'data/ora di programmazione non valida', 'wp-ai-publisher' );
			}

			$type_name = '';
			if ( $types_enabled ) {
				$key = $this->normalize_key( $type_raw );
				if ( '' === $key || ! isset( $type_map[ $key ] ) ) {
					$row_errors[] = sprintf( __( 'Tipologia articolo "%s" non trovata o non attiva', 'wp-ai-publisher' ), $type_raw );
				} else {
					$type_name = $type_raw;
				}
			}

			// Read-only category resolution preview.
			if ( '' !== $cats_raw ) {
				$cat_preview = $this->preview_categories( $cats_raw, $create_terms );
				foreach ( $cat_preview['to_create'] as $name ) {
					$report['categories_create'][] = $name;
				}
				foreach ( $cat_preview['unknown'] as $name ) {
					$report['categories_missing'][] = $name;
					$report['warnings'][]           = sprintf(
						/* translators: 1: line, 2: category name */
						__( 'Riga %1$d: categoria "%2$s" non trovata e non verrà creata (attiva “Crea categorie mancanti”).', 'wp-ai-publisher' ),
						$line,
						$name
					);
				}
			}

			$is_valid = empty( $row_errors );
			if ( $is_valid ) {
				$report['valid']++;
				if ( '' !== $schedule ) {
					if ( '' === $report['schedule_min'] || $schedule < $report['schedule_min'] ) {
						$report['schedule_min'] = $schedule;
					}
					if ( '' === $report['schedule_max'] || $schedule > $report['schedule_max'] ) {
						$report['schedule_max'] = $schedule;
					}
				}
				$type_label                          = $types_enabled ? ( '' !== $type_name ? $type_name : __( '(predefinita)', 'wp-ai-publisher' ) ) : __( '(tipologie non attive)', 'wp-ai-publisher' );
				$report['by_type'][ $type_label ]    = ( $report['by_type'][ $type_label ] ?? 0 ) + 1;
				$report['by_language'][ $language ]  = ( $report['by_language'][ $language ] ?? 0 ) + 1;
			} else {
				$report['invalid']++;
				foreach ( $row_errors as $err ) {
					$report['errors'][] = sprintf( __( 'Riga %1$d: %2$s.', 'wp-ai-publisher' ), $line, $err );
				}
			}

			// Duplicate topic detection (within the file).
			if ( '' !== $topic ) {
				$tkey = $this->normalize_key( $topic );
				if ( isset( $seen_topics[ $tkey ] ) ) {
					$report['duplicates'][ $topic ] = ( $report['duplicates'][ $topic ] ?? 1 ) + 1;
				} else {
					$seen_topics[ $tkey ] = true;
				}
			}

			// Light per-row summary for the on-screen table (capped at display).
			if ( count( $report['rows'] ) < 200 ) {
				$report['rows'][] = array(
					'line'     => $line,
					'topic'    => $topic,
					'language' => $language,
					'type'     => $type_raw,
					'schedule' => $schedule,
					'valid'    => $is_valid,
					'errors'   => $row_errors,
				);
			}
		}

		$report['categories_create']  = array_values( array_unique( $report['categories_create'] ) );
		$report['categories_missing'] = array_values( array_unique( $report['categories_missing'] ) );

		// Keep the stored report bounded.
		foreach ( array( 'errors', 'warnings' ) as $bucket ) {
			if ( count( $report[ $bucket ] ) > 100 ) {
				$report[ $bucket ]   = array_slice( $report[ $bucket ], 0, 100 );
				$report[ $bucket ][] = __( '… altri non mostrati.', 'wp-ai-publisher' );
			}
		}

		return $report;
	}

	/**
	 * Read-only preview of how a "Categorie | Sottocategorie" cell resolves,
	 * without creating any term.
	 *
	 * @param string $raw    Raw cell value.
	 * @param bool   $create Whether missing categories may be created on import.
	 * @return array{existing:array<int,string>,to_create:array<int,string>,unknown:array<int,string>}
	 */
	private function preview_categories( $raw, $create ) {
		$raw   = (string) $raw;
		$names = array();

		if ( false === strpos( $raw, '|' ) ) {
			foreach ( explode( ',', $raw ) as $name ) {
				$name = trim( wp_strip_all_tags( $name ) );
				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		} else {
			$parts   = explode( '|', $raw, 2 );
			$primary = trim( wp_strip_all_tags( (string) ( $parts[0] ?? '' ) ) );
			if ( '' !== $primary ) {
				$names[] = $primary;
			}
			foreach ( preg_split( '/[;,]/', (string) ( $parts[1] ?? '' ) ) as $sub ) {
				$sub = trim( wp_strip_all_tags( (string) $sub ) );
				if ( '' !== $sub ) {
					$names[] = $sub;
				}
			}
		}

		$existing   = array();
		$to_create  = array();
		$unknown    = array();
		$can_create = $create && current_user_can( 'manage_categories' );

		foreach ( $names as $name ) {
			$term = get_term_by( 'name', $name, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$existing[] = $name;
			} elseif ( $can_create ) {
				$to_create[] = $name;
			} else {
				$unknown[] = $name;
			}
		}

		return array(
			'existing'  => array_values( array_unique( $existing ) ),
			'to_create' => array_values( array_unique( $to_create ) ),
			'unknown'   => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * Persist feedback and redirect back to the import page.
	 *
	 * @param array<string,mixed> $feedback Feedback data.
	 * @return void
	 */
	private function store_feedback_and_redirect( $feedback ) {
		// Cap the error list so the transient stays small.
		if ( count( $feedback['errors'] ) > 100 ) {
			$feedback['errors'] = array_slice( $feedback['errors'], 0, 100 );
			$feedback['errors'][] = __( '… altri errori non mostrati.', 'wp-ai-publisher' );
		}
		set_transient( 'wpai_publisher_import_feedback_' . get_current_user_id(), $feedback, 120 );
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/* ---------------------------------------------------------------------
	 * CSV parsing helpers
	 * ------------------------------------------------------------------- */

	/**
	 * Parse the uploaded CSV into an array of rows keyed by field.
	 *
	 * @param string $path Temp file path.
	 * @return array<int,array<string,string>>|\WP_Error
	 */
	private function parse_csv( $path ) {
		$handle = fopen( $path, 'r' );
		if ( false === $handle ) {
			return new \WP_Error( 'wpai_csv_unreadable', __( 'Impossibile leggere il file CSV.', 'wp-ai-publisher' ) );
		}

		$first = fgets( $handle );
		if ( false === $first ) {
			fclose( $handle );
			return new \WP_Error( 'wpai_csv_empty', __( 'Il file CSV è vuoto.', 'wp-ai-publisher' ) );
		}
		// Strip UTF-8 BOM and detect the delimiter (comma or semicolon).
		$first     = preg_replace( '/^\xEF\xBB\xBF/', '', $first );
		$delimiter = ( substr_count( $first, ';' ) > substr_count( $first, ',' ) ) ? ';' : ',';

		$header = str_getcsv( trim( $first ), $delimiter );
		$cols   = $this->map_header_columns( $header );

		$rows = array();
		while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
			if ( 1 === count( $data ) && '' === trim( (string) $data[0] ) ) {
				continue; // blank line.
			}
			$rows[] = array(
				'topic'        => (string) ( $data[ $cols['topic'] ] ?? '' ),
				'language'     => (string) ( $data[ $cols['language'] ] ?? '' ),
				'type'         => (string) ( $data[ $cols['type'] ] ?? '' ),
				'schedule'     => (string) ( $data[ $cols['schedule'] ] ?? '' ),
				'categories'   => (string) ( $data[ $cols['categories'] ] ?? '' ),
				'image'        => isset( $cols['image'] ) ? (string) ( $data[ $cols['image'] ] ?? '' ) : '',
				'facebook'     => isset( $cols['facebook'] ) ? (string) ( $data[ $cols['facebook'] ] ?? '' ) : '',
				'instagram'    => isset( $cols['instagram'] ) ? (string) ( $data[ $cols['instagram'] ] ?? '' ) : '',
				'linkedin'     => isset( $cols['linkedin'] ) ? (string) ( $data[ $cols['linkedin'] ] ?? '' ) : '',
			);
		}
		fclose( $handle );

		if ( empty( $rows ) ) {
			return new \WP_Error( 'wpai_csv_no_rows', __( 'Il CSV non contiene righe di dati.', 'wp-ai-publisher' ) );
		}
		return $rows;
	}

	/**
	 * Map header names to column indexes, falling back to fixed positions.
	 *
	 * @param array<int,string> $header Header cells.
	 * @return array<string,int>
	 */
	private function map_header_columns( $header ) {
		$cols = array( 'topic' => 0, 'language' => 1, 'type' => 2, 'schedule' => 3, 'categories' => 4 );
		foreach ( (array) $header as $index => $name ) {
			$norm = $this->normalize_key( $name );
			if ( false !== strpos( $norm, 'argomento' ) || false !== strpos( $norm, 'titolo' ) ) {
				$cols['topic'] = $index;
			} elseif ( false !== strpos( $norm, 'lingua' ) ) {
				$cols['language'] = $index;
			} elseif ( false !== strpos( $norm, 'tipolog' ) ) {
				$cols['type'] = $index;
			} elseif ( false !== strpos( $norm, 'categor' ) || false !== strpos( $norm, 'sottocategor' ) ) {
				$cols['categories'] = $index;
			} elseif ( false !== strpos( $norm, 'immagine' ) || false !== strpos( $norm, 'image' ) ) {
				$cols['image'] = $index;
			} elseif ( false !== strpos( $norm, 'facebook' ) ) {
				$cols['facebook'] = $index;
			} elseif ( false !== strpos( $norm, 'instagram' ) ) {
				$cols['instagram'] = $index;
			} elseif ( false !== strpos( $norm, 'linkedin' ) ) {
				$cols['linkedin'] = $index;
			} elseif ( false !== strpos( $norm, 'programma' ) || false !== strpos( $norm, 'data' ) ) {
				$cols['schedule'] = $index;
			}
		}
		return $cols;
	}

	/**
	 * Normalize a string for comparison (lowercase, trimmed).
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private function normalize_key( $value ) {
		return strtolower( trim( wp_strip_all_tags( (string) $value ) ) );
	}

	/**
	 * Map a CSV language value (code or label) to an allowed language code.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private function normalize_language( $value ) {
		$value = $this->normalize_key( $value );
		$map   = array(
			'it' => 'it', 'italiano' => 'it', 'italian' => 'it',
			'en' => 'en', 'inglese' => 'en', 'english' => 'en',
			'fr' => 'fr', 'francese' => 'fr', 'french' => 'fr', 'français' => 'fr',
			'es' => 'es', 'spagnolo' => 'es', 'spanish' => 'es', 'español' => 'es',
			'de' => 'de', 'tedesco' => 'de', 'german' => 'de', 'deutsch' => 'de',
		);
		return $map[ $value ] ?? 'it';
	}

	/**
	 * Validate/normalize a date-time string to a canonical wall-clock
	 * "Y-m-d H:i:s" (local time). The local→UTC conversion (using the WordPress
	 * timezone) is performed later by Content_Ideas::normalize_scheduled_at().
	 *
	 * @param string $value Raw value.
	 * @return string Canonical local datetime, or '' if invalid.
	 */
	private function parse_datetime( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Try common explicit formats first (incl. Italian d/m/Y).
		foreach ( array( 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i', 'd/m/Y H:i', 'd/m/Y H:i:s', 'd-m-Y H:i' ) as $format ) {
			$dt = \DateTime::createFromFormat( $format, $value );
			if ( $dt instanceof \DateTime ) {
				return $dt->format( 'Y-m-d H:i:00' );
			}
		}
		$ts = strtotime( str_replace( 'T', ' ', $value ) );
		return false !== $ts ? gmdate( 'Y-m-d H:i:00', $ts ) : '';
	}

	/**
	 * Build a map of active article-type names to IDs.
	 *
	 * @return array<string,int>
	 */
	private function article_type_name_map() {
		$map = array();
		if ( ! wpai_publisher_article_types_enabled() ) {
			return $map;
		}
		foreach ( wpai_publisher_get_active_article_types_safe() as $type ) {
			$name = $this->normalize_key( (string) ( $type['name'] ?? '' ) );
			if ( '' !== $name ) {
				$map[ $name ] = (int) $type['id'];
			}
		}
		return $map;
	}

	/* ---------------------------------------------------------------------
	 * Telegram notify flag
	 * ------------------------------------------------------------------- */

	/**
	 * Flag an idea so a Telegram message is sent when its draft is created.
	 *
	 * @param int $idea_id Idea ID.
	 * @return void
	 */
	/**
	 * Resolve a comma-separated list of category names to existing category IDs.
	 *
	 * @param string $raw Comma-separated names.
	 * @return array{ids:array<int,int>,unknown:array<int,string>}
	 */
	private function resolve_category_names( $raw, $create = false ) {
		$ids     = array();
		$unknown = array();
		foreach ( explode( ',', (string) $raw ) as $name ) {
			$name = trim( wp_strip_all_tags( $name ) );
			if ( '' === $name ) {
				continue;
			}
			$id = $this->get_or_create_category( $name, 0, $create );
			if ( $id > 0 ) {
				$ids[] = $id;
			} else {
				$unknown[] = $name;
			}
		}
		return array(
			'ids'     => array_values( array_unique( $ids ) ),
			'unknown' => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * Resolve a "Categorie | Sottocategorie" cell.
	 *
	 * Format: "PRIMARIA | sub1; sub2" — the primary category is created (when
	 * allowed) as a top-level term and each subcategory as its child. Without the
	 * "|" separator the value is treated as a legacy comma-separated list.
	 *
	 * @param string $raw    Raw cell value.
	 * @param bool   $create Whether missing categories may be created.
	 * @return array{ids:array<int,int>,unknown:array<int,string>}
	 */
	private function resolve_categories( $raw, $create = false ) {
		$raw = (string) $raw;
		if ( false === strpos( $raw, '|' ) ) {
			return $this->resolve_category_names( $raw, $create );
		}

		$ids     = array();
		$unknown = array();
		$parts   = explode( '|', $raw, 2 );
		$primary = trim( wp_strip_all_tags( (string) ( $parts[0] ?? '' ) ) );
		$subs    = preg_split( '/[;,]/', (string) ( $parts[1] ?? '' ) );

		$parent_id = 0;
		if ( '' !== $primary ) {
			$parent_id = $this->get_or_create_category( $primary, 0, $create );
			if ( $parent_id > 0 ) {
				$ids[] = $parent_id;
			} else {
				$unknown[] = $primary;
			}
		}

		foreach ( (array) $subs as $sub ) {
			$sub = trim( wp_strip_all_tags( (string) $sub ) );
			if ( '' === $sub ) {
				continue;
			}
			$sid = $this->get_or_create_category( $sub, $parent_id, $create );
			if ( $sid > 0 ) {
				$ids[] = $sid;
			} else {
				$unknown[] = $sub;
			}
		}

		return array(
			'ids'     => array_values( array_unique( array_map( 'absint', $ids ) ) ),
			'unknown' => array_values( array_unique( $unknown ) ),
		);
	}

	/**
	 * Find an existing category by name (optionally under a parent), creating it
	 * when allowed.
	 *
	 * @param string $name   Category name.
	 * @param int    $parent Desired parent (0 = top level).
	 * @param bool   $create Whether to create when missing.
	 * @return int Category ID, or 0 when not found and not created.
	 */
	private function get_or_create_category( $name, $parent = 0, $create = false ) {
		$name = trim( wp_strip_all_tags( (string) $name ) );
		if ( '' === $name ) {
			return 0;
		}
		// Prefer a term with the matching parent; otherwise reuse any same-name term.
		$match = get_term_by( 'name', $name, 'category' );
		if ( $match && ! is_wp_error( $match ) ) {
			return (int) $match->term_id;
		}
		if ( ! $create || ! current_user_can( 'manage_categories' ) ) {
			return 0;
		}
		$inserted = wp_insert_term( $name, 'category', array( 'parent' => absint( $parent ) ) );
		if ( is_wp_error( $inserted ) ) {
			$existing = $inserted->get_error_data( 'term_exists' );
			return $existing ? absint( $existing ) : 0;
		}
		return absint( $inserted['term_id'] ?? 0 );
	}

	private function flag_for_notify( $idea_id ) {
		$list = get_option( self::NOTIFY_OPTION, array() );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = absint( $idea_id );
		update_option( self::NOTIFY_OPTION, array_values( array_unique( array_map( 'absint', $list ) ) ), false );
	}
}
