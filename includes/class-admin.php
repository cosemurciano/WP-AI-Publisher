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
	 * Article type repository.
	 *
	 * @var Article_Type_Repository|null
	 */
	private $article_type_repository;

	/**
	 * Guide assistant.
	 *
	 * @var Guide_Assistant|null
	 */
	private $guide_assistant;

	/**
	 * Access control service.
	 *
	 * @var Access_Control|null
	 */
	private $access_control;

	/**
	 * Constructor.
	 *
	 * @param DB                           $db Database service.
	 * @param Logger                       $logger Logger service.
	 * @param Settings                     $settings Settings service.
	 * @param AI_Provider_Adapter          $ai_provider AI adapter.
	 * @param Job_Queue                    $job_queue Job queue service.
	 * @param Content_Ideas                $content_ideas Content ideas service.
	 * @param Article_Type_Repository|null $article_type_repository Article type repository.
	 * @param Guide_Assistant|null         $guide_assistant Guide assistant.
	 * @param Access_Control|null          $access_control Access control service.
	 */
	public function __construct( DB $db, Logger $logger, Settings $settings, AI_Provider_Adapter $ai_provider, Job_Queue $job_queue, Content_Ideas $content_ideas, ?Article_Type_Repository $article_type_repository = null, ?Guide_Assistant $guide_assistant = null, ?Access_Control $access_control = null ) {
		$this->db            = $db;
		$this->logger        = $logger;
		$this->settings      = $settings;
		$this->ai_provider   = $ai_provider;
		$this->job_queue     = $job_queue;
		$this->content_ideas = $content_ideas;
		$this->article_type_repository = $article_type_repository;
		$this->guide_assistant = $guide_assistant;
		$this->access_control = $access_control;

		add_action( 'admin_post_wpai_publisher_create_content_idea', array( $this, 'handle_create_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_approve_content_idea', array( $this, 'handle_approve_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_reject_content_idea', array( $this, 'handle_reject_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_create_draft_from_idea', array( $this, 'handle_create_draft_from_idea' ) );
		add_action( 'admin_post_wpai_publisher_regenerate_draft_from_idea', array( $this, 'handle_regenerate_draft_from_idea' ) );
		add_action( 'admin_post_wpai_publisher_process_idea_job_now', array( $this, 'handle_process_idea_job_now' ) );
		add_action( 'admin_post_wpai_publisher_assign_article_type_to_idea', array( $this, 'handle_assign_article_type_to_idea' ) );
		add_action( 'admin_post_wpai_publisher_update_content_idea', array( $this, 'handle_update_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_delete_content_idea', array( $this, 'handle_delete_content_idea' ) );
		add_action( 'admin_post_wpai_publisher_bulk_delete_ideas', array( $this, 'handle_bulk_delete_ideas' ) );
		add_action( 'admin_post_wpai_publisher_save_article_type', array( $this, 'handle_save_article_type' ) );
		add_action( 'admin_post_wpai_publisher_delete_article_type', array( $this, 'handle_delete_article_type' ) );
		add_action( 'admin_post_wpai_publisher_toggle_article_type', array( $this, 'handle_toggle_article_type' ) );
		add_action( 'admin_post_wpai_publisher_test_openai_file_search', array( $this, 'handle_test_openai_file_search' ) );
	}

	/**
	 * Admin action: test the OpenAI file_search / vector store connectivity.
	 *
	 * @return void
	 */
	public function handle_test_openai_file_search() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_test_openai_file_search' );

		$result = method_exists( $this->ai_provider, 'test_openai_file_search' )
			? $this->ai_provider->test_openai_file_search()
			: array( 'ok' => false, 'message' => __( 'Funzione non disponibile.', 'wp-ai-publisher' ) );

		set_transient(
			'wpai_publisher_openai_fs_notice_' . get_current_user_id(),
			array( 'ok' => ! empty( $result['ok'] ), 'message' => sanitize_text_field( (string) ( $result['message'] ?? '' ) ) ),
			60
		);
		wp_safe_redirect( add_query_arg( 'wpai_notice', 'openai_file_search', admin_url( 'admin.php?page=wp-ai-publisher-settings' ) ) );
		exit;
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
			wpai_publisher_capability(),
			'wp-ai-publisher',
			array( $this, 'render_dashboard' ),
			'dashicons-welcome-write-blog',
			58
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Bacheca', 'wp-ai-publisher' ),
			esc_html__( 'Bacheca', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher',
			array( $this, 'render_dashboard' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Idee contenuto', 'wp-ai-publisher' ),
			esc_html__( 'Idee contenuto', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher-content-ideas',
			array( $this, 'render_content_ideas' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ),
			esc_html__( 'Tipologie articolo', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher-article-types',
			array( $this, 'render_article_types' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			esc_html__( 'Coda job', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher-jobs',
			array( $this, 'render_jobs' )
		);

		if ( $this->guide_assistant instanceof Guide_Assistant ) {
			add_submenu_page(
				'wp-ai-publisher',
				esc_html__( 'Assistente Guide AI', 'wp-ai-publisher' ),
				esc_html__( 'Assistente Guide AI', 'wp-ai-publisher' ),
				wpai_publisher_capability(),
				'wp-ai-publisher-guide-assistant',
				array( $this->guide_assistant, 'render_settings_page' )
			);

			add_submenu_page(
				'wp-ai-publisher',
				esc_html__( 'Richieste guide', 'wp-ai-publisher' ),
				esc_html__( 'Richieste guide', 'wp-ai-publisher' ),
				wpai_publisher_capability(),
				'wp-ai-publisher-guide-requests',
				array( $this->guide_assistant, 'render_requests_page' )
			);
		}

		if ( $this->access_control instanceof Access_Control ) {
			add_submenu_page(
				'wp-ai-publisher',
				esc_html__( 'Controllo accessi', 'wp-ai-publisher' ),
				esc_html__( 'Controllo accessi', 'wp-ai-publisher' ),
				wpai_publisher_capability(),
				'wp-ai-publisher-access',
				array( $this->access_control, 'render_settings_page' )
			);
		}

		// Le voci Impostazioni e Stato sistema devono restare sempre alla fine del menu.
		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Impostazioni', 'wp-ai-publisher' ),
			esc_html__( 'Impostazioni', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher-settings',
			array( $this->settings, 'render_page' )
		);

		add_submenu_page(
			'wp-ai-publisher',
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			esc_html__( 'Stato sistema', 'wp-ai-publisher' ),
			wpai_publisher_capability(),
			'wp-ai-publisher-system-status',
			array( $this, 'render_system_status' )
		);
	}


	/**
	 * Read the category selection posted by the idea form.
	 *
	 * The checklist submits an array of term IDs in $_POST['tax_input']['category'].
	 * A legacy comma-separated string (older tag-box markup) is still accepted and
	 * passed through unchanged for Content_Ideas to resolve.
	 *
	 * @return array<int,int>|string
	 */
	private function read_posted_category_ids() {
		if ( ! isset( $_POST['tax_input']['category'] ) ) {
			return array();
		}
		$raw = wp_unslash( $_POST['tax_input']['category'] );
		if ( is_array( $raw ) ) {
			return array_values( array_filter( array_map( 'absint', $raw ) ) );
		}
		return sanitize_text_field( (string) $raw );
	}

	/**
	 * Handle content idea creation.
	 *
	 * @return void
	 */
	public function handle_create_content_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		check_admin_referer( 'wpai_publisher_create_content_idea' );

		$creation_mode = sanitize_key( wp_unslash( $_POST['wpai_creation_mode'] ?? '' ) );
		$scheduled_at  = ( 'schedule' === $creation_mode ) ? sanitize_text_field( wp_unslash( $_POST['wpai_scheduled_at'] ?? '' ) ) : '';

		$posted_categories = $this->read_posted_category_ids();
		$idea_id = $this->content_ideas->create_idea(
			array(
				'topic'            => sanitize_textarea_field( wp_unslash( $_POST['topic'] ?? '' ) ),
				'language'         => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'it' ) ),
				'article_type_id'  => absint( $_POST['article_type_id'] ?? 0 ),
				'category_ids'     => $posted_categories,
				'image_prompt'     => sanitize_textarea_field( wp_unslash( $_POST['image_prompt'] ?? '' ) ),
				'social_facebook'  => sanitize_textarea_field( wp_unslash( $_POST['social_facebook'] ?? '' ) ),
				'social_instagram' => sanitize_textarea_field( wp_unslash( $_POST['social_instagram'] ?? '' ) ),
				'social_linkedin'  => sanitize_textarea_field( wp_unslash( $_POST['social_linkedin'] ?? '' ) ),
				'scheduled_at'     => $scheduled_at,
				'_wpnonce'         => sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $idea_id ) ) {
			$args = array(
				'wpai_notice' => 'wpai_content_ideas_table_missing' === $idea_id->get_error_code() ? 'content_ideas_table_missing' : 'idea_save_failed',
			);
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				$args['wpai_debug'] = sanitize_text_field( $idea_id->get_error_message() );
			}
			$this->redirect_content_ideas( $args );
		}

		// Scheduled idea: the recurring cron will enqueue the draft job when due.
		if ( 'schedule' === $creation_mode ) {
			if ( wpai_publisher_article_types_enabled() && ! wpai_publisher_is_active_article_type_safe( absint( $_POST['article_type_id'] ?? 0 ) ) ) {
				$this->redirect_content_ideas( array( 'wpai_notice' => 'missing_article_type', 'idea_id' => absint( $idea_id ) ) );
			}
			// Announce the resulting draft on Telegram (like bulk-imported ideas),
			// so scheduled drafts also arrive for review when the cron creates them.
			if ( class_exists( __NAMESPACE__ . '\\Bulk_Import' ) ) {
				Bulk_Import::flag_idea_for_notify( absint( $idea_id ) );
			}
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_scheduled', 'idea_id' => absint( $idea_id ) ) );
		}

		$settings      = wpai_publisher_get_settings();
		if ( 'create_draft' === $creation_mode && wpai_publisher_article_types_enabled() && ! wpai_publisher_is_active_article_type_safe( absint( $_POST['article_type_id'] ?? 0 ) ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'missing_article_type', 'idea_id' => absint( $idea_id ) ) );
		}
		if ( 'create_draft' === $creation_mode || ( 'save_only' !== $creation_mode && ! empty( $settings['auto_create_draft_from_idea'] ) ) ) {
			$job_id = $this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => absint( $idea_id ), 'mode' => 'auto_draft' ), 5 );
			if ( ! $job_id ) {
				$this->content_ideas->mark_draft_failed( absint( $idea_id ), __( 'Impossibile creare il job di generazione bozza.', 'wp-ai-publisher' ) );
				$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_creation_failed', 'wpai_step' => 'queue', 'wpai_error' => __( 'Impossibile creare il job di generazione bozza.', 'wp-ai-publisher' ), 'view_idea' => absint( $idea_id ), '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . absint( $idea_id ) ) ) );
			}
			$this->content_ideas->attach_job_to_idea( absint( $idea_id ), absint( $job_id ) );
			$this->content_ideas->update_idea_status( absint( $idea_id ), 'processing' );
			$this->logger->info( __( 'Job creazione bozza creato.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_created', 'idea_id' => absint( $idea_id ), 'job_id' => absint( $job_id ) ) );
			$this->schedule_job_processor();
			$this->redirect_content_ideas( array( 'wpai_notice' => 'auto_draft_started', 'view_idea' => absint( $idea_id ), '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . absint( $idea_id ) ) ) );
		}

		$this->redirect_content_ideas(
			array(
				'wpai_notice' => 'idea_saved',
				'idea_id'     => absint( $idea_id ),
			)
		);
	}

	public function handle_delete_content_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}
		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_delete_content_idea_' . $idea_id );
		$deleted = $this->content_ideas->delete_idea( $idea_id );
		$this->redirect_content_ideas( array( 'wpai_notice' => $deleted ? 'idea_deleted' : 'idea_delete_failed' ) );
	}

	/**
	 * Handle editing an existing content idea.
	 *
	 * @return void
	 */
	public function handle_update_content_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}
		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_update_content_idea_' . $idea_id );

		$posted_categories = $this->read_posted_category_ids();
		$result = $this->content_ideas->update_idea(
			$idea_id,
			array(
				'topic'            => sanitize_textarea_field( wp_unslash( $_POST['topic'] ?? '' ) ),
				'language'         => sanitize_text_field( wp_unslash( $_POST['language'] ?? 'it' ) ),
				'article_type_id'  => absint( $_POST['article_type_id'] ?? 0 ),
				'category_ids'     => $posted_categories,
				'image_prompt'     => sanitize_textarea_field( wp_unslash( $_POST['image_prompt'] ?? '' ) ),
				'social_facebook'  => sanitize_textarea_field( wp_unslash( $_POST['social_facebook'] ?? '' ) ),
				'social_instagram' => sanitize_textarea_field( wp_unslash( $_POST['social_instagram'] ?? '' ) ),
				'social_linkedin'  => sanitize_textarea_field( wp_unslash( $_POST['social_linkedin'] ?? '' ) ),
				'scheduled_at'     => sanitize_text_field( wp_unslash( $_POST['wpai_scheduled_at'] ?? '' ) ),
			)
		);

		if ( is_wp_error( $result ) ) {
			$this->redirect_content_ideas(
				array(
					'wpai_notice' => 'idea_update_failed',
					'wpai_error'  => sanitize_text_field( $result->get_error_message() ),
					'edit_idea'   => $idea_id,
					'_wpnonce'    => wp_create_nonce( 'wpai_publisher_edit_content_idea_' . $idea_id ),
				)
			);
		}

		$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_updated' ) );
	}

	/**
	 * Handle bulk deletion of selected content ideas.
	 *
	 * @return void
	 */
	public function handle_bulk_delete_ideas() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}
		check_admin_referer( 'wpai_publisher_bulk_delete_ideas' );

		$ids = isset( $_POST['idea_ids'] ) && is_array( $_POST['idea_ids'] )
			? array_values( array_filter( array_map( 'absint', wp_unslash( $_POST['idea_ids'] ) ) ) )
			: array();

		$deleted = 0;
		foreach ( $ids as $idea_id ) {
			if ( $this->content_ideas->delete_idea( $idea_id ) ) {
				$deleted++;
			}
		}

		$this->redirect_content_ideas( array( 'wpai_notice' => $deleted > 0 ? 'ideas_bulk_deleted' : 'idea_delete_failed', 'wpai_count' => $deleted ) );
	}

	public function handle_assign_article_type_to_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
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
	 * Handle dry-run approval.
	 *
	 * @return void
	 */
	public function handle_approve_content_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
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
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
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
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_create_draft_from_idea_' . $idea_id );

		$idea = $this->content_ideas->get_idea( $idea_id );
		if ( ! $idea ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_not_found' ) );
		}

		$existing_post_id = absint( $idea->draft_post_id ?? 0 );
		if ( $existing_post_id > 0 && get_post( $existing_post_id ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_already_exists', 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
		}

		$status = sanitize_key( (string) $idea->status );
		if ( ! in_array( $status, array( 'new', 'dry_run_failed', 'draft_failed', 'dry_run_ready', 'approved', 'full_article_ready', 'processing' ), true ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_not_approved', 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
		}

		$job = $this->job_queue->get_active_draft_job_for_idea( $idea_id );
		if ( ! $job ) {
			$job_id = $this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => $idea_id, 'mode' => 'auto_draft' ), 5 );
			if ( ! $job_id ) {
				$this->content_ideas->mark_draft_failed( $idea_id, __( 'Impossibile creare il job di generazione bozza.', 'wp-ai-publisher' ) );
				$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_creation_failed', 'wpai_step' => 'queue', 'wpai_error' => __( 'Impossibile creare il job di generazione bozza.', 'wp-ai-publisher' ), 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
			}
			$this->content_ideas->attach_job_to_idea( $idea_id, absint( $job_id ) );
			$this->content_ideas->update_idea_status( $idea_id, 'processing' );
			$this->logger->info( __( 'Job creazione bozza creato.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_created', 'idea_id' => $idea_id, 'job_id' => absint( $job_id ) ) );
			$this->schedule_job_processor();
		}

		$this->redirect_content_ideas( array( 'wpai_notice' => 'auto_draft_started', 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
	}

	/**
	 * Regenerate the draft for an idea, creating a brand-new draft.
	 *
	 * The previously created draft post is left intact in WordPress; this detaches
	 * it from the idea and enqueues a fresh generation job, producing an
	 * additional draft.
	 *
	 * @return void
	 */
	public function handle_regenerate_draft_from_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}

		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_regenerate_draft_from_idea_' . $idea_id );

		$idea = $this->content_ideas->get_idea( $idea_id );
		if ( ! $idea ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'idea_not_found' ) );
		}

		// Detach the existing draft so a NEW one is created (old draft is kept).
		$this->content_ideas->reset_draft_link( $idea_id );

		$job = $this->job_queue->get_active_draft_job_for_idea( $idea_id );
		if ( ! $job ) {
			$job_id = $this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => $idea_id, 'mode' => 'auto_draft' ), 5 );
			if ( ! $job_id ) {
				$this->content_ideas->mark_draft_failed( $idea_id, __( 'Impossibile creare il job di rigenerazione bozza.', 'wp-ai-publisher' ) );
				$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_creation_failed', 'wpai_step' => 'queue', 'wpai_error' => __( 'Impossibile creare il job di rigenerazione bozza.', 'wp-ai-publisher' ), 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
			}
			$this->content_ideas->attach_job_to_idea( $idea_id, absint( $job_id ) );
			$this->content_ideas->update_idea_status( $idea_id, 'processing' );
			$this->logger->info( __( 'Job rigenerazione bozza creato.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_created', 'idea_id' => $idea_id, 'job_id' => absint( $job_id ), 'mode' => 'regenerate' ) );
			$this->schedule_job_processor();
		}

		$this->redirect_content_ideas( array( 'wpai_notice' => 'draft_regeneration_started', 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
	}

	public function handle_process_idea_job_now() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			$this->redirect_content_ideas( array( 'wpai_notice' => 'insufficient_permissions' ) );
		}
		$idea_id = absint( $_POST['idea_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_process_idea_job_now_' . $idea_id );
		$result = $this->process_idea_job_now( $idea_id );
		$this->redirect_content_ideas( array( 'wpai_notice' => ! empty( $result['success'] ) ? 'draft_created' : 'draft_creation_failed', 'wpai_step' => sanitize_key( (string) ( $result['step_failed'] ?? '' ) ), 'wpai_error' => sanitize_text_field( (string) ( $result['message'] ?? '' ) ), 'view_idea' => $idea_id, '_wpnonce' => wp_create_nonce( 'wpai_publisher_view_content_idea_' . $idea_id ) ) );
	}

	public function process_next_job() {
		// Process a small batch per cron run so low-traffic sites, where WP-Cron
		// fires infrequently, do not drain the queue one job at a time. The batch
		// size stays modest to keep each run within typical PHP time limits.
		$max_per_run = max( 1, (int) apply_filters( 'wpai_publisher_jobs_per_run', 5 ) );

		for ( $processed = 0; $processed < $max_per_run; $processed++ ) {
			$job = $this->job_queue->get_next_pending_job();
			if ( ! $job ) {
				break;
			}
			$this->process_job( (int) $job->id );
		}

		// If work remains, re-arm the processor instead of waiting for the next
		// organic cron tick.
		if ( $this->job_queue->get_next_pending_job() ) {
			$this->schedule_job_processor();
		}
	}

	private function process_idea_job_now( $idea_id ) {
		$idea = $this->content_ideas->get_idea( $idea_id );
		if ( ! $idea ) {
			return array( 'success' => false, 'step_failed' => 'idea', 'message' => __( 'Idea non trovata.', 'wp-ai-publisher' ) );
		}
		$post_id = absint( $idea->draft_post_id ?? 0 );
		if ( $post_id > 0 && get_post( $post_id ) ) {
			return array( 'success' => true, 'post_id' => $post_id, 'message' => __( 'La bozza esiste già.', 'wp-ai-publisher' ) );
		}
		$job = $this->job_queue->get_active_draft_job_for_idea( $idea_id );
		if ( ! $job ) {
			$job_id = $this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => $idea_id, 'mode' => 'manual' ), 5 );
			$this->content_ideas->attach_job_to_idea( $idea_id, absint( $job_id ) );
			$job = $this->job_queue->get_job( $job_id );
		}
		$this->logger->info( __( 'Retry manuale job creazione bozza.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_retry', 'idea_id' => $idea_id, 'job_id' => absint( $job->id ?? 0 ) ) );
		return $job ? $this->process_job( (int) $job->id ) : array( 'success' => false, 'step_failed' => 'queue', 'message' => __( 'Job non disponibile.', 'wp-ai-publisher' ) );
	}

	private function process_job( $job_id ) {
		$job = $this->job_queue->get_job( $job_id );
		if ( ! $job || 'generate_draft_from_idea' !== sanitize_key( (string) $job->job_type ) ) {
			return array( 'success' => false, 'step_failed' => 'queue', 'message' => __( 'Job non valido.', 'wp-ai-publisher' ) );
		}
		$payload = json_decode( (string) $job->payload, true );
		$idea_id = absint( $payload['idea_id'] ?? 0 );
		if ( ! $this->job_queue->claim_job( $job_id, 15 ) ) {
			return array( 'success' => false, 'step_failed' => 'lock', 'message' => __( 'Job già in lavorazione.', 'wp-ai-publisher' ) );
		}
		$this->logger->info( __( 'Job creazione bozza avviato.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_started', 'idea_id' => $idea_id, 'job_id' => absint( $job_id ) ) );
		$result = $this->content_ideas->process_idea_to_draft( $idea_id );
		if ( ! empty( $result['success'] ) ) {
			$this->job_queue->complete_job( $job_id, $result, absint( $result['post_id'] ?? 0 ) );
			return $result;
		}
		$message = sanitize_text_field( (string) ( $result['message'] ?? __( 'Creazione bozza fallita.', 'wp-ai-publisher' ) ) );
		$this->job_queue->fail_job( $job_id, $message, $result );
		$this->logger->warning( __( 'Job creazione bozza fallito.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'job_failed', 'idea_id' => $idea_id, 'job_id' => absint( $job_id ), 'step_failed' => sanitize_key( (string) ( $result['step_failed'] ?? '' ) ), 'message' => $message ) );
		return $result;
	}

	/**
	 * Cron callback: enqueue draft jobs for scheduled ideas that are now due.
	 *
	 * @return void
	 */
	public function process_scheduled_ideas() {
		if ( ! method_exists( $this->content_ideas, 'get_due_scheduled_ideas' ) ) {
			return;
		}
		$due       = $this->content_ideas->get_due_scheduled_ideas( 10 );
		$processed = false;
		foreach ( (array) $due as $idea ) {
			$idea_id = absint( $idea->id ?? 0 );
			if ( $idea_id <= 0 ) {
				continue;
			}
			$job_id = $this->job_queue->create_job( 'generate_draft_from_idea', array( 'idea_id' => $idea_id, 'mode' => 'scheduled' ), 5 );
			if ( ! $job_id ) {
				continue;
			}
			$this->content_ideas->attach_job_to_idea( $idea_id, absint( $job_id ) );
			$this->content_ideas->update_idea_status( $idea_id, 'processing' );
			$this->logger->info( __( 'Idea programmata accodata.', 'wp-ai-publisher' ), array( 'source' => 'job_queue', 'event' => 'scheduled_idea_enqueued', 'idea_id' => $idea_id, 'job_id' => absint( $job_id ) ) );
			$processed = true;
		}
		if ( $processed ) {
			$this->schedule_job_processor();
		}
	}

	private function schedule_job_processor() {
		if ( function_exists( 'wp_schedule_single_event' ) && ! wp_next_scheduled( 'wpai_publisher_process_jobs' ) ) {
			wp_schedule_single_event( time() + 10, 'wpai_publisher_process_jobs' );
		}
	}

	/**
	 * Render content ideas page.
	 *
	 * @return void
	 */
	public function render_content_ideas() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$content_ideas = $this->content_ideas;
		$job_queue = $this->job_queue;
		$article_types_enabled = wpai_publisher_article_types_enabled();
		$active_article_types = $article_types_enabled ? wpai_publisher_get_active_article_types_safe() : array();
		$article_types_url = admin_url( 'admin.php?page=wp-ai-publisher-article-types' );
		$this->content_ideas->mark_stale_processing_ideas( 15 );
		$ideas_per_page = (int) apply_filters( 'wpai_publisher_ideas_per_page', 20 );
		$ideas_per_page = min( 100, max( 5, $ideas_per_page ) );
		$idea_filters   = array(
			'status'          => isset( $_GET['idea_status'] ) ? sanitize_key( wp_unslash( $_GET['idea_status'] ) ) : '',
			'article_type_id' => absint( $_GET['idea_article_type'] ?? 0 ),
			'category_id'     => absint( $_GET['idea_category'] ?? 0 ),
			'orderby'         => isset( $_GET['idea_orderby'] ) ? sanitize_key( wp_unslash( $_GET['idea_orderby'] ) ) : 'created_at',
			'order'           => ( isset( $_GET['idea_order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['idea_order'] ) ) ) ) ? 'ASC' : 'DESC',
		);
		$ideas_total    = $content_ideas->count_all( $idea_filters );
		$ideas_pages    = max( 1, (int) ceil( $ideas_total / $ideas_per_page ) );
		$ideas_page     = min( $ideas_pages, max( 1, absint( $_GET['paged'] ?? 1 ) ) );
		$ideas          = $content_ideas->get_ideas_paginated( $ideas_page, $ideas_per_page, $idea_filters );
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

		// Edit screen for an existing idea (before it becomes a draft).
		$edit_idea_id = absint( $_GET['edit_idea'] ?? 0 );
		if ( $edit_idea_id > 0 && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wpai_publisher_edit_content_idea_' . $edit_idea_id ) ) {
			$edit_idea = $content_ideas->get_idea( $edit_idea_id );
			if ( $edit_idea ) {
				include WPAIP_PLUGIN_DIR . 'admin/views/content-idea-edit.php';
				return;
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
	 * Return the injected article type repository, instantiating a fallback if needed.
	 *
	 * @return Article_Type_Repository
	 */
	private function get_article_type_repository() {
		if ( ! $this->article_type_repository instanceof Article_Type_Repository ) {
			$this->article_type_repository = new Article_Type_Repository();
		}

		return $this->article_type_repository;
	}

	/**
	 * Handle article type creation/update.
	 *
	 * @return void
	 */
	public function handle_save_article_type() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_save_article_type_' . $id );
		$repo = $this->get_article_type_repository();
		$data = wp_unslash( $_POST );
		$result = $id > 0 ? $repo->update_article_type( $id, $data ) : $repo->create_article_type( $data );
		wp_safe_redirect( add_query_arg( 'wpai_notice', $result ? 'article_type_saved' : 'article_type_save_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function handle_delete_article_type() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_delete_article_type_' . $id );
		$repo = $this->get_article_type_repository();
		$deleted = $repo->delete_article_type( $id );
		wp_safe_redirect( add_query_arg( 'wpai_notice', $deleted ? 'article_type_deleted' : 'article_type_delete_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function handle_toggle_article_type() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$id = absint( $_POST['id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_toggle_article_type_' . $id );
		$repo = $this->get_article_type_repository();
		$type = $repo->get_article_type( $id );
		$updated = $type ? $repo->update_article_type( $id, array_merge( $type, array( 'is_active' => empty( $type['is_active'] ) ? 1 : 0 ) ) ) : false;
		wp_safe_redirect( add_query_arg( 'wpai_notice', $updated ? 'article_type_toggled' : 'article_type_toggle_failed', admin_url( 'admin.php?page=wp-ai-publisher-article-types' ) ) );
		exit;
	}

	public function render_article_types() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) { wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) ); }
		$repo = $this->get_article_type_repository();
		$article_type_id = absint( $_GET['article_type_id'] ?? 0 );
		$article_type = $article_type_id > 0 ? $repo->get_article_type( $article_type_id ) : null;
		if ( 'new' === sanitize_key( $_GET['action'] ?? '' ) || $article_type ) { include WPAIP_PLUGIN_DIR . 'admin/views/article-type-edit.php'; return; }
		$article_types = $repo->get_all_article_types();
		include WPAIP_PLUGIN_DIR . 'admin/views/article-types.php';
	}

	/**
	 * Render AI diagnostics page.
	 *
	 * @return void
	 */
	/**
	 * Render jobs queue page.
	 *
	 * @return void
	 */
	public function render_jobs() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$job_queue    = $this->job_queue;
		$status_counts = $job_queue->count_by_status();
		$jobs          = $job_queue->get_recent_jobs( 20 );
		include WPAIP_PLUGIN_DIR . 'admin/views/jobs.php';
	}

	/**
	 * Register the WordPress dashboard widget.
	 *
	 * @return void
	 */
	public function register_dashboard_widget() {
		if ( ! current_user_can( wpai_publisher_capability() ) || ! function_exists( 'wp_add_dashboard_widget' ) ) {
			return;
		}
		wp_add_dashboard_widget(
			'wpai_publisher_dashboard_widget',
			esc_html__( 'WP AI Publisher — Idee contenuto', 'wp-ai-publisher' ),
			array( $this, 'render_dashboard_widget' )
		);
	}

	/**
	 * Render the dashboard widget: status counts, recent ideas and quick links.
	 *
	 * @return void
	 */
	public function render_dashboard_widget() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			return;
		}
		$counts        = $this->content_ideas->count_by_status();
		$total         = $this->content_ideas->count_all();
		$recent        = $this->content_ideas->get_recent_ideas( 5 );
		$ideas_url     = admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' );
		$summary       = array(
			'draft_created' => __( 'Bozze create', 'wp-ai-publisher' ),
			'processing'    => __( 'In lavorazione', 'wp-ai-publisher' ),
			'draft_failed'  => __( 'In errore', 'wp-ai-publisher' ),
			'new'           => __( 'Nuove', 'wp-ai-publisher' ),
		);
		include WPAIP_PLUGIN_DIR . 'admin/views/dashboard-widget.php';
	}

	/**
	 * Render dashboard page.
	 *
	 * @return void
	 */
	public function render_dashboard() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
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
	 * Render the combined "Stato sistema e diagnostica AI" page.
	 *
	 * Merges the former standalone "Diagnostica AI" page into a tabbed
	 * sub-section here, so both read-only diagnostics live at the end of the
	 * plugin menu. The diagnostics manual tests post back to this page.
	 *
	 * @return void
	 */
	public function render_system_status() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		// System status checks.
		$system_status = new System_Status( $this->db, $this->logger, $this->ai_provider );
		$checks        = $system_status->get_checks();
		$critical_logs = $system_status->get_last_critical_errors();

		// AI diagnostics (read-only report + optional manual tests).
		$diagnostics         = new AI_Diagnostics( $this->ai_provider, $this->logger );
		$test_result         = null;
		$connectivity_result = null;
		$wpai_diag_initial   = '';

		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) && isset( $_POST['wpai_publisher_ai_diagnostics_action'] ) ) {
			check_admin_referer( 'wpai_publisher_ai_diagnostics_test', 'wpai_publisher_ai_diagnostics_nonce' );

			$action = sanitize_key( wp_unslash( $_POST['wpai_publisher_ai_diagnostics_action'] ) );
			if ( 'run_safe_generation_test' === $action ) {
				$test_result = $diagnostics->run_safe_generation_test();
			} elseif ( 'run_connectivity_test' === $action ) {
				$connectivity_result = $diagnostics->run_openai_connectivity_test();
			}

			// Reopen the Diagnostica AI tab so the test result is visible.
			$wpai_diag_initial = 'diagnostica';
		}

		$report = $diagnostics->get_report();

		include WPAIP_PLUGIN_DIR . 'admin/views/system-status.php';
	}
}
