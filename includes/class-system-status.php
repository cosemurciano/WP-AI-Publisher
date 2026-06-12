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
		global $wp_version;

		$db_tables        = $this->db->check_tables();
		$upload_dir       = wp_upload_dir();
		$uploads_writable = ! empty( $upload_dir['basedir'] ) && wp_is_writable( $upload_dir['basedir'] );
		$ai_status        = $this->ai_provider->get_status();
		$site_context     = wpai_publisher_get_site_context();
		$models_count        = (int) $ai_status['available_text_models_count'];
		$abilities_count     = (int) $ai_status['available_abilities_count'];
		$wp_ai_available     = $this->ai_provider->is_wordpress_ai_client_available();
		$validator_available       = class_exists( __NAMESPACE__ . '\Structured_Output_Validator' );
		$classic_builder_available = class_exists( __NAMESPACE__ . '\Classic_Content_Builder' );
		$ai_diagnostics_available  = class_exists( __NAMESPACE__ . '\AI_Diagnostics' );
		$ai_diagnostics_paths      = array();
		$ai_diagnostics_routes     = array();

		if ( $ai_diagnostics_available ) {
			$ai_diagnostics        = new AI_Diagnostics( $this->ai_provider, $this->logger );
			$ai_diagnostics_paths  = $ai_diagnostics->get_possible_generation_paths();
			$ai_diagnostics_routes = $ai_diagnostics->get_detected_rest_routes();
		}

		$available_generation_paths = array_filter(
			$ai_diagnostics_paths,
			static function ( $path ) {
				return 'available' === ( $path['status'] ?? '' ) && false === stripos( $path['label'] ?? '', 'fallback' );
			}
		);
		$maybe_generation_paths = array_filter(
			$ai_diagnostics_paths,
			static function ( $path ) {
				return 'maybe' === ( $path['status'] ?? '' );
			}
		);
		$generation_paths_status = ! empty( $available_generation_paths ) ? 'ok' : ( ! empty( $maybe_generation_paths ) ? 'warning' : 'warning' );
		$generation_paths_label  = ! empty( $available_generation_paths )
			? sprintf( __( '%d percorso/i available rilevati', 'wp-ai-publisher' ), count( $available_generation_paths ) )
			: ( ! empty( $maybe_generation_paths ) ? sprintf( __( '%d percorso/i maybe rilevati; serve bridge o verifica manuale', 'wp-ai-publisher' ), count( $maybe_generation_paths ) ) : __( 'Solo fallback locale disponibile', 'wp-ai-publisher' ) );
		$context_configured     = wpai_publisher_is_site_context_configured( $site_context );
		$checks                    = array(
			$this->row( __( 'Versione plugin', 'wp-ai-publisher' ), WPAIP_VERSION, 'ok' ),
			$this->row( __( 'Versione WordPress', 'wp-ai-publisher' ), (string) $wp_version, version_compare( $wp_version, '7.0', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'Versione PHP', 'wp-ai-publisher' ), PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'Provider AI operativo', 'wp-ai-publisher' ), __( 'Solo sistema AI di WordPress', 'wp-ai-publisher' ), 'ok' ),
			$this->row( __( 'Target editoriale', 'wp-ai-publisher' ), __( 'Editor Classico', 'wp-ai-publisher' ), 'ok' ),
			$this->row( __( 'Contesto editoriale', 'wp-ai-publisher' ), $context_configured ? __( 'Descrizione o nicchia contenuti configurata', 'wp-ai-publisher' ) : __( 'Descrizione sito e nicchia contenuti assenti', 'wp-ai-publisher' ), $context_configured ? 'ok' : 'warning' ),
			$this->row( __( 'Editor target', 'wp-ai-publisher' ), wpai_publisher_site_context_label( 'default_editor', $site_context['default_editor'] ), 'classic' === $site_context['default_editor'] ? 'ok' : 'warning' ),
			$this->row( __( 'Stato post futuro', 'wp-ai-publisher' ), wpai_publisher_site_context_label( 'default_post_status_after_generation', $site_context['default_post_status_after_generation'] ), 'ok' ),
			$this->row( __( 'Stato sistema AI WordPress', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Non rilevato', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? 'ok' : 'not-verified' ),
			$this->row( __( 'Modelli AI disponibili', 'wp-ai-publisher' ), sprintf( __( '%d modelli rilevati', 'wp-ai-publisher' ), $models_count ), $models_count > 0 ? 'ok' : 'not-verified' ),
			$this->row( __( 'Abilità AI WordPress', 'wp-ai-publisher' ), sprintf( __( '%d abilità rilevate', 'wp-ai-publisher' ), $abilities_count ), $abilities_count > 0 ? 'ok' : 'not-verified' ),
			$this->row( __( 'Dry-run AI', 'wp-ai-publisher' ), $wp_ai_available ? __( 'WordPress AI disponibile per tentativi reali; fallback locale solo se l’output non è utilizzabile', 'wp-ai-publisher' ) : __( 'Solo fallback locale disponibile: WordPress AI non rilevato', 'wp-ai-publisher' ), $wp_ai_available ? 'ok' : 'warning' ),
			$this->row( __( 'Validatore output strutturato', 'wp-ai-publisher' ), $validator_available ? __( 'Classe Structured_Output_Validator disponibile', 'wp-ai-publisher' ) : __( 'Validazione interna basilare', 'wp-ai-publisher' ), $validator_available ? 'ok' : 'warning' ),
			$this->row( __( 'Classic Content Builder', 'wp-ai-publisher' ), $classic_builder_available ? __( 'Classe Classic_Content_Builder disponibile', 'wp-ai-publisher' ) : __( 'Classe Classic_Content_Builder non disponibile', 'wp-ai-publisher' ), $classic_builder_available ? 'ok' : 'error' ),
			$this->row( __( 'OpenAI diretto', 'wp-ai-publisher' ), __( 'Disabilitato: il plugin non usa un client custom', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Diagnostica AI', 'wp-ai-publisher' ), $ai_diagnostics_available ? __( 'Classe AI_Diagnostics disponibile', 'wp-ai-publisher' ) : __( 'Classe AI_Diagnostics non disponibile', 'wp-ai-publisher' ), $ai_diagnostics_available ? 'ok' : 'error' ),
			$this->row( __( 'Percorsi generazione AI', 'wp-ai-publisher' ), $generation_paths_label, $generation_paths_status ),
			$this->row( __( 'REST route AI', 'wp-ai-publisher' ), ! empty( $ai_diagnostics_routes ) ? sprintf( __( '%d route AI rilevate', 'wp-ai-publisher' ), count( $ai_diagnostics_routes ) ) : __( 'Nessuna route AI rilevata', 'wp-ai-publisher' ), ! empty( $ai_diagnostics_routes ) ? 'ok' : 'warning' ),
		);

		$checks = array_merge( $checks, $this->get_third_party_checks() );

		return array_merge(
			$checks,
			array(
				$this->row( __( 'Database principale', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? __( 'Tabella log disponibile', 'wp-ai-publisher' ) : __( 'Tabella log mancante', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? 'ok' : 'error' ),
				$this->row( __( 'Database secondario', 'wp-ai-publisher' ), __( 'Opzionale / non configurato', 'wp-ai-publisher' ), 'not-configured' ),
				$this->row( __( 'Cron / coda job', 'wp-ai-publisher' ), ! empty( $db_tables['jobs'] ) ? __( 'Tabella job disponibile; processor cron non ancora implementato', 'wp-ai-publisher' ) : __( 'Tabella job mancante', 'wp-ai-publisher' ), ! empty( $db_tables['jobs'] ) ? 'ok' : 'error' ),
				$this->row( __( 'Idee contenuto', 'wp-ai-publisher' ), ! empty( $db_tables['content_ideas'] ) ? __( 'Tabella disponibile', 'wp-ai-publisher' ) : __( 'Tabella mancante', 'wp-ai-publisher' ), ! empty( $db_tables['content_ideas'] ) ? 'ok' : 'error' ),
				$this->row( __( 'Permessi file', 'wp-ai-publisher' ), $uploads_writable ? __( 'Cartella uploads scrivibile', 'wp-ai-publisher' ) : __( 'Cartella uploads non scrivibile', 'wp-ai-publisher' ), $uploads_writable ? 'ok' : 'error' ),
				$this->row( __( 'Media Library', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? __( 'Disponibile', 'wp-ai-publisher' ) : __( 'Non disponibile', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? 'ok' : 'warning' ),
				$this->row( __( 'Knowledge Index', 'wp-ai-publisher' ), __( 'Non ancora implementato', 'wp-ai-publisher' ), 'not-implemented' ),
				$this->row( __( 'Aggiornamenti GitHub', 'wp-ai-publisher' ), __( 'Gestiti tramite Git Updater se installato e configurato.', 'wp-ai-publisher' ), 'not-configured' ),
				$this->row( __( 'Versione schema database', 'wp-ai-publisher' ), $this->db->get_schema_version(), $this->db->get_schema_version() === WPAIP_DB_SCHEMA_VERSION ? 'ok' : 'warning' ),
			)
		);
	}

	/**
	 * Return latest critical logs.
	 *
	 * @return array<int,object>
	 */
	public function get_last_critical_errors() {
		return $this->logger->get_last_critical_errors( 5 );
	}

	/**
	 * Return diagnostics for third-party plugins and integrations.
	 *
	 * @return array<int,array<string,string>>
	 */
	private function get_third_party_checks() {
		if ( ! class_exists( __NAMESPACE__ . '\Third_Party_Plugins' ) ) {
			return array(
				$this->row( __( 'Plugin terzi e integrazioni', 'wp-ai-publisher' ), __( 'Diagnostica non disponibile', 'wp-ai-publisher' ), 'not-verified' ),
			);
		}

		$third_party = new Third_Party_Plugins();
		$plugins     = $third_party->get_plugins();
		$rows        = array();
		$labels      = array(
			'git_updater'            => __( 'Git Updater', 'wp-ai-publisher' ),
			'git_remote_updater'     => __( 'Git Remote Updater', 'wp-ai-publisher' ),
			'wordpress_ai'           => __( 'WordPress AI', 'wp-ai-publisher' ),
			'ai_request_logging'     => __( 'AI Request Logging', 'wp-ai-publisher' ),
			'connector_approval'     => __( 'Connector Approval', 'wp-ai-publisher' ),
			'abilities_explorer'     => __( 'Abilities Explorer', 'wp-ai-publisher' ),
			'aioseo'                 => __( 'AIOSEO', 'wp-ai-publisher' ),
		);

		foreach ( $labels as $plugin_key => $label ) {
			if ( empty( $plugins[ $plugin_key ] ) || ! is_array( $plugins[ $plugin_key ] ) ) {
				continue;
			}

			$plugin = $plugins[ $plugin_key ];
			$status = isset( $plugin['status'] ) ? sanitize_key( (string) $plugin['status'] ) : 'not_detected';
			$rows[] = $this->row( $label, $this->get_third_party_status_value( $plugin_key, $status ), $this->get_third_party_row_status( $plugin_key, $plugin, $status ) );
		}

		return $rows;
	}

	/**
	 * Convert a third-party integration status to a diagnostic value.
	 *
	 * @param string $plugin_key Plugin key.
	 * @param string $status Plugin status.
	 * @return string
	 */
	private function get_third_party_status_value( $plugin_key, $status ) {
		$status_labels = array(
			'active'       => __( 'Attivo', 'wp-ai-publisher' ),
			'inactive'     => __( 'Installato ma non attivo', 'wp-ai-publisher' ),
			'missing'      => __( 'Non installato', 'wp-ai-publisher' ),
			'detected'     => __( 'Rilevato', 'wp-ai-publisher' ),
			'not_detected' => __( 'Non rilevato', 'wp-ai-publisher' ),
		);

		$value = $status_labels[ $status ] ?? $status;

		if ( 'git_updater' === $plugin_key && ! in_array( $status, array( 'active', 'detected' ), true ) ) {
			return $value . ' — ' . __( 'necessario solo per aggiornamenti da GitHub.', 'wp-ai-publisher' );
		}

		if ( 'git_remote_updater' === $plugin_key ) {
			return $value . ' — ' . __( 'la chiave REST resta gestita da Git Updater / Git Remote Updater; WP AI Publisher non la salva né la replica.', 'wp-ai-publisher' );
		}

		if ( 'wordpress_ai' === $plugin_key && ! in_array( $status, array( 'active', 'detected' ), true ) ) {
			return $value . ' — ' . __( 'il layer AI di WordPress è centrale per le funzioni future del plugin.', 'wp-ai-publisher' );
		}

		return $value;
	}

	/**
	 * Convert a third-party integration status to a system status severity.
	 *
	 * @param string              $plugin_key Plugin key.
	 * @param array<string,mixed> $plugin Plugin data.
	 * @param string              $status Plugin status.
	 * @return string
	 */
	private function get_third_party_row_status( $plugin_key, $plugin, $status ) {
		if ( in_array( $status, array( 'active', 'detected' ), true ) ) {
			return 'ok';
		}

		if ( 'wordpress_ai' === $plugin_key ) {
			return 'error';
		}

		if ( 'git_remote_updater' === $plugin_key ) {
			return 'not-configured';
		}

		if ( ! empty( $plugin['required'] ) || ! empty( $plugin['recommended'] ) ) {
			return 'warning';
		}

		return 'not-configured';
	}

	/**
	 * Build a status row.
	 *
	 * @param string $label Label.
	 * @param string $value Value.
	 * @param string $status Status key.
	 * @return array<string,string>
	 */
	private function row( $label, $value, $status ) {
		return array(
			'label'  => $label,
			'value'  => $value,
			'status' => $status,
		);
	}


}
