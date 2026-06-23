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
		add_action( 'admin_post_wpai_publisher_import_ideas', array( $this, 'handle_import' ) );
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
		fputcsv( $out, array( 'Argomento principale', 'Lingua', 'Tipologia articolo', 'Programma creazione' ) );
		fputcsv( $out, array( 'Come scegliere una bici da città', 'it', $example_type, $tomorrow ) );
		fputcsv( $out, array( 'Best lightweight laptops for travel', 'en', $example_type, $after ) );
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
		$article_types_enabled = wpai_publisher_article_types_enabled();
		$ideas_url             = admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' );
		require WPAIP_PLUGIN_DIR . 'admin/views/import-ideas.php';
	}

	/* ---------------------------------------------------------------------
	 * Import handler
	 * ------------------------------------------------------------------- */

	/**
	 * Handle the CSV upload.
	 *
	 * @return void
	 */
	public function handle_import() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_import_ideas' );

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

		$type_map = $this->article_type_name_map();
		$line     = 1; // header is line 1.

		foreach ( $rows as $row ) {
			$line++;
			$feedback['total']++;

			$topic    = trim( (string) ( $row['topic'] ?? '' ) );
			$language = $this->normalize_language( (string) ( $row['language'] ?? '' ) );
			$schedule = trim( (string) ( $row['schedule'] ?? '' ) );
			$type_raw = trim( (string) ( $row['type'] ?? '' ) );

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

			$idea_id = $this->content_ideas->create_idea_programmatic(
				array(
					'topic'           => $topic,
					'language'        => $language,
					'article_type_id' => $article_type_id,
					'scheduled_at'    => $this->parse_datetime( $schedule ),
					'notes'           => __( 'Importazione massiva CSV', 'wp-ai-publisher' ),
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

		$this->logger->info(
			__( 'Importazione massiva idee completata.', 'wp-ai-publisher' ),
			array( 'source' => 'bulk_import', 'event' => 'import_done', 'created' => $feedback['created'], 'skipped' => $feedback['skipped'] )
		);

		$this->store_feedback_and_redirect( $feedback );
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
				'topic'    => (string) ( $data[ $cols['topic'] ] ?? '' ),
				'language' => (string) ( $data[ $cols['language'] ] ?? '' ),
				'type'     => (string) ( $data[ $cols['type'] ] ?? '' ),
				'schedule' => (string) ( $data[ $cols['schedule'] ] ?? '' ),
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
		$cols = array( 'topic' => 0, 'language' => 1, 'type' => 2, 'schedule' => 3 );
		foreach ( (array) $header as $index => $name ) {
			$norm = $this->normalize_key( $name );
			if ( false !== strpos( $norm, 'argomento' ) ) {
				$cols['topic'] = $index;
			} elseif ( false !== strpos( $norm, 'lingua' ) ) {
				$cols['language'] = $index;
			} elseif ( false !== strpos( $norm, 'tipolog' ) ) {
				$cols['type'] = $index;
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
	private function flag_for_notify( $idea_id ) {
		$list = get_option( self::NOTIFY_OPTION, array() );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$list[] = absint( $idea_id );
		update_option( self::NOTIFY_OPTION, array_values( array_unique( array_map( 'absint', $list ) ) ), false );
	}
}
