<?php
/**
 * Main plugin bootstrap class.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates plugin services.
 */
final class Plugin {
	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * DB service.
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
	 * Admin service.
	 *
	 * @var Admin
	 */
	private $admin;

	/**
	 * AI provider adapter.
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
	 * Telegram integration.
	 *
	 * @var Telegram_Integration|null
	 */
	private $telegram;

	/**
	 * Return singleton instance.
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->db = new DB();
		$this->maybe_upgrade_database();

		$this->logger      = new Logger( $this->db );
		$this->settings      = new Settings();
		$this->job_queue     = new Job_Queue( $this->db );
		$this->ai_provider   = new AI_Provider_Adapter();
		$this->content_ideas = new Content_Ideas( $this->db, $this->ai_provider, $this->logger );
		$this->article_type_repository = class_exists( __NAMESPACE__ . '\\Article_Type_Repository' ) ? new Article_Type_Repository() : null;
		$this->admin         = new Admin( $this->db, $this->logger, $this->settings, $this->ai_provider, $this->job_queue, $this->content_ideas, $this->article_type_repository );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_init', array( $this, 'maybe_create_default_article_types' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'wp_dashboard_setup', array( $this->admin, 'register_dashboard_widget' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wpai_publisher_process_jobs', array( $this->admin, 'process_next_job' ) );

		// Scheduled ideas: recurring cron that enqueues due ideas.
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );
		add_action( 'wpai_publisher_run_scheduled_ideas', array( $this->admin, 'process_scheduled_ideas' ) );
		add_action( 'init', array( $this, 'maybe_schedule_scheduled_ideas_cron' ) );

		// Telegram integration: inbound webhook → idea + draft, with reply.
		if ( class_exists( __NAMESPACE__ . '\\Telegram_Integration' ) ) {
			$this->telegram = new Telegram_Integration( $this->content_ideas, $this->logger );
			$this->telegram->register();
		}
	}

	/**
	 * Add a 5-minute cron schedule used to process scheduled ideas.
	 *
	 * @param array<string,array<string,mixed>> $schedules Existing schedules.
	 * @return array<string,array<string,mixed>>
	 */
	public function register_cron_schedules( $schedules ) {
		if ( ! isset( $schedules['wpai_publisher_5min'] ) ) {
			$schedules['wpai_publisher_5min'] = array( 'interval' => 5 * MINUTE_IN_SECONDS, 'display' => __( 'Ogni 5 minuti (WP AI Publisher)', 'wp-ai-publisher' ) );
		}
		return $schedules;
	}

	/**
	 * Ensure the scheduled-ideas cron event is registered.
	 *
	 * @return void
	 */
	public function maybe_schedule_scheduled_ideas_cron() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( 'wpai_publisher_run_scheduled_ideas' ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wpai_publisher_5min', 'wpai_publisher_run_scheduled_ideas' );
		}
	}

	/**
	 * Run lightweight database migrations on normal plugin updates.
	 *
	 * WordPress does not rerun activation hooks after one-click plugin updates,
	 * so schema upgrades must be checked during bootstrap before admin pages query tables.
	 *
	 * @return void
	 */
	private function maybe_upgrade_database() {
		$stored_schema  = $this->db->get_schema_version();
		$stored_version = (string) get_option( 'wpai_publisher_version', '' );

		// Only run the expensive dbDelta + ALTER scan when the schema is actually
		// behind. A version-only bump (no schema change) must not trigger table
		// rebuilds on every request for the whole site.
		if ( version_compare( $stored_schema, WPAIP_DB_SCHEMA_VERSION, '<' ) ) {
			$this->db->create_tables();
			$this->db->set_schema_version( WPAIP_DB_SCHEMA_VERSION );
		}

		// Keep the stored plugin version in sync for diagnostics; this is a cheap
		// option write and does not touch the schema. On upgrade we also (re)grant
		// the plugin capability so the dedicated capability never locks out
		// administrators after a one-click update.
		if ( $stored_version !== WPAIP_VERSION ) {
			if ( function_exists( 'wpai_publisher_grant_capabilities' ) ) {
				wpai_publisher_grant_capabilities();
			}
			update_option( 'wpai_publisher_version', WPAIP_VERSION, false );
		}
	}

	/**
	 * Load translations.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'wp-ai-publisher', false, dirname( WPAIP_PLUGIN_BASENAME ) . '/languages' );
	}

	/**
	 * Enqueue admin assets only on plugin pages.
	 *
	 * @param string $hook_suffix Current admin page hook suffix.
	 * @return void
	 */
	public function maybe_create_default_article_types() {
		if ( wpai_publisher_article_types_enabled() && $this->article_type_repository instanceof Article_Type_Repository ) {
			$this->article_type_repository->maybe_create_default_article_types();
		}
	}

	public function enqueue_admin_assets( $hook_suffix ) {
		if ( false === strpos( (string) $hook_suffix, 'wp-ai-publisher' ) ) {
			return;
		}

		wp_enqueue_style(
			'wpai-publisher-admin',
			WPAIP_PLUGIN_URL . 'admin/css/admin.css',
			array(),
			WPAIP_VERSION
		);

		wp_enqueue_script(
			'wpai-publisher-admin',
			WPAIP_PLUGIN_URL . 'admin/js/admin.js',
			array(),
			WPAIP_VERSION,
			true
		);
	}

	/**
	 * Get database service.
	 *
	 * @return DB
	 */
	public function db() {
		return $this->db;
	}

	/**
	 * Get logger service.
	 *
	 * @return Logger
	 */
	public function logger() {
		return $this->logger;
	}
}
