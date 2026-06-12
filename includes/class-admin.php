<?php
/**
 * Admin menu and page rendering.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin UI registration.
 */
class Admin {
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
	 * Settings service.
	 *
	 * @var Settings
	 */
	private $settings;

	/**
	 * Job queue service.
	 *
	 * @var Job_Queue
	 */
	private $job_queue;

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
	 * @param Settings            $settings Settings service.
	 * @param AI_Provider_Adapter $ai_provider AI adapter.
	 * @param Job_Queue           $job_queue Job queue service.
	 */
	public function __construct( DB $db, Logger $logger, Settings $settings, AI_Provider_Adapter $ai_provider, Job_Queue $job_queue ) {
		$this->db          = $db;
		$this->logger      = $logger;
		$this->settings    = $settings;
		$this->ai_provider = $ai_provider;
		$this->job_queue   = $job_queue;
	}

	/**
	 * Register admin menu and submenus.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_menu_page(
			esc_html__( 'WP AI Publisher', 'wp-ai-publisher' ),
			esc_html__( 'WP AI Publisher', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-write-blog',
			58
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Bacheca', 'wp-ai-publisher' ),
			esc_html__( 'Bacheca', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Impostazioni', 'wp-ai-publisher' ),
			esc_html__( 'Impostazioni', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-settings',
			array( $this->settings, 'render_page' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-jobs',
			array( $this, 'render_jobs' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-system-status',
			array( $this, 'render_system_status' )
		);
	}

	/**
	 * Render jobs queue page.
	 *
	 * @return void
	 */
	public function render_jobs() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$job_queue    = $this->job_queue;
		$status_counts = $job_queue->count_by_status();
		$jobs          = $job_queue->get_recent_jobs( 20 );
		include WPAIP_PLUGIN_DIR . 'admin/views/jobs.php';
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$ai_status           = $this->ai_provider->get_status();
		$db_status           = $this->db->check_tables();
		$third_party_plugins = array();

		if ( class_exists( __NAMESPACE__ . '\\Third_Party_Plugins' ) ) {
			$third_party         = new Third_Party_Plugins();
			$third_party_plugins = $third_party->get_plugins();
		}

		include WPAIP_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	/**
	 * Render system status page.
	 *
	 * @return void
	 */
	public function render_system_status() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$system_status = new System_Status( $this->db, $this->logger, $this->ai_provider );
		$checks        = $system_status->get_checks();
		$critical_logs = $system_status->get_last_critical_errors();
		include WPAIP_PLUGIN_DIR . 'admin/views/system-status.php';
	}
}
