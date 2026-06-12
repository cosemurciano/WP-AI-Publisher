<?php
/**
 * Defensive diagnostics for third-party plugin integrations.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Detects optional and recommended third-party plugins without hard dependencies.
 */
class Third_Party_Plugins {
	/**
	 * Return the monitored plugins and integrations.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function get_plugins() {
		return array(
			'git_updater'            => array(
				'name'         => 'Git Updater',
				'status'       => $this->get_plugin_status( 'git_updater' ),
				'required'     => true,
				'recommended'  => false,
				'purpose'      => __( 'Gestisce gli aggiornamenti del plugin da GitHub.', 'wp-ai-publisher' ),
				'download_url' => 'https://git-updater.com/',
				'admin_url'    => '',
				'notes'        => __( 'WP AI Publisher include gli header Git Updater nel file principale. La configurazione di token e aggiornamenti resta nel pannello Git Updater.', 'wp-ai-publisher' ),
			),
			'git_remote_updater'     => array(
				'name'         => 'Git Remote Updater',
				'status'       => $this->get_plugin_status( 'git_remote_updater' ),
				'required'     => false,
				'recommended'  => true,
				'purpose'      => __( 'Permette la gestione remota degli aggiornamenti tramite endpoint REST di Git Updater.', 'wp-ai-publisher' ),
				'download_url' => 'https://git-updater.com/knowledge-base/git-remote-updater/',
				'admin_url'    => '',
				'notes'        => __( 'La chiave REST di Git Remote Updater deve essere gestita nel pannello Git Updater / Git Remote Updater. WP AI Publisher non salva né replica questa chiave.', 'wp-ai-publisher' ),
			),
			'wordpress_ai'           => array(
				'name'         => 'AI Services / WordPress AI',
				'status'       => $this->get_plugin_status( 'wordpress_ai' ),
				'required'     => true,
				'recommended'  => false,
				'purpose'      => __( 'Fornisce il layer AI usato da WP AI Publisher.', 'wp-ai-publisher' ),
				'download_url' => '',
				'admin_url'    => '',
				'notes'        => __( 'WP AI Publisher usa solo il sistema AI di WordPress. Non usa OpenAI diretto.', 'wp-ai-publisher' ),
			),
			'ai_request_logging'     => array(
				'name'         => 'AI Request Logging',
				'status'       => $this->get_plugin_status( 'ai_request_logging' ),
				'required'     => false,
				'recommended'  => true,
				'purpose'      => __( 'Registra richieste AI per debug e osservabilità.', 'wp-ai-publisher' ),
				'download_url' => '',
				'admin_url'    => '',
				'notes'        => __( 'Consigliato in sviluppo e durante le prime fasi di test.', 'wp-ai-publisher' ),
			),
			'connector_approval'     => array(
				'name'         => 'Connector Approval',
				'status'       => $this->get_plugin_status( 'connector_approval' ),
				'required'     => false,
				'recommended'  => true,
				'purpose'      => __( 'Richiede approvazione amministratore prima che plugin o temi usino i connector AI.', 'wp-ai-publisher' ),
				'download_url' => '',
				'admin_url'    => '',
				'notes'        => __( 'Consigliato per sicurezza.', 'wp-ai-publisher' ),
			),
			'abilities_explorer'     => array(
				'name'         => 'Abilities Explorer',
				'status'       => $this->get_plugin_status( 'abilities_explorer' ),
				'required'     => false,
				'recommended'  => true,
				'purpose'      => __( 'Permette di ispezionare le abilità registrate tramite WordPress Abilities API.', 'wp-ai-publisher' ),
				'download_url' => '',
				'admin_url'    => '',
				'notes'        => __( 'Molto utile in fase sviluppo per capire quali ability AI sono disponibili.', 'wp-ai-publisher' ),
			),
			'aioseo'                 => array(
				'name'         => 'AIOSEO / All in One SEO',
				'status'       => $this->get_plugin_status( 'aioseo' ),
				'required'     => false,
				'recommended'  => true,
				'purpose'      => __( 'Permette integrazione futura con title SEO, meta description e dati social.', 'wp-ai-publisher' ),
				'download_url' => 'https://aioseo.com/',
				'admin_url'    => '',
				'notes'        => __( 'Rilevato o consigliato per le prossime fasi SEO.', 'wp-ai-publisher' ),
			),
		);
	}

	/**
	 * Return a status for a supported plugin key.
	 *
	 * @param string $plugin_key Plugin key.
	 * @return string
	 */
	public function get_plugin_status( $plugin_key ) {
		switch ( $plugin_key ) {
			case 'git_updater':
				return $this->status_from_files_and_detection(
					array( 'git-updater/git-updater.php' ),
					$this->has_any_class( array( 'Fragen\\Git_Updater\\Bootstrap', 'Fragen\\Git_Updater\\Base' ) ) || defined( 'GIT_UPDATER_VERSION' ),
					'missing'
				);

			case 'git_remote_updater':
				return $this->status_from_files_and_detection(
					array( 'git-remote-updater/git-remote-updater.php' ),
					$this->has_any_class( array( 'Fragen\\Git_Remote_Updater\\Bootstrap', 'Fragen\\Git_Remote_Updater\\Base', 'Git_Remote_Updater' ) ) || defined( 'GIT_REMOTE_UPDATER_VERSION' ),
					'missing'
				);

			case 'wordpress_ai':
				return $this->is_wordpress_ai_available() ? 'detected' : 'not_detected';

			case 'ai_request_logging':
				return $this->status_from_files_and_detection(
					array( 'ai-request-logging/ai-request-logging.php' ),
					$this->is_ai_request_logging_available(),
					'not_detected'
				);

			case 'connector_approval':
				return $this->status_from_files_and_detection(
					array( 'connector-approval/connector-approval.php' ),
					$this->is_connector_approval_available(),
					'not_detected'
				);

			case 'abilities_explorer':
				return $this->status_from_files_and_detection(
					array( 'abilities-explorer/abilities-explorer.php' ),
					$this->is_abilities_explorer_available(),
					'not_detected'
				);

			case 'aioseo':
				return $this->status_from_files_and_detection(
					array( 'all-in-one-seo-pack/all_in_one_seo_pack.php', 'all-in-one-seo-pack-pro/all_in_one_seo_pack.php' ),
					defined( 'AIOSEO_VERSION' ) || class_exists( '\\AIOSEO\\Plugin\\AIOSEO' ) || function_exists( 'aioseo' ),
					'missing'
				);
		}

		return 'not_detected';
	}

	/**
	 * Check if a plugin file is active, loading WordPress helpers only when needed.
	 *
	 * @param string $plugin_file Relative plugin file.
	 * @return bool
	 */
	public function is_plugin_active_by_file( $plugin_file ) {
		$this->load_plugin_helpers();

		return function_exists( 'is_plugin_active' ) && is_plugin_active( $plugin_file );
	}

	/**
	 * Detect Git Updater.
	 *
	 * @return bool
	 */
	public function is_git_updater_active() {
		return 'active' === $this->get_plugin_status( 'git_updater' ) || 'detected' === $this->get_plugin_status( 'git_updater' );
	}

	/**
	 * Detect Git Remote Updater.
	 *
	 * @return bool
	 */
	public function is_git_remote_updater_active() {
		return 'active' === $this->get_plugin_status( 'git_remote_updater' ) || 'detected' === $this->get_plugin_status( 'git_remote_updater' );
	}

	/**
	 * Detect All in One SEO.
	 *
	 * @return bool
	 */
	public function is_aioseo_active() {
		return 'active' === $this->get_plugin_status( 'aioseo' ) || 'detected' === $this->get_plugin_status( 'aioseo' );
	}

	/**
	 * Detect the WordPress AI layer without making remote calls.
	 *
	 * @return bool
	 */
	public function is_wordpress_ai_available() {
		$functions = array(
			'wp_ai_client',
			'wp_get_ai_client',
			'wp_ai_generate_text',
			'wp_ai_get_models',
			'wp_get_ai_models',
			'wp_ai_get_available_models',
			'wp_ai_services',
		);

		$classes = array(
			'WP_AI_Client',
			'WordPress\\AI\\Client',
			'WP_AI\\Client',
			'WordPress\\AI\\Services\\Services_API',
			'WordPress\\AI\\Services\\AI_Service',
		);

		return $this->has_any_function( $functions ) || $this->has_any_class( $classes ) || (bool) apply_filters( 'wpai_publisher_wordpress_ai_available', false );
	}

	/**
	 * Detect AI Request Logging.
	 *
	 * @return bool
	 */
	public function is_ai_request_logging_available() {
		$detected = $this->has_any_function( array( 'wp_ai_request_logging', 'wp_ai_request_logger', 'wp_ai_log_request' ) )
			|| $this->has_any_class( array( 'AI_Request_Logging', 'WP_AI_Request_Logging', 'WordPress\\AI\\RequestLogging\\Plugin', 'WordPress\\AI\\Request_Logging\\Plugin', 'WP_AI\\Request_Logging\\Plugin' ) )
			|| defined( 'AI_REQUEST_LOGGING_VERSION' )
			|| defined( 'WP_AI_REQUEST_LOGGING_VERSION' );

		return (bool) apply_filters( 'wpai_publisher_ai_request_logging_available', $detected );
	}

	/**
	 * Detect Connector Approval.
	 *
	 * @return bool
	 */
	public function is_connector_approval_available() {
		$detected = $this->has_any_function( array( 'wp_ai_connector_approval', 'wp_ai_is_connector_approved', 'connector_approval' ) )
			|| $this->has_any_class( array( 'Connector_Approval', 'WP_AI_Connector_Approval', 'WordPress\\AI\\ConnectorApproval\\Plugin', 'WordPress\\AI\\Connector_Approval\\Plugin', 'WP_AI\\Connector_Approval\\Plugin' ) )
			|| defined( 'CONNECTOR_APPROVAL_VERSION' )
			|| defined( 'WP_AI_CONNECTOR_APPROVAL_VERSION' );

		return (bool) apply_filters( 'wpai_publisher_connector_approval_available', $detected );
	}

	/**
	 * Detect Abilities Explorer.
	 *
	 * @return bool
	 */
	public function is_abilities_explorer_available() {
		$detected = $this->has_any_function( array( 'wp_abilities_explorer', 'abilities_explorer', 'wp_ai_abilities_explorer' ) )
			|| $this->has_any_class( array( 'Abilities_Explorer', 'WP_Abilities_Explorer', 'WordPress\\Abilities\\Explorer\\Plugin', 'WordPress\\AI\\Abilities_Explorer\\Plugin' ) )
			|| defined( 'ABILITIES_EXPLORER_VERSION' )
			|| defined( 'WP_ABILITIES_EXPLORER_VERSION' );

		return (bool) apply_filters( 'wpai_publisher_abilities_explorer_available', $detected );
	}

	/**
	 * Build a status from plugin files and local detection indicators.
	 *
	 * @param array<int,string> $plugin_files Plugin files.
	 * @param bool              $detected Detection result.
	 * @param string            $missing_status Missing status key.
	 * @return string
	 */
	private function status_from_files_and_detection( $plugin_files, $detected, $missing_status ) {
		foreach ( $plugin_files as $plugin_file ) {
			if ( $this->is_plugin_active_by_file( $plugin_file ) ) {
				return 'active';
			}
		}

		if ( $detected ) {
			return 'detected';
		}

		foreach ( $plugin_files as $plugin_file ) {
			if ( $this->is_plugin_installed_by_file( $plugin_file ) ) {
				return 'inactive';
			}
		}

		return $missing_status;
	}

	/**
	 * Check if a plugin file exists among installed plugins.
	 *
	 * @param string $plugin_file Relative plugin file.
	 * @return bool
	 */
	private function is_plugin_installed_by_file( $plugin_file ) {
		$this->load_plugin_helpers();

		if ( ! function_exists( 'get_plugins' ) ) {
			return false;
		}

		$plugins = get_plugins();

		return is_array( $plugins ) && isset( $plugins[ $plugin_file ] );
	}

	/**
	 * Load WordPress plugin helper functions when available.
	 *
	 * @return void
	 */
	private function load_plugin_helpers() {
		if ( ( function_exists( 'is_plugin_active' ) && function_exists( 'get_plugins' ) ) || ! defined( 'ABSPATH' ) ) {
			return;
		}

		$plugin_helpers = ABSPATH . 'wp-admin/includes/plugin.php';

		if ( is_readable( $plugin_helpers ) ) {
			require_once $plugin_helpers;
		}
	}

	/**
	 * Check multiple functions defensively.
	 *
	 * @param array<int,string> $functions Function names.
	 * @return bool
	 */
	private function has_any_function( $functions ) {
		foreach ( $functions as $function_name ) {
			if ( function_exists( $function_name ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check multiple classes defensively.
	 *
	 * @param array<int,string> $classes Class names.
	 * @return bool
	 */
	private function has_any_class( $classes ) {
		foreach ( $classes as $class_name ) {
			if ( class_exists( $class_name ) ) {
				return true;
			}
		}

		return false;
	}


}
