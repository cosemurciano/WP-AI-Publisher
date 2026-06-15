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
	 * Article types service.
	 *
	 * @var Article_Types
	 */
	private $article_types;

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
		$this->settings    = new Settings();
		$this->article_types = class_exists( __NAMESPACE__ . '\Article_Types' ) ? new Article_Types() : null;
		$this->job_queue   = new Job_Queue( $this->db );
		$this->ai_provider   = new AI_Provider_Adapter();
		$this->content_ideas = new Content_Ideas( $this->db, $this->ai_provider, $this->logger );
		$this->admin         = new Admin( $this->db, $this->logger, $this->settings, $this->ai_provider, $this->job_queue, $this->content_ideas );

		add_action( 'init', array( $this, 'load_textdomain' ) );
		if ( $this->article_types && method_exists( $this->article_types, 'hooks' ) ) {
			$this->article_types->hooks();
		} elseif ( $this->logger ) {
			$this->logger->warning( __( 'Classe Article_Types non disponibile: il plugin continua senza CPT tipologie.', 'wp-ai-publisher' ), array( 'source' => 'plugin' ) );
		}
		add_action( 'admin_init', array( $this->settings, 'register' ) );
		add_action( 'admin_menu', array( $this->admin, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
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

		if ( version_compare( $stored_schema, WPAIP_DB_SCHEMA_VERSION, '<' ) || $stored_version !== WPAIP_VERSION ) {
			$this->db->create_tables();
			$this->db->set_schema_version( WPAIP_DB_SCHEMA_VERSION );
			update_option( 'wpai_publisher_version', WPAIP_VERSION, false );
			if ( class_exists( __NAMESPACE__ . '\Article_Types' ) && method_exists( __NAMESPACE__ . '\Article_Types', 'maybe_create_defaults' ) ) {
				Article_Types::maybe_create_defaults();
			}
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
