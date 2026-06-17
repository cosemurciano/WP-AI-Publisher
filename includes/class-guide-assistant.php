<?php
/**
 * Assistente Guide AI — public one-shot guide generator grounded in site content.
 *
 * @package WPAIPublisher
 */

namespace WPAIPublisher;

use WP_Error;
use WP_Query;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end shortcode + REST endpoint that turns a visitor's question into a
 * personalized guide, grounded in the site's own articles.
 *
 * Design constraints:
 * - One-shot (not a chat), rate-limited per IP/session with a global daily cap.
 * - Links are grounded: the AI only receives real internal URLs (plus an
 *   optional external whitelist) and any link it emits is validated against
 *   that allowlist; recommended articles are real WP search results.
 * - Identical queries are cached to save tokens.
 * - Every request is stored and can be converted into a Content Idea / draft.
 */
class Guide_Assistant {

	const OPTION         = 'wpai_publisher_guide_assistant';
	const REST_NAMESPACE = 'wp-ai-publisher/v1';
	const REST_ROUTE     = '/guide';
	const NONCE_ACTION   = 'wpai_guide_request';

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
	 * AI provider adapter.
	 *
	 * @var AI_Provider_Adapter
	 */
	private $ai_provider;

	/**
	 * Content ideas service (for conversion).
	 *
	 * @var Content_Ideas
	 */
	private $content_ideas;

	/**
	 * Constructor.
	 *
	 * @param DB                  $db DB service.
	 * @param Logger              $logger Logger service.
	 * @param AI_Provider_Adapter $ai_provider AI provider adapter.
	 * @param Content_Ideas       $content_ideas Content ideas service.
	 */
	public function __construct( DB $db, Logger $logger, AI_Provider_Adapter $ai_provider, Content_Ideas $content_ideas ) {
		$this->db            = $db;
		$this->logger        = $logger;
		$this->ai_provider   = $ai_provider;
		$this->content_ideas = $content_ideas;
	}

	/**
	 * Register hooks (shortcode, REST, admin-post handlers).
	 *
	 * @return void
	 */
	const CPT = 'wpai_guide';

	public function register() {
		add_shortcode( 'wpai_guide_generator', array( $this, 'render_shortcode' ) );
		add_action( 'init', array( $this, 'register_guide_cpt' ) );
		add_action( 'rest_api_init', array( $this, 'register_rest_route' ) );
		add_action( 'admin_post_wpai_publisher_save_guide_assistant', array( $this, 'handle_save_settings' ) );
		add_action( 'admin_post_wpai_publisher_guide_request_to_idea', array( $this, 'handle_convert_to_idea' ) );
		add_action( 'admin_post_wpai_publisher_delete_guide_request', array( $this, 'handle_delete_request' ) );

		// Keep generated guide pages out of search engines and front-end search.
		add_filter( 'wp_robots', array( $this, 'filter_guide_robots' ) );
		add_filter( 'the_content', array( $this, 'filter_guide_content' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_single_guide_assets' ) );

		// Scheduled cleanup of old public guide pages (retention configurable).
		add_action( 'init', array( $this, 'maybe_schedule_cleanup' ) );
		add_action( self::CLEANUP_HOOK, array( $this, 'run_cleanup' ) );

		// Friendly handling of links to guides that were deleted by the cleanup.
		add_action( 'template_redirect', array( $this, 'maybe_handle_deleted_guide' ) );
	}

	/**
	 * When a /guida/... URL 404s (because the guide was deleted by the retention
	 * cleanup), redirect the visitor to the configured generator page with a
	 * notice so they can recreate an up-to-date guide.
	 *
	 * @return void
	 */
	public function maybe_handle_deleted_guide() {
		if ( ! is_404() ) {
			return;
		}
		$config  = $this->get_config();
		$page_id = absint( $config['generator_page_id'] );
		if ( $page_id <= 0 ) {
			return;
		}
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path        = (string) wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( '' === $path || false === strpos( $path, '/guida/' ) ) {
			return;
		}
		$target = get_permalink( $page_id );
		if ( ! $target ) {
			return;
		}
		wp_safe_redirect( add_query_arg( 'guide_deleted', '1', $target ), 302 );
		exit;
	}

	const CLEANUP_HOOK = 'wpai_publisher_guide_cleanup';

	/**
	 * Ensure the daily guide-cleanup cron event is scheduled.
	 *
	 * @return void
	 */
	public function maybe_schedule_cleanup() {
		if ( function_exists( 'wp_next_scheduled' ) && ! wp_next_scheduled( self::CLEANUP_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CLEANUP_HOOK );
		}
	}

	/**
	 * Delete public guide pages older than the configured retention window.
	 *
	 * Request rows are kept (they hold editorial value); only their link to the
	 * deleted page is cleared. Runs in capped batches to avoid timeouts.
	 *
	 * @return int Number of guide pages deleted.
	 */
	public function run_cleanup() {
		$config = $this->get_config();
		$days   = (int) $config['retention_days'];
		if ( $days <= 0 ) {
			return 0;
		}

		$query = new WP_Query(
			array(
				'post_type'      => self::CPT,
				'post_status'    => 'any',
				'posts_per_page' => 200,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'date_query'     => array(
					array( 'column' => 'post_date_gmt', 'before' => $days . ' days ago' ),
				),
			)
		);

		$deleted = 0;
		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		foreach ( (array) $query->posts as $post_id ) {
			$post_id = absint( $post_id );
			if ( $post_id <= 0 ) {
				continue;
			}
			$wpdb->update( $table, array( 'post_id' => null ), array( 'post_id' => $post_id ), array( '%d' ), array( '%d' ) );
			if ( wp_delete_post( $post_id, true ) ) {
				$deleted++;
			}
		}
		wp_reset_postdata();

		if ( $deleted > 0 ) {
			$this->logger->info(
				sprintf( __( 'Pulizia guide pubbliche: eliminate %d pagine.', 'wp-ai-publisher' ), $deleted ),
				array( 'source' => 'guide_assistant', 'event' => 'cleanup', 'deleted' => $deleted, 'retention_days' => $days )
			);
		}

		return $deleted;
	}

	/**
	 * Register the public (but noindex) guide content type.
	 *
	 * @return void
	 */
	public function register_guide_cpt() {
		register_post_type(
			self::CPT,
			array(
				'labels'              => array(
					'name'          => __( 'Guide AI', 'wp-ai-publisher' ),
					'singular_name' => __( 'Guida AI', 'wp-ai-publisher' ),
				),
				'public'              => true,
				'publicly_queryable'  => true,
				'exclude_from_search' => true,
				'show_ui'             => false,
				'show_in_menu'        => false,
				'show_in_nav_menus'   => false,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => array( 'slug' => 'guida', 'with_front' => false ),
				'supports'            => array( 'title', 'editor' ),
				'capability_type'     => 'post',
			)
		);

		// Flush rewrite rules once after the CPT is first registered so single
		// guide permalinks resolve instead of 404'ing.
		if ( get_option( 'wpai_guide_cpt_flushed' ) !== WPAIP_VERSION ) {
			flush_rewrite_rules( false );
			update_option( 'wpai_guide_cpt_flushed', WPAIP_VERSION, false );
		}
	}

	/**
	 * Force noindex/nofollow on single guide pages.
	 *
	 * @param array<string,bool> $robots Robots directives.
	 * @return array<string,bool>
	 */
	public function filter_guide_robots( $robots ) {
		if ( is_singular( self::CPT ) ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			$robots['index']    = false;
		}
		return $robots;
	}

	/**
	 * Append the recommended-article cards to a single guide's content.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_guide_content( $content ) {
		if ( ! is_singular( self::CPT ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id  = get_the_ID();
		$ids      = (array) json_decode( (string) get_post_meta( $post_id, '_wpai_guide_article_ids', true ), true );
		$articles = $this->build_recommended_articles( $this->ids_to_candidates( $ids ), 12 );
		if ( empty( $articles ) ) {
			return $content;
		}
		$cards = '<div class="wpai-guide"><div class="wpai-guide__related"><h3 class="wpai-guide__related-title">' . esc_html__( 'Articoli per la tua guida', 'wp-ai-publisher' ) . '</h3><div class="wpai-guide__cards">';
		foreach ( $articles as $a ) {
			$cards .= '<a class="wpai-guide__card" href="' . esc_url( (string) $a['url'] ) . '">';
			if ( ! empty( $a['thumb'] ) ) {
				$cards .= '<span class="wpai-guide__card-media"><img src="' . esc_url( (string) $a['thumb'] ) . '" alt="" loading="lazy"></span>';
			}
			$cards .= '<span class="wpai-guide__card-body"><span class="wpai-guide__card-title">' . esc_html( (string) $a['title'] ) . '</span>';
			if ( ! empty( $a['excerpt'] ) ) {
				$cards .= '<span class="wpai-guide__card-excerpt">' . esc_html( (string) $a['excerpt'] ) . '</span>';
			}
			$cards .= '</span></a>';
		}
		$cards .= '</div></div></div>';
		return $content . $cards;
	}

	/**
	 * Load the guide stylesheet on single guide pages (for the cards).
	 *
	 * @return void
	 */
	public function maybe_enqueue_single_guide_assets() {
		if ( is_singular( self::CPT ) ) {
			wp_enqueue_style( 'wpai-guide', WPAIP_PLUGIN_URL . 'public/css/guide.css', array(), WPAIP_VERSION );
		}
	}

	/* ---------------------------------------------------------------------
	 * Configuration
	 * ------------------------------------------------------------------- */

	/**
	 * Default configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_default_config() {
		return array(
			'enabled'                  => false,
			'system_instructions'      => __( 'Sei un assistente esperto del sito. Rispondi alla richiesta dell’utente con una guida pratica, chiara e ben strutturata, citando e collegando gli articoli pertinenti del sito.', 'wp-ai-publisher' ),
			'placeholder'              => __( 'Descrivi cosa vuoi imparare o risolvere…', 'wp-ai-publisher' ),
			'heading'                  => __( 'Crea la tua guida personalizzata', 'wp-ai-publisher' ),
			'language'                 => 'it',
			'max_articles'             => 4,
			'search_post_types'        => array( 'post' ),
			'search_category_ids'      => array(),
			'external_links_whitelist' => '',
			'model'                    => '',
			'use_file_search'          => false,
			'create_public_page'       => true,
			'retention_days'           => 30,
			'max_tokens'               => 1500,
			'per_ip_daily_limit'       => 3,
			'global_daily_limit'       => 200,
			'cooldown_seconds'         => 20,
			'cache_ttl_days'           => 30,
			'result_footer'            => '',
			'deleted_message'          => __( 'Questa guida è stata eliminata perché sono trascorsi più dei giorni previsti. Ricrea la tua guida aggiornata qui sotto.', 'wp-ai-publisher' ),
			'generator_page_id'        => 0,
		);
	}

	/**
	 * Get the merged configuration.
	 *
	 * @return array<string,mixed>
	 */
	public function get_config() {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}
		return wp_parse_args( $stored, $this->get_default_config() );
	}

	/**
	 * Sanitize a raw config array.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	private function sanitize_config( $input ) {
		$defaults = $this->get_default_config();
		$allowed_langs = array( 'it', 'en', 'fr', 'es', 'de' );
		$lang = sanitize_key( (string) ( $input['language'] ?? 'it' ) );

		$post_types = array();
		foreach ( (array) ( $input['search_post_types'] ?? array() ) as $pt ) {
			$pt = sanitize_key( (string) $pt );
			if ( '' !== $pt && post_type_exists( $pt ) ) {
				$post_types[] = $pt;
			}
		}

		$cat_ids = array();
		foreach ( (array) ( $input['search_category_ids'] ?? array() ) as $cid ) {
			$cid = absint( $cid );
			if ( $cid > 0 ) {
				$cat_ids[] = $cid;
			}
		}

		return array(
			'enabled'                  => ! empty( $input['enabled'] ),
			'system_instructions'      => sanitize_textarea_field( (string) ( $input['system_instructions'] ?? $defaults['system_instructions'] ) ),
			'placeholder'              => sanitize_text_field( (string) ( $input['placeholder'] ?? $defaults['placeholder'] ) ),
			'heading'                  => sanitize_text_field( (string) ( $input['heading'] ?? $defaults['heading'] ) ),
			'language'                 => in_array( $lang, $allowed_langs, true ) ? $lang : 'it',
			'max_articles'             => min( 10, max( 1, absint( $input['max_articles'] ?? $defaults['max_articles'] ) ) ),
			'search_post_types'        => ! empty( $post_types ) ? array_values( array_unique( $post_types ) ) : array( 'post' ),
			'search_category_ids'      => array_values( array_unique( $cat_ids ) ),
			'external_links_whitelist' => $this->sanitize_url_list( (string) ( $input['external_links_whitelist'] ?? '' ) ),
			'model'                    => sanitize_text_field( (string) ( $input['model'] ?? '' ) ),
			'use_file_search'          => ! empty( $input['use_file_search'] ),
			'create_public_page'       => ! empty( $input['create_public_page'] ),
			'retention_days'           => max( 0, absint( $input['retention_days'] ?? $defaults['retention_days'] ) ),
			'max_tokens'               => min( 32000, max( 128, absint( $input['max_tokens'] ?? $defaults['max_tokens'] ) ) ),
			'per_ip_daily_limit'       => max( 0, absint( $input['per_ip_daily_limit'] ?? $defaults['per_ip_daily_limit'] ) ),
			'global_daily_limit'       => max( 0, absint( $input['global_daily_limit'] ?? $defaults['global_daily_limit'] ) ),
			'cooldown_seconds'         => max( 0, absint( $input['cooldown_seconds'] ?? $defaults['cooldown_seconds'] ) ),
			'cache_ttl_days'           => max( 0, absint( $input['cache_ttl_days'] ?? $defaults['cache_ttl_days'] ) ),
			'result_footer'            => sanitize_textarea_field( (string) ( $input['result_footer'] ?? '' ) ),
			'deleted_message'          => sanitize_textarea_field( (string) ( $input['deleted_message'] ?? $defaults['deleted_message'] ) ),
			'generator_page_id'        => absint( $input['generator_page_id'] ?? 0 ),
		);
	}

	/**
	 * Normalize a newline-separated URL list, keeping only valid http(s) URLs.
	 *
	 * @param string $raw Raw textarea content.
	 * @return string
	 */
	private function sanitize_url_list( $raw ) {
		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) $raw ) as $line ) {
			$url = esc_url_raw( trim( (string) $line ) );
			if ( '' !== $url ) {
				$out[] = $url;
			}
		}
		return implode( "\n", array_values( array_unique( $out ) ) );
	}

	/**
	 * Whitelisted external hosts derived from the URL list.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return array<int,string> Lowercased hosts.
	 */
	private function whitelist_hosts( $config ) {
		$hosts = array();
		foreach ( preg_split( '/\r\n|\r|\n/', (string) ( $config['external_links_whitelist'] ?? '' ) ) as $line ) {
			$host = wp_parse_url( trim( (string) $line ), PHP_URL_HOST );
			if ( $host ) {
				$hosts[] = strtolower( (string) $host );
			}
		}
		return array_values( array_unique( $hosts ) );
	}

	/* ---------------------------------------------------------------------
	 * Front-end shortcode
	 * ------------------------------------------------------------------- */

	/**
	 * Render the shortcode.
	 *
	 * @param array<string,mixed>|string $atts Attributes.
	 * @return string
	 */
	public function render_shortcode( $atts ) {
		$config = $this->get_config();
		if ( empty( $config['enabled'] ) ) {
			return current_user_can( wpai_publisher_capability() )
				? '<p>' . esc_html__( 'Assistente Guide AI non attivo. Abilitalo in WP AI Publisher → Assistente Guide AI.', 'wp-ai-publisher' ) . '</p>'
				: '';
		}

		$atts = shortcode_atts(
			array(
				'heading'     => $config['heading'],
				'placeholder' => $config['placeholder'],
			),
			is_array( $atts ) ? $atts : array(),
			'wpai_guide_generator'
		);

		$this->enqueue_front_assets();

		$uid = 'wpai-guide-' . wp_generate_password( 6, false, false );
		$show_deleted_notice = isset( $_GET['guide_deleted'] );
		ob_start();
		?>
		<div class="wpai-guide" id="<?php echo esc_attr( $uid ); ?>">
			<?php if ( '' !== trim( (string) $atts['heading'] ) ) : ?>
				<h2 class="wpai-guide__heading"><?php echo esc_html( $atts['heading'] ); ?></h2>
			<?php endif; ?>
			<?php if ( $show_deleted_notice && '' !== trim( (string) $config['deleted_message'] ) ) : ?>
				<div class="wpai-guide__deleted-notice"><?php echo esc_html( (string) $config['deleted_message'] ); ?></div>
			<?php endif; ?>
			<form class="wpai-guide__form" autocomplete="off">
				<div class="wpai-guide__field">
					<textarea class="wpai-guide__input" rows="1" maxlength="500" placeholder="<?php echo esc_attr( $atts['placeholder'] ); ?>" required></textarea>
					<button type="submit" class="wpai-guide__submit" aria-label="<?php echo esc_attr__( 'Genera guida', 'wp-ai-publisher' ); ?>" title="<?php echo esc_attr__( 'Genera guida', 'wp-ai-publisher' ); ?>">
						<svg class="wpai-guide__submit-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 19V5M5 12l7-7 7 7" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
						<span class="wpai-guide__submit-spinner" aria-hidden="true"></span>
					</button>
				</div>
				<input type="text" class="wpai-guide__hp" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute;left:-9999px;" />
			</form>
			<div class="wpai-guide__status" aria-live="polite" hidden></div>
			<div class="wpai-guide__result" hidden>
				<div class="wpai-guide__content"></div>
				<div class="wpai-guide__related"></div>
				<div class="wpai-guide__tools">
					<button type="button" class="wpai-guide__tool wpai-guide__print" data-tooltip="<?php echo esc_attr__( 'Salva come PDF', 'wp-ai-publisher' ); ?>" aria-label="<?php echo esc_attr__( 'Salva come PDF', 'wp-ai-publisher' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2M6 14h12v7H6z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
					<button type="button" class="wpai-guide__tool wpai-guide__whatsapp" data-tooltip="<?php echo esc_attr__( 'Invia su WhatsApp', 'wp-ai-publisher' ); ?>" aria-label="<?php echo esc_attr__( 'Invia su WhatsApp', 'wp-ai-publisher' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.04 2c-5.46 0-9.91 4.45-9.91 9.91 0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.9 9.9 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2Zm5.8 14.16c-.24.68-1.42 1.32-1.96 1.36-.5.05-.97.24-3.3-.69-2.78-1.1-4.56-3.95-4.7-4.13-.14-.18-1.13-1.5-1.13-2.86 0-1.36.71-2.03.97-2.31.24-.27.53-.34.71-.34.18 0 .36 0 .51.01.16.01.39-.06.6.46.24.58.81 2 .88 2.14.07.14.12.31.02.49-.09.18-.14.29-.27.45-.14.16-.29.36-.41.48-.14.14-.28.29-.12.57.16.27.71 1.17 1.52 1.9 1.05.93 1.93 1.22 2.21 1.36.27.14.43.12.59-.07.16-.18.68-.79.86-1.07.18-.27.36-.22.61-.13.24.09 1.55.73 1.81.86.27.14.45.2.51.31.07.11.07.63-.17 1.31Z"/></svg>
					</button>
					<button type="button" class="wpai-guide__tool wpai-guide__save" data-tooltip="<?php echo esc_attr__( 'Salva la tua guida', 'wp-ai-publisher' ); ?>" aria-label="<?php echo esc_attr__( 'Salva la tua guida', 'wp-ai-publisher' ); ?>">
						<svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M19 21l-7-5-7 5V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2v16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</button>
				</div>
				<p class="wpai-guide__save-note" hidden></p>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Register and enqueue front-end assets.
	 *
	 * @return void
	 */
	private function enqueue_front_assets() {
		wp_enqueue_style( 'wpai-guide', WPAIP_PLUGIN_URL . 'public/css/guide.css', array(), WPAIP_VERSION );
		wp_enqueue_script( 'wpai-guide', WPAIP_PLUGIN_URL . 'public/js/guide.js', array(), WPAIP_VERSION, true );
		wp_localize_script(
			'wpai-guide',
			'wpaiGuide',
			array(
				'endpoint' => esc_url_raw( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) ),
				'nonce'    => wp_create_nonce( self::NONCE_ACTION ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'i18n'     => array(
					'error'      => __( 'Si è verificato un errore. Riprova più tardi.', 'wp-ai-publisher' ),
					'tooShort'   => __( 'Scrivi una richiesta più dettagliata.', 'wp-ai-publisher' ),
					'related'    => __( 'Articoli per la tua guida', 'wp-ai-publisher' ),
					'printTitle' => __( 'Guida', 'wp-ai-publisher' ),
					'waText'     => __( 'Ecco la guida che ho creato:', 'wp-ai-publisher' ),
					'saveNote'   => __( 'Presto potrai registrarti per salvare e ritrovare tutte le tue guide. Funzione in arrivo!', 'wp-ai-publisher' ),
					'loadingSteps' => array(
						__( 'Sto studiando la tua richiesta…', 'wp-ai-publisher' ),
						__( 'Ho raccolto le informazioni migliori…', 'wp-ai-publisher' ),
						__( 'Sto selezionando gli articoli più utili…', 'wp-ai-publisher' ),
						__( 'Creo la guida…', 'wp-ai-publisher' ),
						__( 'Ci siamo quasi…', 'wp-ai-publisher' ),
					),
				),
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * REST endpoint
	 * ------------------------------------------------------------------- */

	/**
	 * Register the REST route.
	 *
	 * @return void
	 */
	public function register_rest_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'handle_request' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'query'   => array( 'type' => 'string', 'required' => true ),
					'nonce'   => array( 'type' => 'string', 'required' => false ),
					'website' => array( 'type' => 'string', 'required' => false ),
				),
			)
		);
	}

	/**
	 * Handle a guide request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle_request( WP_REST_Request $request ) {
		$config = $this->get_config();
		if ( empty( $config['enabled'] ) ) {
			return new WP_Error( 'wpai_guide_disabled', __( 'Servizio non disponibile.', 'wp-ai-publisher' ), array( 'status' => 403 ) );
		}

		// Nonce (issued in the shortcode; soft check — public endpoint).
		$nonce = (string) $request->get_param( 'nonce' );
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			return new WP_Error( 'wpai_guide_bad_nonce', __( 'Sessione scaduta, ricarica la pagina.', 'wp-ai-publisher' ), array( 'status' => 403 ) );
		}

		// Honeypot: bots fill hidden fields.
		if ( '' !== trim( (string) $request->get_param( 'website' ) ) ) {
			return new WP_Error( 'wpai_guide_spam', __( 'Richiesta non valida.', 'wp-ai-publisher' ), array( 'status' => 400 ) );
		}

		$query = trim( (string) $request->get_param( 'query' ) );
		$query = wp_strip_all_tags( $query );
		if ( mb_strlen( $query ) < 8 ) {
			return new WP_Error( 'wpai_guide_too_short', __( 'Scrivi una richiesta più dettagliata.', 'wp-ai-publisher' ), array( 'status' => 400 ) );
		}
		if ( mb_strlen( $query ) > 500 ) {
			$query = mb_substr( $query, 0, 500 );
		}

		// Rate limiting.
		$limit_error = $this->check_rate_limits( $config );
		if ( is_wp_error( $limit_error ) ) {
			return $limit_error;
		}

		// Cache by normalized query hash. Privileged users skip the cache so they
		// always see a fresh result while testing/tuning the prompt.
		$hash = $this->query_hash( $query, (array) $config );
		if ( ! $this->is_privileged() ) {
			$cached = $this->get_cached( $hash, (int) $config['cache_ttl_days'] );
			if ( null !== $cached ) {
				return new WP_REST_Response( $cached, 200 );
			}
		}

		// Ground on real site content.
		$candidates = $this->search_candidates( $query, $config );

		$generated = $this->generate_guide( $query, $candidates, $config );
		if ( is_wp_error( $generated ) ) {
			return $generated;
		}

		$articles = $this->build_recommended_articles( $candidates, (int) $config['max_articles'] );
		$payload  = array(
			'html'     => $generated,
			'articles' => $articles,
		);

		// Persist and count usage.
		$request_id = $this->store_request( $query, $hash, $config, $generated, $articles );
		$payload['request_id'] = $request_id;

		// Optionally create a public (noindex) page for the guide so it has a
		// permanent, shareable URL.
		if ( ! empty( $config['create_public_page'] ) ) {
			$guide_url = $this->create_public_guide( $request_id, $query, $generated, $articles );
			if ( '' !== $guide_url ) {
				$payload['guide_url'] = $guide_url;
			}
		}

		$this->bump_usage( $config );

		return new WP_REST_Response( $payload, 200 );
	}

	/* ---------------------------------------------------------------------
	 * Rate limiting
	 * ------------------------------------------------------------------- */

	/**
	 * Enforce cooldown, per-IP daily and global daily limits.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return true|WP_Error
	 */
	private function check_rate_limits( $config ) {
		// Privileged users (logged-in admins) bypass limits so they can test freely.
		if ( $this->is_privileged() ) {
			return true;
		}

		$ip_hash = $this->client_ip_hash();
		$date    = gmdate( 'Ymd' );

		$cooldown = (int) $config['cooldown_seconds'];
		if ( $cooldown > 0 ) {
			$cool_key = 'wpai_guide_cool_' . $ip_hash;
			if ( false !== get_transient( $cool_key ) ) {
				return new WP_Error( 'wpai_guide_cooldown', __( 'Attendi qualche secondo prima di una nuova richiesta.', 'wp-ai-publisher' ), array( 'status' => 429 ) );
			}
			set_transient( $cool_key, 1, $cooldown );
		}

		$ip_limit = (int) $config['per_ip_daily_limit'];
		if ( $ip_limit > 0 ) {
			$ip_key   = 'wpai_guide_ip_' . $ip_hash . '_' . $date;
			$ip_count = (int) get_transient( $ip_key );
			if ( $ip_count >= $ip_limit ) {
				return new WP_Error( 'wpai_guide_ip_limit', __( 'Hai raggiunto il numero massimo di guide per oggi.', 'wp-ai-publisher' ), array( 'status' => 429 ) );
			}
		}

		$global_limit = (int) $config['global_daily_limit'];
		if ( $global_limit > 0 ) {
			$global_count = (int) get_transient( 'wpai_guide_global_' . $date );
			if ( $global_count >= $global_limit ) {
				return new WP_Error( 'wpai_guide_global_limit', __( 'Il servizio ha raggiunto il limite giornaliero. Riprova domani.', 'wp-ai-publisher' ), array( 'status' => 429 ) );
			}
		}

		return true;
	}

	/**
	 * Increment per-IP and global daily counters after a successful generation.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return void
	 */
	private function bump_usage( $config ) {
		if ( $this->is_privileged() ) {
			return;
		}
		$date = gmdate( 'Ymd' );
		if ( (int) $config['per_ip_daily_limit'] > 0 ) {
			$ip_key = 'wpai_guide_ip_' . $this->client_ip_hash() . '_' . $date;
			set_transient( $ip_key, ( (int) get_transient( $ip_key ) ) + 1, DAY_IN_SECONDS );
		}
		if ( (int) $config['global_daily_limit'] > 0 ) {
			$g_key = 'wpai_guide_global_' . $date;
			set_transient( $g_key, ( (int) get_transient( $g_key ) ) + 1, DAY_IN_SECONDS );
		}
	}

	/**
	 * Whether the current user can manage the plugin (bypasses limits/cache).
	 *
	 * @return bool
	 */
	private function is_privileged() {
		return function_exists( 'is_user_logged_in' ) && is_user_logged_in() && current_user_can( wpai_publisher_capability() );
	}

	/**
	 * One-way hash of the client IP (GDPR-friendly, salted).
	 *
	 * @return string
	 */
	private function client_ip_hash() {
		$ip = '';
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
		}
		return hash( 'sha256', $ip . '|' . wp_salt( 'auth' ) );
	}

	/* ---------------------------------------------------------------------
	 * Grounding + generation
	 * ------------------------------------------------------------------- */

	/**
	 * Search the site for articles related to the query.
	 *
	 * @param string              $query Query.
	 * @param array<string,mixed> $config Config.
	 * @return array<int,array<string,mixed>>
	 */
	private function search_candidates( $query, $config ) {
		$limit = max( 6, (int) $config['max_articles'] * 2 );

		// 1) Full-phrase search (WordPress applies AND across the terms).
		$out = $this->run_search( $query, $config, $limit );

		// 2) If recall is poor, broaden by querying each significant keyword and
		// merging the unique results. WP's default search is strict (AND), so a
		// natural-language question like "come posso creare un sito web?" often
		// returns nothing even when relevant articles exist.
		if ( count( $out ) < $limit ) {
			$seen = array();
			foreach ( $out as $c ) {
				$seen[ (int) $c['id'] ] = true;
			}
			foreach ( $this->extract_keywords( $query ) as $keyword ) {
				if ( count( $out ) >= $limit ) {
					break;
				}
				foreach ( $this->run_search( $keyword, $config, $limit ) as $c ) {
					if ( empty( $seen[ (int) $c['id'] ] ) ) {
						$seen[ (int) $c['id'] ] = true;
						$out[] = $c;
						if ( count( $out ) >= $limit ) {
							break;
						}
					}
				}
			}
		}

		return $out;
	}

	/**
	 * Run a single WP search and return candidate stubs.
	 *
	 * @param string              $search Search string.
	 * @param array<string,mixed> $config Config.
	 * @param int                 $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	private function run_search( $search, $config, $limit ) {
		$search = trim( (string) $search );
		if ( '' === $search ) {
			return array();
		}

		$args = array(
			's'                   => $search,
			'post_type'           => (array) $config['search_post_types'],
			'post_status'         => 'publish',
			'posts_per_page'      => $limit,
			'ignore_sticky_posts' => true,
			'no_found_rows'       => true,
		);
		if ( ! empty( $config['search_category_ids'] ) ) {
			$args['category__in'] = array_map( 'absint', (array) $config['search_category_ids'] );
		}

		$found = new WP_Query( $args );
		$out   = array();
		foreach ( $found->posts as $post ) {
			$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 45 );
			$out[]   = array(
				'id'      => (int) $post->ID,
				'title'   => get_the_title( $post ),
				'url'     => (string) get_permalink( $post ),
				'excerpt' => (string) $excerpt,
			);
		}
		wp_reset_postdata();
		return $out;
	}

	/**
	 * Extract significant keywords from a natural-language query, dropping
	 * common Italian/English stopwords.
	 *
	 * @param string $query Query.
	 * @return array<int,string>
	 */
	private function extract_keywords( $query ) {
		$stopwords = array(
			'come', 'cosa', 'quale', 'quali', 'posso', 'voglio', 'vorrei', 'devo', 'puoi', 'fare',
			'che', 'chi', 'dove', 'quando', 'perche', 'perché', 'un', 'uno', 'una', 'il', 'lo', 'la',
			'i', 'gli', 'le', 'di', 'del', 'della', 'dei', 'delle', 'a', 'ad', 'da', 'in', 'con', 'su',
			'per', 'tra', 'fra', 'e', 'o', 'ma', 'mi', 'ti', 'si', 'ci', 'vi', 'è', 'sono', 'ho', 'hai',
			'the', 'a', 'an', 'how', 'what', 'can', 'i', 'to', 'do', 'my', 'of', 'in', 'on', 'for', 'and', 'or',
		);
		$normalized = strtolower( preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', (string) $query ) );
		$words      = preg_split( '/\s+/', trim( $normalized ) );
		$keywords   = array();
		foreach ( (array) $words as $word ) {
			if ( mb_strlen( $word ) >= 3 && ! in_array( $word, $stopwords, true ) ) {
				$keywords[ $word ] = mb_strlen( $word );
			}
		}
		// Longer words first: they tend to be the most specific.
		arsort( $keywords );
		return array_keys( $keywords );
	}

	/**
	 * Build the prompt and call the AI, then sanitize and link-validate.
	 *
	 * @param string                          $query Query.
	 * @param array<int,array<string,mixed>>  $candidates Candidate articles.
	 * @param array<string,mixed>             $config Config.
	 * @return string|WP_Error Sanitized HTML or error.
	 */
	private function generate_guide( $query, $candidates, $config ) {
		$lang_names = array( 'it' => 'italiano', 'en' => 'English', 'fr' => 'français', 'es' => 'español', 'de' => 'Deutsch' );
		$lang       = $lang_names[ $config['language'] ] ?? 'italiano';

		$sources = '';
		foreach ( array_slice( $candidates, 0, 8 ) as $c ) {
			$sources .= sprintf( "- %s | %s | %s\n", $c['title'], $c['url'], wp_trim_words( $c['excerpt'], 25 ) );
		}
		if ( '' === $sources ) {
			$sources = "(nessun articolo interno trovato: non inserire link)\n";
		}

		$whitelist = trim( (string) $config['external_links_whitelist'] );
		$whitelist_block = '' !== $whitelist ? ( "Link esterni consentiti (puoi usarli se pertinenti):\n" . $whitelist . "\n" ) : "Nessun link esterno consentito: NON inserire link esterni.\n";

		$prompt = sprintf(
			"%s\n\n" .
			"Richiesta dell'utente:\n\"%s\"\n\n" .
			"Scrivi una guida/tutorial in %s, in HTML semplice usando solo questi tag: <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <blockquote>. " .
			"Struttura la risposta con un'introduzione, passaggi o sezioni con titoli, e una breve conclusione.\n" .
			"IMPORTANTE: concludi sempre la guida in modo completo. Non lasciare frasi o elenchi a metà; se lo spazio sta per esaurirsi, accorcia le sezioni e chiudi con una conclusione finita.\n\n" .
			"REGOLE SUI LINK (tassative):\n" .
			"- Per i link interni usa ESCLUSIVAMENTE gli URL elencati qui sotto, copiandoli esattamente. Non inventare URL.\n" .
			"- Inserisci i link in modo naturale nel testo dove sono pertinenti.\n" .
			"- %s\n\n" .
			"Articoli del sito disponibili (titolo | URL | estratto):\n%s\n" .
			"Non includere il tag <html> o <body>: restituisci solo il contenuto della guida.",
			(string) $config['system_instructions'],
			$query,
			$lang,
			$whitelist_block,
			$sources
		);

		$max_tokens = (int) $config['max_tokens'];

		// Optional grounding on the OpenAI vector store (file_search) in addition
		// to the site's own articles. Falls back to the standard text channel.
		$use_fs = ! empty( $config['use_file_search'] )
			&& method_exists( $this->ai_provider, 'is_file_search_ready' )
			&& $this->ai_provider->is_file_search_ready();

		if ( $use_fs ) {
			$raw = $this->ai_provider->generate_text_with_file_search( $prompt, (string) $config['system_instructions'], $max_tokens );
			if ( is_wp_error( $raw ) ) {
				// Graceful degradation: fall back to the plain text channel.
				$raw = $this->ai_provider->generate_short_text( $prompt, (string) $config['system_instructions'], $max_tokens );
			}
		} else {
			$raw = $this->ai_provider->generate_short_text( $prompt, (string) $config['system_instructions'], $max_tokens );
		}

		if ( is_wp_error( $raw ) ) {
			$this->logger->warning( __( 'Generazione guida non riuscita.', 'wp-ai-publisher' ), array( 'source' => 'guide_assistant', 'event' => 'generation_failed', 'message' => $raw->get_error_message() ) );
			return new WP_Error( 'wpai_guide_ai_failed', __( 'Non è stato possibile generare la guida. Riprova più tardi.', 'wp-ai-publisher' ), array( 'status' => 503 ) );
		}

		return $this->sanitize_and_validate_html( (string) $raw, $config );
	}

	/**
	 * Sanitize AI HTML and strip/validate links against the allowlist.
	 *
	 * @param string              $html Raw HTML.
	 * @param array<string,mixed> $config Config.
	 * @return string
	 */
	private function sanitize_and_validate_html( $html, $config ) {
		// Drop code fences the model may add.
		$html = preg_replace( '/^```[a-z]*\s*|\s*```$/i', '', trim( $html ) );

		$allowed = array(
			'h2'         => array(),
			'h3'         => array(),
			'h4'         => array(),
			'p'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'strong'     => array(),
			'em'         => array(),
			'br'         => array(),
			'blockquote' => array(),
			'a'          => array( 'href' => array(), 'title' => array() ),
		);
		$html = wp_kses( $html, $allowed );

		$site_host  = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$white_hosts = $this->whitelist_hosts( $config );

		// Validate each anchor: keep internal + whitelisted, otherwise unwrap to text.
		$html = preg_replace_callback(
			'/<a\b[^>]*href=("|\')(.*?)\1[^>]*>(.*?)<\/a>/is',
			function ( $m ) use ( $site_host, $white_hosts ) {
				$url  = html_entity_decode( $m[2] );
				$text = $m[3];
				$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
				$is_internal = ( '' === $host || $host === $site_host );
				$is_white    = in_array( $host, $white_hosts, true );
				if ( $is_internal ) {
					return '<a href="' . esc_url( $url ) . '">' . $text . '</a>';
				}
				if ( $is_white ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="nofollow noopener">' . $text . '</a>';
				}
				return $text; // Disallowed link → keep only the text.
			},
			$html
		);

		$footer = trim( (string) $config['result_footer'] );
		if ( '' !== $footer ) {
			$html .= '<p class="wpai-guide__footer"><em>' . esc_html( $footer ) . '</em></p>';
		}

		return $html;
	}

	/**
	 * Build the recommended-articles payload from the real search results.
	 *
	 * @param array<int,array<string,mixed>> $candidates Candidates.
	 * @param int                            $max Max items.
	 * @return array<int,array<string,mixed>>
	 */
	private function build_recommended_articles( $candidates, $max ) {
		$out = array();
		foreach ( array_slice( $candidates, 0, max( 1, $max ) ) as $c ) {
			$out[] = array(
				'id'      => (int) $c['id'],
				'title'   => $c['title'],
				'url'     => $c['url'],
				'excerpt' => wp_trim_words( (string) $c['excerpt'], 28 ),
				'thumb'   => (string) get_the_post_thumbnail_url( (int) $c['id'], 'medium' ),
			);
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Persistence
	 * ------------------------------------------------------------------- */

	/**
	 * Build a cache key for a normalized query.
	 *
	 * @param string              $query Query.
	 * @param array<string,mixed> $config Config.
	 * @return string
	 */
	private function query_hash( $query, $config ) {
		$norm = strtolower( trim( preg_replace( '/\s+/', ' ', $query ) ) );
		return hash( 'sha256', $norm . '|' . $config['language'] . '|' . $config['max_tokens'] );
	}

	/**
	 * Return a cached payload for a query hash, if fresh.
	 *
	 * @param string $hash Hash.
	 * @param int    $ttl_days TTL in days (0 disables cache).
	 * @return array<string,mixed>|null
	 */
	private function get_cached( $hash, $ttl_days ) {
		if ( $ttl_days <= 0 ) {
			return null;
		}
		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		$since = gmdate( 'Y-m-d H:i:s', time() - ( $ttl_days * DAY_IN_SECONDS ) );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT result_html, recommended_post_ids, id, post_id FROM {$table} WHERE query_hash = %s AND result_html IS NOT NULL AND created_at >= %s ORDER BY id DESC LIMIT 1",
				$hash,
				$since
			)
		);
		if ( ! $row ) {
			return null;
		}
		$ids     = json_decode( (string) $row->recommended_post_ids, true );
		$payload = array(
			'html'       => (string) $row->result_html,
			'articles'   => $this->build_recommended_articles( $this->ids_to_candidates( is_array( $ids ) ? $ids : array() ), 10 ),
			'request_id' => (int) $row->id,
			'cached'     => true,
		);
		$guide_post_id = absint( $row->post_id ?? 0 );
		if ( $guide_post_id > 0 && 'publish' === get_post_status( $guide_post_id ) ) {
			$payload['guide_url'] = (string) get_permalink( $guide_post_id );
		}
		return $payload;
	}

	/**
	 * Create a public (noindex) guide page and link it to the request row.
	 *
	 * @param int                            $request_id Request row ID.
	 * @param string                         $query Original query (used as title).
	 * @param string                         $html Sanitized guide HTML.
	 * @param array<int,array<string,mixed>> $articles Recommended articles.
	 * @return string Permalink, or '' on failure.
	 */
	private function create_public_guide( $request_id, $query, $html, $articles ) {
		$title = wp_trim_words( $query, 14, '…' );
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::CPT,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => $html,
			),
			true
		);
		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return '';
		}

		$ids = array_map( static function ( $a ) {
			return (int) $a['id'];
		}, $articles );
		update_post_meta( $post_id, '_wpai_guide_article_ids', wp_json_encode( $ids ) );
		update_post_meta( $post_id, '_wpai_guide_request_id', (int) $request_id );

		if ( $request_id > 0 ) {
			global $wpdb;
			$wpdb->update(
				$this->db->get_guide_requests_table_name(),
				array( 'post_id' => (int) $post_id ),
				array( 'id' => (int) $request_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		return (string) get_permalink( $post_id );
	}

	/**
	 * Rebuild candidate stubs from stored post IDs (for cached responses).
	 *
	 * @param array<int,int> $ids Post IDs.
	 * @return array<int,array<string,mixed>>
	 */
	private function ids_to_candidates( $ids ) {
		$out = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			$post = $id ? get_post( $id ) : null;
			if ( $post && 'publish' === $post->post_status ) {
				$out[] = array(
					'id'      => $id,
					'title'   => get_the_title( $post ),
					'url'     => (string) get_permalink( $post ),
					'excerpt' => has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 40 ),
				);
			}
		}
		return $out;
	}

	/**
	 * Store a guide request.
	 *
	 * @param string                          $query Query.
	 * @param string                          $hash Hash.
	 * @param array<string,mixed>             $config Config.
	 * @param string                          $html Generated HTML.
	 * @param array<int,array<string,mixed>>  $articles Recommended articles.
	 * @return int Inserted row ID (0 on failure).
	 */
	private function store_request( $query, $hash, $config, $html, $articles ) {
		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		$ids   = array_map( static function ( $a ) {
			return (int) $a['id'];
		}, $articles );

		$ok = $wpdb->insert(
			$table,
			array(
				'created_at'           => current_time( 'mysql' ),
				'query'                => $query,
				'query_hash'           => $hash,
				'language'             => (string) $config['language'],
				'result_html'          => $html,
				'recommended_post_ids' => wp_json_encode( $ids ),
				'ip_hash'              => $this->client_ip_hash(),
				'status'               => 'new',
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/* ---------------------------------------------------------------------
	 * Admin: settings page
	 * ------------------------------------------------------------------- */

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		$config = $this->get_config();
		require WPAIP_PLUGIN_DIR . 'admin/views/guide-assistant.php';
	}

	/**
	 * Render the saved requests list page.
	 *
	 * @return void
	 */
	public function render_requests_page() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}

		$view_id = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
		if ( $view_id > 0 ) {
			$request  = $this->get_request( $view_id );
			$articles = $request ? $this->build_recommended_articles( $this->ids_to_candidates( (array) json_decode( (string) $request->recommended_post_ids, true ) ), 10 ) : array();
			require WPAIP_PLUGIN_DIR . 'admin/views/guide-request-detail.php';
			return;
		}

		$requests = $this->get_requests( 100 );
		require WPAIP_PLUGIN_DIR . 'admin/views/guide-requests.php';
	}

	/**
	 * Fetch a single request row.
	 *
	 * @param int $id Request ID.
	 * @return object|null
	 */
	public function get_request( $id ) {
		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Fetch recent requests.
	 *
	 * @param int $limit Limit.
	 * @return array<int,object>
	 */
	public function get_requests( $limit = 100 ) {
		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		$limit = max( 1, min( 500, (int) $limit ) );
		return (array) $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT {$limit}" );
	}

	/**
	 * Handle settings save.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		check_admin_referer( 'wpai_publisher_save_guide_assistant' );

		$raw = isset( $_POST['wpai_guide'] ) && is_array( $_POST['wpai_guide'] ) ? wp_unslash( $_POST['wpai_guide'] ) : array();
		update_option( self::OPTION, $this->sanitize_config( $raw ) );

		wp_safe_redirect( add_query_arg( 'wpai_notice', 'guide_saved', admin_url( 'admin.php?page=wp-ai-publisher-guide-assistant' ) ) );
		exit;
	}

	/**
	 * Convert a stored request into a Content Idea.
	 *
	 * @return void
	 */
	public function handle_convert_to_idea() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		$request_id = absint( $_POST['request_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_guide_request_to_idea_' . $request_id );

		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $request_id ) );
		if ( ! $row ) {
			wp_safe_redirect( add_query_arg( 'wpai_notice', 'guide_request_missing', admin_url( 'admin.php?page=wp-ai-publisher-guide-requests' ) ) );
			exit;
		}

		$idea_id = $this->content_ideas->create_idea_programmatic(
			array(
				'topic'    => (string) $row->query,
				'language' => (string) $row->language,
			)
		);

		if ( is_wp_error( $idea_id ) ) {
			wp_safe_redirect( add_query_arg( 'wpai_notice', 'guide_idea_failed', admin_url( 'admin.php?page=wp-ai-publisher-guide-requests' ) ) );
			exit;
		}

		$wpdb->update( $table, array( 'status' => 'idea', 'idea_id' => (int) $idea_id ), array( 'id' => $request_id ), array( '%s', '%d' ), array( '%d' ) );
		wp_safe_redirect( add_query_arg( 'wpai_notice', 'guide_idea_created', admin_url( 'admin.php?page=wp-ai-publisher-content-ideas' ) ) );
		exit;
	}

	/**
	 * Delete a stored request.
	 *
	 * @return void
	 */
	public function handle_delete_request() {
		if ( ! current_user_can( wpai_publisher_capability() ) ) {
			wp_die( esc_html__( 'Permessi insufficienti.', 'wp-ai-publisher' ) );
		}
		$request_id = absint( $_POST['request_id'] ?? 0 );
		check_admin_referer( 'wpai_publisher_delete_guide_request_' . $request_id );

		global $wpdb;
		$table = $this->db->get_guide_requests_table_name();

		// Remove the linked public guide page, if any.
		$post_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$table} WHERE id = %d", $request_id ) ) );
		if ( $post_id > 0 && self::CPT === get_post_type( $post_id ) ) {
			wp_delete_post( $post_id, true );
		}

		$wpdb->delete( $table, array( 'id' => $request_id ), array( '%d' ) );

		wp_safe_redirect( add_query_arg( 'wpai_notice', 'guide_request_deleted', admin_url( 'admin.php?page=wp-ai-publisher-guide-requests' ) ) );
		exit;
	}
}
