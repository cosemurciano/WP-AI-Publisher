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

		$db_tables       = $this->db->check_tables();
		$upload_dir      = wp_upload_dir();
		$uploads_writable = ! empty( $upload_dir['basedir'] ) && wp_is_writable( $upload_dir['basedir'] );

		return array(
			$this->row( __( 'Plugin version', 'wp-ai-publisher' ), WPAIP_VERSION, 'ok' ),
			$this->row( __( 'WordPress version', 'wp-ai-publisher' ), (string) $wp_version, version_compare( $wp_version, '7.0', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'PHP version', 'wp-ai-publisher' ), PHP_VERSION, version_compare( PHP_VERSION, '8.1', '>=' ) ? 'ok' : 'error' ),
			$this->row( __( 'OpenAI status', 'wp-ai-publisher' ), $this->ai_provider->is_openai_direct_available() ? __( 'Configuration detected; not verified', 'wp-ai-publisher' ) : __( 'Not verified', 'wp-ai-publisher' ), $this->ai_provider->is_openai_direct_available() ? 'warning' : 'not-verified' ),
			$this->row( __( 'WordPress AI Client/API status', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? __( 'Detected', 'wp-ai-publisher' ) : __( 'Not detected', 'wp-ai-publisher' ), $this->ai_provider->is_wordpress_ai_client_available() ? 'ok' : 'not-configured' ),
			$this->row( __( 'AIOSEO status', 'wp-ai-publisher' ), $this->is_aioseo_active() ? __( 'Detected', 'wp-ai-publisher' ) : __( 'Not detected', 'wp-ai-publisher' ), $this->is_aioseo_active() ? 'ok' : 'not-configured' ),
			$this->row( __( 'Main DB status', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? __( 'Log table available', 'wp-ai-publisher' ) : __( 'Log table missing', 'wp-ai-publisher' ), ! empty( $db_tables['logs'] ) ? 'ok' : 'error' ),
			$this->row( __( 'Secondary DB status', 'wp-ai-publisher' ), __( 'Optional / not configured', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Cron/job queue status', 'wp-ai-publisher' ), __( 'Not implemented yet', 'wp-ai-publisher' ), 'not-implemented' ),
			$this->row( __( 'File permissions status', 'wp-ai-publisher' ), $uploads_writable ? __( 'Uploads directory writable', 'wp-ai-publisher' ) : __( 'Uploads directory not writable', 'wp-ai-publisher' ), $uploads_writable ? 'ok' : 'error' ),
			$this->row( __( 'Media Library status', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? __( 'Available', 'wp-ai-publisher' ) : __( 'Not available', 'wp-ai-publisher' ), function_exists( 'media_handle_sideload' ) || function_exists( 'wp_insert_attachment' ) ? 'ok' : 'warning' ),
			$this->row( __( 'Knowledge Index status', 'wp-ai-publisher' ), __( 'Not implemented yet', 'wp-ai-publisher' ), 'not-implemented' ),
			$this->row( __( 'GitHub updater status', 'wp-ai-publisher' ), __( 'Not configured', 'wp-ai-publisher' ), 'not-configured' ),
			$this->row( __( 'Database schema version', 'wp-ai-publisher' ), $this->db->get_schema_version(), $this->db->get_schema_version() === WPAIP_DB_SCHEMA_VERSION ? 'ok' : 'warning' ),
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
