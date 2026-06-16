<?php
/**
 * System status checks.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds read-only environment diagnostics.
 */
class System_Status {
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
	 * AI adapter.
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Constructor.
	 *
	 * @param DB                  $db Database service.
	 * @param Logger              $logger Logger service.
	 * @param AI_Provider_Adapter $ai_provider AI adapter.
	 */
	public function __construct( DB $db, Logger $logger, AI_Provider_Adapter $ai_provider ) {
		$this->db          = $db;
		$this->logger      = $logger;
		$this->ai_provider = $ai_provider;
	}

	/**
	 * Return status checks.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_checks() {
		return $this->get_status_items();
	}

	/**
	 * Return status checks in the stable diagnostic shape.
	 *
	 * @return array<int,array<string,string>>
	 */
	public function get_status_items() {
		return array(
			$this->plugin_version_item(),
			$this->wordpress_version_item(),
			$this->php_version_item(),
			$this->openai_settings_item(),
			$this->wordpress_ai_item(),
			$this->aioseo_item(),
			$this->main_database_item(),
			$this->secondary_database_item(),
			$this->wp_cron_item(),
			$this->job_queue_item(),
			$this->file_permissions_item(),
			$this->media_library_item(),
			$this->knowledge_index_item(),
			$this->github_updater_item(),
			$this->database_schema_item(),
			$this->article_types_table_item(),
			$this->active_article_types_item(),
			$this->ideas_without_article_type_item(),
			$this->article_types_cpt_item(),
			$this->article_types_map_meta_cap_item(),
			$this->global_categories_item(),
			$this->article_type_global_categories_item(),
			$this->deprecated_editorial_settings_item(),
			$this->recent_critical_errors_item(),
		);
	}


	private function global_categories_item() {
		$context = function_exists( 'wpai_publisher_get_raw_site_context' ) ? wpai_publisher_get_raw_site_context() : array();
		$stored  = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $context['allowed_category_ids'] ?? array() ) ) ) ) );

		if ( empty( $stored ) ) {
			return $this->row( 'global_allowed_categories', __( 'Categorie globali', 'wp-ai-publisher' ), 'info', __( 'Nessun limite globale configurato', 'wp-ai-publisher' ), '' );
		}

		$existing = get_terms( array( 'taxonomy' => 'category', 'hide_empty' => false, 'fields' => 'ids', 'include' => $stored ) );
		if ( is_wp_error( $existing ) ) {
			return $this->row( 'global_allowed_categories', __( 'Categorie globali', 'wp-ai-publisher' ), 'warning', __( 'Categorie non verificabili', 'wp-ai-publisher' ), __( 'Impossibile verificare le categorie globali salvate. Riprova o riselezionale nelle impostazioni.', 'wp-ai-publisher' ) );
		}

		$valid   = array_values( array_intersect( $stored, array_map( 'absint', (array) $existing ) ) );
		$invalid = array_values( array_diff( $stored, $valid ) );

		if ( ! empty( $invalid ) ) {
			return $this->row( 'global_allowed_categories', __( 'Categorie globali', 'wp-ai-publisher' ), 'warning', sprintf( __( 'ID categoria non validi: %s', 'wp-ai-publisher' ), implode( ', ', array_map( 'absint', $invalid ) ) ), __( 'Alcune categorie globali salvate non esistono più. Riseleziona le categorie globali nelle impostazioni.', 'wp-ai-publisher' ) );
		}

		return $this->row( 'global_allowed_categories', __( 'Categorie globali', 'wp-ai-publisher' ), 'ok', sprintf( _n( '%d categoria valida', '%d categorie valide', count( $valid ), 'wp-ai-publisher' ), count( $valid ) ), '' );
	}

	private function article_type_global_categories_item() {
		$context = wpai_publisher_get_site_context();
		if ( empty( $context['allowed_category_ids'] ) ) {
			return $this->row( 'article_type_global_categories', __( 'Tipologie vs categorie globali', 'wp-ai-publisher' ), 'ok', __( 'Nessun limite globale', 'wp-ai-publisher' ), '' );
		}

		$conflicts = 0;
		foreach ( wpai_publisher_get_active_article_types_safe() as $article_type ) {
			$boundary = wpai_publisher_resolve_allowed_category_ids( $article_type, $context );
			if ( ! empty( $boundary['conflict'] ) ) {
				++$conflicts;
			}
		}

		return $this->row( 'article_type_global_categories', __( 'Tipologie vs categorie globali', 'wp-ai-publisher' ), $conflicts > 0 ? 'warning' : 'ok', $conflicts > 0 ? sprintf( _n( '%d conflitto', '%d conflitti', $conflicts, 'wp-ai-publisher' ), $conflicts ) : __( 'OK', 'wp-ai-publisher' ), $conflicts > 0 ? __( 'Una o più Tipologie Articolo non hanno categorie in comune con il limite globale.', 'wp-ai-publisher' ) : '' );
	}

	private function deprecated_editorial_settings_item() {
		$context = wpai_publisher_get_site_context();
		$has_old = '' !== trim( (string) ( $context['allowed_categories'] ?? '' ) ) || '' !== trim( (string) ( $context['preferred_tags'] ?? '' ) ) || 'chiaro_didattico_e_operativo' !== (string) ( $context['default_tone'] ?? 'chiaro_didattico_e_operativo' );
		return $this->row( 'deprecated_editorial_settings', __( 'Impostazioni editoriali legacy', 'wp-ai-publisher' ), $has_old ? 'info' : 'ok', $has_old ? __( 'Presenti', 'wp-ai-publisher' ) : __( 'Assenti', 'wp-ai-publisher' ), $has_old ? __( 'Sono presenti vecchie impostazioni editoriali globali. Le Tipologie Articolo hanno priorità.', 'wp-ai-publisher' ) : '' );
	}

	private function article_types_table_item() {
		$tables = $this->db->check_tables();
		return $this->row( 'article_types_table', __( 'Tabella article_types', 'wp-ai-publisher' ), ! empty( $tables['article_types'] ) ? 'ok' : 'warning', ! empty( $tables['article_types'] ) ? __( 'OK', 'wp-ai-publisher' ) : __( 'Mancante', 'wp-ai-publisher' ), '' );
	}

	private function active_article_types_item() {
		$count = count( wpai_publisher_get_active_article_types_safe() );
		return $this->row( 'active_article_types', __( 'Tipologie attive', 'wp-ai-publisher' ), $count > 0 ? 'ok' : 'warning', (string) $count, '' );
	}

	private function ideas_without_article_type_item() {
		$count = method_exists( $this->db, 'count_content_ideas_without_article_type' ) ? $this->db->count_content_ideas_without_article_type() : 0;
		return $this->row( 'ideas_without_article_type', __( 'Idee senza tipologia', 'wp-ai-publisher' ), $count > 0 ? 'warning' : 'ok', (string) $count, '' );
	}

	private function article_types_cpt_item() {
		$used = function_exists( 'post_type_exists' ) && post_type_exists( 'wpai_article_type' );
		return $this->row( 'article_types_cpt', __( 'CPT Tipologie Articolo', 'wp-ai-publisher' ), $used ? 'warning' : 'ok', $used ? __( 'Registrato', 'wp-ai-publisher' ) : __( 'Disabilitato/non usato', 'wp-ai-publisher' ), '' );
	}

	private function article_types_map_meta_cap_item() {
		return $this->row( 'article_types_map_meta_cap', __( 'map_meta_cap Tipologie', 'wp-ai-publisher' ), 'ok', __( 'Non usato dal modulo tipologie', 'wp-ai-publisher' ), '' );
	}

	/**
	 * Return latest critical logs.
	 *
	 * @return array<int,object>
	 */
	public function get_last_critical_errors() {
		return $this->logger->get_last_critical_errors( 15 );
	}

	/**
	 * Build plugin version row.
	 *
	 * @return array<string,string>
	 */
	private function plugin_version_item() {
		$version = defined( 'WPAIP_VERSION' ) ? (string) WPAIP_VERSION : '';
		if ( '' === $version && defined( 'WPAIP_PLUGIN_FILE' ) ) {
			if ( ! function_exists( 'get_file_data' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
			if ( function_exists( 'get_file_data' ) ) {
				$data    = get_file_data( WPAIP_PLUGIN_FILE, array( 'Version' => 'Version' ), 'plugin' );
				$version = isset( $data['Version'] ) ? (string) $data['Version'] : '';
			}
		}

		return $this->row( 'plugin_version', __( 'Versione plugin', 'wp-ai-publisher' ), '' !== $version ? 'ok' : 'warning', '' !== $version ? $version : __( 'Mancante', 'wp-ai-publisher' ), '' !== $version ? '' : __( 'Verifica l’header Version del file principale del plugin.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check current WordPress version against the plugin requirement.
	 *
	 * @return array<string,string>
	 */
	private function wordpress_version_item() {
		$current_version  = (string) get_bloginfo( 'version' );
		$required_version = defined( 'WPAIP_MIN_WP_VERSION' ) ? (string) WPAIP_MIN_WP_VERSION : '6.5';
		$meets_requirement = '' !== $current_version && version_compare( $current_version, $required_version, '>=' );

		return $this->row(
			'wordpress_version',
			__( 'Versione WordPress', 'wp-ai-publisher' ),
			$meets_requirement ? 'ok' : 'warning',
			sprintf(
				/* translators: 1: Current WordPress version, 2: Required WordPress version. */
				__( '%1$s (richiesto: %2$s o superiore)', 'wp-ai-publisher' ),
				$current_version,
				$required_version
			),
			$meets_requirement ? '' : sprintf(
				/* translators: %s: Required WordPress version. */
				__( 'Aggiorna WordPress alla versione %s o superiore.', 'wp-ai-publisher' ),
				$required_version
			)
		);
	}

	/**
	 * Check current PHP version against the plugin requirement.
	 *
	 * @return array<string,string>
	 */
	private function php_version_item() {
		$current_version  = PHP_VERSION;
		$required_version = defined( 'WPAIP_MIN_PHP_VERSION' ) ? (string) WPAIP_MIN_PHP_VERSION : '8.1';
		$meets_requirement = version_compare( $current_version, $required_version, '>=' );

		return $this->row(
			'php_version',
			__( 'Versione PHP', 'wp-ai-publisher' ),
			$meets_requirement ? 'ok' : 'warning',
			sprintf(
				/* translators: 1: Current PHP version, 2: Required PHP version. */
				__( '%1$s (richiesto: %2$s o superiore)', 'wp-ai-publisher' ),
				$current_version,
				$required_version
			),
			$meets_requirement ? '' : sprintf(
				/* translators: %s: Required PHP version. */
				__( 'Aggiorna PHP alla versione %s o superiore.', 'wp-ai-publisher' ),
				$required_version
			)
		);
	}

	/**
	 * Check OpenAI setting presence without exposing secrets or making calls.
	 *
	 * @return array<string,string>
	 */
	private function openai_settings_item() {
		$settings = get_option( Settings::OPTION_NAME, null );
		if ( ! is_array( $settings ) ) {
			return $this->row( 'openai_settings', __( 'Impostazioni OpenAI', 'wp-ai-publisher' ), 'unknown', __( 'Sconosciuto', 'wp-ai-publisher' ), __( 'Nessuna opzione OpenAI diretta evidente; il plugin usa il client AI di WordPress.', 'wp-ai-publisher' ) );
		}

		$key_fields = array( 'openai_api_key', 'api_key', 'openai_key' );
		foreach ( $key_fields as $field ) {
			if ( isset( $settings[ $field ] ) && '' !== trim( (string) $settings[ $field ] ) ) {
				return $this->row( 'openai_settings', __( 'Impostazioni OpenAI', 'wp-ai-publisher' ), 'ok', __( 'Configurato', 'wp-ai-publisher' ), '' );
			}
		}

		return $this->row( 'openai_settings', __( 'Impostazioni OpenAI', 'wp-ai-publisher' ), 'info', __( 'Non configurato', 'wp-ai-publisher' ), __( 'Nessuna chiave OpenAI diretta rilevata; non è necessaria per questa pagina.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check local WordPress AI/API availability without invoking generation.
	 *
	 * @return array<string,string>
	 */
	private function wordpress_ai_item() {
		$diag      = method_exists( $this->ai_provider, 'get_ai_generation_diagnostics' ) ? $this->ai_provider->get_ai_generation_diagnostics() : array();
		$available = ! empty( $diag['ai_available'] ) || $this->ai_provider->is_wordpress_ai_client_available();

		$channels = array();
		if ( ! empty( $diag['channel_filter'] ) ) { $channels[] = 'filter'; }
		if ( ! empty( $diag['channel_openai_responses'] ) ) { $channels[] = 'openai_responses'; }
		if ( ! empty( $diag['channel_php_ai_client'] ) ) { $channels[] = 'php_ai_client'; }
		if ( ! empty( $diag['channel_abilities_api'] ) ) { $channels[] = 'abilities_api'; }
		if ( ! empty( $diag['channel_ai_services'] ) ) { $channels[] = 'ai_services'; }
		if ( ! empty( $diag['channel_wp_ai_generate_text'] ) ) { $channels[] = 'wp_ai_generate_text'; }

		$detected = array_slice( array_merge( (array) ( $diag['present_classes'] ?? array() ), (array) ( $diag['present_functions'] ?? array() ) ), 0, 5 );
		$value    = $available ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Non rilevato', 'wp-ai-publisher' );
		if ( ! empty( $detected ) ) {
			$value .= ' (' . implode( ', ', array_map( 'sanitize_text_field', $detected ) ) . ')';
		}

		if ( ! $available ) {
			return $this->row( 'wordpress_ai_api', __( 'WordPress AI Client / API', 'wp-ai-publisher' ), 'info', $value, __( 'Installa o attiva un sistema AI di WordPress (es. il plugin AI Services) per usare la generazione.', 'wp-ai-publisher' ) );
		}
		if ( empty( $channels ) ) {
			return $this->row( 'wordpress_ai_api', __( 'WordPress AI Client / API', 'wp-ai-publisher' ), 'warning', $value, __( 'Integrazione rilevata ma nessun canale di generazione compatibile: la creazione bozza non può produrre contenuto. Verifica il plugin AI oppure registra il filtro wpai_publisher_generate_article_from_idea.', 'wp-ai-publisher' ) );
		}
		return $this->row( 'wordpress_ai_api', __( 'WordPress AI Client / API', 'wp-ai-publisher' ), 'ok', $value, sprintf( __( 'Canali di generazione disponibili: %s.', 'wp-ai-publisher' ), implode( ', ', $channels ) ) );
	}

	/**
	 * Check AIOSEO availability defensively.
	 *
	 * @return array<string,string>
	 */
	private function aioseo_item() {
		$active = false;
		if ( ! function_exists( 'is_plugin_active' ) && defined( 'ABSPATH' ) && is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		if ( function_exists( 'is_plugin_active' ) ) {
			$active = is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) || is_plugin_active( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' );
		}
		$active = $active || defined( 'AIOSEO_VERSION' ) || class_exists( '\AIOSEO\Plugin\AIOSEO' ) || function_exists( 'aioseo' );

		return $this->row( 'aioseo', __( 'AIOSEO', 'wp-ai-publisher' ), $active ? 'ok' : 'info', $active ? __( 'Attivo', 'wp-ai-publisher' ) : __( 'Non attivo', 'wp-ai-publisher' ), $active ? '' : __( 'Attivalo solo se ti serve l’integrazione SEO futura.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check the WordPress database connection and required plugin tables.
	 *
	 * @return array<string,string>
	 */
	private function main_database_item() {
		global $wpdb;

		if ( ! is_object( $wpdb ) || empty( $wpdb->posts ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return $this->row( 'main_database', __( 'Database WordPress principale', 'wp-ai-publisher' ), 'error', __( 'Database WordPress non disponibile', 'wp-ai-publisher' ), __( 'Verifica la connessione database di WordPress.', 'wp-ai-publisher' ) );
		}

		$posts_table = (string) $wpdb->posts;
		$core_found  = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $posts_table ) );
		if ( $core_found !== $posts_table ) {
			return $this->row( 'main_database', __( 'Database WordPress principale', 'wp-ai-publisher' ), 'error', __( 'Tabella posts WordPress non trovata', 'wp-ai-publisher' ), __( 'Verifica la connessione database di WordPress.', 'wp-ai-publisher' ) );
		}

		$required_tables = $this->get_required_plugin_table_names();
		$missing_tables  = array();

		if ( method_exists( $this->db, 'check_tables' ) ) {
			$table_checks = $this->db->check_tables();
			$table_map    = array(
				'logs'          => $required_tables[0],
				'jobs'          => $required_tables[1],
				'content_ideas' => $required_tables[2],
			);

			foreach ( $table_map as $check_key => $table_name ) {
				if ( empty( $table_checks[ $check_key ] ) ) {
					$missing_tables[] = $table_name;
				}
			}
		} else {
			foreach ( $required_tables as $table_name ) {
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) );
				if ( $found !== $table_name ) {
					$missing_tables[] = $table_name;
				}
			}
		}

		if ( ! empty( $missing_tables ) ) {
			return $this->row( 'main_database', __( 'Database WordPress principale', 'wp-ai-publisher' ), 'error', sprintf( __( 'Tabelle plugin mancanti: %s', 'wp-ai-publisher' ), implode( ', ', $missing_tables ) ), __( 'Disattiva e riattiva il plugin oppure esegui lo strumento di migrazione/riparazione database del plugin, se disponibile.', 'wp-ai-publisher' ) );
		}

		return $this->row( 'main_database', __( 'Database WordPress principale', 'wp-ai-publisher' ), 'ok', __( 'Database WordPress OK; tutte le tabelle plugin richieste sono presenti.', 'wp-ai-publisher' ), '' );
	}

	/**
	 * Return the required plugin table names using the active site prefix.
	 *
	 * @return array<int,string>
	 */
	private function get_required_plugin_table_names() {
		global $wpdb;

		if ( method_exists( $this->db, 'get_logs_table_name' ) && method_exists( $this->db, 'get_jobs_table_name' ) && method_exists( $this->db, 'get_content_ideas_table_name' ) ) {
			return array(
				(string) $this->db->get_logs_table_name(),
				(string) $this->db->get_jobs_table_name(),
				(string) $this->db->get_content_ideas_table_name(),
			);
		}

		$prefix = is_object( $wpdb ) && isset( $wpdb->prefix ) ? (string) $wpdb->prefix : '';
		return array(
			$prefix . 'wpai_publisher_logs',
			$prefix . 'wpai_publisher_jobs',
			$prefix . 'wpai_publisher_content_ideas',
		);
	}

	/**
	 * Check secondary DB configuration presence only.
	 *
	 * @return array<string,string>
	 */
	private function secondary_database_item() {
		$configured = defined( 'WPAIP_SECONDARY_DB_HOST' ) || false !== get_option( 'wpai_publisher_secondary_db', false );
		return $this->row( 'secondary_database', __( 'Database secondario', 'wp-ai-publisher' ), $configured ? 'ok' : 'info', $configured ? __( 'Configurato', 'wp-ai-publisher' ) : __( 'Non configurato', 'wp-ai-publisher' ), $configured ? '' : __( 'Nessun database secondario richiesto in questa fase.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check WP-Cron status.
	 *
	 * @return array<string,string>
	 */
	private function wp_cron_item() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;
		return $this->row( 'wp_cron', __( 'WP-Cron', 'wp-ai-publisher' ), $disabled ? 'warning' : 'ok', $disabled ? __( 'Disabilitato', 'wp-ai-publisher' ) : __( 'Abilitato', 'wp-ai-publisher' ), $disabled ? __( 'WP-Cron è disattivato. Configura un cron reale sul server o usa il pulsante manuale per processare i job.', 'wp-ai-publisher' ) : '' );
	}

	private function job_queue_item() {
		if ( ! method_exists( $this->db, 'get_jobs_table_name' ) ) {
			return $this->row( 'job_queue', __( 'Coda WP AI Publisher', 'wp-ai-publisher' ), 'info', __( 'Non disponibile', 'wp-ai-publisher' ), '' );
		}
		$queue = new Job_Queue( $this->db );
		$diagnostics = $queue->get_queue_diagnostics();
		$value = sprintf(
			__( 'In coda: %1$d; in lavorazione: %2$d; falliti: %3$d; scaduti: %4$d; stale: %5$d; ultimo completato: %6$s', 'wp-ai-publisher' ),
			(int) $diagnostics['pending'],
			(int) $diagnostics['running'],
			(int) $diagnostics['failed'],
			(int) $diagnostics['timeout'],
			(int) $diagnostics['stale'],
			'' !== (string) $diagnostics['last_finished_at'] ? (string) $diagnostics['last_finished_at'] : __( 'mai', 'wp-ai-publisher' )
		);
		$status = (int) $diagnostics['stale'] > 0 ? 'warning' : 'ok';
		$suggestion = (int) $diagnostics['stale'] > 0 ? __( 'Ci sono job rimasti in lavorazione oltre il tempo previsto.', 'wp-ai-publisher' ) : '';
		return $this->row( 'job_queue', __( 'Coda WP AI Publisher', 'wp-ai-publisher' ), $status, $value, $suggestion );
	}


	/**
	 * Check upload directory writability.
	 *
	 * @return array<string,string>
	 */
	private function file_permissions_item() {
		$upload_dir = wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return $this->row( 'file_permissions', __( 'Permessi file', 'wp-ai-publisher' ), 'error', (string) $upload_dir['error'], __( 'Verifica la configurazione della cartella uploads.', 'wp-ai-publisher' ) );
		}
		$basedir  = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
		$exists   = '' !== $basedir && file_exists( $basedir );
		$writable = $exists && wp_is_writable( $basedir );
		$status   = $writable ? 'ok' : ( $exists ? 'warning' : 'error' );
		$value    = $writable ? __( 'OK', 'wp-ai-publisher' ) : ( $exists ? __( 'Non scrivibile', 'wp-ai-publisher' ) : __( 'Cartella mancante', 'wp-ai-publisher' ) );

		return $this->row( 'file_permissions', __( 'Permessi file', 'wp-ai-publisher' ), $status, $value, $writable ? '' : __( 'Verifica permessi e proprietà della cartella uploads.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check Media Library upload directory availability.
	 *
	 * @return array<string,string>
	 */
	private function media_library_item() {
		$upload_dir = wp_upload_dir();
		$ok         = empty( $upload_dir['error'] ) && ! empty( $upload_dir['basedir'] );
		return $this->row( 'media_library', __( 'Media Library', 'wp-ai-publisher' ), $ok ? 'ok' : 'warning', $ok ? __( 'OK', 'wp-ai-publisher' ) : __( 'Avviso', 'wp-ai-publisher' ), $ok ? '' : __( 'La cartella uploads non è disponibile.', 'wp-ai-publisher' ) );
	}

	/**
	 * Detect Knowledge Index presence without creating anything.
	 *
	 * @return array<string,string>
	 */
	private function knowledge_index_item() {
		$detected = class_exists( __NAMESPACE__ . '\Knowledge_Index' ) || ( function_exists( 'post_type_exists' ) && post_type_exists( 'wpai_knowledge' ) ) || false !== get_option( 'wpai_publisher_knowledge_index_version', false );
		return $this->row( 'knowledge_index', __( 'Knowledge Index', 'wp-ai-publisher' ), $detected ? 'ok' : 'info', $detected ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Non rilevato', 'wp-ai-publisher' ), $detected ? '' : __( 'Il Knowledge Index non è ancora attivo in questa installazione.', 'wp-ai-publisher' ) );
	}

	/**
	 * Detect GitHub updater configuration without changing updater behavior.
	 *
	 * @return array<string,string>
	 */
	private function github_updater_item() {
		$detected = defined( 'WPAIP_PLUGIN_FILE' ) && is_readable( WPAIP_PLUGIN_FILE ) && false !== strpos( (string) file_get_contents( WPAIP_PLUGIN_FILE ), 'GitHub Plugin URI:' );
		$detected = $detected || defined( 'GIT_UPDATER_VERSION' ) || class_exists( 'Fragen\Git_Updater\Bootstrap' );
		return $this->row( 'github_updater', __( 'GitHub updater', 'wp-ai-publisher' ), $detected ? 'ok' : 'unknown', $detected ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Sconosciuto', 'wp-ai-publisher' ), $detected ? '' : __( 'Nessuna configurazione updater GitHub rilevata.', 'wp-ai-publisher' ) );
	}

	/**
	 * Display stored schema version only.
	 *
	 * @return array<string,string>
	 */
	private function database_schema_item() {
		$version = (string) get_option( 'wpai_publisher_db_schema_version', '' );
		return $this->row( 'database_schema_version', __( 'Versione schema database', 'wp-ai-publisher' ), '' !== $version ? 'ok' : 'info', '' !== $version ? $version : __( 'Non disponibile', 'wp-ai-publisher' ), '' !== $version ? '' : __( 'Nessuna versione schema salvata.', 'wp-ai-publisher' ) );
	}

	/**
	 * Check recent internal critical logs.
	 *
	 * @return array<string,string>
	 */
	private function recent_critical_errors_item() {
		if ( ! method_exists( $this->logger, 'get_last_critical_errors' ) ) {
			return $this->row( 'recent_critical_errors', __( 'Errori critici recenti', 'wp-ai-publisher' ), 'info', __( 'Log interno plugin non ancora disponibile', 'wp-ai-publisher' ), '' );
		}
		$errors = $this->logger->get_last_critical_errors( 5 );
		$count  = is_array( $errors ) ? count( $errors ) : 0;
		return $this->row( 'recent_critical_errors', __( 'Errori critici recenti', 'wp-ai-publisher' ), $count > 0 ? 'warning' : 'ok', $count > 0 ? sprintf( _n( '%d errore recente', '%d errori recenti', $count, 'wp-ai-publisher' ), $count ) : __( 'Nessun errore critico recente', 'wp-ai-publisher' ), $count > 0 ? __( 'Controlla il log interno del plugin.', 'wp-ai-publisher' ) : '' );
	}

	/**
	 * Build a status row.
	 *
	 * @param string $key Item key.
	 * @param string $label Label.
	 * @param string $status Status key.
	 * @param string $value Value.
	 * @param string $suggestion Suggestion.
	 * @return array<string,string>
	 */
	private function row( $key, $label, $status, $value, $suggestion ) {
		return array(
			'key'        => sanitize_key( $key ),
			'label'      => $label,
			'status'     => sanitize_key( $status ),
			'value'      => $value,
			'suggestion' => $suggestion,
		);
	}
}
