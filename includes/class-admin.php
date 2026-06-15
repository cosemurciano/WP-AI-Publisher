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
		add_action( 'admin_post_wpai_publisher_approve_content_idea', array( $this, 'handle_approve_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_reject_content_idea', array( $this, 'handle_reject_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_create_draft_from_idea', array( $this, 'handle_create_draft_from_idea' ) );
		add_action( 'admin_post_wpai_publisher_generate_full_article', array( $this, 'handle_generate_full_article' ) );
		add_action( 'admin_post_wpai_publisher_assign_article_type_to_idea', array( $this, 'handle_assign_article_type_to_idea' ) );
		add_action( 'admin_post_wpai_publisher_save_article_type', array( $this, 'handle_save_article_type' ) );
		add_action( 'admin_post_wpai_publisher_delete_article_type', array( $this, 'handle_delete_article_type' ) );
		add_action( 'admin_post_wpai_publisher_toggle_article_type', array( $this, 'handle_toggle_article_type' ) );
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
			esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ),
			esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ),
			'manage_options',
			'wp-ai-publisher-article-types',
			array( $this, 'render_article_types' )
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
				'article_type_id' => absint( $_POST['article_type_id'] ?? 0 ),
				'_wpnonce'        => sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $idea_id ) ) {
			$args = array(
				'wpai_notice' => 'wpai_content_ideas_table_missing' === $idea_id->get_error_code() ? 'content_ideas_table_missing' : 'idea_save_failed',
			);
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$args['wpai_debug'] = rawurlencode( sanitize_text_field( $idea_id->get_error_message() ) );
			}
			$this->redirect_content_ideas( $args );
		}

		$creation_mode = sanitize_key( wp_unslash( $_POST['wpai_creation_mode'] ?? '' ) );
		$settings      = wpai_publisher_get_settings();
		if ( 'create_draft' === $creation_mode && ! wpai_publisher_is_active_article_type_safe( absint( $_POST['article_type_id'] ?? 0 ) ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'missing_article_type', 'idea_id' => absint( $idea_id ) ) );
		}
		if ( 'create_draft' === $creation_mode || ( 'save_only' !== $creation_mode && ! empty( $settings['auto_create_draft_from_idea'] ) ) ) {
			$this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => absint( $idea_id ), 'mode' => 'auto_draft' ), 5 );
			$result = $this->content_ideas->process_idea_to_draft( absint( $idea_id ) );
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => ! empty( $result['success'] ) ? 'draft_created' : ( 'article_type' === ( $result['step_failed'] ?? '' ) ? 'missing_article_type' : ( 'full_article' === ( $result['step_failed'] ?? '' ) ? 'full_article_failed' : ( 'draft' === ( $result['step_failed'] ?? '' ) && false !== strpos( (string) ( $result['message'] ?? '' ), 'Genera prima' ) ? 'missing_full_article' : 'draft_creation_failed' ) ) ),
					'wpai_step'   => sanitize_key( (string) ( $result['step_failed'] ?? '' ) ),
					'wpai_error'  => rawurlencode( sanitize_text_field( (string) ( $result['error_code'] ?? $result['message'] ?? '' ) ) ),
					'view_idea'   => absint( $idea_id ),
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . absint( $idea_id ) ),
				)
			);
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
					'wpai_notice' => is_wp_error( $result ) && 'wpai_content_idea_missing_article_type' === $result->get_error_code() ? 'missing_article_type' : 'dry_run_failed',
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

	public function handle_assign_article_type_to_idea() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}
		if ( ! wpai_publisher_article_types_enabled() ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'article_type_assign_failed' ) );
		}
		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_assign_article_type_to_idea_' . $idea_id );
		$article_type_id = absint( $_POST['article_type_id'] ?? 0 );
		$assigned = method_exists( $this->content_ideas, 'assign_article_type' ) ? $this->content_ideas->assign_article_type( $idea_id, $article_type_id ) : false;
		$this->redirect_content_ideas(
			array(
				'wpai_notice' => $assigned ? 'article_type_assigned' : 'article_type_assign_failed',
				'view_idea'   => $idea_id,
				'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
			)
		);
	}


	/**
	 * Handle full article generation.
	 *
	 * @return void
	 */
	public function handle_generate_full_article() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_generate_full_article_' . $idea_id );

		$result = $this->content_ideas->generate_full_article( $idea_id );
		$this->redirect_content_ideas(
			array(
				'wpai_notice' => is_wp_error( $result ) ? 'full_article_failed' : 'full_article_generated',
				'view_idea'   => $idea_id,
				'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
			)
		);
	}

	/**
	 * Handle dry-run approval.
	 *
	 * @return void
	 */
	public function handle_approve_content_idea() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_approve_content_idea_' . $idea_id );

		$result = $this->content_ideas->approve_idea( $idea_id );
		$this->redirect_content_ideas(
			array(
				'wpai_notice' => is_wp_error( $result ) || false === $result ? 'idea_not_found' : 'dry_run_approved',
				'view_idea'   => $idea_id,
				'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
			)
		);
	}

	/**
	 * Handle dry-run rejection.
	 *
	 * @return void
	 */
	public function handle_reject_content_idea() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_reject_content_idea_' . $idea_id );

		$result = $this->content_ideas->reject_idea( $idea_id );
		$this->redirect_content_ideas(
			array(
				'wpai_notice' => is_wp_error( $result ) || false === $result ? 'idea_not_found' : 'dry_run_rejected',
				'view_idea'   => $idea_id,
				'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
			)
		);
	}

	/**
	 * Handle draft creation from an approved idea.
	 *
	 * @return void
	 */
	public function handle_create_draft_from_idea() {
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_create_draft_from_idea_' . $idea_id );

		$idea = $this->content_ideas->get_idea( $idea_id );
		if ( ! $idea ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_not_found' ) );
		}

		if ( in_array( sanitize_key( (string) $idea->status ), array( 'new', 'dry_run_failed', 'draft_failed' ), true ) ) {
			$this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => $idea_id, 'mode' => 'auto_draft' ), 5 );
			$result = $this->content_ideas->process_idea_to_draft( $idea_id );
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => ! empty( $result['success'] ) ? 'draft_created' : ( 'full_article' === ( $result['step_failed'] ?? '' ) ? 'full_article_failed' : ( 'draft' === ( $result['step_failed'] ?? '' ) && false !== strpos( (string) ( $result['message'] ?? '' ), 'Genera prima' ) ? 'missing_full_article' : 'draft_creation_failed' ) ),
					'view_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
				)
			);
		}

		if ( ! in_array( sanitize_key( (string) $idea->status ), array( 'approved', 'full_article_ready' ), true ) ) {
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => 'draft_not_approved',
					'view_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
				)
			);
		}

		$existing_post_id = absint( $idea->draft_post_id ?? 0 );
		if ( $existing_post_id > 0 && ! get_post( $existing_post_id ) ) {
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => 'linked_draft_not_found',
					'view_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
				)
			);
		}

		if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => 'draft_already_exists',
					'view_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ),
				)
			);
		}

		$creator = new Draft_Creator( $this->db, $this->logger );
		$result  = $creator->create_draft_from_idea( $idea );

		$this->redirect_content_ideas(
			array(
				'wpai_notice' => is_wp_error( $result ) && 'wpai_draft_creator_missing_full_article' === $result->get_error_code() ? 'missing_full_article' : ( is_wp_error( $result ) ? 'draft_creation_failed' : 'draft_created' ),
				'wpai_step'   => is_wp_error( $result ) ? 'draft_insert' : '',
				'wpai_error'  => is_wp_error( $result ) ? rawurlencode( sanitize_text_field( $result->get_error_code() . ': ' . $result->get_error_message() ) ) : '',
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
		$article_types_enabled = wpai_publisher_article_types_enabled();
		$active_article_types = $article_types_enabled ? wpai_publisher_get_active_article_types_safe() : array();
		$article_types_url = admin_url( 'admin.php?page=wp-ai-publisher-article-types' );
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
	 * Render a safe fallback when article types CPT is not available.
	 *
	 * @return void
	 */
	public function handle_save_article_type() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_save_article_type_' . $id );
		$repo = new Article_Type_Repository();
		$data = wp_unslash( $_POST );
		$result = $id > 0 ? $repo->update_article_type( $id, $data ) : $repo->create_article_type( $data );
		wp_safe_redirect( add_query_arg( 'wpai_notice', $result ? 'article_type_saved' : 'article_type_save_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function handle_delete_article_type() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_delete_article_type_' . $id );
		$repo = new Article_Type_Repository();
		$deleted = $repo->delete_article_type( $id );
		wp_safe_redirect( add_query_arg( 'wpai_notice', $deleted ? 'article_type_deleted' : 'article_type_delete_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function handle_toggle_article_type() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_toggle_article_type_' . $id );
		$repo = new Article_Type_Repository();
		$type = $repo->get_article_type( $id );
		$updated = $type ? $repo->update_article_type( $id, array_merge( $type, array( 'is_active' => empty( $type['is_active'] ) ? 1 : 0 ) ) ) : false;
		wp_safe_redirect( add_query_arg( 'wpai_notice', $updated ? 'article_type_toggled' : 'article_type_toggle_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function render_article_types() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$repo = new Article_Type_Repository();
		$article_type_id = absint( $_GET['article_type_id'] ?? 0 );
		$article_type = $article_type_id > 0 ? $repo->get_article_type( $article_type_id ) : null;
		if ( 'new' === sanitize_key( $_GET['action'] ?? '' ) || $article_type ) { include WPAIP_PLUGIN_DIR . 'admin/views/article-type-edit.php'; return; }
		$article_types = $repo->get_all_article_types();
		include WPAIP_PLUGIN_DIR . 'admin/views/article-types.php';
	}

	public function render_article_types_unavailable() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		echo '<div class="wrap wpai-admin"><h1>' . esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ) . '</h1><div class="notice notice-warning inline"><p>' . esc_html__( 'Il modulo Tipologie articolo è temporaneamente disabilitato in questa versione di recovery.', 'wp-ai-publisher' ) . '</p></div></div>';
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
		$content_idea_counts = $this->content_ideas->count_by_status();
		$active_article_types_count = count( wpai_publisher_get_active_article_types_safe() );
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
