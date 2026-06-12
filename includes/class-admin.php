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
	 * Content ideas service.
	 *
	 * @var Content_Ideas
	 */
	private $content_ideas;

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
	 * @param Content_Ideas       $content_ideas Content ideas service.
	 */
	public function __construct( DB $db, Logger $logger, Settings $settings, AI_Provider_Adapter $ai_provider, Job_Queue $job_queue, Content_Ideas $content_ideas ) {
		$this->db            = $db;
		$this->logger        = $logger;
		$this->settings      = $settings;
		$this->ai_provider   = $ai_provider;
		$this->job_queue     = $job_queue;
		$this->content_ideas = $content_ideas;

		add_action( 'admin_post_wpai_publisher_create_content_idea', array( $this, 'handle_create_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_run_content_idea_dry_run', array( $this, 'handle_run_content_idea_dry_run' ) );
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
			esc_html__( 'Idee contenuto', 'wp-ai-publisher' ),
			esc_html__( 'Idee contenuto', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-content-ideas',
			array( $this, 'render_content_ideas' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Diagnostica AI', 'wp-ai-publisher' ),
			esc_html__( 'Diagnostica AI', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-ai-diagnostics',
			array( $this, 'render_ai_diagnostics' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-jobs',
			array( $this, 'render_jobs' )
		);

		// Le voci Impostazioni e Stato sistema devono restare sempre alla fine del menu.
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
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-system-status',
			array( $this, 'render_system_status' )
		);
	}


	/**
	 * Handle content idea creation.
	 *
	 * @return void
	 */
	public function handle_create_content_idea() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		check_admin_referer( 'wpai_publisher_create_content_idea' );

		$idea_id = $this->content_ideas->create_idea(
			array(
				'topic'           => sanitize_textarea_field( wp_unslash( $_POST['topic'] ?? '' ) ),
				'keyword'         => sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) ),
				'language'        => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'it' ) ),
				'target_audience' => sanitize_text_field( wp_unslash( $_POST['target_audience'] ?? '' ) ),
				'tutorial_level'  => sanitize_text_field( wp_unslash( $_POST['tutorial_level'] ?? '' ) ),
				'notes'           => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $idea_id ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_save_failed' ) );
		}

		$this->redirect_content_ideas(
			array(
				'wpai_notice' => 'idea_saved',
				'idea_id'     => absint( $idea_id ),
			)
		);
	}

	/**
	 * Handle dry-run execution.
	 *
	 * @return void
	 */
	public function handle_run_content_idea_dry_run() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		check_admin_referer( 'wpai_publisher_run_content_idea_dry_run' );

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		if ( 0 === $idea_id || ! $this->content_ideas->get_idea( $idea_id ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_not_found' ) );
		}

		$result = $this->content_ideas->run_dry_run( $idea_id );
		if ( is_wp_error( $result ) || empty( $result['valid'] ) ) {
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => 'dry_run_failed',
					'view_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
				)
			);
		}

		$this->redirect_content_ideas(
			array(
				'wpai_notice' => 'dry_run_completed',
				'view_idea'   => $idea_id,
				'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
			)
		);
	}

	/**
	 * Render content ideas page.
	 *
	 * @return void
	 */
	public function render_content_ideas() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$content_ideas = $this->content_ideas;
		$ideas         = $content_ideas->get_recent_ideas( 20 );
		$selected_idea = null;
		$dry_run_data  = array();
		$notes_data    = array();
		$view_idea_id  = absint( $_GET['view_idea'] ?? 0 );

		if ( $view_idea_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpai_publisher_view_content_idea_' . $view_idea_id ) ) {
			$selected_idea = $content_ideas->get_idea( $view_idea_id );
			if ( $selected_idea && ! empty( $selected_idea->dry_run_output ) ) {
				$decoded_output = json_decode( (string) $selected_idea->dry_run_output, true );
				$decoded_notes  = json_decode( (string) $selected_idea->validation_notes, true );
				$dry_run_data   = is_array( $decoded_output ) ? $decoded_output : array();
				$notes_data     = is_array( $decoded_notes ) ? $decoded_notes : array();

				if ( ! empty( $dry_run_data ) && empty( $dry_run_data['classic_editor_preview'] ) ) {
					$classic_builder = new Classic_Content_Builder();
					$dry_run_data['classic_editor_preview'] = $classic_builder->build_from_dry_run( $dry_run_data );
				}

			}
		}

		include WPAIP_PLUGIN_DIR . 'admin/views/content-ideas.php';
	}

	/**
	 * Redirect to content ideas page and stop execution.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return void
	 */
	private function redirect_content_ideas( $args = array() ) {
		wp_safe_redirect(
			add_query_arg(
				$args,
				admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' )
			)
		);
		exit;
	}

	/**
	 * Render AI diagnostics page.
	 *
	 * @return void
	 */
	public function render_ai_diagnostics() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$diagnostics = new AI_Diagnostics( $this->ai_provider, $this->logger );
		$test_result = null;

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['wpai_publisher_ai_diagnostics_action'] ) ) {
			check_admin_referer( 'wpai_publisher_ai_diagnostics_test', 'wpai_publisher_ai_diagnostics_nonce' );

			$action = sanitize_key( wp_unslash( $_POST['wpai_publisher_ai_diagnostics_action'] ) );
			if ( 'run_safe_generation_test' === $action ) {
				$test_result = $diagnostics->run_safe_generation_test();
			}
		}

		$report = $diagnostics->get_report();
		include WPAIP_PLUGIN_DIR . 'admin/views/ai-diagnostics.php';
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
