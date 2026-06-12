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
		$models_count     = (int) $ai_status['available_text_models_count'];

		return array(
			$this->row( __( 'Versione plugin', 'wp-ai-publisher' ), WPAIP_VERSION, 'ok' ),
			$this->row( __( 'Versione WordPress', 'wp-ai-publisher' ), (string) $wp_version, version_compare( $wp_version, '7.0', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'Versione PHP', 'wp-ai-publisher' ), PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'Provider AI operativo', 'wp-ai-publisher' ), __( 'Solo sistema AI di WordPress', 'wp-ai-publisher' ), 'ok' ),
			$this->row( __( 'Stato sistema AI WordPress', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Non rilevato', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? 'ok' : 'not-verified' ),
			$this->row( __( 'Modelli AI disponibili', 'wp-ai-publisher' ), sprintf( __( '%d modelli rilevati', 'wp-ai-publisher' ), $models_count ), $models_count > 0 ? 'ok' : 'not-verified' ),
			$this->row( __( 'OpenAI diretto', 'wp-ai-publisher' ), __( 'Disabilitato: il plugin non usa un client custom', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Stato AIOSEO', 'wp-ai-publisher' ), $this->is_aioseo_active() ? __( 'Rilevato', 'wp-ai-publisher' ) : __( 'Non rilevato', 'wp-ai-publisher' ), $this->is_aioseo_active() ? 'ok' : 'not-configured' ),
			$this->row( __( 'Database principale', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? __( 'Tabella log disponibile', 'wp-ai-publisher' ) : __( 'Tabella log mancante', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? 'ok' : 'error' ),
			$this->row( __( 'Database secondario', 'wp-ai-publisher' ), __( 'Opzionale / non configurato', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Cron / coda job', 'wp-ai-publisher' ), __( 'Non ancora implementata', 'wp-ai-publisher' ), 'not-implemented' ),
			$this->row( __( 'Permessi file', 'wp-ai-publisher' ), $uploads_writable ? __( 'Cartella uploads scrivibile', 'wp-ai-publisher' ) : __( 'Cartella uploads non scrivibile', 'wp-ai-publisher' ), $uploads_writable ? 'ok' : 'error' ),
			$this->row( __( 'Media Library', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? __( 'Disponibile', 'wp-ai-publisher' ) : __( 'Non disponibile', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? 'ok' : 'warning' ),
			$this->row( __( 'Knowledge Index', 'wp-ai-publisher' ), __( 'Non ancora implementato', 'wp-ai-publisher' ), 'not-implemented' ),
			$this->row( __( 'Aggiornamenti GitHub', 'wp-ai-publisher' ), __( 'Non configurati', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Versione schema database', 'wp-ai-publisher' ), $this->db->get_schema_version(), $this->db->get_schema_version() === WPAIP_DB_SCHEMA_VERSION ? 'ok' : 'warning' ),
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

	/**
	 * Detect All in One SEO.
	 *
	 * @return bool
	 */
	private function is_aioseo_active() {
		if ( defined( 'AIOSEO_VERSION' ) || class_exists( '\\AIOSEO\\Plugin\\AIOSEO' ) || function_exists( 'aioseo' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'all-in-one-seo-pack/all_in_one_seo_pack.php' ) || is_plugin_active( 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' );
	}
}
